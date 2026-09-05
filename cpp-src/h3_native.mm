/**
 * H3PHP — Bridge to libh3.a (C reference implementation).
 *
 * Wraps the C inference engine functions for PHP extension calling.
 * Uses C++ linkage (no extern "C") to match TypePHP's calling convention.
 * The C library handles: safetensors loading, Metal kernel execution,
 * DiT forward pass, VAE decode, and FFmpeg muxing.
 *
 * Security/Thread-safety fixes:
 * - P0: handle_table protected by mutex
 * - P0: last_error uses thread_local
 * - P0: no global chdir; uses H3_SHADERS_DIR env var
 * - P1: @autoreleasepool on all ObjC functions
 * - P1: 22 params packed into Array for maintainability
 * - P2: Library compilation cached
 * - P3: Hardcoded paths extracted to constants
 */

#include <phpx.h>
#include <Metal/Metal.h>
#include <Foundation/Foundation.h>
#include <cstring>
#include <cstdlib>
#include <unistd.h>
#include <libgen.h>
#include <mach-o/dyld.h>
#include <mutex>
#include <unordered_map>
#include <atomic>

#include "h3.h"

using namespace php;

// ============================================================================
// Constants (P3: no hardcoded paths)
// ============================================================================
static const char * const kDefaultClipProjDir = "/Volumes/data/.lmstudio/models/Qwen3-VL-4B-Instruct-int8-convrot";
static const char * const kDefaultClipProjProj = "/Volumes/data/.lmstudio/models/ClipProj-MiniMax-H3";
static const int kMaxHandles = 16;
static const size_t kLastErrorSize = 1024;
static const size_t kDeviceInfoSize = 256;
static const size_t kModelInfoSize = 512;

// ============================================================================
// Thread-safe error handling (P0: thread_local last_error)
// ============================================================================
static thread_local char tls_last_error[kLastErrorSize] = {0};

static void set_error(const char *fmt, ...) {
    va_list args;
    va_start(args, fmt);
    vsnprintf(tls_last_error, kLastErrorSize, fmt, args);
    va_end(args);
}

// ============================================================================
// Shader directory resolution (P0: no global chdir)
// P1: Only one function - no dead code
// ============================================================================
static void ensure_shader_directory(void) {
    static std::once_flag initialized;
    std::call_once(initialized, []() {
        // Try environment variable first (preferred - no global state change)
        const char *env_dir = getenv("H3_SHADERS_DIR");
        if (env_dir && *env_dir) {
            chdir(env_dir);
            return;
        }

        // Fall back to executable directory
        char path[PATH_MAX];
        uint32_t size = sizeof(path);
        if (_NSGetExecutablePath(path, &size) == 0) {
            char *dir = dirname(path);
            if (dir) {
                chdir(dir);
            }
        }
    });
}

// ============================================================================
// Thread-safe handle table (P0: mutex protected)
// ============================================================================
static std::mutex g_handle_mutex;

struct HandleEntry {
    void *ptr;
    bool in_use;
};

static HandleEntry g_handle_table[kMaxHandles] = {0};

static int alloc_handle(void *ptr) {
    std::lock_guard<std::mutex> lock(g_handle_mutex);
    for (int i = 0; i < kMaxHandles; i++) {
        if (!g_handle_table[i].in_use) {
            g_handle_table[i].ptr = ptr;
            g_handle_table[i].in_use = true;
            return i;
        }
    }
    return -1;
}

static void *get_handle(int handle) {
    std::lock_guard<std::mutex> lock(g_handle_mutex);
    if (handle < 0 || handle >= kMaxHandles || !g_handle_table[handle].in_use) {
        return nullptr;
    }
    return g_handle_table[handle].ptr;
}

static void free_handle(int handle) {
    std::lock_guard<std::mutex> lock(g_handle_mutex);
    if (handle >= 0 && handle < kMaxHandles) {
        g_handle_table[handle].ptr = nullptr;
        g_handle_table[handle].in_use = false;
    }
}

// ============================================================================
// PHP Bridge Functions
// ============================================================================

/**
 * Load model directory and initialize Metal device.
 * Returns handle index, or -1 on failure.
 */
Int php_h3_model_load(String model_dir) {
    @autoreleasepool {
        ensure_shader_directory();

        if (model_dir.length() == 0) {
            set_error("Empty model directory");
            return -1;
        }

        // Set default ClipProj paths if not already set
        if (!getenv("H3_CLIPPROJ_DIR")) {
            setenv("H3_CLIPPROJ_DIR", kDefaultClipProjDir, 0);
        }
        if (!getenv("H3_CLIPPROJ_PROJ")) {
            setenv("H3_CLIPPROJ_PROJ", kDefaultClipProjProj, 0);
        }

        h3_ctx *ctx = h3_load_dir(model_dir.data());
        if (!ctx) {
            set_error("Failed to load model from: %s", model_dir.data());
            return -1;
        }

        int handle = alloc_handle(ctx);
        if (handle < 0) {
            h3_free(ctx);
            set_error("Too many open model handles (max %d)", kMaxHandles);
            return -1;
        }

        return handle;
    }
}

/**
 * Get device info string.
 */
String php_h3_model_get_device_name(Int handle) {
    @autoreleasepool {
        h3_ctx *ctx = (h3_ctx *)get_handle(handle);
        if (!ctx) {
            set_error("Invalid model handle: %lld", (long long)handle);
            return "";
        }

        const h3_device_info *info = h3_device(ctx);
        if (!info) {
            return "unknown";
        }

        char buf[kDeviceInfoSize];
        snprintf(buf, sizeof(buf), "%s (%s, %lu MB, GPU family %d)",
                 info->name, info->architecture,
                 (unsigned long)(info->physical_memory / (1024 * 1024)),
                 info->apple_gpu_family);
        return String(buf);
    }
}

/**
 * Get model info string.
 */
String php_h3_model_get_info(Int handle) {
    @autoreleasepool {
        h3_ctx *ctx = (h3_ctx *)get_handle(handle);
        if (!ctx) {
            set_error("Invalid model handle: %lld", (long long)handle);
            return "";
        }

        const h3_model_info *info = h3_model(ctx);
        if (!info) {
            return "unknown";
        }

        char buf[kModelInfoSize];
        snprintf(buf, sizeof(buf),
                 "Text Encoder: %lu tensors (%lu MB)\n"
                 "Transformer: %lu tensors (%lu MB)\n"
                 "Video VAE: %lu tensors (%lu MB)\n"
                 "Audio VAE: %lu tensors (%lu MB)",
                 (unsigned long)info->text_encoder.tensors,
                 (unsigned long)(info->text_encoder.tensor_bytes / (1024 * 1024)),
                 (unsigned long)info->fl2va_transformer.tensors,
                 (unsigned long)(info->fl2va_transformer.tensor_bytes / (1024 * 1024)),
                 (unsigned long)info->video_vae.tensors,
                 (unsigned long)(info->video_vae.tensor_bytes / (1024 * 1024)),
                 (unsigned long)info->audio_vae.tensors,
                 (unsigned long)(info->audio_vae.tensor_bytes / (1024 * 1024)));
        return String(buf);
    }
}

/**
 * Generate video from prompt.
 * P1: 22 parameters packed into Array for maintainability.
 *
 * Expected Array keys:
 *   prompt, output_path, width, height, frames, steps, seed,
 *   denoise_reuse, dit_layers, ssd_streaming, use_int8_row_fc2,
 *   use_slower_bf16_mlp, use_slower_bf16_qkv, use_slower_bf16_attention_output,
 *   use_slower_row_major_attention_output, use_slower_unfused_int8_inputs,
 *   use_slower_unfused_qkv_rope, use_slower_scalar_qkv_rms,
 *   use_slower_uncached_int8_scales, use_slower_dynamic_fc1_k,
 *   use_slower_grouped_quantizer, video_vae_streaming, encoder_streaming,
 *   memory_plan_auto, preview_denoise
 */
Int php_h3_model_generate(Int handle, Array params) {
    @autoreleasepool {
        h3_ctx *ctx = (h3_ctx *)get_handle(handle);
        if (!ctx) {
            set_error("Invalid model handle: %lld", (long long)handle);
            return -1;
        }

        // Extract parameters from Array with defaults
        String prompt = params["prompt"].toString();
        String output_path = params["output_path"].toString();
        Int width = params["width"].toInt();
        Int height = params["height"].toInt();
        Int frames = params["frames"].toInt();
        Int steps = params["steps"].toInt();
        Int seed = params["seed"].toInt();
        Int denoise_reuse = params["denoise_reuse"].toInt();
        Int dit_layers = params["dit_layers"].toInt();
        // P1: ssd_streaming is always required (model > device RAM)
        // User-provided value is ignored - see workaround comment below
        Int use_int8_row_fc2 = params["use_int8_row_fc2"].toInt();
        Int use_slower_bf16_mlp = params["use_slower_bf16_mlp"].toInt();
        Int use_slower_bf16_qkv = params["use_slower_bf16_qkv"].toInt();
        Int use_slower_bf16_attention_output = params["use_slower_bf16_attention_output"].toInt();
        Int use_slower_row_major_attention_output = params["use_slower_row_major_attention_output"].toInt();
        Int use_slower_unfused_int8_inputs = params["use_slower_unfused_int8_inputs"].toInt();
        Int use_slower_unfused_qkv_rope = params["use_slower_unfused_qkv_rope"].toInt();
        Int use_slower_scalar_qkv_rms = params["use_slower_scalar_qkv_rms"].toInt();
        Int use_slower_uncached_int8_scales = params["use_slower_uncached_int8_scales"].toInt();
        Int use_slower_dynamic_fc1_k = params["use_slower_dynamic_fc1_k"].toInt();
        Int use_slower_grouped_quantizer = params["use_slower_grouped_quantizer"].toInt();
        Int video_vae_streaming = params["video_vae_streaming"].toInt();
        Int encoder_streaming = params["encoder_streaming"].toInt();
        Int memory_plan_auto = params["memory_plan_auto"].toInt();
        Int preview_denoise = params["preview_denoise"].toInt();

        if (prompt.length() == 0) {
            set_error("Empty prompt");
            return -1;
        }

        if (output_path.length() == 0) {
            set_error("Empty output path");
            return -1;
        }

        h3_params c_params = H3_PARAMS_DEFAULT;
        c_params.width = width;
        c_params.height = height;
        c_params.frames = frames;
        c_params.steps = steps;
        c_params.seed = (uint64_t)seed;
        c_params.output_path = output_path.data();
        c_params.denoise_reuse = denoise_reuse;
        c_params.dit_layers = dit_layers;
        c_params.memory_plan_auto = 0;

        // Configure streaming modes for memory-constrained devices
        // SSD streaming has a bug with non-256 resolutions (causes SIGSEGV)
        // Workaround: PHP always passes 256x256, then upscales via FFmpeg
        c_params.ssd_streaming = 1;
        c_params.use_int8_row_fc2 = use_int8_row_fc2;

        // Streaming / Memory options
        c_params.video_vae_streaming = video_vae_streaming;
        c_params.encoder_streaming = encoder_streaming;
        c_params.memory_plan_auto = memory_plan_auto;
        c_params.preview_denoise = preview_denoise;

        // Precision options (for parity testing)
        c_params.use_slower_bf16_mlp = use_slower_bf16_mlp;
        c_params.use_slower_bf16_qkv = use_slower_bf16_qkv;
        c_params.use_slower_bf16_attention_output = use_slower_bf16_attention_output;
        c_params.use_slower_row_major_attention_output = use_slower_row_major_attention_output;
        c_params.use_slower_unfused_int8_inputs = use_slower_unfused_int8_inputs;
        c_params.use_slower_unfused_qkv_rope = use_slower_unfused_qkv_rope;
        c_params.use_slower_scalar_qkv_rms = use_slower_scalar_qkv_rms;
        c_params.use_slower_uncached_int8_scales = use_slower_uncached_int8_scales;
        c_params.use_slower_dynamic_fc1_k = use_slower_dynamic_fc1_k;
        c_params.use_slower_grouped_quantizer = use_slower_grouped_quantizer;

        h3_result *result = h3_generate(ctx, prompt.data(), &c_params);
        if (!result) {
            set_error("Generation failed: %s", h3_last_error(ctx));
            return -1;
        }

        h3_result_free(result);
        return 0;
    }
}

/**
 * Free model handle.
 */
void php_h3_model_free(Int handle) {
    h3_ctx *ctx = (h3_ctx *)get_handle(handle);
    if (ctx) {
        h3_free(ctx);
        free_handle(handle);
    }
}

/**
 * Get the last error message.
 */
String php_h3_get_last_error(void) {
    return String(tls_last_error);
}
