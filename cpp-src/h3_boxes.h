#pragma once

#include <phpx.h>
#include <Foundation/Foundation.h>
#include <Metal/Metal.h>

// Shared Box wrappers for all H3PHP native objects.
// Each cpp-src/*.mm is compiled as a separate translation unit, yet several
// Box types are referenced across files (e.g. MetalDeviceBox is used by every
// kernel file). Declaring them all here keeps the definitions in one place.

struct MetalDeviceBox : php::Box {
    id<MTLDevice> device;
    MetalDeviceBox(id<MTLDevice> d) : device(d) {}
    ~MetalDeviceBox() { device = nil; }
};

struct MetalBufferBox : php::Box {
    id<MTLBuffer> buffer;
    MetalBufferBox(id<MTLBuffer> b) : buffer(b) {}
    ~MetalBufferBox() { buffer = nil; }
};

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

struct MetalPipelineBox : php::Box {
    id<MTLComputePipelineState> pipeline;
    MetalPipelineBox(id<MTLComputePipelineState> p) : pipeline(p) {}
    ~MetalPipelineBox() { pipeline = nil; }
};

struct H3VAEKernelsBox : php::Box {
    id<MTLLibrary> library;
    id<MTLDevice> device;
    H3VAEKernelsBox(id<MTLDevice> dev, id<MTLLibrary> lib) : device(dev), library(lib) {}
    ~H3VAEKernelsBox() { library = nil; device = nil; }
};

struct H3OptimizedKernelsBox : php::Box {
    id<MTLLibrary> library;
    id<MTLDevice> device;
    H3OptimizedKernelsBox(id<MTLDevice> dev, id<MTLLibrary> lib) : device(dev), library(lib) {}
    ~H3OptimizedKernelsBox() { library = nil; device = nil; }
};

struct H3HybridKernelsBox : php::Box {
    id<MTLLibrary> library;
    id<MTLDevice> device;
    H3HybridKernelsBox(id<MTLDevice> dev, id<MTLLibrary> lib) : device(dev), library(lib) {}
    ~H3HybridKernelsBox() { library = nil; device = nil; }
};

struct H3KernelsBox : php::Box {
    id<MTLLibrary> library;
    id<MTLDevice> device;
    H3KernelsBox(id<MTLDevice> dev, id<MTLLibrary> lib) : device(dev), library(lib) {}
    ~H3KernelsBox() { library = nil; device = nil; }
};
