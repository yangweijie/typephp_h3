<?php

/**
 * H3PHP — Bidirectional Linear Attention Scan.
 *
 * Implements the bidirectional delta-rule scan from VDN-H3 scan.py.
 *
 * The scan runs forward and reverse passes over frames, maintaining
 * a state matrix S that summarizes the past/future context:
 *
 * Forward scan:  S_t = delta_apply(S_{t-1}, A_t, B_t, alpha_t)
 * Reverse scan:  S_t = delta_apply(S_{t+1}, A_t, B_t, alpha_t)
 *
 * The final readout for frame f combines states from both directions
 * with bridge decay.
 *
 * PRECISION CRITICAL: Scan state recurrence MUST run in FP32. The state
 * S is updated multiplicatively over ~100 frames; BF16 accumulation
 * causes the state to either explode or vanish exponentially.
 *
 * From VDN-H3 scan.py:
 *   _run_scans(): forward/reverse state banks
 *   gather_linear_state(): boundary state with bridge decay
 */

namespace H3Php\Inference\HybridAttention;

class BidirectionalScan
{
    /** @var DeltaRule Delta rule backend */
    private DeltaRule $deltaRule;

    /** @var FrameKDAAlpha Per-frame decay gate */
    private FrameKDAAlpha $alpha;

    /** @var int Head dimension */
    private int $headDim;

    /** @var int Number of heads */
    private int $numHeads;

    /**
     * @param DeltaRule     $deltaRule Delta rule backend
     * @param FrameKDAAlpha $alpha     Per-frame decay gate
     * @param int           $numHeads  Number of attention heads
     * @param int           $headDim   Head dimension
     */
    public function __construct(
        DeltaRule $deltaRule,
        FrameKDAAlpha $alpha,
        int $numHeads,
        int $headDim,
    ) {
        $this->deltaRule = $deltaRule;
        $this->alpha = $alpha;
        $this->numHeads = $numHeads;
        $this->headDim = $headDim;
    }

    /**
     * Run bidirectional scan over frames.
     *
     * @param array $frames Frame statistics array [numFrames][head]{A, B}
     * @param array $delta  Per-frame log-dt [numFrames][numHeads]
     *
     * @return array Forward and reverse state banks [numFrames+1][head][d][d]
     */
    public function runScans(array $frames, array $delta): array
    {
        $numFrames = count($frames);
        $d = $this->headDim;

        // Compute alpha values
        $alpha = $this->alpha->computeAlpha($delta);

        // Initialize state banks (numFrames+1, one extra for initial state)
        $forward = array_fill(0, $numFrames + 1, []);
        $reverse = array_fill(0, $numFrames + 1, []);

        // Initialize initial states to zero matrix
        for ($h = 0; $h < $this->numHeads; ++$h) {
            $forward[0][$h] = array_fill(0, $d, array_fill(0, $d, 0.0));
            $reverse[$numFrames][$h] = array_fill(0, $d, array_fill(0, $d, 0.0));
        }

        // Forward scan: S_t = delta_apply(S_{t-1}, A_t, B_t, alpha_t)
        for ($t = 0; $t < $numFrames; ++$t) {
            for ($h = 0; $h < $this->numHeads; ++$h) {
                $A = $frames[$t][$h]['A'] ?? array_fill(0, $d, array_fill(0, $d, 0.0));
                $B = $frames[$t][$h]['B'] ?? array_fill(0, $d, array_fill(0, $d, 0.0));
                $decay = array_fill(0, $d, $alpha[$t][$h] ?? 1.0);

                $forward[$t + 1][$h] = $this->deltaRule->apply(
                    $forward[$t][$h],
                    $A,
                    $B,
                    $decay
                );
            }
        }

        // Reverse scan: S_t = delta_apply(S_{t+1}, A_t, B_t, alpha_t)
        for ($t = $numFrames - 1; $t >= 0; --$t) {
            for ($h = 0; $h < $this->numHeads; ++$h) {
                $A = $frames[$t][$h]['A'] ?? array_fill(0, $d, array_fill(0, $d, 0.0));
                $B = $frames[$t][$h]['B'] ?? array_fill(0, $d, array_fill(0, $d, 0.0));
                $decay = array_fill(0, $d, $alpha[$t][$h] ?? 1.0);

                $reverse[$t][$h] = $this->deltaRule->apply(
                    $reverse[$t + 1][$h],
                    $A,
                    $B,
                    $decay
                );
            }
        }

        return [
            'forward' => $forward,
            'reverse' => $reverse,
            'alpha' => $alpha,
        ];
    }

    /**
     * Gather linear state for a query frame with bridge decay.
     *
     * Combines forward and reverse states at the window boundaries,
     * decaying the boundary state to the query frame using alpha.
     *
     * @param array $forwardStates Forward state bank from runScans()
     * @param array $reverseStates Reverse state bank from runScans()
     * @param array $alpha         Per-frame alpha values [numFrames][numHeads]
     * @param int   $windowStart   Window start frame (inclusive)
     * @param int   $windowEnd     Window end frame (inclusive)
     * @param int   $queryFrame    Frame to compute readout for
     *
     * @return array Per-head state matrices [numHeads][d][d]
     */
    public function gatherLinearState(
        array $forwardStates,
        array $reverseStates,
        array $alpha,
        int $windowStart,
        int $windowEnd,
        int $queryFrame,
    ): array {
        $d = $this->headDim;
        $state = [];

        for ($h = 0; $h < $this->numHeads; ++$h) {
            // Forward state at window start (decayed to query frame)
            $fwdState = $forwardStates[$windowStart][$h];
            $fwdDecay = $this->computeCumulativeDecay($alpha, $windowStart, $queryFrame, $h);

            // Reverse state at window end (decayed to query frame)
            $revState = $reverseStates[$windowEnd + 1][$h];
            $revDecay = $this->computeCumulativeDecay($alpha, $queryFrame + 1, $windowEnd + 1, $h);

            // Combine: S_query = decay_fwd * S_fwd + decay_rev * S_rev
            $state[$h] = array_fill(0, $d, array_fill(0, $d, 0.0));
            for ($i = 0; $i < $d; ++$i) {
                for ($j = 0; $j < $d; ++$j) {
                    $state[$h][$i][$j] = $fwdDecay * $fwdState[$i][$j] + $revDecay * $revState[$i][$j];
                }
            }
        }

        return $state;
    }

    /**
     * Compute cumulative decay between two frames.
     *
     * decay = prod(alpha[start..end-1])
     *
     * @param array $alpha Per-frame alpha values
     * @param int   $start Start frame (inclusive)
     * @param int   $end   End frame (exclusive)
     * @param int   $head  Head index
     *
     * @return float Cumulative decay
     */
    private function computeCumulativeDecay(array $alpha, int $start, int $end, int $head): float
    {
        $decay = 1.0;
        for ($t = $start; $t < $end; ++$t) {
            $decay *= ($alpha[$t][$head] ?? 1.0);
        }

        return $decay;
    }

    /**
     * Compute linear attention readout.
     *
     * output = einsum("hvk,hk->hv", state, query)
     *
     * @param array $state State matrices [numHeads][d][d]
     * @param array $query Query vectors [numHeads][d]
     *
     * @return array Output vectors [numHeads][d]
     */
    public function readout(array $state, array $query): array
    {
        $d = $this->headDim;
        $output = [];

        for ($h = 0; $h < $this->numHeads; ++$h) {
            $output[$h] = array_fill(0, $d, 0.0);
            for ($v = 0; $v < $d; ++$v) {
                $sum = 0.0;
                for ($k = 0; $k < $d; ++$k) {
                    $sum += ($state[$h][$v][$k] ?? 0.0) * ($query[$h][$k] ?? 0.0);
                }
                $output[$h][$v] = $sum;
            }
        }

        return $output;
    }
}
