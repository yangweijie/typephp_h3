/**
 * H3PHP — Metal Buffer Implementation
 *
 * Objective-C++ wrapper for MTLBuffer.
 * Exposes php_ prefix functions for TypePHP ABI interop.
 */

#include <phpx.h>
#include <Foundation/Foundation.h>
#include <Metal/Metal.h>

using namespace php;

// Storage mode constants (matching Metal storage modes)
enum {
    H3_METAL_STORAGE_SHARED = MTLStorageModeShared,
    H3_METAL_STORAGE_MANAGED = MTLStorageModeManaged,
    H3_METAL_STORAGE_PRIVATE = MTLStorageModePrivate,
    H3_METAL_STORAGE_MEMORYLESS = MTLStorageModeMemoryless,
};

#include "h3_boxes.h"

/**
 * Create a Metal buffer with given length and options.
 */
var php_h3_metal_buffer_create(var deviceBox, int64_t length, int64_t options) {
    auto dev = deviceBox.toBox<MetalDeviceBox>()->device;
    MTLStorageMode mode = (MTLStorageMode)options;
    id<MTLBuffer> buffer = [dev newBufferWithLength:length options:mode];
    if (!buffer) {
        return false;
    }
    return {new MetalBufferBox(buffer)};
}

/**
 * Get the length of a Metal buffer.
 */
int64_t php_h3_metal_buffer_get_length(var box) {
    auto buf = box.toBox<MetalBufferBox>()->buffer;
    return (int64_t)buf.length;
}

/**
 * Get raw pointer contents as string (read from GPU).
 */
var php_h3_metal_buffer_get_contents(var box, int64_t offset, int64_t length) {
    auto buf = box.toBox<MetalBufferBox>()->buffer;
    if (buf.contents == nullptr) return false; // 私有缓冲区无 CPU 映射，禁止直接读
    int64_t avail = (int64_t)buf.length - offset;
    if (avail <= 0) return false;
    if (length == 0 || length > avail) length = avail; // 防越界读
    const void* ptr = (const uint8_t*)buf.contents + offset;
    return php::String((const char*)ptr, (size_t)length);
}

/**
 * Write raw bytes to a Metal buffer.
 */
void php_h3_metal_buffer_set_contents(var box, var data, int64_t offset) {
    auto buf = box.toBox<MetalBufferBox>()->buffer;
    if (buf.contents == nullptr) return; // 私有缓冲区需经 blit 命令写入
    int64_t avail = (int64_t)buf.length - offset;
    size_t n = data.length();
    if (static_cast<int64_t>(n) > avail) n = static_cast<size_t>(avail); // 防越界写
    memcpy((uint8_t*)buf.contents + offset, data.toCString(), n);
}

/**
 * Get the GPU address of a buffer.
 */
int64_t php_h3_metal_buffer_get_gpu_address(var box) {
    auto buf = box.toBox<MetalBufferBox>()->buffer;
    return (int64_t)buf.gpuAddress;
}

/**
 * Free a Metal buffer.
 */
void php_h3_metal_buffer_free(var box) {
    delete box.toBox<MetalBufferBox>();
}
