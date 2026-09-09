/**
 * H3PHP — Windows Stub Implementations
 *
 * Provides stub implementations for functions normally defined in .mm files
 * (Objective-C++ / Metal). These are not available on Windows (MSVC).
 * Each function returns a sensible default so the linker is satisfied.
 *
 * This file is only compiled on Windows builds (included via project_windows.yml).
 */

#include <phpx.h>
#include <string>

using namespace php;

// ============================================================================
// H3 Model functions (from h3_native.mm)
// ============================================================================

int64_t php_h3_model_load(String model_dir) {
    (void)model_dir;
    return 0;
}

String php_h3_model_get_device_name(int64_t handle) {
    (void)handle;
    return String("CPU (Windows - Metal not available)");
}

int64_t php_h3_model_generate(int64_t handle, Array params) {
    (void)handle;
    (void)params;
    return 0;
}

void php_h3_model_free(int64_t handle) {
    (void)handle;
}

String php_h3_get_last_error() {
    return String("Metal backend not available on Windows");
}

String php_h3_model_get_info(int64_t handle) {
    (void)handle;
    return String("{\"backend\":\"none\",\"platform\":\"windows\"}");
}

// ============================================================================
// Metal Device functions (from metal.mm / metal_native.mm)
// ============================================================================

int64_t php_h3_metal_device_create() {
    return 0;
}

Array php_h3_metal_device_get_info(int64_t handle) {
    (void)handle;
    Array info;
    info["name"] = String("CPU (Windows)");
    info["architecture"] = String("x86_64");
    info["physical_memory"] = 0;
    info["recommended_working_set"] = 0;
    info["max_buffer_length"] = 0;
    info["apple_gpu_family"] = 0;
    info["metal4"] = false;
    info["unified_memory"] = false;
    return info;
}

String php_h3_metal_device_get_name(int64_t handle) {
    (void)handle;
    return String("CPU (Windows - Metal not available)");
}

bool php_h3_metal_device_supports_metal4(int64_t handle) {
    (void)handle;
    return false;
}

void php_h3_metal_device_free(int64_t handle) {
    (void)handle;
}

// ============================================================================
// Metal Buffer functions (from metal.mm / metal_native.mm)
// ============================================================================

int64_t php_h3_metal_buffer_create(int64_t device, int64_t length, int64_t options) {
    (void)device;
    (void)length;
    (void)options;
    return 0;
}

int64_t php_h3_metal_buffer_get_length(int64_t handle) {
    (void)handle;
    return 0;
}

String php_h3_metal_buffer_get_contents(int64_t handle, int64_t offset, int64_t length) {
    (void)handle;
    (void)offset;
    (void)length;
    return String("");
}

void php_h3_metal_buffer_set_contents(int64_t handle, String data, int64_t offset) {
    (void)handle;
    (void)data;
    (void)offset;
}

int64_t php_h3_metal_buffer_get_gpu_address(int64_t handle) {
    (void)handle;
    return 0;
}

void php_h3_metal_buffer_free(int64_t handle) {
    (void)handle;
}

// ============================================================================
// Metal Command Queue functions (from metal.mm / metal_native.mm)
// ============================================================================

int64_t php_h3_metal_command_queue_create(int64_t device) {
    (void)device;
    return 0;
}

int64_t php_h3_metal_command_buffer_create(int64_t queue) {
    (void)queue;
    return 0;
}

int64_t php_h3_metal_compute_encoder_create(int64_t cmdBuffer) {
    (void)cmdBuffer;
    return 0;
}

void php_h3_metal_compute_encoder_set_pipeline(int64_t encoder, int64_t pipeline) {
    (void)encoder;
    (void)pipeline;
}

void php_h3_metal_compute_encoder_set_buffer(int64_t encoder, int64_t buffer, int64_t index, int64_t offset) {
    (void)encoder;
    (void)buffer;
    (void)index;
    (void)offset;
}

void php_h3_metal_compute_encoder_set_bytes(int64_t encoder, String data, int64_t index) {
    (void)encoder;
    (void)data;
    (void)index;
}

void php_h3_metal_compute_encoder_dispatch(int64_t encoder, int64_t gridX, int64_t gridY, int64_t gridZ,
                                            int64_t threadgroupX, int64_t threadgroupY, int64_t threadgroupZ) {
    (void)encoder;
    (void)gridX;
    (void)gridY;
    (void)gridZ;
    (void)threadgroupX;
    (void)threadgroupY;
    (void)threadgroupZ;
}

void php_h3_metal_compute_encoder_end(int64_t encoder) {
    (void)encoder;
}

void php_h3_metal_command_buffer_commit(int64_t cmdBuffer) {
    (void)cmdBuffer;
}

void php_h3_metal_command_buffer_wait(int64_t cmdBuffer) {
    (void)cmdBuffer;
}

void php_h3_metal_command_buffer_free(int64_t cmdBuffer) {
    (void)cmdBuffer;
}

void php_h3_metal_command_queue_free(int64_t queue) {
    (void)queue;
}

// ============================================================================
// Metal Pipeline functions (from metal.mm / metal_native.mm)
// ============================================================================

int64_t php_h3_metal_pipeline_create(int64_t device, String shaderSource, String functionName) {
    (void)device;
    (void)shaderSource;
    (void)functionName;
    return 0;
}

int64_t php_h3_metal_pipeline_create_with_file(int64_t device, String metallibPath, String functionName) {
    (void)device;
    (void)metallibPath;
    (void)functionName;
    return 0;
}

int64_t php_h3_metal_pipeline_get_max_threads_per_threadgroup(int64_t handle) {
    (void)handle;
    return 0;
}

void php_h3_metal_pipeline_free(int64_t handle) {
    (void)handle;
}

// ============================================================================
// ComfyUI functions
// ============================================================================

bool php_h3_comfyui_init(String host) {
    (void)host;
    return false;
}

bool php_h3_comfyui_ping(String host, int64_t port) {
    (void)host;
    (void)port;
    return false;
}

Array php_h3_comfyui_load_model(String host, String model) {
    (void)host;
    (void)model;
    return Array();
}

Array php_h3_comfyui_execute(Array params) {
    (void)params;
    return Array();
}

bool php_h3_comfyui_interrupt() {
    return false;
}

Array php_h3_comfyui_get_stats() {
    return Array();
}

void php_h3_comfyui_shutdown() {
}
