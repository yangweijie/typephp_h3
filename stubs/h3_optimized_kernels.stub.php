<?php
/**
 * H3PHP — Optimized Metal Kernel Stubs
 *
 * PHP declarations for optimized native Metal kernel functions.
 * Implemented in cpp-src/h3_optimized_kernels.mm.
 */

/**
 * Compile the optimized MSL kernel library.
 *
 * @param mixed $device Device handle
 * @return mixed Kernel library handle
 * @throws \RuntimeException If compilation fails
 */
function h3_optimized_kernels_compile(mixed $device): mixed {}

/**
 * Get an optimized kernel function by name.
 *
 * @param mixed $kernels Kernel library handle
 * @param string $name Function name (e.g., "flash_attention_tiled", "fused_qkv_rope")
 * @return string|false Function name on success, false on failure
 */
function h3_optimized_kernels_get_function(mixed $kernels, string $name): string|false {}

/**
 * Free the optimized kernel library.
 *
 * @param mixed $kernels Kernel library handle
 */
function h3_optimized_kernels_free(mixed $kernels): void {}
