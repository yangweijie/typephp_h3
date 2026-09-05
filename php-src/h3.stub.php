<?php

/**
 * H3PHP — C Inference Engine Bridge (libh3.a).
 *
 * These functions wrap the C reference implementation for full model inference.
 * The C library handles: safetensors loading, Metal kernel execution,
 * DiT forward pass, VAE decode, and FFmpeg muxing.
 */

/**
 * Load model directory and initialize Metal device.
 *
 * @param string $model_dir Path to model directory (e.g. /path/to/MiniMax-H3-Convrot)
 * @return int Model handle, or -1 on failure (use h3_get_last_error() for details)
 */
function h3_model_load(string $model_dir): int {}

/**
 * Get device information string.
 *
 * @param int $handle Model handle from h3_model_load()
 * @return string Device name and capabilities
 */
function h3_model_get_device_name(int $handle): string {}

/**
 * Get model information string.
 *
 * @param int $handle Model handle from h3_model_load()
 * @return string Model component sizes and tensor counts
 */
function h3_model_get_info(int $handle): string {}

/**
 * Generate video from text prompt.
 *
 * @param int    $handle                              Model handle from h3_model_load()
 * @param string $prompt                              Text prompt for video generation
 * @param string $output_path                         Output MP4 file path
 * @param int    $width                               Video width (multiple of 32)
 * @param int    $height                              Video height (multiple of 32)
 * @param int    $frames                              Number of frames (22-362)
 * @param int    $steps                               Denoising steps (1-1000)
 * @param int    $seed                                Random seed
 * @param int    $denoise_reuse                       Denoiser reuse level (1=quality, 2=fast, 3=aggressive)
 * @param int    $dit_layers                          Number of DiT blocks (35-50)
 * @param int    $ssd_streaming                       Enable SSD streaming mode (0 or 1)
 * @param int    $use_int8_row_fc2                    Use INT8 per-row FC2 quantization (0 or 1)
 * @param int    $use_slower_bf16_mlp                 Force close-reference BF16/MPS MLP
 * @param int    $use_slower_bf16_qkv                 Force close-reference BF16 QKV
 * @param int    $use_slower_bf16_attention_output   Force BF16 attention output
 * @param int    $use_slower_row_major_attention_output Materialize row-major BF16 before int8 quantization
 * @param int    $use_slower_unfused_int8_inputs      Keep int8 projection-input quantization standalone
 * @param int    $use_slower_unfused_qkv_rope        Keep QK norm and RoPE as separate kernel
 * @param int    $use_slower_scalar_qkv_rms           Force scalar BF16 loads in fused Q/K RMS reducer
 * @param int    $use_slower_uncached_int8_scales     Reread int8 dequantization scales from device memory
 * @param int    $use_slower_dynamic_fc1_k            Use generic runtime-bound FC1 TensorOps K loop
 * @param int    $use_slower_grouped_quantizer        Force original 256-thread FC2 grouped activation quantizer
 * @param int    $video_vae_streaming                 Stream video VAE decoder weights (0 or 1)
 * @param int    $encoder_streaming                   Release text encoder after conditioning (0 or 1)
 * @param int    $memory_plan_auto                    Auto-pick streaming/int8/layers (0 or 1)
 * @param int    $preview_denoise                     Decode and deliver one frame after each Euler step (0 or 1)
 * @return int 0 on success, -1 on failure
 */
function h3_model_generate(
    int $handle,
    string $prompt,
    string $output_path,
    int $width,
    int $height,
    int $frames,
    int $steps,
    int $seed,
    int $denoise_reuse,
    int $dit_layers,
    int $ssd_streaming,
    int $use_int8_row_fc2,
    int $use_slower_bf16_mlp,
    int $use_slower_bf16_qkv,
    int $use_slower_bf16_attention_output,
    int $use_slower_row_major_attention_output,
    int $use_slower_unfused_int8_inputs,
    int $use_slower_unfused_qkv_rope,
    int $use_slower_scalar_qkv_rms,
    int $use_slower_uncached_int8_scales,
    int $use_slower_dynamic_fc1_k,
    int $use_slower_grouped_quantizer,
    int $video_vae_streaming,
    int $encoder_streaming,
    int $memory_plan_auto,
    int $preview_denoise
): int {}

/**
 * Free model handle and release resources.
 *
 * @param int $handle Model handle from h3_model_load()
 */
function h3_model_free(int $handle): void {}

/**
 * Get the last error message.
 *
 * @return string Last error description
 */
function h3_get_last_error(): string {}
