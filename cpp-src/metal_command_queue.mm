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

// Box wrappers
struct MetalCommandQueueBox : php::Box {
    id<MTLCommandQueue> queue;
    MetalCommandQueueBox(id<MTLCommandQueue> q) : queue(q) {}
    ~MetalCommandQueueBox() { queue = nil; }
};

struct MetalCommandBufferBox : php::Box {
    id<MTLCommandBuffer> buffer;
    MetalCommandBufferBox(id<MTLCommandBuffer> b) : buffer(b) {}
    ~MetalCommandBufferBox() { buffer = nil; }
};

struct MetalComputeEncoderBox : php::Box {
    id<MTLComputeCommandEncoder> encoder;
    MetalComputeEncoderBox(id<MTLComputeCommandEncoder> e) : encoder(e) {}
    ~MetalComputeEncoderBox() { encoder = nil; }
};

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
    [encoder setBytes:data.c_str() length:data.len() atIndex:index];
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
    MTLSize gridSize = MTLSizeMake(gridX, gridY, gridZ);
    MTLSize threadgroupSize = MTLSizeMake(tgX, tgY, tgZ);
    [encoder dispatchThreadgroups:gridSize threadsPerThreadgroup:threadgroupSize];
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
