<?php

/**
 * H3PHP — Hybrid Attention (Softmax Window + Linear Far Branch).
 *
 * Implements the hybrid attention architecture from VDN-H3 hybrid_attention.py.
 *
 * The attention output is a gated sum of two branches:
 *
 *   output = softmax_gate(x) * window_softmax(Q, K, V)      // local exact
 *          + linear_gate(x) * RMSNorm(linear_attention(Q, K, V))  // global efficient
 *
 * Components:
 * - Softmax branch: windowed softmax attention (local, exact)
 * - Linear branch: bidirectional delta-rule scan (global, efficient)
 * - Fusion: learned sigmoid gates for each branch
 *
 * The softmax branch handles local dependencies within a temporal window
 * (e.g. 15 frames for chunk=5, radius=1). The linear branch captures
 * long-range dependencies outside the window via efficient state-space
 * recurrence.
 *
 * From VDN-H3 hybrid_attention.py:
 *   HybridAttention.orig = original softmax attention (frozen teacher)
 *   HybridAttention.linear_attention = BidirectionalLinearBranch
 *   HybridAttention.softmax_gate = OutputGate(init=0.99)
 *   HybridAttention.output_gate = OutputGate(init="random")
 */

namespace H3Php\Inference\HybridAttention;

class HybridAttention
{
    /** @var DeltaRule Delta rule backend for linear branch */
    private DeltaRule $deltaRule;

    /** @var FrameKDAAlpha Per-frame decay gate */
    private FrameKDAAlpha $alphaGate;

    /** @var OutputGate Softmax branch gate */
    private OutputGate $softmaxGate;

    /** @var OutputGate Linear branch gate */
    private OutputGate $linearGate;

    /** @var BidirectionalScan Bidirectional scan engine */
    private BidirectionalScan $scan;

    /** @var int Number of attention heads */
    private int $numHeads;

    /** @var int Head dimension */
    private int $headDim;

    /** @var int Window radius (frames each side) */
    private int $radius;

    /** @var int Chunk size for window alignment */
    private int $chunk;

    /** @var bool Whether linear branch is enabled */
    private bool $linearEnabled;

    /**
     * @param int    $numHeads      Number of attention heads
     * @param int    $headDim       Head dimension
     * @param string $deltaRule     Delta rule variant ('vdn_solve', 'sana', 'vdn_scaled')
     * @param int    $radius        Window radius (frames each side of query)
     * @param int    $chunk         Chunk size for window alignment (0 = frame mode)
     * @param bool   $linearEnabled Enable linear attention branch
     */
    public function __construct(
        int $numHeads = 40,
        int $headDim = 128,
        string $deltaRule = 'vdn_solve',
        int $radius = 1,
        int $chunk = 5,
        bool $linearEnabled = true,
    ) {
        $this->numHeads = $numHeads;
        $this->headDim = $headDim;
        $this->radius = $radius;
        $this->chunk = $chunk;
        $this->linearEnabled = $linearEnabled;

        // Initialize components
        $this->deltaRule = new DeltaRule($deltaRule, $headDim);
        $this->alphaGate = new FrameKDAAlpha($numHeads);
        $this->softmaxGate = new OutputGate($numHeads, null, 0.99);
        $this->linearGate = new OutputGate($numHeads, $headDim, 'random');
        $this->scan = new BidirectionalScan($this->deltaRule, $this->alphaGate, $numHeads, $headDim);
    }

    /**
     * Compute window bounds for a query frame.
     *
     * @param int $queryFrame Query frame index
     * @param int $numFrames  Total number of frames
     *
     * @return array{start: int, end: int} Window start (inclusive) and end (inclusive)
     */
    public function windowBounds(int $queryFrame, int $numFrames): array
    {
        if (0 === $this->chunk) {
            // Frame mode: centered window |t_q - t_k| <= radius
            $start = max(0, $queryFrame - $this->radius);
            $end = min($numFrames - 1, $queryFrame + $this->radius);
        } else {
            // Chunk-aligned mode
            $queryChunk = intdiv($queryFrame, $this->chunk);
            $chunkStart = max(0, $queryChunk - $this->radius);
            $chunkEnd = min(intdiv($numFrames - 1, $this->chunk), $queryChunk + $this->radius);
            $start = $chunkStart * $this->chunk;
            $end = min($numFrames - 1, ($chunkEnd + 1) * $this->chunk - 1);
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Check if a frame needs the linear branch.
     *
     * The linear branch is needed when the window doesn't cover all frames.
     *
     * @param int $queryFrame Query frame index
     * @param int $numFrames  Total number of frames
     *
     * @return bool True if linear branch should run
     */
    public function needsLinearBranch(int $queryFrame, int $numFrames): bool
    {
        if (!$this->linearEnabled) {
            return false;
        }

        $bounds = $this->windowBounds($queryFrame, $numFrames);

        return $bounds['start'] > 0 || $bounds['end'] < $numFrames - 1;
    }

    /**
     * Forward pass for the linear attention branch.
     *
     * @param array $frames     Frame-level Q/K/V statistics [numFrames][head]{A, B}
     * @param array $delta      Per-frame log-dt [numFrames][numHeads]
     * @param array $query      Query vectors for readout [numHeads][head_dim]
     * @param int   $queryFrame Frame to compute readout for
     * @param int   $numFrames  Total number of frames
     *
     * @return array Output vectors [numHeads][head_dim]
     */
    public function linearForward(
        array $frames,
        array $delta,
        array $query,
        int $queryFrame,
        int $numFrames,
    ): array {
        // Run bidirectional scan
        $scanResult = $this->scan->runScans($frames, $delta);

        // Get window bounds
        $bounds = $this->windowBounds($queryFrame, $numFrames);

        // Gather state with bridge decay
        $state = $this->scan->gatherLinearState(
            $scanResult['forward'],
            $scanResult['reverse'],
            $scanResult['alpha'],
            $bounds['start'],
            $bounds['end'],
            $queryFrame
        );

        // Compute readout: output = state @ query
        return $this->scan->readout($state, $query);
    }

    /**
     * Apply softmax gate to softmax branch output.
     *
     * @param array $softmaxOutput Output from windowed softmax attention
     *
     * @return array Gated output
     */
    public function applySoftmaxGate(array $softmaxOutput): array
    {
        return $this->softmaxGate->apply($softmaxOutput);
    }

    /**
     * Apply linear gate to linear branch output.
     *
     * @param array $linearOutput Output from linear attention
     *
     * @return array Gated output
     */
    public function applyLinearGate(array $linearOutput): array
    {
        return $this->linearGate->apply($linearOutput);
    }

    /**
     * Fuse both branches.
     *
     * output = gated_softmax + gated_linear
     *
     * @param array $gatedSoftmax Softmax branch output (after gate)
     * @param array $gatedLinear  Linear branch output (after gate)
     *
     * @return array Fused output
     */
    public function fuse(array $gatedSoftmax, array $gatedLinear): array
    {
        $output = [];

        for ($h = 0; $h < $this->numHeads; ++$h) {
            $output[$h] = [];
            for ($d = 0; $d < $this->headDim; ++$d) {
                $output[$h][$d] = ($gatedSoftmax[$h][$d] ?? 0.0) + ($gatedLinear[$h][$d] ?? 0.0);
            }
        }

        return $output;
    }

    /**
     * Get the delta rule backend.
     */
    public function getDeltaRule(): DeltaRule
    {
        return $this->deltaRule;
    }

    /**
     * Get the alpha gate.
     */
    public function getAlphaGate(): FrameKDAAlpha
    {
        return $this->alphaGate;
    }

    /**
     * Get the softmax gate.
     */
    public function getSoftmaxGate(): OutputGate
    {
        return $this->softmaxGate;
    }

    /**
     * Get the linear gate.
     */
    public function getLinearGate(): OutputGate
    {
        return $this->linearGate;
    }

    /**
     * Get the bidirectional scan engine.
     */
    public function getScan(): BidirectionalScan
    {
        return $this->scan;
    }

    /**
     * Get window configuration.
     */
    public function getWindowConfig(): array
    {
        return [
            'radius' => $this->radius,
            'chunk' => $this->chunk,
            'window_size' => $this->chunk > 0
                ? ($this->radius * 2 + 1) * $this->chunk
                : ($this->radius * 2 + 1),
        ];
    }

    /**
     * Free resources.
     */
    public function free(): void
    {
        // PHP handles memory automatically
    }
}
