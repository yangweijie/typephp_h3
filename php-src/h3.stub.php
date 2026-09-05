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
 * @param int   $handle  Model handle from h3_model_load()
 * @param array $params  Generation parameters (all C library options)
 *
 * Expected keys:
 *   prompt, output_path, width, height, frames, steps, seed,
 *   denoise_reuse, dit_layers, ssd_streaming, use_int8_row_fc2,
 *   use_slower_bf16_mlp, use_slower_bf16_qkv, use_slower_bf16_attention_output,
 *   use_slower_row_major_attention_output, use_slower_unfused_int8_inputs,
 *   use_slower_unfused_qkv_rope, use_slower_scalar_qkv_rms,
 *   use_slower_uncached_int8_scales, use_slower_dynamic_fc1_k,
 *   use_slower_grouped_quantizer, video_vae_streaming, encoder_streaming,
 *   memory_plan_auto, preview_denoise
 *
 * @return int 0 on success, -1 on failure
 */
function h3_model_generate(int $handle, array $params): int {}

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
