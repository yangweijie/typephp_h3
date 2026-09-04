<?php

/**
 * H3PHP — DeltaRule Test.
 */

namespace H3Php\Tests\Inference\HybridAttention;

use H3Php\Inference\HybridAttention\DeltaRule;
use PHPUnit\Framework\TestCase;

class DeltaRuleTest extends TestCase
{
    public function testCholeskyIdentity(): void
    {
        $rule = new DeltaRule('vdn_solve', 4);

        // Cholesky of identity matrix should be identity
        $I = [
            [1.0, 0.0, 0.0, 0.0],
            [0.0, 1.0, 0.0, 0.0],
            [0.0, 0.0, 1.0, 0.0],
            [0.0, 0.0, 0.0, 1.0],
        ];

        $L = $rule->cholesky($I);

        $this->assertNotNull($L);
        for ($i = 0; $i < 4; ++$i) {
            for ($j = 0; $j < 4; ++$j) {
                $this->assertEqualsWithDelta($I[$i][$j], $L[$i][$j], 0.001);
            }
        }
    }

    public function testCholesky2x2(): void
    {
        $rule = new DeltaRule('vdn_solve', 2);

        // M = [[4, 2], [2, 3]] is positive definite
        $M = [
            [4.0, 2.0],
            [2.0, 3.0],
        ];

        $L = $rule->cholesky($M);

        $this->assertNotNull($L);
        // L = [[2, 0], [1, sqrt(2)]]
        $this->assertEqualsWithDelta(2.0, $L[0][0], 0.001);
        $this->assertEqualsWithDelta(0.0, $L[0][1], 0.001);
        $this->assertEqualsWithDelta(1.0, $L[1][0], 0.001);
        $this->assertEqualsWithDelta(sqrt(2.0), $L[1][1], 0.001);

        // Verify L @ L^T = M
        $LLt = $rule->matmul($L, $rule->transpose($L));
        for ($i = 0; $i < 2; ++$i) {
            for ($j = 0; $j < 2; ++$j) {
                $this->assertEqualsWithDelta($M[$i][$j], $LLt[$i][$j], 0.001);
            }
        }
    }

    public function testTriangularInverse(): void
    {
        $rule = new DeltaRule('vdn_solve', 2);

        $L = [
            [2.0, 0.0],
            [1.0, 1.5],
        ];

        $Linv = $rule->triangularInverse($L);

        // Verify L @ L^{-1} = I
        $product = $rule->matmul($L, $Linv);
        $this->assertEqualsWithDelta(1.0, $product[0][0], 0.001);
        $this->assertEqualsWithDelta(0.0, $product[0][1], 0.001);
        $this->assertEqualsWithDelta(0.0, $product[1][0], 0.001);
        $this->assertEqualsWithDelta(1.0, $product[1][1], 0.001);
    }

    public function testVdnSolveIdentity(): void
    {
        $rule = new DeltaRule('vdn_solve', 2);

        // With A=0, B=I, D=I: S_out = (S_in + I) @ I = S_in + I
        $stateIn = [
            [1.0, 0.0],
            [0.0, 1.0],
        ];
        $A = [
            [0.0, 0.0],
            [0.0, 0.0],
        ];
        $B = [
            [1.0, 0.0],
            [0.0, 1.0],
        ];
        $decay = [1.0, 1.0];

        $result = $rule->apply($stateIn, $A, $B, $decay);

        // S_out = (I @ I + I) @ I = 2I
        $this->assertEqualsWithDelta(2.0, $result[0][0], 0.001);
        $this->assertEqualsWithDelta(0.0, $result[0][1], 0.001);
        $this->assertEqualsWithDelta(0.0, $result[1][0], 0.001);
        $this->assertEqualsWithDelta(2.0, $result[1][1], 0.001);
    }

    public function testFrameStatisticsShape(): void
    {
        $rule = new DeltaRule('vdn_solve', 4);

        // Create simple K, V, beta
        $k = array_fill(0, 10, array_fill(0, 4, 0.5));
        $v = array_fill(0, 10, array_fill(0, 4, 0.3));
        $beta = array_fill(0, 10, 1.0);

        $stats = $rule->computeFrameStatistics($k, $v, $beta);

        $this->assertArrayHasKey('A', $stats);
        $this->assertArrayHasKey('B', $stats);

        // A and B should be 4×4
        $this->assertCount(4, $stats['A']);
        $this->assertCount(4, $stats['A'][0]);
        $this->assertCount(4, $stats['B']);
        $this->assertCount(4, $stats['B'][0]);
    }

    public function testFrameStatisticsSymmetry(): void
    {
        $rule = new DeltaRule('vdn_solve', 4);

        $k = [];
        for ($i = 0; $i < 10; ++$i) {
            $k[] = [mt_rand() / mt_getrandmax(), mt_rand() / mt_getrandmax(),
                mt_rand() / mt_getrandmax(), mt_rand() / mt_getrandmax()];
        }
        $v = [];
        for ($i = 0; $i < 10; ++$i) {
            $v[] = [mt_rand() / mt_getrandmax(), mt_rand() / mt_getrandmax(),
                mt_rand() / mt_getrandmax(), mt_rand() / mt_getrandmax()];
        }
        $beta = array_fill(0, 10, 1.0);

        $stats = $rule->computeFrameStatistics($k, $v, $beta);

        // A should be symmetric
        for ($i = 0; $i < 4; ++$i) {
            for ($j = $i + 1; $j < 4; ++$j) {
                $this->assertEqualsWithDelta($stats['A'][$i][$j], $stats['A'][$j][$i], 0.001);
            }
        }
    }

    public function testMatmul(): void
    {
        $rule = new DeltaRule('vdn_solve', 2);

        $A = [[1.0, 2.0], [3.0, 4.0]];
        $B = [[5.0, 6.0], [7.0, 8.0]];

        $C = $rule->matmul($A, $B);

        // C[0][0] = 1*5 + 2*7 = 19
        $this->assertEqualsWithDelta(19.0, $C[0][0], 0.001);
        // C[0][1] = 1*6 + 2*8 = 22
        $this->assertEqualsWithDelta(22.0, $C[0][1], 0.001);
        // C[1][0] = 3*5 + 4*7 = 43
        $this->assertEqualsWithDelta(43.0, $C[1][0], 0.001);
        // C[1][1] = 3*6 + 4*8 = 50
        $this->assertEqualsWithDelta(50.0, $C[1][1], 0.001);
    }

    public function testTranspose(): void
    {
        $rule = new DeltaRule('vdn_solve', 2);

        $M = [[1.0, 2.0], [3.0, 4.0]];
        $T = $rule->transpose($M);

        $this->assertEqualsWithDelta(1.0, $T[0][0], 0.001);
        $this->assertEqualsWithDelta(3.0, $T[0][1], 0.001);
        $this->assertEqualsWithDelta(2.0, $T[1][0], 0.001);
        $this->assertEqualsWithDelta(4.0, $T[1][1], 0.001);
    }
}
