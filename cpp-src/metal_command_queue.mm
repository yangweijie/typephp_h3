/**
 * H3PHP — Metal Command Queue Implementation
 *
 * Objective-C++ wrapper for MTLCommandQueue, MTLCommandBuffer, and MTLComputeCommandEncoder.
 * Exposes php_ prefix functions for TypePHP ABI interop.
 */

#include <phpx.h>
#include <Foundation/Foundation.h>
#include <Metal/Metal.h>

using namespace php;

#include "h3_boxes.h"

/**
 * Create a Metal command queue.
 */
var php_h3_metal_command_queue_create(var deviceBox) {
    auto dev = deviceBox.toBox<MetalDeviceBox>()->device;
    id<MTLCommandQueue> queue = [dev newCommandQueue];
    if (!queue) return false;
    return {new MetalCommandQueueBox(queue)};
}

/**
 * Create a command buffer from a queue.
 */
var php_h3_metal_command_buffer_create(var queueBox) {
    auto queue = queueBox.toBox<MetalCommandQueueBox>()->queue;
    id<MTLCommandBuffer> buffer = [queue commandBuffer];
    if (!buffer) return false;
    return {new MetalCommandBufferBox(buffer)};
}

/**
 * Create a compute command encoder.
 */
var php_h3_metal_compute_encoder_create(var cmdBufferBox) {
    auto buffer = cmdBufferBox.toBox<MetalCommandBufferBox>()->buffer;
    id<MTLComputeCommandEncoder> encoder = [buffer computeCommandEncoder];
    if (!encoder) return false;
    return {new MetalComputeEncoderBox(encoder)};
}

/**
 * Set the compute pipeline state on an encoder.
 */
void php_h3_metal_compute_encoder_set_pipeline(var encoderBox, var pipelineBox) {
    auto encoder = encoderBox.toBox<MetalComputeEncoderBox>()->encoder;
    auto pipeline = pipelineBox.toBox<MetalPipelineBox>()->pipeline;
    [encoder setComputePipelineState:pipeline];
}

/**
 * Set a buffer argument on a compute encoder.
 */
void php_h3_metal_compute_encoder_set_buffer(var encoderBox, var bufferBox, int64_t index, int64_t offset) {
    auto encoder = encoderBox.toBox<MetalComputeEncoderBox>()->encoder;
    auto buffer = bufferBox.toBox<MetalBufferBox>()->buffer;
    [encoder setBuffer:buffer offset:offset atIndex:index];
}

/**
 * Set raw bytes (inline <4KB data) on a compute encoder.
 */
void php_h3_metal_compute_encoder_set_bytes(var encoderBox, var data, int64_t index) {
    auto encoder = encoderBox.toBox<MetalComputeEncoderBox>()->encoder;
    size_t len = data.length();
    if (len > 4096) len = 4096; // Metal 内联常量上限，超出需改用 buffer 参数
    [encoder setBytes:data.toCString() length:len atIndex:index];
}

/**
 * Dispatch threads on a compute encoder.
 */
void php_h3_metal_compute_encoder_dispatch(
    var encoderBox,
    int64_t gridX, int64_t gridY, int64_t gridZ,
    int64_t tgX, int64_t tgY, int64_t tgZ
) {
    auto encoder = encoderBox.toBox<MetalComputeEncoderBox>()->encoder;
    // 入参为总线程数，换算为线程组数量（ceil 向上取整）
    auto ceil_div = [](int64_t g, int64_t tg) { return (g + tg - 1) / tg; };
    MTLSize groups = MTLSizeMake(ceil_div(gridX, tgX), ceil_div(gridY, tgY), ceil_div(gridZ, tgZ));
    MTLSize threadgroupSize = MTLSizeMake(tgX, tgY, tgZ);
    [encoder dispatchThreadgroups:groups threadsPerThreadgroup:threadgroupSize];
}

/**
 * End encoding on a compute encoder.
 */
void php_h3_metal_compute_encoder_end(var encoderBox) {
    auto encoder = encoderBox.toBox<MetalComputeEncoderBox>()->encoder;
    [encoder endEncoding];
}

/**
 * Commit a command buffer for execution.
 */
void php_h3_metal_command_buffer_commit(var bufferBox) {
    auto buffer = bufferBox.toBox<MetalCommandBufferBox>()->buffer;
    [buffer commit];
}

/**
 * Wait until the command buffer has completed execution.
 */
void php_h3_metal_command_buffer_wait(var bufferBox) {
    auto buffer = bufferBox.toBox<MetalCommandBufferBox>()->buffer;
    [buffer waitUntilCompleted];
}

/**
 * Free a command buffer.
 */
void php_h3_metal_command_buffer_free(var box) {
    delete box.toBox<MetalCommandBufferBox>();
}

/**
 * Free a command queue.
 */
void php_h3_metal_command_queue_free(var box) {
    delete box.toBox<MetalCommandQueueBox>();
}
