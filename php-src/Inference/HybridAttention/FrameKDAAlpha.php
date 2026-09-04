<?php

/**
 * H3PHP — Frame KDA Alpha (Per-Frame Decay Gate).
 *
 * Implements the per-frame decay gate from VDN-H3 layers.py.
 *
 * Computes per-frame, per-head decay factors:
 *   alpha_t = exp(-exp(A_log) * softplus(delta + bias))
 *
 * Where:
 *   A_log: per-head parameter (initialized ~ U(1, 16))
 *   delta: per-frame, per-head log-dt (learned or computed)
 *   bias: per-channel parameter (initialized log-uniform[1e-3, 1e-1])
 *
 * PRECISION CRITICAL: This runs with autocast OFF (FP32). Errors in alpha
 * compound over ~100 frames, causing visible artifacts in the linear branch
 * readout. BF16 precision is insufficient for the exponential decay.
 *
 * From VDN-H3 layers.py FrameKDAAlpha.forward():
 *   alpha_t = exp(-exp(A_log) * softplus(delta + bias))
 */

namespace H3Php\Inference\HybridAttention;

class FrameKDAAlpha
{
    /** @var int Number of attention heads */
    private int $numHeads;

    /** @var array Per-head A_log parameter [numHeads] */
    private array $aLog;

    /** @var array Per-head bias parameter [numHeads] */
    private array $bias;

    /**
     * @param int        $numHeads Number of attention heads
     * @param array|null $aLog     Optional pre-trained A_log values
     * @param array|null $bias     Optional pre-trained bias values
     */
    public function __construct(int $numHeads, ?array $aLog = null, ?array $bias = null)
    {
        $this->numHeads = $numHeads;

        // Initialize A_log ~ U(1, 16) if not provided
        $this->aLog = $aLog ?? array_map(
            fn () => 1.0 + mt_rand() / mt_getrandmax() * 15.0,
            range(0, $numHeads - 1)
        );

        // Initialize bias ~ log-uniform[1e-3, 1e-1] if not provided
        $this->bias = $bias ?? array_map(
            fn () => log(1e-3) + mt_rand() / mt_getrandmax() * (log(1e-1) - log(1e-3)),
            range(0, $numHeads - 1)
        );
    }

    /**
     * Compute per-frame decay factors.
     *
     * alpha_t = exp(-exp(A_log) * softplus(delta + bias))
     *
     * @param array $delta Per-frame, per-head log-dt values [numFrames, numHeads]
     *
     * @return array Per-frame, per-head alpha values [numFrames, numHeads]
     */
    public function computeAlpha(array $delta): array
    {
        $numFrames = count($delta);
        $alpha = [];

        for ($t = 0; $t < $numFrames; ++$t) {
            $alpha[$t] = [];
            for ($h = 0; $h < $this->numHeads; ++$h) {
                // delta + bias
                $dt = ($delta[$t][$h] ?? 0.0) + $this->bias[$h];

                // softplus(dt) = log(1 + exp(dt))
                $softplus = log(1.0 + exp($dt));

                // alpha = exp(-exp(A_log) * softplus(dt))
                $alpha[$t][$h] = exp(-exp($this->aLog[$h]) * $softplus);
            }
        }

        return $alpha;
    }

    /**
     * Compute log-prefix-sum of alpha for bridge decay.
     *
     * Used to efficiently compute the cumulative decay between
     * the boundary state and the query frame.
     *
     * log_prefix[i] = sum(log(alpha[0..i-1]))
     *
     * @param array $alpha Per-frame alpha values [numFrames, numHeads]
     *
     * @return array Log-prefix-sums [numFrames+1, numHeads]
     */
    public function logPrefixSum(array $alpha): array
    {
        $numFrames = count($alpha);
        $prefix = array_fill(0, $numFrames + 1, array_fill(0, $this->numHeads, 0.0));

        for ($t = 0; $t < $numFrames; ++$t) {
            for ($h = 0; $h < $this->numHeads; ++$h) {
                $logAlpha = log(max($alpha[$t][$h], 1e-10));
                $prefix[$t + 1][$h] = $prefix[$t][$h] + $logAlpha;
            }
        }

        return $prefix;
    }

    /**
     * Compute bridge decay between boundary and query frame.
     *
     * decay = exp(log_prefix[query] - log_prefix[boundary])
     *
     * @param array $logPrefix     Log-prefix-sums from logPrefixSum()
     * @param int   $boundaryFrame Boundary frame index
     * @param int   $queryFrame    Query frame index
     *
     * @return array Per-head decay values [numHeads]
     */
    public function bridgeDecay(array $logPrefix, int $boundaryFrame, int $queryFrame): array
    {
        $decay = [];
        for ($h = 0; $h < $this->numHeads; ++$h) {
            $decay[$h] = exp($logPrefix[$queryFrame][$h] - $logPrefix[$boundaryFrame][$h]);
        }

        return $decay;
    }

    /**
     * Get A_log parameters.
     */
    public function getALog(): array
    {
        return $this->aLog;
    }

    /**
     * Get bias parameters.
     */
    public function getBias(): array
    {
        return $this->bias;
    }

    /**
     * Set A_log parameters (for loading from checkpoint).
     */
    public function setALog(array $aLog): void
    {
        $this->aLog = $aLog;
    }

    /**
     * Set bias parameters (for loading from checkpoint).
     */
    public function setBias(array $bias): void
    {
        $this->bias = $bias;
    }
}
