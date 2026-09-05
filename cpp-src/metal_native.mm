/**
 * H3PHP — Metal Native Layer (Objective-C++)
 *
 * Uses ObjC Metal API with C++ linkage (no extern "C").
 * Compiled as Objective-C++ (.mm).
 *
 * Security/Thread-safety fixes:
 * - P0: All map operations protected by single mutex
 * - P0: Library cache uses string key (not pointer)
 * - P0: unique_lock for controlled unlock (no manual destructor)
 * - P1: @autoreleasepool on all functions creating ObjC objects
 * - P1: Proper exception safety in storeH (retain before release)
 * - P1: Source compilation cached
 * - P2: map → unordered_map for O(1) lookup
 * - P2: waitUntilCompleted outside global lock
 * - P3: Magic numbers extracted to constants
 */

#include <phpx.h>
#include <Metal/Metal.h>
#include <Foundation/Foundation.h>
#include <cstring>
#include <unordered_map>
#include <mutex>
#include <atomic>
#include <string>

using namespace php;

// ============================================================================
// Constants (P3: no magic numbers)
// ============================================================================
static const int kMaxSetBytes = 4096;

// ============================================================================
// Thread-safe handle storage (P0: single mutex, P2: unordered_map)
// ============================================================================
typedef int64_t MetalHandle;

static std::mutex g_metal_mutex;
static std::unordered_map<MetalHandle, id<MTLDevice>> g_devices;
static std::unordered_map<MetalHandle, id<MTLBuffer>> g_buffers;
static std::unordered_map<MetalHandle, id<MTLCommandQueue>> g_commandQueues;
static std::unordered_map<MetalHandle, id<MTLCommandBuffer>> g_commandBuffers;
static std::unordered_map<MetalHandle, id<MTLComputeCommandEncoder>> g_encoders;
static std::unordered_map<MetalHandle, id<MTLComputePipelineState>> g_pipelines;
static std::atomic<MetalHandle> g_nextHandle{1};

// P2: Library caches with string keys (P0: not pointer addresses)
static std::unordered_map<std::string, id<MTLLibrary>> g_fileLibraries;
static std::unordered_map<std::string, id<MTLLibrary>> g_sourceLibraries;
static std::mutex g_library_mutex;

static MetalHandle allocHandle() { return g_nextHandle++; }

// P1: Exception-safe store with proper retain/release order
template<typename T>
static void storeH(std::unordered_map<MetalHandle, T*>& m, MetalHandle h, T* o) {
    if (!o) return;
    T* retained = [o retain];  // Retain first, in case o is same as existing
    std::lock_guard<std::mutex> lock(g_metal_mutex);
    auto it = m.find(h);
    if (it != m.end()) {
        [it->second release];
    }
    m[h] = retained;
}

template<typename T>
static T* getH(std::unordered_map<MetalHandle, T*>& m, MetalHandle h) {
    std::lock_guard<std::mutex> lock(g_metal_mutex);
    auto it = m.find(h);
    return it != m.end() ? it->second : nullptr;
}

// P2: Get handle without locking (caller holds lock or doesn't need it)
template<typename T>
static T* getHUnsafe(std::unordered_map<MetalHandle, T*>& m, MetalHandle h) {
    auto it = m.find(h);
    return it != m.end() ? it->second : nullptr;
}

template<typename T>
static void removeH(std::unordered_map<MetalHandle, T*>& m, MetalHandle h) {
    std::lock_guard<std::mutex> lock(g_metal_mutex);
    auto it = m.find(h);
    if (it != m.end()) {
        [it->second release];
        m.erase(it);
    }
}

// P0: Cached library compilation with unique_lock (no manual destructor)
static id<MTLLibrary> getOrCreateFileLibrary(id<MTLDevice> device, const std::string &path) {
    // P0: Use unique_lock for controlled unlock
    std::unique_lock<std::mutex> lock(g_library_mutex);
    auto it = g_fileLibraries.find(path);
    if (it != g_fileLibraries.end()) {
        return it->second;
    }
    lock.unlock();  // Unlock for slow compilation

    @autoreleasepool {
        NSString *nsPath = [NSString stringWithUTF8String:path.c_str()];
        NSError *err = nil;
        id<MTLLibrary> lib = [device newLibraryWithFile:nsPath error:&err];
        if (!lib) return nil;

        lock.lock();
        // Double-check after re-locking
        it = g_fileLibraries.find(path);
        if (it != g_fileLibraries.end()) {
            [lib release];
            return it->second;
        }
        g_fileLibraries[path] = [lib retain];
        return lib;
    }
}

// P1: Source compilation cache
static id<MTLLibrary> getOrCreateSourceLibrary(id<MTLDevice> device, const std::string &source, const std::string &key) {
    std::unique_lock<std::mutex> lock(g_library_mutex);
    auto it = g_sourceLibraries.find(key);
    if (it != g_sourceLibraries.end()) {
        return it->second;
    }
    lock.unlock();

    @autoreleasepool {
        NSString *nsSource = [NSString stringWithUTF8String:source.c_str()];
        NSError *err = nil;
        id<MTLLibrary> lib = [device newLibraryWithSource:nsSource options:nil error:&err];
        if (!lib) return nil;

        lock.lock();
        it = g_sourceLibraries.find(key);
        if (it != g_sourceLibraries.end()) {
            [lib release];
            return it->second;
        }
        g_sourceLibraries[key] = [lib retain];
        return lib;
    }
}

// P2: Library cache cleanup
static void clearLibraryCache() {
    std::lock_guard<std::mutex> lock(g_library_mutex);
    for (auto &pair : g_fileLibraries) {
        [pair.second release];
    }
    g_fileLibraries.clear();
    for (auto &pair : g_sourceLibraries) {
        [pair.second release];
    }
    g_sourceLibraries.clear();
}

// ============================================================================
// Device
// ============================================================================

Int php_h3_metal_device_create() {
    @autoreleasepool {
        id<MTLDevice> d = MTLCreateSystemDefaultDevice();
        if (!d) return 0;
        MetalHandle h = allocHandle();
        storeH(g_devices, h, d);
        return (Int)h;
    }
}

Array php_h3_metal_device_get_info(Int handle) {
    @autoreleasepool {
        id<MTLDevice> d = getH(g_devices, (MetalHandle)handle);
        Array info;
        if (!d) return info;
        info["name"] = [d.name UTF8String];
        info["architecture"] = [d.architecture.name UTF8String];
        info["physical_memory"] = (Int)[d physicalMemory];
        info["recommended_working_set"] = (Int)[d recommendedMaxWorkingSetSize];
        info["max_buffer_length"] = (Int)[d maxBufferLength];
        info["apple_gpu_family"] = 0;
        info["metal4"] = [d supportsFamily:MTLGPUFamilyMetal4];
        info["unified_memory"] = [d hasUnifiedMemory];
        return info;
    }
}

String php_h3_metal_device_get_name(Int handle) {
    @autoreleasepool {
        id<MTLDevice> d = getH(g_devices, (MetalHandle)handle);
        return d ? [d.name UTF8String] : String();
    }
}

Bool php_h3_metal_device_supports_metal4(Int handle) {
    @autoreleasepool {
        id<MTLDevice> d = getH(g_devices, (MetalHandle)handle);
        return d ? [d supportsFamily:MTLGPUFamilyMetal4] : false;
    }
}

void php_h3_metal_device_free(Int handle) { removeH(g_devices, (MetalHandle)handle); }

// ============================================================================
// Buffer
// ============================================================================

Int php_h3_metal_buffer_create(Int devH, Int len, Int opts) {
    @autoreleasepool {
        id<MTLDevice> d = getH(g_devices, (MetalHandle)devH);
        if (!d) return 0;
        id<MTLBuffer> b = [d newBufferWithLength:(NSUInteger)len options:(MTLStorageMode)opts];
        if (!b) return 0;
        MetalHandle h = allocHandle();
        storeH(g_buffers, h, b);
        return (Int)h;
    }
}

Int php_h3_metal_buffer_get_length(Int h) {
    id<MTLBuffer> b = getH(g_buffers, (MetalHandle)h);
    return b ? (Int)b.length : 0;
}

String php_h3_metal_buffer_get_contents(Int handle, Int off, Int len) {
    @autoreleasepool {
        id<MTLBuffer> b = getH(g_buffers, (MetalHandle)handle);
        if (!b || b.contents == nullptr) return String();
        Int avail = (Int)b.length - off;
        if (avail <= 0) return String();
        if (len == 0 || len > avail) len = avail;
        return String((const char*)((uint8_t*)b.contents + off), (size_t)len);
    }
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
    @autoreleasepool {
        id<MTLDevice> d = getH(g_devices, (MetalHandle)devH);
        if (!d) return 0;
        id<MTLCommandQueue> q = [d newCommandQueue];
        if (!q) return 0;
        MetalHandle h = allocHandle();
        storeH(g_commandQueues, h, q);
        return (Int)h;
    }
}

Int php_h3_metal_command_buffer_create(Int qH) {
    @autoreleasepool {
        id<MTLCommandQueue> q = getH(g_commandQueues, (MetalHandle)qH);
        if (!q) return 0;
        id<MTLCommandBuffer> b = [q commandBuffer];
        if (!b) return 0;
        MetalHandle h = allocHandle();
        storeH(g_commandBuffers, h, b);
        return (Int)h;
    }
}

Int php_h3_metal_compute_encoder_create(Int cbH) {
    @autoreleasepool {
        id<MTLCommandBuffer> b = getH(g_commandBuffers, (MetalHandle)cbH);
        if (!b) return 0;
        id<MTLComputeCommandEncoder> e = [b computeCommandEncoder];
        if (!e) return 0;
        MetalHandle h = allocHandle();
        storeH(g_encoders, h, e);
        return (Int)h;
    }
}

void php_h3_metal_compute_encoder_set_pipeline(Int eH, Int pH) {
    id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)eH);
    id<MTLComputePipelineState> p = getH(g_pipelines, (MetalHandle)pH);
    if (e && p) {
        @autoreleasepool {
            [e setComputePipelineState:p];
        }
    }
}

void php_h3_metal_compute_encoder_set_buffer(Int eH, Int bH, Int idx, Int off) {
    id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)eH);
    id<MTLBuffer> b = getH(g_buffers, (MetalHandle)bH);
    if (e && b) {
        @autoreleasepool {
            [e setBuffer:b offset:(NSUInteger)off atIndex:(NSUInteger)idx];
        }
    }
}

void php_h3_metal_compute_encoder_set_bytes(Int eH, String data, Int idx) {
    @autoreleasepool {
        id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)eH);
        if (!e) return;
        size_t len = data.length();
        if (len > kMaxSetBytes) len = kMaxSetBytes;
        [e setBytes:data.data() length:len atIndex:(NSUInteger)idx];
    }
}

void php_h3_metal_compute_encoder_dispatch(Int eH, Int gx, Int gy, Int gz, Int tx, Int ty, Int tz) {
    @autoreleasepool {
        id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)eH);
        if (!e) return;
        auto cd = [](Int g, Int t) { return (g + t - 1) / t; };
        MTLSize groups = MTLSizeMake((NSUInteger)cd(gx,tx), (NSUInteger)cd(gy,ty), (NSUInteger)cd(gz,tz));
        MTLSize tg = MTLSizeMake((NSUInteger)tx, (NSUInteger)ty, (NSUInteger)tz);
        [e dispatchThreadgroups:groups threadsPerThreadgroup:tg];
    }
}

void php_h3_metal_compute_encoder_end(Int h) {
    id<MTLComputeCommandEncoder> e = getH(g_encoders, (MetalHandle)h);
    if (e) {
        @autoreleasepool {
            [e endEncoding];
        }
    }
}

void php_h3_metal_command_buffer_commit(Int h) {
    id<MTLCommandBuffer> b = getH(g_commandBuffers, (MetalHandle)h);
    if (b) {
        @autoreleasepool {
            [b commit];
        }
    }
}

// P2: waitUntilCompleted outside global lock (doesn't block other threads)
void php_h3_metal_command_buffer_wait(Int h) {
    id<MTLCommandBuffer> b = getH(g_commandBuffers, (MetalHandle)h);
    if (b) {
        [b waitUntilCompleted];
    }
}

void php_h3_metal_command_buffer_free(Int h) { removeH(g_commandBuffers, (MetalHandle)h); }
void php_h3_metal_command_queue_free(Int h) { removeH(g_commandQueues, (MetalHandle)h); }

// ============================================================================
// Pipeline (P1: cached source compilation)
// ============================================================================

Int php_h3_metal_pipeline_create(Int devH, String src, String fn) {
    @autoreleasepool {
        id<MTLDevice> d = getH(g_devices, (MetalHandle)devH);
        if (!d) return 0;

        // P1: Cache source compilation using hash of source as key
        std::string sourceKey = std::string(src.data(), src.length());
        id<MTLLibrary> lib = getOrCreateSourceLibrary(d, sourceKey, sourceKey);
        if (!lib) return 0;

        NSString *n = [NSString stringWithUTF8String:fn.data()];
        id<MTLFunction> fun = [lib newFunctionWithName:n];
        if (!fun) return 0;
        NSError *err = nil;
        id<MTLComputePipelineState> p = [d newComputePipelineStateWithFunction:fun error:&err];
        [fun release];
        if (!p) return 0;
        MetalHandle h = allocHandle();
        storeH(g_pipelines, h, p);
        return (Int)h;
    }
}

Int php_h3_metal_pipeline_create_with_file(Int devH, String path, String fn) {
    @autoreleasepool {
        id<MTLDevice> d = getH(g_devices, (MetalHandle)devH);
        if (!d) return 0;

        std::string pathStr(path.data(), path.length());
        id<MTLLibrary> lib = getOrCreateFileLibrary(d, pathStr);
        if (!lib) return 0;

        NSString *n = [NSString stringWithUTF8String:fn.data()];
        id<MTLFunction> fun = [lib newFunctionWithName:n];
        if (!fun) return 0;
        NSError *err = nil;
        id<MTLComputePipelineState> pl = [d newComputePipelineStateWithFunction:fun error:&err];
        [fun release];
        if (!pl) return 0;
        MetalHandle h = allocHandle();
        storeH(g_pipelines, h, pl);
        return (Int)h;
    }
}

Int php_h3_metal_pipeline_get_max_threads_per_threadgroup(Int h) {
    id<MTLComputePipelineState> p = getH(g_pipelines, (MetalHandle)h);
    return p ? (Int)p.maxTotalThreadsPerThreadgroup : 0;
}

void php_h3_metal_pipeline_free(Int h) { removeH(g_pipelines, (MetalHandle)h); }
