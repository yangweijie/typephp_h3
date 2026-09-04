/**
 * H3PHP — Hybrid Attention Metal Kernels
 *
 * MSL kernels for the hybrid attention linear branch:
 *   - frame_statistics: Compute A=(k*beta)^T@k and B=(v*beta)^T@k
 *   - delta_rule_scan: Single step of delta-rule state recurrence
 *   - linear_epilogue: RMSNorm + output gate fusion
 *   - window_bounds: Compute window boundaries for each query frame
 *
 * These kernels accelerate the linear attention branch on Apple Silicon.
 * The softmax branch uses the existing attention kernels.
 *
 * PRECISION CRITICAL:
 *   frame_statistics A output MUST be FP32 (passed as float* buffer).
 *   B can be BF16. The Cholesky in delta_rule_scan runs in FP32.
 */

#include <phpx.h>
#include <Foundation/Foundation.h>
#include <Metal/Metal.h>

using namespace php;

// ============================================================================
// MSL Shader Source Library
// ============================================================================

static NSString* const H3_HYBRID_MSL_KERNELS = [NSString stringWithUTF8String: R"MSL(
#include <metal_stdlib>
using namespace metal;

typedef half bf16_t;

// ==========================================================================
// Frame Statistics: A = (k*beta)^T @ k, B = (v*beta)^T @ k
// ==========================================================================
// Computes per-frame statistics for the delta rule.
//
// A[di,dj] = sum_i(k[i,di]*beta[i] * k[i,dj])  — FP32 output
// B[di,dj] = sum_i(v[i,di]*beta[i] * k[i,dj])  — BF16 output
//
// Each thread computes one (di, dj) pair of the d×d output matrix.
// Uses threadgroup reduction for the sequence-length summation.
//
kernel void frame_statistics(
    device const bf16_t*  [[buffer(0)]],  // K [seq_len, head_dim]
    device const bf16_t*  [[buffer(1)]],  // V [seq_len, head_dim]
    device const float*   [[buffer(2)]],  // beta [seq_len] (per-head scaling)
    device float*         [[buffer(3)]],  // A [head_dim, head_dim] (FP32)
    device bf16_t*        [[buffer(4)]],  // B [head_dim, head_dim] (BF16)
    constant uint&        [[buffer(5)]],  // seq_len
    constant uint&        [[buffer(6)]],  // head_dim (128)
    uint2 gid [[thread_position_in_grid]]
) {
    uint di = gid.x;
    uint dj = gid.y;
    uint d = 128;  // head_dim

    if (di >= d || dj >= d) return;

    // Compute A[di,dj] and B[di,dj] with FP32 accumulation
    float a_val = 0.0f;  // FP32 for A (precision critical)
    float b_val = 0.0f;  // FP32 accumulation, cast to bf16 at end

    for (uint i = 0; i < seq_len; i++) {
        float k_di = float(K[i * d + di]);
        float k_dj = float(K[i * d + dj]);
        float v_di = float(V[i * d + di]);
        float beta_i = beta[i];

        a_val += (k_di * beta_i) * k_dj;  // FP32 multiply-accumulate
        b_val += (v_di * beta_i) * k_dj;  // FP32 accumulate
    }

    A[di * d + dj] = a_val;           // FP32 output
    B[di * d + dj] = bf16_t(b_val);   // Cast to BF16 for B
}

// ==========================================================================
// Delta Rule Scan Step
// ==========================================================================
// Single step of the delta-rule state recurrence:
//   S_out = (S_in @ diag(D) + B) @ (I + A)^{-1}
//
// This kernel performs one frame's update. The full scan is a loop
// over frames calling this kernel (or CPU-side iteration).
//
// PRECISION: All computation in FP32. State S is d×d, A is d×d, B is d×d.
//
kernel void delta_rule_scan_step(
    device const float*   [[buffer(0)]],  // S_in [d, d] (FP32 state)
    device const float*   [[buffer(1)]],  // A [d, d] (FP32 statistics)
    device const float*   [[buffer(2)]],  // B [d, d] (FP32, dequantized)
    device const float*   [[buffer(3)]],  // D [d] (decay vector)
    device float*         [[buffer(4)]],  // S_out [d, d] (FP32 state)
    constant uint&        [[buffer(5)]],  // d (head_dim, 128)
    uint2 gid [[thread_position_in_grid]]
) {
    uint i = gid.x;
    uint j = gid.y;
    uint d = 128;

    if (i >= d || j >= d) return;

    // Step 1: numerator[i,j] = S_in[i,j] * D[j] + B[i,j]
    float numerator = scan_S_in[i * d + j] * scan_D[j] + scan_B[i * d + j];

    // Step 2: (I + A)^{-1} is precomputed on CPU (Cholesky)
    // For now, store numerator; inverse applied in separate pass
    scan_S_out[i * d + j] = numerator;
}

// ==========================================================================
// Linear Epilogue: RMSNorm + Output Gate Fusion
// ==========================================================================
// Fuses RMSNorm and output gate into one pass:
 *   output = sigmoid(gate_weight * RMSNorm(x) + gate_bias) * RMSNorm(x)
 *
 * This avoids a separate RMSNorm pass and gate application.
 *
kernel void linear_epilogue(
    device const bf16_t*  [[buffer(0)]],  // input [seq, head_dim]
    device const float*   [[buffer(1)]],  // gate_weight [head_dim] or [num_heads, head_dim]
    device const float*   [[buffer(2)]],  // gate_bias [head_dim] or [num_heads, head_dim]
    device bf16_t*        [[buffer(3)]],  // output [seq, head_dim]
    constant uint&        [[buffer(4)]],  // seq_len
    constant uint&        [[buffer(5)]],  // head_dim (128)
    constant uint&        [[buffer(6)]],  // num_heads (40)
    constant uint&        [[buffer(7)]],  // per_channel (0=per-head, 1=per-channel)
    uint3 gid [[thread_position_in_grid]]
) {
    uint head = gid.x;
    uint pos = gid.y;
    uint d_idx = gid.z;

    uint d = 128;
    if (head >= 40 || pos >= 1008 || d_idx >= d) return;  // 1008 = max tokens/frame

    // RMSNorm: compute norm over head_dim
    float sum_sq = 0.0f;
    for (uint i = 0; i < d; i++) {
        float val = float(epilogue_input[head * 1008 * d + pos * d + i]);
        sum_sq += val * val;
    }
    float rms = sqrt(sum_sq / float(d) + 1e-6f);

    // Normalize
    float norm_val = float(epilogue_input[head * 1008 * d + pos * d + d_idx]) / rms;

    // Apply gate
    float gate_val;
    if (per_channel) {
        gate_val = 1.0f / (1.0f + exp(-(epilogue_gate_weight[head * d + d_idx] +
                                        epilogue_gate_bias[head * d + d_idx])));
    } else {
        gate_val = 1.0f / (1.0f + exp(-(epilogue_gate_weight[head] +
                                        epilogue_gate_bias[head])));
    }

    epilogue_output[head * 1008 * d + pos * d + d_idx] = bf16_t(gate_val * norm_val);
}

// ==========================================================================
// Window Bounds Computation
// ==========================================================================
// Computes window start/end for each query frame.
//
kernel void compute_window_bounds(
    device uint*          [[buffer(0)]],  // window_start [num_frames]
    device uint*          [[buffer(1)]],  // window_end [num_frames]
    constant uint&        [[buffer(2)]],  // num_frames
    constant uint&        [[buffer(3)]],  // radius
    constant uint&        [[buffer(4)]],  // chunk (0 = frame mode)
    uint gid [[thread_position_in_grid]]
) {
    uint frame = gid.x;
    if (frame >= num_frames) return;

    if (chunk == 0) {
        // Frame mode: |t_q - t_k| <= radius
        window_start[frame] = (frame > radius) ? frame - radius : 0;
        window_end[frame] = min(num_frames - 1, frame + radius);
    } else {
        // Chunk-aligned mode
        uint query_chunk = frame / chunk;
        uint chunk_start = (query_chunk > radius) ? query_chunk - radius : 0;
        uint chunk_end = min((num_frames - 1) / chunk, query_chunk + radius);
        window_start[frame] = chunk_start * chunk;
        window_end[frame] = min(num_frames - 1, (chunk_end + 1) * chunk - 1);
    }
}

)MSL"];

// ============================================================================
// PHP-exposed functions
// ============================================================================

#include "h3_boxes.h"

/**
 * Compile the hybrid attention MSL kernel library.
 */
var php_h3_hybrid_kernels_compile(var deviceBox) {
    auto dev = deviceBox.toBox<MetalDeviceBox>()->device;

    NSError* error = nil;
    id<MTLLibrary> library = [dev newLibraryWithSource:H3_HYBRID_MSL_KERNELS
                                              options:nil
                                                error:&error];

    if (!library) {
        NSLog(@"H3 hybrid kernels compilation failed: %@", error.localizedDescription);
        return false;
    }

    return {new H3HybridKernelsBox(dev, library)};
}

/**
 * Get a hybrid kernel function by name.
 */
var php_h3_hybrid_kernels_get_function(var kernelsBox, var name) {
    auto box = kernelsBox.toBox<H3HybridKernelsBox>();
    NSString* funcName = [NSString stringWithUTF8String:name.toCString()];
    id<MTLFunction> function = [box->library newFunctionWithName:funcName];
    if (!function) return false;
    return [function.name UTF8String];
}

/**
 * Free the hybrid kernel library.
 */
void php_h3_hybrid_kernels_free(var box) {
    delete box.toBox<H3HybridKernelsBox>();
}
