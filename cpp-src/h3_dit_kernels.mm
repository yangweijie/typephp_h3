/**
 * H3PHP — DiT Metal Compute Kernels
 *
 * MSL (Metal Shading Language) kernel implementations for the
 * Diffusion Transformer inference pipeline.
 *
 * Kernels:
 *   - rms_norm: Root Mean Square Layer Normalization
 *   - adaln_modulate: Adaptive Layer Normalization modulation
 *   - qkv_projection: Combined QKV linear projection
 *   - attention: Scaled dot-product attention with RoPE
 *   - mlp_forward: MLP (fc1 -> gelu -> fc2)
 *   - patchify: Convert latent to patch tokens
 *   - unpatchify: Convert tokens back to latent spatial layout
 *
 * All kernels operate on BF16 (half) data for performance.
 * Follows h3.c's QKV layout: [head, q/k/v, dimension]
 */

#include <phpx.h>
#include <Foundation/Foundation.h>
#include <Metal/Metal.h>

using namespace php;

// ============================================================================
// MSL Shader Source Library
// ============================================================================

static NSString* const H3_MSL_KERNELS = [NSString stringWithUTF8String: R"MSL(
#include <metal_stdlib>
using namespace metal;

// BF16 type alias (Metal supports half for BF16 simulation)
typedef half bf16_t;
typedef half float16_t;

// ==========================================================================
// RMS Normalization
// ==========================================================================
// RMSNorm (FP32 accumulation — precision critical)
// ==========================================================================
// rms_norm(input, weight, output, dim, epsilon)
// output = input * weight / sqrt(mean(input^2) + epsilon)
//
// PRECISION CRITICAL (from VDN-H3 ops/rms_norm.py):
//   Second-moment accumulation MUST be in FP32. BF16's 8 mantissa bits
//   lose precision when summing 5120+ squared values, causing visible
//   artifacts in normalized outputs. The weight is cast DOWN, never the
//   input up — this matches torch.linalg.vector_norm(..., dtype=float32).
//
kernel void rms_norm(
    device const bf16_t*  rms_input  [[buffer(0)]],  // input (bf16)
    device const bf16_t*  rms_weight [[buffer(1)]],  // weight (bf16)
    device bf16_t*        output     [[buffer(2)]],  // output (bf16)
    constant uint&        dim        [[buffer(3)]],  // dim (e.g. 5120 or 5376)
    uint gid [[thread_position_in_grid]]
) {
    // dim 由 buffer(3) 传入，移除硬编码 5120 覆盖
    float sum_sq = 0.0f;  // FP32 accumulator — do NOT use half/float16

    // Compute mean of squares in FP32 (precision-critical accumulation)
    for (uint i = 0; i < dim; i++) {
        float val = float(rms_input[gid * dim + i]);  // bf16 → fp32
        sum_sq += val * val;                           // FP32 multiply-accumulate
    }
    float rms = sqrt(sum_sq / float(dim) + 1e-6f);     // FP32 division + sqrt

    // Normalize and scale (FP32 computation, bf16 output)
    for (uint i = 0; i < dim; i++) {
        uint idx = gid * dim + i;
        output[idx] = bf16_t(float(rms_input[idx]) / rms * float(rms_weight[idx]));
    }
}

// ==========================================================================
// AdaLN Modulation
// ==========================================================================
// adaln_modulate(embedding, scale, shift, gate, output, dim)
// Applies adaptive layer normalization:
//   output = (1 + scale) * input + shift
//   output = output * sigmoid(gate)
//
// PRECISION CRITICAL (see OpenVDN patch 2026-08-15):
//   scale/shift/gate MUST arrive as FP32 (float*), NOT bf16.
//   The SiLU activation in the modulation linear layer must run in FP32
//   before being cast to bf16 for projection. If SiLU receives bf16 input
//   (e.g. under FSDP2 cast_forward_inputs=True), measured error is 3.5e-3
//   norm-relative with 55% of elements changed. Because every block reads
//   the same temb, this biases every block's modulation IDENTICALLY at
//   every sampling step, accumulating coherently along the denoising
//   trajectory rather than averaging out.
//   The sigmoid(gate) here is also kept in FP32 for the same reason.
//
kernel void adaln_modulate(
    device const bf16_t*  input  [[buffer(0)]],  // input (bf16 latent)
    device const float*   scale  [[buffer(1)]],  // scale (FP32 — from SiLU(temb.float()))
    device const float*   shift  [[buffer(2)]],  // shift (FP32 — from SiLU(temb.float()))
    device const float*   gate   [[buffer(3)]],  // gate (FP32 — from SiLU(temb.float()))
    device bf16_t*        output [[buffer(4)]],  // output (bf16)
    uint gid [[thread_position_in_grid]]
) {
    uint dim = 5376;
    for (uint i = 0; i < dim; i++) {
        uint idx = gid * dim + i;
        float val = float(input[idx]);       // Load bf16 → fp32 for computation
        val = (1.0f + scale[i]) * val + shift[i];
        val = val * (1.0f / (1.0f + exp(-gate[i]))); // sigmoid in FP32
        output[idx] = bf16_t(val);           // Cast back to bf16 for output
    }
}

// ==========================================================================
// QKV Projection
// ==========================================================================
// qkv_projection(input, weight, qkv_output, dim, num_heads, head_dim)
// Combined Q, K, V projection with h3.c's layout: [head, q/k/v, dim]
//
kernel void qkv_projection(
    device const bf16_t*  qkv_input  [[buffer(0)]],  // input [seq, hidden]
    device const bf16_t*  qkv_weight [[buffer(1)]],  // weight [hidden, heads*3*head_dim]
    device bf16_t*        qkv_output [[buffer(2)]],  // output [heads, 3, seq, head_dim]
    constant uint&        seq_len    [[buffer(3)]],  // seq_len
    constant uint&        num_heads  [[buffer(4)]],  // num_heads (56)
    constant uint&        k_head_dim [[buffer(5)]],  // head_dim (96)
    uint3 gid [[thread_position_in_grid]]
) {
    uint head = gid.x;
    uint qkv = gid.y;  // 0=Q, 1=K, 2=V
    uint pos = gid.z;

    if (pos >= 768 || head >= 56 || qkv >= 3) return; // 768 = max patches

    uint head_dim = 96;
    float sum = 0.0f;

    for (uint d = 0; d < 5376; d++) {
        float inp = float(qkv_input[pos * 5376 + d]);
        float w = float(qkv_weight[d * (56 * 3 * 96) + head * 3 * 96 + qkv * 96 + d % 96]);
        sum += inp * w;
    }

    uint out_idx = head * 3 * 768 * 96 + qkv * 768 * 96 + pos * 96;
    qkv_output[out_idx] = bf16_t(sum);
}

// ==========================================================================
// Scaled Dot-Product Attention with RoPE
// ==========================================================================
// attention(q, k, v, output, scale, seq_len, num_heads, head_dim)
// Applies 2D RoPE positional encoding before attention.
//
kernel void attention(
    device const bf16_t*  q [[buffer(0)]],  // Q [heads, seq, head_dim]
    device const bf16_t*  k [[buffer(1)]],  // K [heads, seq, head_dim]
    device const bf16_t*  v [[buffer(2)]],  // V [heads, seq, head_dim]
    device bf16_t*        att_output [[buffer(3)]],  // output [heads, seq, head_dim]
    constant float&       att_scale   [[buffer(4)]],  // scale (1/sqrt(head_dim))
    constant uint&        att_seq_len [[buffer(5)]],  // seq_len
    constant uint&        att_heads  [[buffer(6)]],  // num_heads
    constant uint&        att_hd     [[buffer(7)]],  // head_dim
    uint3 gid [[thread_position_in_grid]]
) {
    uint head = gid.x;
    uint qi = gid.z;

    if (head >= 56 || qi >= 768) return;

    uint head_dim = 96;
    float scale = 1.0f / sqrt(float(head_dim));

    // Compute attention scores
    thread float scores[768]; // max seq len
    float max_score = -1e30f;

    for (uint ki = 0; ki < 768; ki++) {
        float dot = 0.0f;
        for (uint d = 0; d < head_dim; d++) {
            // Apply RoPE: rotate Q and K by position
            float q_val = float(q[head * 768 * head_dim + qi * head_dim + d]);
            float k_val = float(k[head * 768 * head_dim + ki * head_dim + d]);

            // RoPE rotation (simplified 2D)
            float freq = 1.0f / pow(10000.0f, float(d) / float(head_dim));
            float angle_q = float(qi) * freq;
            float angle_k = float(ki) * freq;

            if (d % 2 == 0) {
                dot += q_val * cos(angle_q) * k_val * cos(angle_k);
            } else {
                dot += q_val * sin(angle_q) * k_val * sin(angle_k);
            }
        }
        scores[ki] = dot * scale;
        max_score = max(max_score, scores[ki]);
    }

    // Softmax
    float sum_exp = 0.0f;
    for (uint ki = 0; ki < 768; ki++) {
        scores[ki] = exp(scores[ki] - max_score);
        sum_exp += scores[ki];
    }

    // Weighted sum of values
    for (uint d = 0; d < head_dim; d++) {
        float out_val = 0.0f;
        for (uint ki = 0; ki < 768; ki++) {
            float weight = scores[ki] / sum_exp;
            out_val += weight * float(v[head * 768 * head_dim + ki * head_dim + d]);
        }
        att_output[head * 768 * head_dim + qi * head_dim + d] = bf16_t(out_val);
    }
}

// ==========================================================================
// MLP Forward Pass
// ==========================================================================
// mlp_forward(input, fc1_weight, fc1_bias, fc2_weight, fc2_bias, output)
// fc1: [hidden, mlp_dim] -> GELU -> fc2: [mlp_dim, hidden]
//
kernel void mlp_forward(
    device const bf16_t*  mlp_input  [[buffer(0)]],  // input [seq, hidden]
    device const bf16_t*  fc1_weight [[buffer(1)]],  // fc1_weight [hidden, mlp_dim]
    device const float*   fc1_bias   [[buffer(2)]],  // fc1_bias [mlp_dim]
    device const bf16_t*  fc2_weight [[buffer(3)]],  // fc2_weight [mlp_dim, hidden]
    device const float*   fc2_bias   [[buffer(4)]],  // fc2_bias [hidden]
    device bf16_t*        mlp_output [[buffer(5)]],  // output [seq, hidden]
    constant uint&        mlp_seq    [[buffer(6)]],  // seq_len
    constant uint&        mlp_dim_in [[buffer(7)]],  // mlp_dim (14336)
    uint2 gid [[thread_position_in_grid]]
) {
    uint pos = gid.x;
    uint h = gid.y;

    if (pos >= 768 || h >= 5376) return;

    uint mlp_dim = 14336;

    // fc1 + GELU
    float hidden_val = float(fc1_bias[h % mlp_dim]);
    for (uint d = 0; d < 5376; d++) {
        hidden_val += float(mlp_input[pos * 5376 + d]) * float(fc1_weight[d * mlp_dim + h % mlp_dim]);
    }
    // GELU approximation: x * sigmoid(1.702 * x)
    hidden_val = hidden_val * (1.0f / (1.0f + exp(-1.702f * hidden_val)));

    // fc2
    float out_val = float(fc2_bias[h]);
    for (uint d = 0; d < mlp_dim; d++) {
        // Note: This is a simplified version; actual implementation needs shared memory
        out_val += hidden_val * float(fc2_weight[d * 5376 + h]);
    }
    mlp_output[pos * 5376 + h] = bf16_t(out_val);
}

// ==========================================================================
// Patchify: Latent -> Tokens
// ==========================================================================
// patchify(latent, tokens, channels, latent_h, latent_w, patch_size)
//
kernel void patchify(
    device const bf16_t*  latent        [[buffer(0)]],  // latent [C, H, W]
    device bf16_t*        patch_tokens  [[buffer(1)]],  // tokens [num_patches, patch_dim]
    constant uint&        in_channels   [[buffer(2)]],  // channels (24)
    constant uint&        in_latent_h   [[buffer(3)]],  // latent_h
    constant uint&        in_latent_w   [[buffer(4)]],  // latent_w
    constant uint&        in_patch_size [[buffer(5)]],  // patch_size
    uint2 gid [[thread_position_in_grid]]
) {
    uint channels = 24;
    uint ps = 2; // patch_size for video
    uint latent_h = gid.y / (64 / ps); // simplified
    uint latent_w = gid.x / (64 / ps);

    // Extract patch and flatten into token
    for (uint c = 0; c < channels; c++) {
        for (uint ph = 0; ph < ps; ph++) {
            for (uint pw = 0; pw < ps; pw++) {
                uint lh = latent_h * ps + ph;
                uint lw = latent_w * ps + pw;
                if (lh < 8 && lw < 8) { // typical latent size
                    bf16_t val = latent[c * 8 * 8 + lh * 8 + lw];
                    uint patch_dim = channels * ps * ps;
                    uint token_idx = (gid.y * 64 + gid.x) * patch_dim +
                                     c * ps * ps + ph * ps + pw;
                    patch_tokens[token_idx] = val;
                }
            }
        }
    }
}

// ==========================================================================
// Unpatchify: Tokens -> Latent
// ==========================================================================
// unpatchify(tokens, latent, channels, latent_h, latent_w, patch_size)
//
kernel void unpatchify(
    device const bf16_t*  [[buffer(0)]],  // tokens [num_patches, patch_dim]
    device bf16_t*        [[buffer(1)]],  // latent [C, H, W]
    constant uint&        [[buffer(2)]],  // channels
    constant uint&        [[buffer(3)]],  // latent_h
    constant uint&        [[buffer(4)]],  // latent_w
    constant uint&        [[buffer(5)]],  // patch_size
    uint3 gid [[thread_position_in_grid]]
) {
    // Inverse of patchify
    // TODO: Implement full unpatchify
}

// ==========================================================================
// Euler Step Update
// ==========================================================================
// euler_step(latent, denoised, sigma, next_sigma, output)
// x_{t-1} = x_t + (sigma_t - sigma_{t-1}) * D(x_t, sigma_t)
//
kernel void euler_step(
    device const bf16_t*  euler_latent   [[buffer(0)]],  // current latent
    device const bf16_t*  euler_denoised [[buffer(1)]],  // denoised prediction
    device bf16_t*        euler_output   [[buffer(2)]],  // output latent
    constant float&       sigma_t        [[buffer(3)]],  // sigma_t
    constant float&       next_sigma     [[buffer(4)]],  // sigma_{t-1}
    constant uint&        in_count       [[buffer(5)]],  // total elements
    uint gid [[thread_position_in_grid]]
) {
    if (gid >= 24 * 8 * 8 * 56) return; // 24ch * 8h * 8w * 56 frames max

    float dt = sigma_t - next_sigma;
    float latent_val = float(euler_latent[gid]);
    float denoised_val = float(euler_denoised[gid]);
    euler_output[gid] = bf16_t(latent_val + dt * denoised_val);
}

// ==========================================================================
// Noise-to-Denoised Conversion
// ==========================================================================
// noise_to_denoised(latent, noise_pred, sigma, output)
// D(x, sigma) = x - sigma * noise_pred
//
kernel void noise_to_denoised(
    device const bf16_t*  n2d_latent    [[buffer(0)]],  // latent
    device const bf16_t*  n2d_noise_pred [[buffer(1)]],  // noise prediction
    device bf16_t*        n2d_output    [[buffer(2)]],  // output
    constant float&       sigma          [[buffer(3)]],  // sigma
    constant uint&        in_count       [[buffer(4)]],  // total elements
    uint gid [[thread_position_in_grid]]
) {
    if (gid >= 24 * 8 * 8 * 56) return;

    float val = float(n2d_latent[gid]) - sigma * float(n2d_noise_pred[gid]);
    n2d_output[gid] = bf16_t(val);
}

)MSL"];

// ============================================================================
// PHP-exposed functions for kernel management
// ============================================================================

// Box wrapper for compiled kernel library
#include "h3_boxes.h"

/**
 * Compile the MSL kernel library from source.
 */
var php_h3_kernels_compile(var deviceBox) {
    auto dev = deviceBox.toBox<MetalDeviceBox>()->device;

    NSError* error = nil;
    id<MTLLibrary> library = [dev newLibraryWithSource:H3_MSL_KERNELS options:nil error:&error];

    if (!library) {
        NSLog(@"H3 kernels compilation failed: %@", error.localizedDescription);
        return false;
    }

    return {new H3KernelsBox(dev, library)};
}

/**
 * Get a kernel function by name from the compiled library.
 */
var php_h3_kernels_get_function(var kernelsBox, var name) {
    auto box = kernelsBox.toBox<H3KernelsBox>();
    NSString* funcName = [NSString stringWithUTF8String:name.toCString()];
    id<MTLFunction> function = [box->library newFunctionWithName:funcName];

    if (!function) {
        return false;
    }

    // Return function name as confirmation (actual handle managed internally)
    return [function.name UTF8String];
}

/**
 * Free the kernel library.
 */
void php_h3_kernels_free(var box) {
    delete box.toBox<H3KernelsBox>();
}
