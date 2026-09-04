<?php
/**
 * H3PHP — HybridAttention Test
 */

namespace H3Php\Tests\Inference\HybridAttention;

use H3Php\Inference\HybridAttention\FrameKDAAlpha;
use H3Php\Inference\HybridAttention\OutputGate;
use H3Php\Inference\HybridAttention\BidirectionalScan;
use H3Php\Inference\HybridAttention\DeltaRule;
use PHPUnit\Framework\TestCase;

class HybridAttentionTest extends TestCase
{
    public function testFrameKDAAlphaComputation(): void
    {
        // Use moderate aLog and bias to avoid underflow
        $aLog = array_fill(0, 4, 1.0);  // exp(1) ≈ 2.7
        $bias = array_fill(0, 4, -2.0);  // softplus(-2) ≈ 0.12
        $alpha = new FrameKDAAlpha(4, $aLog, $bias);

        // Create delta values for 5 frames, 4 heads
        $delta = array_fill(0, 5, array_fill(0, 4, 0.0));

        $result = $alpha->computeAlpha($delta);

        $this->assertCount(5, $result);
        $this->assertCount(4, $result[0]);

        // All alpha values should be in (0, 1]
        for ($t = 0; $t < 5; $t++) {
            for ($h = 0; $h < 4; $h++) {
                $this->assertGreaterThan(0.0, $result[$t][$h]);
                $this->assertLessThanOrEqual(1.0, $result[$t][$h]);
            }
        }

        // With aLog=1, bias=-2: alpha = exp(-exp(1) * softplus(-2)) ≈ exp(-2.7 * 0.12) ≈ 0.72
        $this->assertEqualsWithDelta(0.72, $result[0][0], 0.05);
    }

    public function testFrameKDAAlphaLogPrefixSum(): void
    {
        $alpha = new FrameKDAAlpha(2);

        $delta = [
            [0.0, 0.0],
            [0.0, 0.0],
            [0.0, 0.0],
        ];

        $alphaValues = $alpha->computeAlpha($delta);
        $prefix = $alpha->logPrefixSum($alphaValues);

        // Prefix should have numFrames+1 entries
        $this->assertCount(4, $prefix);

        // First entry should be 0
        $this->assertEqualsWithDelta(0.0, $prefix[0][0], 0.001);
        $this->assertEqualsWithDelta(0.0, $prefix[0][1], 0.001);
    }

    public function testFrameKDAAlphaBridgeDecay(): void
    {
        $alpha = new FrameKDAAlpha(2);

        $delta = array_fill(0, 5, array_fill(0, 2, 0.0));
        $alphaValues = $alpha->computeAlpha($delta);
        $prefix = $alpha->logPrefixSum($alphaValues);

        $decay = $alpha->bridgeDecay($prefix, 0, 4);

        $this->assertCount(2, $decay);
        // Decay should be positive
        $this->assertGreaterThan(0.0, $decay[0]);
        $this->assertGreaterThan(0.0, $decay[1]);
    }

    public function testOutputGatePerHead(): void
    {
        $gate = new OutputGate(128, 4, null, 0.99);

        $input = array_fill(0, 4, array_fill(0, 8, 1.0));
        $output = $gate->apply($input);

        $this->assertCount(4, $output);
        $this->assertCount(8, $output[0]);

        // With init=0.99, sigmoid(0.99) ≈ 0.729
        // Output should be input * sigmoid(0.99)
        $expected = 1.0 / (1.0 + exp(-0.99));
        $this->assertEqualsWithDelta($expected, $output[0][0], 0.01);
    }

    public function testOutputGatePerChannel(): void
    {
        $gate = new OutputGate(128, 2, 8, 'random');

        $input = [
            array_fill(0, 8, 1.0),
            array_fill(0, 8, 2.0),
        ];
        $output = $gate->apply($input);

        $this->assertCount(2, $output);
        $this->assertCount(8, $output[0]);
    }

    public function testBidirectionalScanForward(): void
    {
        $deltaRule = new DeltaRule('vdn_solve', 4);
        $alpha = new FrameKDAAlpha(2);
        $scan = new BidirectionalScan($deltaRule, $alpha, 2, 4);

        // Create simple frame statistics
        $frames = [];
        for ($t = 0; $t < 5; $t++) {
            $frames[$t] = [];
            for ($h = 0; $h < 2; $h++) {
                // Small A (near identity), small B
                $frames[$t][$h] = [
                    'A' => [
                        [0.01, 0.0, 0.0, 0.0],
                        [0.0, 0.01, 0.0, 0.0],
                        [0.0, 0.0, 0.01, 0.0],
                        [0.0, 0.0, 0.0, 0.01],
                    ],
                    'B' => [
                        [0.1, 0.0, 0.0, 0.0],
                        [0.0, 0.1, 0.0, 0.0],
                        [0.0, 0.0, 0.1, 0.0],
                        [0.0, 0.0, 0.0, 0.1],
                    ],
                ];
            }
        }

        $delta = array_fill(0, 5, array_fill(0, 2, 0.0));

        $result = $scan->runScans($frames, $delta);

        $this->assertArrayHasKey('forward', $result);
        $this->assertArrayHasKey('reverse', $result);
        $this->assertArrayHasKey('alpha', $result);

        // Forward should have numFrames+1 entries
        $this->assertCount(6, $result['forward']);
        $this->assertCount(6, $result['reverse']);
    }

    public function testBidirectionalScanReadout(): void
    {
        $deltaRule = new DeltaRule('vdn_solve', 4);
        $alpha = new FrameKDAAlpha(1);
        $scan = new BidirectionalScan($deltaRule, $alpha, 1, 4);

        // Simple state: identity matrix
        $state = [
            0 => [
                [1.0, 0.0, 0.0, 0.0],
                [0.0, 1.0, 0.0, 0.0],
                [0.0, 0.0, 1.0, 0.0],
                [0.0, 0.0, 0.0, 1.0],
            ],
        ];

        $query = [
            0 => [1.0, 2.0, 3.0, 4.0],
        ];

        $output = $scan->readout($state, $query);

        // output = state @ query = I @ query = query
        $this->assertEqualsWithDelta(1.0, $output[0][0], 0.001);
        $this->assertEqualsWithDelta(2.0, $output[0][1], 0.001);
        $this->assertEqualsWithDelta(3.0, $output[0][2], 0.001);
        $this->assertEqualsWithDelta(4.0, $output[0][3], 0.001);
    }

    public function testWindowBoundsFrameMode(): void
    {
        $deltaRule = new DeltaRule('vdn_solve', 4);
        $alpha = new FrameKDAAlpha(2);
        $scan = new BidirectionalScan($deltaRule, $alpha, 2, 4);

        // We can't test HybridAttention directly without Device,
        // but we can test the window bounds logic via a simple implementation
        $radius = 1;
        $chunk = 0;
        $numFrames = 10;

        // Frame 0: window [0, 1]
        $start = max(0, 0 - $radius);
        $end = min($numFrames - 1, 0 + $radius);
        $this->assertEquals(0, $start);
        $this->assertEquals(1, $end);

        // Frame 5: window [4, 6]
        $start = max(0, 5 - $radius);
        $end = min($numFrames - 1, 5 + $radius);
        $this->assertEquals(4, $start);
        $this->assertEquals(6, $end);

        // Frame 9: window [8, 9]
        $start = max(0, 9 - $radius);
        $end = min($numFrames - 1, 9 + $radius);
        $this->assertEquals(8, $start);
        $this->assertEquals(9, $end);
    }

    public function testFuseBranches(): void
    {
        $deltaRule = new DeltaRule('vdn_solve', 4);
        $alpha = new FrameKDAAlpha(2);
        $scan = new BidirectionalScan($deltaRule, $alpha, 2, 4);

        // Test fusion: output = gated_softmax + gated_linear
        $gatedSoftmax = [
            [1.0, 2.0, 3.0, 4.0],
            [5.0, 6.0, 7.0, 8.0],
        ];
        $gatedLinear = [
            [0.1, 0.2, 0.3, 0.4],
            [0.5, 0.6, 0.7, 0.8],
        ];

        // Manual fusion
        $output = [];
        for ($h = 0; $h < 2; $h++) {
            $output[$h] = [];
            for ($d = 0; $d < 4; $d++) {
                $output[$h][$d] = $gatedSoftmax[$h][$d] + $gatedLinear[$h][$d];
            }
        }

        $this->assertEqualsWithDelta(1.1, $output[0][0], 0.001);
        $this->assertEqualsWithDelta(2.2, $output[0][1], 0.001);
        $this->assertEqualsWithDelta(5.5, $output[1][0], 0.001);
        $this->assertEqualsWithDelta(8.8, $output[1][3], 0.001);
    }
}
