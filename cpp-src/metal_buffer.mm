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

// Box wrapper for MTLBuffer
struct MetalBufferBox : php::Box {
    id<MTLBuffer> buffer;

    MetalBufferBox(id<MTLBuffer> b) : buffer(b) {}

    ~MetalBufferBox() {
        buffer = nil; // ARC release
    }
};

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
    if (length == 0) length = buf.length;
    const void* ptr = (const uint8_t*)buf.contents + offset;
    return php::String(ptr, (int)length);
}

/**
 * Write raw bytes to a Metal buffer.
 */
void php_h3_metal_buffer_set_contents(var box, var data, int64_t offset) {
    auto buf = box.toBox<MetalBufferBox>()->buffer;
    void* ptr = (uint8_t*)buf.contents + offset;
    memcpy(ptr, data.c_str(), data.len());
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
