<?php
/**
 * H3PHP — Metal Buffer Stubs
 *
 * PHP declarations for native Metal buffer functions.
 * Implemented in cpp-src/metal_buffer.mm via php_ prefix ABI.
 */

/**
 * Create a Metal buffer with given length and options.
 *
 * @param mixed $device Device handle
 * @param int $length Buffer size in bytes
 * @param int $options Storage mode options (0=shared, 1=managed, 2=private, 3=memoryless)
 * @return mixed Buffer handle
 */
function h3_metal_buffer_create(mixed $device, int $length, int $options = 0): mixed {}

/**
 * Get the length of a Metal buffer.
 *
 * @param mixed $buffer Buffer handle
 */
function h3_metal_buffer_get_length(mixed $buffer): int {}

/**
 * Get raw pointer contents as string (read from GPU).
 *
 * @param mixed $buffer Buffer handle
 * @param int $offset Start offset
 * @param int $length Number of bytes to read
 */
function h3_metal_buffer_get_contents(mixed $buffer, int $offset = 0, int $length = 0): string {}

/**
 * Write raw bytes to a Metal buffer.
 *
 * @param mixed $buffer Buffer handle
 * @param string $data Raw bytes to write
 * @param int $offset Start offset
 */
function h3_metal_buffer_set_contents(mixed $buffer, string $data, int $offset = 0): void {}

/**
 * Get the GPU address of a buffer (for bindless parameters).
 *
 * @param mixed $buffer Buffer handle
 */
function h3_metal_buffer_get_gpu_address(mixed $buffer): int {}

/**
 * Free a Metal buffer.
 *
 * @param mixed $buffer Buffer handle
 */
function h3_metal_buffer_free(mixed $buffer): void {}
