<?php
/**
 * H3PHP — Metal Command Queue Stubs
 *
 * PHP declarations for native Metal command queue and encoder functions.
 * Implemented in cpp-src/metal_command_queue.mm via php_ prefix ABI.
 */

/**
 * Create a Metal command queue.
 *
 * @param mixed $device Device handle
 * @return mixed Command queue handle
 */
function h3_metal_command_queue_create(mixed $device): mixed {}

/**
 * Create a command buffer from a queue.
 *
 * @param mixed $queue Command queue handle
 * @return mixed Command buffer handle
 */
function h3_metal_command_buffer_create(mixed $queue): mixed {}

/**
 * Create a compute command encoder.
 *
 * @param mixed $cmdBuffer Command buffer handle
 * @return mixed Compute encoder handle
 */
function h3_metal_compute_encoder_create(mixed $cmdBuffer): mixed {}

/**
 * Set the compute pipeline state on an encoder.
 *
 * @param mixed $encoder Compute encoder handle
 * @param mixed $pipeline Pipeline state handle
 */
function h3_metal_compute_encoder_set_pipeline(mixed $encoder, mixed $pipeline): void {}

/**
 * Set a buffer argument on a compute encoder.
 *
 * @param mixed $encoder Compute encoder handle
 * @param mixed $buffer Buffer handle
 * @param int $index Argument index
 * @param int $offset Byte offset within buffer
 */
function h3_metal_compute_encoder_set_buffer(mixed $encoder, mixed $buffer, int $index, int $offset = 0): void {}

/**
 * Set raw bytes (inline <4KB data) on a compute encoder.
 *
 * @param mixed $encoder Compute encoder handle
 * @param string $data Raw bytes
 * @param int $index Argument index
 */
function h3_metal_compute_encoder_set_bytes(mixed $encoder, string $data, int $index): void {}

/**
 * Dispatch threads on a compute encoder.
 *
 * @param mixed $encoder Compute encoder handle
 * @param int $gridX Grid width
 * @param int $gridY Grid height
 * @param int $gridZ Grid depth
 * @param int $threadgroupX Threadgroup width
 * @param int $threadgroupY Threadgroup height
 * @param int $threadgroupZ Threadgroup depth
 */
function h3_metal_compute_encoder_dispatch(
    mixed $encoder,
    int $gridX, int $gridY, int $gridZ,
    int $threadgroupX, int $threadgroupY, int $threadgroupZ
): void {}

/**
 * End encoding on a compute encoder.
 *
 * @param mixed $encoder Compute encoder handle
 */
function h3_metal_compute_encoder_end(mixed $encoder): void {}

/**
 * Commit a command buffer for execution.
 *
 * @param mixed $cmdBuffer Command buffer handle
 */
function h3_metal_command_buffer_commit(mixed $cmdBuffer): void {}

/**
 * Wait until the command buffer has completed execution.
 *
 * @param mixed $cmdBuffer Command buffer handle
 */
function h3_metal_command_buffer_wait(mixed $cmdBuffer): void {}

/**
 * Free a command buffer.
 *
 * @param mixed $cmdBuffer Command buffer handle
 */
function h3_metal_command_buffer_free(mixed $cmdBuffer): void {}

/**
 * Free a command queue.
 *
 * @param mixed $queue Command queue handle
 */
function h3_metal_command_queue_free(mixed $queue): void {}
