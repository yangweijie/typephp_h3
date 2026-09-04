<?php
/**
 * H3PHP — Metal Device Stubs
 *
 * PHP declarations for native Metal device functions.
 * Implemented in cpp-src/metal_device.mm via php_ prefix ABI.
 */

/**
 * Create a Metal device wrapper for the system default GPU.
 * Returns an opaque resource handle.
 */
function h3_metal_device_create(): mixed {}

/**
 * Get device information as an associative array.
 *
 * @param mixed $device Device handle from h3_metal_device_create()
 * @return array{name: string, architecture: string, physical_memory: int, recommended_working_set: int, max_buffer_length: int, apple_gpu_family: int, metal4: bool, unified_memory: bool}
 */
function h3_metal_device_get_info(mixed $device): array {}

/**
 * Get the device name.
 *
 * @param mixed $device Device handle
 */
function h3_metal_device_get_name(mixed $device): string {}

/**
 * Check if the device supports Metal 4 features.
 *
 * @param mixed $device Device handle
 */
function h3_metal_device_supports_metal4(mixed $device): bool {}

/**
 * Free a Metal device wrapper.
 *
 * @param mixed $device Device handle
 */
function h3_metal_device_free(mixed $device): void {}
