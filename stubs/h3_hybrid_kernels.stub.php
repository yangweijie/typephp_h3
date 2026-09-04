<?php
/**
 * H3PHP — Hybrid Attention Kernel Stubs
 *
 * PHP declarations for hybrid attention Metal kernel functions.
 * Implemented in cpp-src/h3_hybrid_kernels.mm.
 */

/**
 * Compile the hybrid attention MSL kernel library.
 *
 * @param mixed $device Device handle
 * @return mixed Kernel library handle
 * @throws \RuntimeException If compilation fails
 */
function h3_hybrid_kernels_compile(mixed $device): mixed {}

/**
 * Get a hybrid kernel function by name.
 *
 * @param mixed $kernels Kernel library handle
 * @param string $name Function name (e.g., "frame_statistics", "linear_epilogue")
 * @return string|false Function name on success, false on failure
 */
function h3_hybrid_kernels_get_function(mixed $kernels, string $name): string|false {}

/**
 * Free the hybrid kernel library.
 *
 * @param mixed $kernels Kernel library handle
 */
function h3_hybrid_kernels_free(mixed $kernels): void {}
