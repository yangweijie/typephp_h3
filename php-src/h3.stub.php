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
 * @param int    $handle          Model handle from h3_model_load()
 * @param string $prompt          Text prompt for video generation
 * @param string $output_path     Output MP4 file path
 * @param int    $width           Video width (multiple of 32)
 * @param int    $height          Video height (multiple of 32)
 * @param int    $frames          Number of frames (22-362)
 * @param int    $steps           Denoising steps (1-1000)
 * @param int    $seed            Random seed
 * @param int    $denoise_reuse   Denoiser reuse level (1=quality, 2=fast, 3=aggressive)
 * @param int    $dit_layers      Number of DiT blocks (35-50)
 * @param int    $ssd_streaming   Enable SSD streaming mode (0 or 1)
 * @param int    $use_int8_row_fc2 Use INT8 per-row FC2 quantization (0 or 1)
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
    int $use_int8_row_fc2
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
