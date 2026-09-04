<?php
/**
 * H3PHP — Metal Compute Pipeline Stubs
 *
 * PHP declarations for native Metal compute pipeline functions.
 * Implemented in cpp-src/metal_pipeline.mm via php_ prefix ABI.
 */

/**
 * Create a compute pipeline state from Metal shader source (MSL).
 *
 * @param mixed $device Device handle
 * @param string $shaderSource Metal Shading Language source code
 * @param string $functionName Kernel function name
 * @return mixed Pipeline state handle
 */
function h3_metal_pipeline_create(mixed $device, string $shaderSource, string $functionName): mixed {}

/**
 * Create a compute pipeline from a pre-compiled .metallib file.
 *
 * @param mixed $device Device handle
 * @param string $metallibPath Path to .metallib file
 * @param string $functionName Kernel function name
 * @return mixed Pipeline state handle
 */
function h3_metal_pipeline_create_with_file(mixed $device, string $metallibPath, string $functionName): mixed {}

/**
 * Get the maximum total threads per threadgroup for this pipeline.
 *
 * @param mixed $pipeline Pipeline state handle
 */
function h3_metal_pipeline_get_max_threads_per_threadgroup(mixed $pipeline): int {}

/**
 * Free a compute pipeline state.
 *
 * @param mixed $pipeline Pipeline state handle
 */
function h3_metal_pipeline_free(mixed $pipeline): void {}
