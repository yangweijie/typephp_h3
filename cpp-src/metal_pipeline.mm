/**
 * H3PHP — Metal Compute Pipeline Implementation
 *
 * Objective-C++ wrapper for MTLComputePipelineState.
 * Supports runtime MSL compilation and pre-compiled .metallib loading.
 * Exposes php_ prefix functions for TypePHP ABI interop.
 */

#include <phpx.h>
#include <Foundation/Foundation.h>
#include <Metal/Metal.h>

using namespace php;

// Box wrapper for MTLComputePipelineState
struct MetalPipelineBox : php::Box {
    id<MTLComputePipelineState> pipeline;

    MetalPipelineBox(id<MTLComputePipelineState> p) : pipeline(p) {}

    ~MetalPipelineBox() {
        pipeline = nil; // ARC release
    }
};

/**
 * Create a compute pipeline state from Metal shader source (MSL).
 */
var php_h3_metal_pipeline_create(var deviceBox, var shaderSource, var functionName) {
    auto dev = deviceBox.toBox<MetalDeviceBox>()->device;

    NSError* error = nil;
    NSString* source = [NSString stringWithUTF8String:shaderSource.c_str()];
    id<MTLLibrary> library = [dev newLibraryWithSource:source options:nil error:&error];

    if (!library) {
        NSLog(@"Metal library compilation failed: %@", error.localizedDescription);
        return false;
    }

    NSString* name = [NSString stringWithUTF8String:functionName.c_str()];
    id<MTLFunction> function = [library newFunctionWithName:name];

    if (!function) {
        NSLog(@"Metal function '%@' not found in library", name);
        return false;
    }

    id<MTLComputePipelineState> pipeline = [dev newComputePipelineStateWithFunction:function error:&error];
    if (!pipeline) {
        NSLog(@"Metal pipeline creation failed: %@", error.localizedDescription);
        return false;
    }

    return {new MetalPipelineBox(pipeline)};
}

/**
 * Create a compute pipeline from a pre-compiled .metallib file.
 */
var php_h3_metal_pipeline_create_with_file(var deviceBox, var metallibPath, var functionName) {
    auto dev = deviceBox.toBox<MetalDeviceBox>()->device;

    NSError* error = nil;
    NSString* path = [NSString stringWithUTF8String:metallibPath.c_str()];
    id<MTLLibrary> library = [dev newLibraryWithFile:path error:&error];

    if (!library) {
        NSLog(@"Metal library load failed: %@", error.localizedDescription);
        return false;
    }

    NSString* name = [NSString stringWithUTF8String:functionName.c_str()];
    id<MTLFunction> function = [library newFunctionWithName:name];

    if (!function) {
        NSLog(@"Metal function '%@' not found", name);
        return false;
    }

    id<MTLComputePipelineState> pipeline = [dev newComputePipelineStateWithFunction:function error:&error];
    if (!pipeline) {
        NSLog(@"Metal pipeline creation failed: %@", error.localizedDescription);
        return false;
    }

    return {new MetalPipelineBox(pipeline)};
}

/**
 * Get the maximum total threads per threadgroup for this pipeline.
 */
int64_t php_h3_metal_pipeline_get_max_threads_per_threadgroup(var box) {
    auto pipeline = box.toBox<MetalPipelineBox>()->pipeline;
    return (int64_t)pipeline.maxTotalThreadsPerThreadgroup;
}

/**
 * Free a compute pipeline state.
 */
void php_h3_metal_pipeline_free(var box) {
    delete box.toBox<MetalPipelineBox>();
}
