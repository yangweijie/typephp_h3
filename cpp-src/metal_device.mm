/**
 * H3PHP — Metal Device Implementation
 *
 * Objective-C++ wrapper for MTLDevice.
 * Exposes php_ prefix functions for TypePHP ABI interop.
 *
 * Follows the pattern from php-metal-gpu and TypePHP's MIXED_CPP_PHP.md.
 */

#include <phpx.h>
#include <Foundation/Foundation.h>
#include <Metal/Metal.h>

using namespace php;

#include "h3_boxes.h"

/**
 * Create a Metal device wrapper for the system default GPU.
 */
var php_h3_metal_device_create() {
    id<MTLDevice> device = MTLCreateSystemDefaultDevice();
    if (!device) {
        return false;
    }
    return {new MetalDeviceBox(device)};
}

/**
 * Get device information as an associative array.
 */
var php_h3_metal_device_get_info(var box) {
    auto dev = box.toBox<MetalDeviceBox>()->device;

    php::Array info;
    info["name"] = [dev.name UTF8String];
    info["architecture"] = [dev.architecture.name UTF8String];
    info["physical_memory"] = (int64_t)[dev recommendedMaxWorkingSetSize];
    info["recommended_working_set"] = (int64_t)[dev recommendedMaxWorkingSetSize];
    info["max_buffer_length"] = (int64_t)[dev maxBufferLength];
    info["apple_gpu_family"] = 0; // TODO: Detect GPU family
    info["metal4"] = [dev supportsFamily:MTLGPUFamilyMetal4];
    info["unified_memory"] = [dev hasUnifiedMemory];

    return info;
}

/**
 * Get the device name.
 */
var php_h3_metal_device_get_name(var box) {
    auto dev = box.toBox<MetalDeviceBox>()->device;
    return [dev.name UTF8String];
}

/**
 * Check if the device supports Metal 4 features.
 */
var php_h3_metal_device_supports_metal4(var box) {
    auto dev = box.toBox<MetalDeviceBox>()->device;
    return [dev supportsFamily:MTLGPUFamilyMetal4];
}

/**
 * Free a Metal device wrapper.
 */
void php_h3_metal_device_free(var box) {
    // Box destructor handles ARC release
    delete box.toBox<MetalDeviceBox>();
}
