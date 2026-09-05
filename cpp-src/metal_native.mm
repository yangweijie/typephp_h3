/**
 * H3PHP — Metal Native Layer (Objective-C++)
 *
 * Uses ObjC Metal API with C++ linkage (no extern "C").
 * Compiled as Objective-C++ (.mm).
 */

#include <phpx.h>
#include <Metal/Metal.h>
#include <Foundation/Foundation.h>
#include <cstring>
#include <map>
#include <mutex>
#include <atomic>

using namespace php;

typedef int64_t MetalHandle;

static std::mutex g_mutex;
static std::map<MetalHandle, id<MTLDevice>> g_devices;
static std::map<MetalHandle, id<MTLBuffer>> g_buffers;
static std::map<MetalHandle, id<MTLCommandQueue>> g_commandQueues;
static std::map<MetalHandle, id<MTLCommandBuffer>> g_commandBuffers;
static std::map<MetalHandle, id<MTLComputeCommandEncoder>> g_encoders;
static std::map<MetalHandle, id<MTLComputePipelineState>> g_pipelines;
static std::atomic<MetalHandle> g_nextHandle{1};

static MetalHandle allocHandle() { return g_nextHandle++; }

template<typename T>
static void storeH(std::map<MetalHandle, T*>& m, MetalHandle h, T* o) {
    std::lock_guard<std::mutex> lock(g_mutex);
    auto it = m.find(h);
    if (it != m.end()) [it->second release];
    m[h] = [o retain];
}

template<typename T>
static T* getH(std::map<MetalHandle, T*>& m, MetalHandle h) {
    std::lock_guard<std::mutex> lock(g_mutex);
    auto it = m.find(h);
    return it != m.end() ? it->second : nullptr;
}

template<typename T>
static void removeH(std::map<MetalHandle, T*>& m, MetalHandle h) {
    std::lock_guard<std::mutex> lock(g_mutex);
    auto it = m.find(h);
    if (it != m.end()) { [it->second release]; m.erase(it); }
}

// ============================================================================
// Device
// ============================================================================

Int php_h3_metal_device_create() {
    id<MTLDevice> d = MTLCreateSystemDefaultDevice();
    if (!d) return 0;
    MetalHandle h = allocHandle();
    storeH(g_devices, h, d);
    return (Int)h;
}

Array php_h3_metal_device_get_info(Int handle) {
    id<MTLDevice> d = getH(g_devices, (MetalHandle)handle);
    Array info;
    if (!d) return info;
    info["name"] = [d.name UTF8String];
    info["architecture"] = [d.architecture.name UTF8String];
    info["physical_memory"] = (Int)[d recommendedMaxWorkingSetSize];
    info["recommended_working_set"] = (Int)[d recommendedMaxWorkingSetSize];
    info["max_buffer_length"] = (Int)[d maxBufferLength];
    info["apple_gpu_family"] = 0;
    info["metal4"] = [d supportsFamily:MTLGPUFamilyMetal4];
    info["unified_memory"] = [d hasUnifiedMemory];
    return info;
}

String php_h3_metal_device_get_name(Int handle) {
    id<MTLDevice> d = getH(g_devices, (MetalHandle)handle);
    return d ? [d.name UTF8String] : String();
}

Bool php_h3_metal_device_supports_metal4(Int handle) {
    id<MTLDevice> d = getH(g_devices, (MetalHandle)handle);
    return d ? [d supportsFamily:MTLGPUFamilyMetal4] : false;
}

void php_h3_metal_device_free(Int handle) { removeH(g_devices, (MetalHandle)handle); }

// ============================================================================
// Buffer
// ============================================================================

Int php_h3_metal_buffer_create(Int devH, Int len, Int opts) {
    id<MTLDevice> d = getH(g_devices, (MetalHandle)devH);
    if (!d) return 0;
    id<MTLBuffer> b = [d newBufferWithLength:(NSUInteger)len options:(MTLStorageMode)opts];
    if (!b) return 0;
    MetalHandle h = allocHandle();
    storeH(g_buffers, h, b);
    return (Int)h;
}

Int php_h3_metal_buffer_get_length(Int h) {
    id<MTLBuffer> b = getH(g_buffers, (MetalHandle)h);
    return b ? (Int)b.length : 0;
}

String php_h3_metal_buffer_get_contents(Int handle, Int off, Int len) {
    id<MTLBuffer> b = getH(g_buffers, (MetalHandle)handle);
    if (!b || b.contents == nullptr) return String();
    Int avail = (Int)b.length - off;
    if (avail <= 0) return String();
    if (len == 0 || len > avail) len = avail;
    return String((const char*)((uint8_t*)b.contents + off), (size_t)len);
}

void php_h3_metal_buffer_set_contents(Int handle, String data, Int off) {
    id<MTLBuffer> b = getH(g_buffers, (MetalHandle)handle);
    if (!b || b.contents == nullptr) return;
    Int avail = (Int)b.length - off;
    size_t n = data.length();
    if ((Int)n > avail) n = (size_t)avail;
    memcpy((uint8_t*)b.contents + off, data.data(), n);
}

Int php_h3_metal_buffer_get_gpu_address(Int h) {
    id<MTLBuffer> b = getH(g_buffers, (MetalHandle)h);
    return b ? (Int)b.gpuAddress : 0;
}

void php_h3_metal_buffer_free(Int h) { removeH(g_buffers, (MetalHandle)h); }

// ============================================================================
// Command Queue
// ============================================================================

Int php_h3_metal_command_queue_create(Int devH) {
    id<MTLDevice> d = getH(g_devices, (MetalHandle)devH);
    if (!d) return 0;
    id<MTLCommandQueue> q = [d newCommandQueue];
    if (!q) return 0;
    MetalHandle h = allocHandle();
    storeH(g_commandQueues, h, q);
    return (Int)h;
}

Int php_h3_metal_command_buffer_create(Int qH) {
    id<MTLCommandQueue> q = getH(g_commandQueues, (MetalHandle)qH);
    if (!q) return 0;
    id<MTLCommandBuffer> b = [q commandBuffer];
    if (!b) return 0;
    MetalHandle h = allocHandle();
    storeH(g_commandBuffers, h, b);
    return (Int)h;
}

Int php_h3_metal_compute_encoder_create(Int cbH) {
    id<MTLCommandBuffer> b = getH(g_commandBuffers, (MetalHandle)cbH);
    if (!b) return 0;
    id<MTLComputeCommandEncoder> e = [b computeCommandEncoder];
    if (!e) return 0;
    MetalHandle h = allocHandle();
    storeH(g_encoders, h, e);
    return (Int)h;
}

void php_h3_metal_compute_encoder_set_pipeline(Int eH, Int pH) {
    id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)eH);
    id<MTLComputePipelineState> p = getH(g_pipelines, (MetalHandle)pH);
    if (e && p) [e setComputePipelineState:p];
}

void php_h3_metal_compute_encoder_set_buffer(Int eH, Int bH, Int idx, Int off) {
    id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)eH);
    id<MTLBuffer> b = getH(g_buffers, (MetalHandle)bH);
    if (e && b) [e setBuffer:b offset:(NSUInteger)off atIndex:(NSUInteger)idx];
}

void php_h3_metal_compute_encoder_set_bytes(Int eH, String data, Int idx) {
    id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)eH);
    if (!e) return;
    size_t len = data.length();
    if (len > 4096) len = 4096;
    [e setBytes:data.data() length:len atIndex:(NSUInteger)idx];
}

void php_h3_metal_compute_encoder_dispatch(Int eH, Int gx, Int gy, Int gz, Int tx, Int ty, Int tz) {
    id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)eH);
    if (!e) return;
    auto cd = [](Int g, Int t) { return (g + t - 1) / t; };
    MTLSize groups = MTLSizeMake((NSUInteger)cd(gx,tx), (NSUInteger)cd(gy,ty), (NSUInteger)cd(gz,tz));
    MTLSize tg = MTLSizeMake((NSUInteger)tx, (NSUInteger)ty, (NSUInteger)tz);
    [e dispatchThreadgroups:groups threadsPerThreadgroup:tg];
}

void php_h3_metal_compute_encoder_end(Int h) {
    id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)h);
    if (e) [e endEncoding];
}

void php_h3_metal_command_buffer_commit(Int h) {
    id<MTLCommandBuffer> b = getH(g_commandBuffers, (MetalHandle)h);
    if (b) [b commit];
}

void php_h3_metal_command_buffer_wait(Int h) {
    id<MTLCommandBuffer> b = getH(g_commandBuffers, (MetalHandle)h);
    if (b) [b waitUntilCompleted];
}

void php_h3_metal_command_buffer_free(Int h) { removeH(g_commandBuffers, (MetalHandle)h); }
void php_h3_metal_command_queue_free(Int h) { removeH(g_commandQueues, (MetalHandle)h); }

// ============================================================================
// Pipeline
// ============================================================================

Int php_h3_metal_pipeline_create(Int devH, String src, String fn) {
    id<MTLDevice> d = getH(g_devices, (MetalHandle)devH);
    if (!d) return 0;
    NSString* s = [NSString stringWithUTF8String:src.data()];
    NSError* err = nil;
    id<MTLLibrary> lib = [d newLibraryWithSource:s options:nil error:&err];
    if (!lib) return 0;
    NSString* n = [NSString stringWithUTF8String:fn.data()];
    id<MTLFunction> fun = [lib newFunctionWithName:n];
    if (!fun) { [lib release]; return 0; }
    id<MTLComputePipelineState> p = [d newComputePipelineStateWithFunction:fun error:&err];
    [fun release]; [lib release];
    if (!p) return 0;
    MetalHandle h = allocHandle();
    storeH(g_pipelines, h, p);
    return (Int)h;
}

Int php_h3_metal_pipeline_create_with_file(Int devH, String path, String fn) {
    id<MTLDevice> d = getH(g_devices, (MetalHandle)devH);
    if (!d) return 0;
    NSString* p = [NSString stringWithUTF8String:path.data()];
    NSError* err = nil;
    id<MTLLibrary> lib = [d newLibraryWithFile:p error:&err];
    if (!lib) return 0;
    NSString* n = [NSString stringWithUTF8String:fn.data()];
    id<MTLFunction> fun = [lib newFunctionWithName:n];
    if (!fun) { [lib release]; return 0; }
    id<MTLComputePipelineState> pl = [d newComputePipelineStateWithFunction:fun error:&err];
    [fun release]; [lib release];
    if (!pl) return 0;
    MetalHandle h = allocHandle();
    storeH(g_pipelines, h, pl);
    return (Int)h;
}

Int php_h3_metal_pipeline_get_max_threads_per_threadgroup(Int h) {
    id<MTLComputePipelineState> p = getH(g_pipelines, (MetalHandle)h);
    return p ? (Int)p.maxTotalThreadsPerThreadgroup : 0;
}

void php_h3_metal_pipeline_free(Int h) { removeH(g_pipelines, (MetalHandle)h); }
