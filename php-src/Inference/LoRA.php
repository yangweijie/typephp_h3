<?php

/**
 * H3PHP — LoRA (Low-Rank Adaptation).
 *
 * Merges LoRA weights into the DiT model for fine-tuned generation.
 *
 * From h3.c documentation:
 *   - Supports "LightX2V Turbo" and "official Lightning" distillation LoRAs
 *   - LoRA weights are merged into DiT blocks before inference
 *   - Rank and alpha control the strength of adaptation
 *   - Applied to QKV projections and MLP layers
 */

namespace H3Php\Inference;

class LoRA
{
    /** Path to LoRA weights file */
    private string $loraPath;

    /** LoRA configuration */
    private array $config = [];

    /** LoRA weights (rank-decomposed matrices) */
    private array $weights = [];

    /** LoRA rank */
    private int $rank = 32;

    /** LoRA alpha (scaling factor) */
    private float $alpha = 1.0;

    /** Scaling factor = alpha / rank */
    private float $scaling;

    public function __construct(string $loraPath)
    {
        $this->loraPath = $loraPath;
        $this->scaling = $this->alpha / $this->rank;
        $this->loadLoRA();
    }

    /**
     * Load LoRA weights from file.
     */
    private function loadLoRA(): void
    {
        if (!file_exists($this->loraPath)) {
            throw new \RuntimeException("LoRA file not found: {$this->loraPath}");
        }

        $content = file_get_contents($this->loraPath);
        $data = json_decode($content, true);

        if (null !== $data) {
            // JSON format (config + weights)
            $this->config = $data['config'] ?? [];
            $this->weights = $data['weights'] ?? [];
            $this->rank = $this->config['rank'] ?? 32;
            $this->alpha = $this->config['alpha'] ?? 1.0;
        } else {
            // Binary safetensors format
            // TODO: Parse safetensors format
            $this->weights = [];
        }

        $this->scaling = $this->alpha / $this->rank;
    }

    /**
     * Merge LoRA weights into a DiT block's projection.
     *
     * @param array  $baseWeight Base weight matrix
     * @param string $layerName  Layer identifier (e.g., "blocks.0.attn.qkv")
     *
     * @return array Merged weight matrix
     */
    public function mergeInto(array $baseWeight, string $layerName): array
    {
        $loraA = $this->weights[$layerName . '.lora_a'] ?? null;
        $loraB = $this->weights[$layerName . '.lora_b'] ?? null;

        if (null === $loraA || null === $loraB) {
            return $baseWeight; // No LoRA for this layer
        }

        // LoRA delta: delta_W = scaling * (B @ A)
        $deltaW = $this->matmul($loraB, $loraA);
        $deltaW = $this->scaleMatrix($deltaW, $this->scaling);

        // Merge: W' = W + delta_W
        return $this->addMatrices($baseWeight, $deltaW);
    }

    /**
     * Apply LoRA to all applicable layers in a DiT block.
     *
     * @param array $blockWeights Block weight matrices
     * @param int   $blockIndex   Block index
     *
     * @return array Modified block weights
     */
    public function applyToBlock(array $blockWeights, int $blockIndex): array
    {
        $prefix = "blocks.{$blockIndex}";

        $targetLayers = [
            'attn.qkv',
            'attn.proj',
            'mlp.fc1',
            'mlp.fc2',
        ];

        foreach ($targetLayers as $layer) {
            $layerName = $prefix . '.' . $layer;
            if (isset($blockWeights[$layerName])) {
                $blockWeights[$layerName] = $this->mergeInto($blockWeights[$layerName], $layerName);
            }
        }

        return $blockWeights;
    }

    /**
     * Matrix multiplication (2D).
     */
    private function matmul(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b[0]);
        $p = count($b);

        $result = array_fill(0, $m, array_fill(0, $n, 0.0));

        for ($i = 0; $i < $m; ++$i) {
            for ($j = 0; $j < $n; ++$j) {
                $sum = 0.0;
                for ($k = 0; $k < $p; ++$k) {
                    $sum += $a[$i][$k] * $b[$k][$j];
                }
                $result[$i][$j] = $sum;
            }
        }

        return $result;
    }

    /**
     * Scale a matrix by a scalar.
     */
    private function scaleMatrix(array $matrix, float $scale): array
    {
        return array_map(
            fn ($row) => array_map(fn ($v) => $v * $scale, $row),
            $matrix
        );
    }

    /**
     * Add two matrices element-wise.
     */
    private function addMatrices(array $a, array $b): array
    {
        $result = [];
        for ($i = 0; $i < count($a); ++$i) {
            for ($j = 0; $j < count($a[0]); ++$j) {
                $result[$i][$j] = ($a[$i][$j] ?? 0) + ($b[$i][$j] ?? 0);
            }
        }

        return $result;
    }

    /**
     * Get the LoRA rank.
     */
    public function getRank(): int
    {
        return $this->rank;
    }

    /**
     * Get the LoRA alpha.
     */
    public function getAlpha(): float
    {
        return $this->alpha;
    }

    /**
     * Get the scaling factor.
     */
    public function getScaling(): float
    {
        return $this->scaling;
    }

    /**
     * Get the LoRA configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Free LoRA resources.
     */
    public function free(): void
    {
        $this->weights = [];
        $this->config = [];
    }
}
