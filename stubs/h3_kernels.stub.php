<?php
/**
 * H3PHP — Metal Kernel Stubs
 *
 * PHP declarations for native DiT Metal kernel functions.
 * Implemented in cpp-src/h3_dit_kernels.mm.
 */

/**
 * Compile the MSL kernel library from embedded source.
 *
 * @param mixed $device Device handle from Device::getHandle()
 * @return mixed Kernel library handle
 */
function h3_kernels_compile(mixed $device): mixed {}

/**
 * Get a kernel function by name.
 *
 * @param mixed $kernels Kernel library handle
 * @param string $name Function name (e.g., "rms_norm", "attention")
 * @return string|false Function name on success, false on failure
 */
function h3_kernels_get_function(mixed $kernels, string $name): string|false {}

/**
 * Free the kernel library.
 *
 * @param mixed $kernels Kernel library handle
 */
function h3_kernels_free(mixed $kernels): void {}
