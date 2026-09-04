<?php

/**
 * H3PHP — Delta Rule for Linear Attention.
 *
 * Implements the VdnDelta backend from VDN-H3, which uses exact Cholesky
 * inverse for the linear attention state recurrence.
 *
 * The delta rule computes:
 *   A = (k * beta)^T @ k    (d×d matrix, FP32 — precision critical)
 *   B = (v * beta)^T @ k    (d×d matrix, BF16 acceptable)
 *   S_out = (S_in @ diag(D) + B) @ (I + A)^{-1}
 *
 * Where (I + A)^{-1} is computed via Cholesky decomposition:
 *   L = cholesky(A + I)
 *   inv = L^{-T} @ L^{-1}
 *
 * From VDN-H3 delta_rule.py:
 *   VdnDelta: exact joint solve (default)
 *   SanaDelta: scaled subtractive (first-order)
 *   VdnScaledDelta: exact solve with Sana's key scaling
 *
 * PRECISION CRITICAL: A MUST be computed in FP32. BF16's 8 mantissa bits
 * break the conditioning of I + A, causing numerical instability in the
 * Cholesky decomposition.
 */

namespace H3Php\Inference\HybridAttention;

class DeltaRule
{
    /** Delta rule variant */
    private string $variant;

    /** Head dimension */
    private int $headDim;

    /**
     * @param string $variant Delta rule variant: 'vdn_solve', 'sana', 'vdn_scaled'
     * @param int    $headDim Head dimension (e.g. 128)
     */
    public function __construct(
        string $variant = 'vdn_solve',
        int $headDim = 128,
    ) {
        $this->variant = $variant;
        $this->headDim = $headDim;
    }

    /**
     * Compute frame statistics A and B.
     *
     * A = (k * beta)^T @ k  — d×d matrix (FP32)
     * B = (v * beta)^T @ k  — d×d matrix (BF16 acceptable)
     *
     * @param array $k    Key tensor [seq_len, head_dim]
     * @param array $v    Value tensor [seq_len, head_dim]
     * @param array $beta Per-head scaling [seq_len, num_heads]
     *
     * @return array{A: array, B: array} Frame statistics matrices
     */
    public function computeFrameStatistics(array $k, array $v, array $beta): array
    {
        $seqLen = count($k);
        $d = $this->headDim;

        // Initialize A and B matrices (d×d)
        $A = array_fill(0, $d, array_fill(0, $d, 0.0));
        $B = array_fill(0, $d, array_fill(0, $d, 0.0));

        // Compute weighted k and v
        // k_weighted[i] = k[i] * beta[i]  (element-wise per head)
        for ($i = 0; $i < $seqLen; ++$i) {
            $beta_i = $beta[$i] ?? 1.0;

            // A += (k * beta)^T @ k  (outer product, FP32)
            for ($di = 0; $di < $d; ++$di) {
                $k_w = ($k[$i][$di] ?? 0.0) * $beta_i;
                for ($dj = 0; $dj < $d; ++$dj) {
                    $A[$di][$dj] += $k_w * ($k[$i][$dj] ?? 0.0);
                }
            }

            // B += (v * beta)^T @ k  (outer product)
            for ($di = 0; $di < $d; ++$di) {
                $v_w = ($v[$i][$di] ?? 0.0) * $beta_i;
                for ($dj = 0; $dj < $d; ++$dj) {
                    $B[$di][$dj] += $v_w * ($k[$i][$dj] ?? 0.0);
                }
            }
        }

        // Apply variant-specific scaling
        if ('vdn_scaled' === $this->variant) {
            $scale = 1.0 / sqrt($seqLen);
            for ($di = 0; $di < $d; ++$di) {
                for ($dj = 0; $dj < $d; ++$dj) {
                    $A[$di][$dj] *= $scale * $scale;
                    $B[$di][$dj] *= $scale;
                }
            }
        }

        // Symmetrize A for numerical stability
        for ($di = 0; $di < $d; ++$di) {
            for ($dj = $di + 1; $dj < $d; ++$dj) {
                $avg = 0.5 * ($A[$di][$dj] + $A[$dj][$di]);
                $A[$di][$dj] = $avg;
                $A[$dj][$di] = $avg;
            }
        }

        return ['A' => $A, 'B' => $B];
    }

    /**
     * Apply the delta rule: compute state update.
     *
     * S_out = (S_in @ diag(D) + B) @ (I + A)^{-1}
     *
     * @param array $stateIn Current state [head_dim, head_dim]
     * @param array $A       Frame statistics A [head_dim, head_dim]
     * @param array $B       Frame statistics B [head_dim, head_dim]
     * @param array $decay   Decay vector D [head_dim]
     *
     * @return array New state [head_dim, head_dim]
     */
    public function apply(array $stateIn, array $A, array $B, array $decay): array
    {
        $d = $this->headDim;

        switch ($this->variant) {
            case 'sana':
                return $this->applySana($stateIn, $A, $B, $decay);
            case 'vdn_scaled':
            case 'vdn_solve':
            default:
                return $this->applyVdnSolve($stateIn, $A, $B, $decay);
        }
    }

    /**
     * VdnDelta: exact joint solve via Cholesky.
     *
     * S_out = (S_in @ diag(D) + B) @ (I + A)^{-1}
     *
     * (I + A)^{-1} computed as L^{-T} @ L^{-1} where L = cholesky(A + I)
     */
    private function applyVdnSolve(array $stateIn, array $A, array $B, array $decay): array
    {
        $d = $this->headDim;

        // Step 1: S_in @ diag(D) + B
        $numerator = array_fill(0, $d, array_fill(0, $d, 0.0));
        for ($i = 0; $i < $d; ++$i) {
            for ($j = 0; $j < $d; ++$j) {
                $numerator[$i][$j] = $stateIn[$i][$j] * ($decay[$j] ?? 1.0) + $B[$i][$j];
            }
        }

        // Step 2: Compute (I + A)^{-1} via Cholesky
        $AI = $A;  // Copy A
        for ($i = 0; $i < $d; ++$i) {
            $AI[$i][$i] += 1.0;  // A + I
        }

        // Cholesky decomposition: A + I = L @ L^T
        $L = $this->cholesky($AI);

        if (null === $L) {
            // Fallback: return numerator if Cholesky fails
            return $numerator;
        }

        // Compute L^{-1}
        $Linv = $this->triangularInverse($L);

        // Compute (I + A)^{-1} = L^{-T} @ L^{-1}
        $inv = $this->matmul($this->transpose($Linv), $Linv);

        // Step 3: S_out = numerator @ (I + A)^{-1}
        return $this->matmul($numerator, $inv);
    }

    /**
     * SanaDelta: scaled subtractive (first-order).
     *
     * S_out = (S_in @ diag(D)) @ (I - c^2 @ A) + c @ B
     * where c = 1/sqrt(seq_len)
     */
    private function applySana(array $stateIn, array $A, array $B, array $decay): array
    {
        $d = $this->headDim;
        $c = 1.0 / sqrt(max(1, $d));  // Simplified; actual uses seq_len

        $result = array_fill(0, $d, array_fill(0, $d, 0.0));

        for ($i = 0; $i < $d; ++$i) {
            for ($j = 0; $j < $d; ++$j) {
                // (S_in @ diag(D)) @ (I - c^2 @ A)
                $term1 = $stateIn[$i][$j] * ($decay[$j] ?? 1.0);
                $term2 = $term1;
                for ($k = 0; $k < $d; ++$k) {
                    $term2 -= $term1 * $c * $c * $A[$j][$k] / ($decay[$k] ?? 1.0);
                }
                // + c @ B
                $result[$i][$j] = $term2 + $c * $B[$i][$j];
            }
        }

        return $result;
    }

    /**
     * Cholesky decomposition: M = L @ L^T.
     *
     * @param array $M Symmetric positive-definite matrix [d, d]
     *
     * @return array|null Lower triangular L [d, d] or null if decomposition fails
     */
    public function cholesky(array $M): ?array
    {
        $d = count($M);
        $L = array_fill(0, $d, array_fill(0, $d, 0.0));

        for ($i = 0; $i < $d; ++$i) {
            for ($j = 0; $j <= $i; ++$j) {
                $sum = 0.0;
                for ($k = 0; $k < $j; ++$k) {
                    $sum += $L[$i][$k] * $L[$j][$k];
                }

                if ($i === $j) {
                    $val = $M[$i][$i] - $sum;
                    if ($val <= 0) {
                        // Not positive definite — add small regularization
                        $val = 1e-6;
                    }
                    $L[$i][$j] = sqrt($val);
                } else {
                    $denom = $L[$j][$j];
                    if (abs($denom) < 1e-10) {
                        $L[$i][$j] = 0.0;
                    } else {
                        $L[$i][$j] = ($M[$i][$j] - $sum) / $denom;
                    }
                }
            }
        }

        return $L;
    }

    /**
     * Triangular matrix inverse (forward substitution).
     *
     * @param array $L Lower triangular matrix [d, d]
     *
     * @return array L^{-1} [d, d]
     */
    public function triangularInverse(array $L): array
    {
        $d = count($L);
        $inv = array_fill(0, $d, array_fill(0, $d, 0.0));

        for ($i = 0; $i < $d; ++$i) {
            $inv[$i][$i] = 1.0 / $L[$i][$i];
            for ($j = 0; $j < $i; ++$j) {
                $sum = 0.0;
                for ($k = $j; $k < $i; ++$k) {
                    $sum += $L[$i][$k] * $inv[$k][$j];
                }
                $inv[$i][$j] = -$sum / $L[$i][$i];
            }
        }

        return $inv;
    }

    /**
     * Matrix multiplication: C = A @ B.
     */
    public function matmul(array $A, array $B): array
    {
        $m = count($A);
        $n = count($B[0]);
        $p = count($B);

        $C = array_fill(0, $m, array_fill(0, $n, 0.0));

        for ($i = 0; $i < $m; ++$i) {
            for ($j = 0; $j < $n; ++$j) {
                $sum = 0.0;
                for ($k = 0; $k < $p; ++$k) {
                    $sum += $A[$i][$k] * $B[$k][$j];
                }
                $C[$i][$j] = $sum;
            }
        }

        return $C;
    }

    /**
     * Matrix transpose.
     */
    public function transpose(array $M): array
    {
        $m = count($M);
        $n = count($M[0]);

        $T = array_fill(0, $n, array_fill(0, $m, 0.0));

        for ($i = 0; $i < $m; ++$i) {
            for ($j = 0; $j < $n; ++$j) {
                $T[$j][$i] = $M[$i][$j];
            }
        }

        return $T;
    }

    /**
     * Get the variant name.
     */
    public function getVariant(): string
    {
        return $this->variant;
    }

    /**
     * Get the head dimension.
     */
    public function getHeadDim(): int
    {
        return $this->headDim;
    }
}
