<?php
/**
 * H3PHP — VAE Kernel Stubs
 *
 * PHP declarations for native VAE Metal kernel functions.
 * Implemented in cpp-src/h3_vae_kernels.mm.
 */

/**
 * Compile the VAE MSL kernel library.
 *
 * @param mixed $device Device handle
 * @return mixed Kernel library handle
 */
function h3_vae_kernels_compile(mixed $device): mixed {}

/**
 * Get a VAE kernel function by name.
 *
 * @param mixed $kernels Kernel library handle
 * @param string $name Function name (e.g., "conv3d_decode", "bigvgan_snake")
 * @return string|false Function name on success, false on failure
 */
function h3_vae_kernels_get_function(mixed $kernels, string $name): string|false {}

/**
 * Free the VAE kernel library.
 *
 * @param mixed $kernels Kernel library handle
 */
function h3_vae_kernels_free(mixed $kernels): void {}
