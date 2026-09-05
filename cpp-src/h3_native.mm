/**
 * H3PHP — Bridge to libh3.a (C reference implementation).
 *
 * Wraps the C inference engine functions for PHP extension calling.
 * Uses C++ linkage (no extern "C") to match TypePHP's calling convention.
 * The C library handles: safetensors loading, Metal kernel execution,
 * DiT forward pass, VAE decode, and FFmpeg muxing.
 */

#include <phpx.h>
#include <Metal/Metal.h>
#include <Foundation/Foundation.h>
#include <cstring>
#include <cstdlib>
#include <unistd.h>
#include <libgen.h>
#include <mach-o/dyld.h>

#include "h3.h"

using namespace php;

// Change working directory to the directory containing this binary
// so the C library can find h3_shaders.metal
static void ensure_shader_directory(void) {
    static int initialized = 0;
    if (initialized) return;
    initialized = 1;

    char path[PATH_MAX];
    uint32_t size = sizeof(path);
    if (_NSGetExecutablePath(path, &size) == 0) {
        char *dir = dirname(path);
        if (dir) {
            chdir(dir);
        }
    }
}

// Error buffer for last error message
static char last_error[1024] = {0};

// Opaque handle storage (PHP int -> C pointer)
#define MAX_HANDLES 16
static struct {
    void *ptr;
    int in_use;
} handle_table[MAX_HANDLES] = {0};

static int alloc_handle(void *ptr) {
    for (int i = 0; i < MAX_HANDLES; i++) {
        if (!handle_table[i].in_use) {
            handle_table[i].ptr = ptr;
            handle_table[i].in_use = 1;
            return i;
        }
    }
    return -1;
}

static void *get_handle(int handle) {
    if (handle < 0 || handle >= MAX_HANDLES || !handle_table[handle].in_use) {
        return nullptr;
    }
    return handle_table[handle].ptr;
}

static void free_handle(int handle) {
    if (handle >= 0 && handle < MAX_HANDLES) {
        handle_table[handle].ptr = nullptr;
        handle_table[handle].in_use = 0;
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
    ensure_shader_directory();

    if (model_dir.length() == 0) {
        snprintf(last_error, sizeof(last_error), "Empty model directory");
        return -1;
    }

    // Set default ClipProj paths if not already set
    if (!getenv("H3_CLIPPROJ_DIR")) {
        setenv("H3_CLIPPROJ_DIR", "/Volumes/data/.lmstudio/models/Qwen3-VL-4B-Instruct-int8-convrot", 0);
    }
    if (!getenv("H3_CLIPPROJ_PROJ")) {
        setenv("H3_CLIPPROJ_PROJ", "/Volumes/data/.lmstudio/models/ClipProj-MiniMax-H3", 0);
    }

    h3_ctx *ctx = h3_load_dir(model_dir.data());
    if (!ctx) {
        snprintf(last_error, sizeof(last_error), "Failed to load model from: %s", model_dir.data());
        return -1;
    }

    int handle = alloc_handle(ctx);
    if (handle < 0) {
        h3_free(ctx);
        snprintf(last_error, sizeof(last_error), "Too many open model handles");
        return -1;
    }

    return handle;
}

/**
 * Get device info string.
 */
String php_h3_model_get_device_name(Int handle) {
    h3_ctx *ctx = (h3_ctx *)get_handle(handle);
    if (!ctx) {
        snprintf(last_error, sizeof(last_error), "Invalid model handle");
        return "";
    }

    const h3_device_info *info = h3_device(ctx);
    if (!info) {
        return "unknown";
    }

    char buf[256];
    snprintf(buf, sizeof(buf), "%s (%s, %lu MB, GPU family %d)",
             info->name, info->architecture,
             (unsigned long)(info->physical_memory / (1024 * 1024)),
             info->apple_gpu_family);
    return String(buf);
}

/**
 * Get model info string.
 */
String php_h3_model_get_info(Int handle) {
    h3_ctx *ctx = (h3_ctx *)get_handle(handle);
    if (!ctx) {
        snprintf(last_error, sizeof(last_error), "Invalid model handle");
        return "";
    }

    const h3_model_info *info = h3_model(ctx);
    if (!info) {
        return "unknown";
    }

    char buf[512];
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

/**
 * Generate video from prompt.
 * Returns 0 on success, -1 on failure.
 */
Int php_h3_model_generate(Int handle, String prompt, String output_path,
                      Int width, Int height, Int frames, Int steps,
                      Int seed, Int denoise_reuse, Int dit_layers,
                      Int ssd_streaming, Int use_int8_row_fc2) {
    h3_ctx *ctx = (h3_ctx *)get_handle(handle);
    if (!ctx) {
        snprintf(last_error, sizeof(last_error), "Invalid model handle");
        return -1;
    }

    if (prompt.length() == 0) {
        snprintf(last_error, sizeof(last_error), "Empty prompt");
        return -1;
    }

    if (output_path.length() == 0) {
        snprintf(last_error, sizeof(last_error), "Empty output path");
        return -1;
    }

    h3_params params = H3_PARAMS_DEFAULT;
    params.width = width;
    params.height = height;
    params.frames = frames;
    params.steps = steps;
    params.seed = (uint64_t)seed;
    params.output_path = output_path.data();
    params.denoise_reuse = denoise_reuse;
    params.dit_layers = dit_layers;
    params.memory_plan_auto = 0;

    // Manually enable streaming modes for memory-constrained devices
    // SSD streaming uses BF16 weights, so it cannot be combined with int8 row FC2
    params.ssd_streaming = 1;        // Force enable SSD streaming to reduce memory
    params.video_vae_streaming = 1;  // Always stream VAE to save memory
    params.encoder_streaming = 1;    // Release text encoder after conditioning
    params.use_int8_row_fc2 = 0;     // Disable int8 to avoid conflict with SSD streaming

    h3_result *result = h3_generate(ctx, prompt.data(), &params);
    if (!result) {
        snprintf(last_error, sizeof(last_error), "Generation failed: %s",
                 h3_last_error(ctx));
        return -1;
    }

    h3_result_free(result);
    return 0;
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
    return String(last_error);
}
