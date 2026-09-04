<?php
/**
 * H3PHP — Output Gate (Sigmoid Gate for Branch Fusion)
 *
 * Implements the sigmoid gate from VDN-H3 attention_gates.py.
 *
 * Two gate variants:
 * 1. **Softmax branch gate**: per-head (head_dim=None), init=0.99
 *    — Softmax IS the teacher at step 0, so gate starts near 1
 * 2. **Linear branch gate**: per-channel (head_dim set), init="random"
 *    — Live low-rank path from step 0
 *
 * The fusion formula:
 *   output = softmax_gate(x) * softmax_out + linear_gate(x) * linear_out
 *
 * Each gate applies a learned sigmoid scaling:
 *   gate(x) = sigmoid(weight @ x + bias)
 */

namespace H3Php\Inference\HybridAttention;

class OutputGate
{
    /** @var int Hidden dimension */
    private int $hiddenSize;

    /** @var int|null Head dimension (null for per-head gate) */
    private ?int $headDim;

    /** @var int Number of heads */
    private int $numHeads;

    /** @var array Gate weights */
    private array $weight;

    /** @var array Gate biases */
    private array $bias;

    /** @var float Initial value for sigmoid */
    private float $initValue;

    /**
     * @param int $hiddenSize Hidden dimension
     * @param int $numHeads Number of attention heads
     * @param int|null $headDim Head dimension (null for per-head gate)
     * @param float|string $initValue Initial sigmoid value or "random"
     */
    public function __construct(
        int $hiddenSize,
        int $numHeads,
        ?int $headDim = null,
        float|string $initValue = 0.99
    ) {
        $this->hiddenSize = $hiddenSize;
        $this->numHeads = $numHeads;
        $this->headDim = $headDim;
        $this->initValue = is_string($initValue) ? 0.0 : $initValue;

        $this->initializeWeights();
    }

    /**
     * Initialize gate weights based on configuration.
     */
    private function initializeWeights(): void
    {
        if ($this->headDim === null) {
            // Per-head gate: one weight per head
            $this->weight = array_map(
                fn() => $this->initValue,
                range(0, $this->numHeads - 1)
            );
            $this->bias = array_map(
                fn() => 0.0,
                range(0, $this->numHeads - 1)
            );
        } else {
            // Per-channel gate: weight per head per channel
            $this->weight = array_fill(
                0, $this->numHeads,
                array_fill(0, $this->headDim, $this->initValue)
            );
            $this->bias = array_fill(
                0, $this->numHeads,
                array_fill(0, $this->headDim, 0.0)
            );
        }
    }

    /**
     * Apply gate to input.
     *
     * gate(x) = sigmoid(weight * x + bias)
     *
     * @param array $x Input tensor
     * @return array Gated output
     */
    public function apply(array $x): array
    {
        if ($this->headDim === null) {
            return $this->applyPerHead($x);
        }
        return $this->applyPerChannel($x);
    }

    /**
     * Per-head gating: single scale per head.
     */
    private function applyPerHead(array $x): array
    {
        $result = [];
        for ($h = 0; $h < $this->numHeads; $h++) {
            $sigmoid = 1.0 / (1.0 + exp(-($this->weight[$h] + $this->bias[$h])));
            $result[$h] = [];
            if (is_array($x[$h])) {
                foreach ($x[$h] as $i => $val) {
                    $result[$h][$i] = $sigmoid * $val;
                }
            } else {
                $result[$h] = $sigmoid * $x[$h];
            }
        }
        return $result;
    }

    /**
     * Per-channel gating: scale per head per channel.
     */
    private function applyPerChannel(array $x): array
    {
        $result = [];
        for ($h = 0; $h < $this->numHeads; $h++) {
            $result[$h] = [];
            for ($d = 0; $d < $this->headDim; $d++) {
                $sigmoid = 1.0 / (1.0 + exp(-($this->weight[$h][$d] + $this->bias[$h][$d])));
                $result[$h][$d] = $sigmoid * ($x[$h][$d] ?? 0.0);
            }
        }
        return $result;
    }

    /**
     * Get gate weights.
     */
    public function getWeight(): array
    {
        return $this->weight;
    }

    /**
     * Get gate biases.
     */
    public function getBias(): array
    {
        return $this->bias;
    }

    /**
     * Set gate weights (for loading from checkpoint).
     */
    public function setWeight(array $weight): void
    {
        $this->weight = $weight;
    }

    /**
     * Set gate biases (for loading from checkpoint).
     */
    public function setBias(array $bias): void
    {
        $this->bias = $bias;
    }
}
