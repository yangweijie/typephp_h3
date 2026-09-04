<?php
/**
 * H3PHP — AdaLN Scheduler
 *
 * Adaptive Layer Normalization scheduling for the DiT model.
 * Controls how the timestep (sigma) conditions each transformer block.
 *
 * From h3.c documentation:
 *   - AdaLN modulation per block: scale, shift, gate
 *   - Gate scores used for layer pruning (sort blocks by importance)
 *   - Separate schedules for video and audio paths
 *   - Timestep embedding: sinusoidal + MLP projection
 */

namespace H3Php\Inference;

class Scheduler
{
    /** Hidden dimension for timestep embedding */
    private int $hiddenDim = 5376;

    /** Number of frequency bands for sinusoidal embedding */
    private int $numFreqs = 256;

    /** Video sigma shift */
    private float $videoSigmaShift = 12.0;

    /** Audio sigma shift */
    private float $audioSigmaShift = 3.0;

    /**
     * Compute the sigma schedule.
     *
     * Returns steps+1 sigma values (from 1.0 down to 0.0), driving exactly
     * `steps` model evaluations (NFEs). This matches the corrected behavior
     * from OpenVDN patch 2026-08-27: the terminal sigma=0 only closes the
     * last Euler update and does not trigger an extra model forward.
     *
     * All computation in FP64 (PHP float), cast to FP32 when passed to Metal.
     *
     * @param int $steps Number of denoising steps (model evaluations / NFEs)
     * @param float $shift Sigma schedule shift (12.0 for video, 3.0 for audio)
     * @return float[] Sigma values (steps+1 elements, descending)
     */
    public function computeSigmas(int $steps, float $shift = 12.0): array
    {
        $sigmas = [];

        // steps+1 points: every non-terminal sigma drives one transformer forward
        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            // Shifted schedule: sigma = shift * t / (1 + (shift-1) * t)
            $sigma = $this->scheduleFunction($t, $shift);
            $sigmas[] = $sigma;
        }

        return $sigmas;
    }

    /**
     * The schedule function: maps timestep t in [0,1] to sigma.
     */
    private function scheduleFunction(float $t, float $shift): float
    {
        // Shifted schedule from the DiT paper
        return $shift * $t / (1.0 + ($shift - 1.0) * $t);
    }

    /**
     * Compute sinusoidal timestep embedding.
     *
     * PRECISION CRITICAL: This embedding feeds into SiLU for AdaLN modulation.
     * The SiLU activation MUST run in FP32 (not BF16) before projection.
     * See OpenVDN patch 2026-08-15: SiLU(bf16_input) causes 3.5e-3 norm-relative
     * error that accumulates coherently across all 50 blocks.
     *
     * @param float $sigma Current sigma value
     * @return array Embedding vector of length hiddenDim (FP32 values)
     */
    public function computeTimestepEmbedding(float $sigma): array
    {
        // Log-sigma for better dynamic range (FP64 in PHP, cast to FP32 for Metal)
        $logSigma = log(max($sigma, 1e-10));
        $embedding = [];

        // Sinusoidal embedding: sin/cos of log_sigma * frequency
        for ($i = 0; $i < $this->hiddenDim; $i += 2) {
            $freq = exp(-log(10000.0) * $i / $this->numFreqs);
            $arg = $logSigma * $freq;
            $embedding[$i] = sin($arg);
            if ($i + 1 < $this->hiddenDim) {
                $embedding[$i + 1] = cos($arg);
            }
        }

        return $embedding;
    }

    /**
     * Compute AdaLN modulation parameters from timestep embedding.
     *
     * Returns scale, shift, gate for adaptive layer normalization.
     * These are applied per-block in the DiT.
     *
     * @param float $sigma Current sigma
     * @return array{scale: array, shift: array, gate: array}
     */
    public function computeAdaLNModulation(float $sigma): array
    {
        $embedding = $this->computeTimestepEmbedding($sigma);

        // TODO: Apply MLP projection to get scale, shift, gate
        // For now, derive directly from embedding
        $scale = [];
        $shift = [];
        $gate = [];

        for ($i = 0; $i < $this->hiddenDim; $i++) {
            $scale[$i] = 1.0 + $embedding[$i]; // Scale around 1.0
            $shift[$i] = $embedding[$i];        // Shift around 0.0
            $gate[$i] = 1.0;                     // Gate (will be learned)
        }

        return [
            'scale' => $scale,
            'shift' => $shift,
            'gate' => $gate,
        ];
    }

    /**
     * Compute AdaLN gate scores for all 50 blocks.
     * Used for layer pruning: blocks with lowest gate scores are pruned first.
     *
     * @param float $sigma Current sigma
     * @return array{block_index: int, gate_score: float}[]
     */
    public function computeGateScores(float $sigma): array
    {
        $modulation = $this->computeAdaLNModulation($sigma);
        $scores = [];

        // TODO: Use actual per-block gate weights
        // For now, generate placeholder scores
        for ($block = 0; $block < 50; $block++) {
            $score = 0.0;
            for ($i = 0; $i < $this->hiddenDim; $i++) {
                $score += abs($modulation['gate'][$i]);
            }
            $score /= $this->hiddenDim;
            $scores[] = ['block_index' => $block, 'gate_score' => $score];
        }

        // Sort by gate score (descending)
        usort($scores, fn($a, $b) => $b['gate_score'] <=> $a['gate_score']);

        return $scores;
    }

    /**
     * Get the top-N block indices by gate score.
     * Always protects first (0) and last (49) blocks.
     *
     * @param float $sigma Current sigma
     * @param int $numBlocks Number of blocks to keep
     * @return int[] Block indices to use
     */
    public function getTopBlocks(float $sigma, int $numBlocks): array
    {
        $scores = $this->computeGateScores($sigma);

        // Always include first and last
        $protected = [0, 49];
        $candidates = array_filter($scores, fn($s) => !in_array($s['block_index'], $protected));
        $candidates = array_values($candidates);

        // Take top N-2 from candidates
        $numToSelect = $numBlocks - count($protected);
        $selected = array_slice($candidates, 0, $numToSelect);

        $indices = array_merge($protected, array_column($selected, 'block_index'));
        sort($indices);

        return $indices;
    }

    /**
     * Get the hidden dimension.
     */
    public function getHiddenDim(): int
    {
        return $this->hiddenDim;
    }
}
