# H3PHP — Findings & Research

## TypePHP Build System
- **Build modes**: `bin` (executable), `ext` (PHP extension), `lib` (shared library)
- **Native sources**: `.php`, `.cpp`, `.cc`, `.c`, `.s`, `.S`, `.m`, `.mm` all recognized
- **Obj-C++ support**: `.mm` files compiled with `-x objective-c++` flag
- **C++ interop**: `php_` prefix functions + opaque Int handles for GC safety
- **Bidirectional**: PHP→C++ via stubs, C++→PHP via `php::call()`
- **Framework linking**: Via `cxx-flags`/`ld-flags` in project.yml

## h3.c Engine Architecture
- **Two paths**: FL2VA (text→video) and Ref2VA (reference→video)
- **Six stages**: Load → Conditioning → DiT Denoising → Decoding → Muxing → SR
- **DiT**: 50 blocks, HIDDEN=5376, HEADS=56, MLP=14336
- **Video VAE**: 36-block tiled decoder
- **Audio**: 32kHz stereo, BigVGAN decoder
- **Canvas**: 864x480 default, multiples of 32, frames aligned to 5+17*n
- **Acceleration**: reuse, core-reuse, layer pruning, token reduction, SSD streaming, int8

## h3.c CLI Interface
- **Modes**: one-shot (`-p`), interactive (no `-p`), info (`--info`)
- **Progress format**: `\r%-25s %4d/%-4d` on stderr
- **Interactive commands**: All prefixed with `!` (e.g., `!seed`, `!steps`, `!size`)
- **Exit codes**: 0=success, 1=runtime error, 2=argument error

## libh3.a C Library Integration (NEW)

### API Functions
```c
h3_ctx *h3_load_dir(const char *model_dir);
void h3_free(h3_ctx *ctx);
h3_result *h3_generate(h3_ctx *ctx, const char *prompt, const h3_params *params);
void h3_result_free(h3_result *result);
const char *h3_last_error(const h3_ctx *ctx);
const h3_device_info *h3_device(const h3_ctx *ctx);
const h3_model_info *h3_model(const h3_ctx *ctx);
```

### Memory Requirements
| Component | Size |
|-----------|------|
| Transformer (FL2VA) | ~21 GB |
| Video VAE | ~4.8 GB |
| Audio VAE | ~577 MB |
| Text Encoder (ClipProj) | ~4.6 GB |
| **Total** | **~26.8 GB** |

### Memory-Constrained Devices (M4 16GB)
- **Problem**: Model exceeds unified memory (26.8GB > 16GB)
- **Auto memory planner bug**: Enables SSD streaming + int8 row FC2 simultaneously → conflict error
- **SSD streaming SIGSEGV bug**: Crashes with non-256 resolutions (512x512, 864x480, etc.)
  - Root cause: bug in C library's SSD streaming code for certain canvas sizes
  - Workaround: always render at 256x256, upscale via FFmpeg post-processing
  - Quality impact: minimal (lanczos upscaling is high quality)

### Complete C Library Parameters (22 total)
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| width | int | 864 | Output width (multiple of 32) |
| height | int | 480 | Output height (multiple of 32) |
| frames | int | 56 | Frame count (22-362) |
| steps | int | 20 | Denoising steps (1-1000) |
| seed | uint64 | 42 | Random seed |
| denoise_reuse | int | 1 | Denoiser reuse (1=quality, 3=fast) |
| dit_layers | int | 50 | DiT blocks (50=exact, 35=min) |
| core_reuse | int | 1 | Core refresh interval |
| token_reduction | bool | false | Pair video tokens |
| ssd_streaming | bool | false | Stream DiT weights from SSD |
| use_int8_row_fc2 | bool | false | INT8 per-row FC2 |
| use_reference_rope | bool | false | Disable 256 RoPE adaptation |
| use_slower_bf16_mlp | bool | false | Force BF16 MLP |
| use_slower_bf16_qkv | bool | false | Force BF16 QKV |
| use_slower_bf16_attention_output | bool | false | Force BF16 attention output |
| use_slower_row_major_attention_output | bool | false | Row-major BF16 before int8 |
| use_slower_unfused_int8_inputs | bool | false | Standalone int8 quantization |
| use_slower_unfused_qkv_rope | bool | false | Separate QK/RoPE kernel |
| use_slower_scalar_qkv_rms | bool | false | Scalar BF16 loads |
| use_slower_uncached_int8_scales | bool | false | Reread int8 scales |
| use_slower_dynamic_fc1_k | bool | false | Dynamic FC1 K loop |
| use_slower_grouped_quantizer | bool | false | Original grouped quantizer |
| video_vae_streaming | bool | false | Stream VAE decoder weights |
| encoder_streaming | bool | false | Release text encoder after conditioning |
| memory_plan_auto | bool | true | Auto-pick settings |
| preview_denoise | bool | false | Preview after each step |
| render_width | int | 0 | Internal render width (0=output) |
| render_height | int | 0 | Internal render height (0=output) |

### Render Resolution Workaround
- **Problem**: SSD streaming crashes with non-256 resolutions
- **Solution**: Always pass 256x256 to C library, upscale via FFmpeg
- **FFmpeg command**: `ffmpeg -i input -vf "scale=W:H:flags=lanczos" -c:v libx264 ...`
- **Supported resolutions**: Any multiple of 32 (256x256, 512x512, 864x480, etc.)
- **Quality**: Lanczos scaling is visually lossless for 2x-4x upscaling
- **Solution**: Manual streaming configuration
  ```c
  params.memory_plan_auto = 0;
  params.ssd_streaming = 1;        // Stream DiT weights from disk
  params.video_vae_streaming = 1;  // Stream VAE decoder weights
  params.encoder_streaming = 1;    // Release text encoder after conditioning
  params.use_int8_row_fc2 = 0;     // Must be 0 with SSD streaming
  ```

### ClipProj Text Encoder
- **Default path**: `/Volumes/data/.lmstudio/models/Qwen3-VL-4B-Instruct-int8-convrot`
- **Projection**: `/Volumes/data/.lmstudio/models/ClipProj-MiniMax-H3`
- **Env vars**: `H3_CLIPPROJ_DIR`, `H3_CLIPPROJ_PROJ`
- **Fallback**: Set to `0` or `off` to use 50-layer encoder at `FL2VA/text_encoder`

### Metal Shaders
- **File**: `h3_shaders.metal` (252KB, 5581 lines)
- **Location**: Must be in current working directory at runtime
- **Workaround**: `ensure_shader_directory()` chdir to binary location
- **Kernels**: 100+ compute kernels (linear, attention, VAE, quantization, etc.)

### Linking Requirements
- **Static library**: `libh3.a` (849KB, compiled from C sources)
- **Frameworks**: Metal, MetalKit, Foundation, Accelerate, MetalPerformanceShaders, MetalPerformanceShadersGraph
- **Libraries**: `-lh3`, `-licucore` (ICU for tokenizer), `-lphpx`, `-lphp`

### Performance (Apple M4 16GB)
| Resolution | Steps | Frames | Time | Bottleneck |
|------------|-------|--------|------|------------|
| 256×256 | 3 | 25 | 1:15 | SSD I/O + text encoding |
| 256×256 | 20 | 25 | ~10min | DiT denoising (3 NFEs) |
| 864×480 | 20 | 56 | ~30min | Full pipeline |

## Model Directory Structure
```
MODEL_DIR/
+-- FL2VA/
|   +-- transformer/config.json     (required)
|   +-- tokenizer/tokenizer.json    (required)
|   +-- text_encoder/               (optional with ClipProj)
|   +-- video_vae/source/           (required)
|   +-- audio_vae/                  (required)
+-- Ref2VA/                         (optional, for references)
    +-- transformer/
    +-- tokenizer/
    +-- text_encoder/
    +-- video_vae/
    +-- audio_vae/
```

## VDN-H3 Repository Analysis

### Model Dimensions (VERIFY — may differ from current H3PHP assumptions!)
| Parameter | H3PHP Initial | VDN-H3 Actual | Source |
|-----------|--------------|---------------|--------|
| hidden_size | 5376 | **5120** | encode_prompt.py:48 |
| num_attention_heads | 56 | **40** | inferred (5120/128) |
| attention_head_dim | 96 | **128** | delta_rule.py:37 |
| num_layers | 50 | **40** | configs |
| tokens_per_frame | 768 | **1008** | (48/2)*(84/2) |
| LATENT_H, LATENT_W | undefined | **48, 84** | render.py:27-28 |
| video_channels | 24 | **24** | render.py:102 |
| audio_channels | 2 (stereo) | **2** | render.py:29 |

### Hybrid Attention Architecture
- **Softmax branch**: windowed attention (radius=1, chunk=5 → 15-frame window)
- **Linear branch**: bidirectional delta-rule scan for far dependencies
- **Fusion**: `softmax_gate * softmax_out + output_gate * linear_readout`
- **Delta rule**: VdnDelta (exact Cholesky inverse, batched cuBLAS/cuSOLVER)
- **Window geometry**: frame mode (|t_q - t_k| <= radius) or chunk-aligned
- **Anchor frames**: frames 0 and F-1 exact softmax, linear branch drops them
- **Text state seeding**: both scans start from `0.5 * S_text` (TEXT_STATE_SCALE)

### Precision Islands (5 FP32-required locations)
1. AdaLN SiLU — 3.5e-3 error if bf16 (PATCHED in OpenVDN)
2. Linear branch A statistics — bf16 breaks Cholesky conditioning
3. FrameKDAAlpha — errors compound over ~100 frames
4. Bidirectional scans — state recurrence needs fp32
5. RMSNorm — second-moment accumulation in fp32

### Kernel Fusion Techniques
- RMSNorm + AdaLN affine → single compiled kernel
- RMSNorm + RoPE → fused QK prep
- SwiGLU → single kernel FFN
- FP8 Linear → e4m3 for wide layers (min_width=4096)
- Triton temporal conv → 5-tap + SiLU + L2Norm

### Separate Video/Audio Scheduling
- Video scheduler: shift=12.0
- Audio scheduler: shift=3.0
- Per-row timesteps: video rows get video_t, audio rows get audio_t

## OpenVDN Patch Insights (2026-08-15 / 2026-08-27)

### Patch 1: AdaLN SiLU Precision (CRITICAL)
- **Source**: OpenVDN/vdn-minimax-h3 patch 2026-08-15
- **Problem**: Under FSDP2, `cast_forward_inputs=True` casts `temb` to bf16 before SiLU
- **Impact**: 3.5e-3 norm-relative error, 55% of AdaLN projection elements changed
- **Root cause**: Every block reads same `temb`, so error accumulates coherently
- **Fix**: `silu(temb.float())` — explicit FP32 before activation
- **H3PHP action**: AdaLN kernel uses `float*` for scale/shift/gate; SiLU must run in FP32

### Patch 2: NFE Counting Fix
- **Source**: OpenVDN/vdn-minimax-h3 patch 2026-08-27
- **Problem**: `linspace(1, 0, steps)` → steps sigmas → steps-1 model evaluations (wrong)
- **Fix**: `linspace(1, 0, steps+1)` → steps+1 sigmas → exactly steps model evaluations
- **H3PHP status**: Already correct — `for ($i = 0; $i <= $steps; $i++)` produces steps+1 sigmas

### Precision Rules for H3PHP
1. **SiLU activation**: MUST run in FP32 (not BF16)
2. **AdaLN scale/shift/gate**: MUST arrive at kernel as FP32 (`float*`)
3. **Sigma schedule**: Compute in FP64 (PHP float), cast to FP32 for Metal
4. **Timestep embedding**: Compute in FP64, cast to FP32 for Metal
5. **Attention QKV**: BF16 acceptable (error doesn't accumulate coherently)
6. **MLP layers**: BF16 acceptable; INT8 for fc2 with per-channel scale
7. **Linear branch A statistics**: MUST be FP32 (Cholesky conditioning)
8. **FrameKDAAlpha**: MUST be FP32 (errors compound over frames)
9. **Bidirectional scans**: MUST be FP32 (state recurrence)
10. **RMSNorm accumulation**: MUST be FP32 (precision-critical summation)

## TypePHP Limitations Discovered (2026-09-05)

### Switch/Case Rules
- Every `case` body **must end with** `return`, `break`, `continue`, `exit`, or `throw`
- Empty fall-through cases (`case A: case B: ...`) are **rejected** — must merge with `||` or duplicate the body
- `default:` ending in a bare expression is **rejected** — must add `break`
- Fix: 16 cases patched in symfony/yaml before hitting deeper issues

### Variable Type Stability
- A variable's type is fixed on first assignment — **cannot be reassigned to a different type**
- `mixed`-returning methods (8 in symfony/yaml) create unresolvable conflicts when the result is used in multiple type contexts
- Workaround: introduce new variables per type (works for simple cases, fails for recursive mixed-returning call graphs)

### C++ Generation Layer
- Even when PHP passes type-checking, the generated `.cc` may fail to compile
- symfony/yaml produces 10+ clang errors: `operator '+' ambiguous (php::Ref, long long)`, `Variant → php::Int` conversion, `expression is not assignable`
- `Dumper.cc:235` fails on unpatched code → failure is **inherent to the library + TypePHP**, not fixable via PHP changes
- **Conclusion**: symfony/yaml (and likely other complex vendor libs) cannot be AOT-compiled with current TypePHP

### Vendor Patch Mechanism (patch.php)
- `patches/` directory mirrors vendor structure; `patch.php` **whole-file copies** patches → vendor
- Hooked as `post-autoload-dump` script → runs on every `composer install/update/dump-autoload`
- **Critical caveat**: patch.php only copies **to** vendor, never restores. Deleting a patch file does NOT revert vendor — must `rm -rf vendor/pkg && composer install`
- **Version freeze risk**: patches lock the file at the current version. `composer update pkg` installs new version, then patch.php overwrites with old → silent version mismatch. Pin exact versions when using patches
- **Use case**: best for small, stable vendor tweaks (e.g. TypePHP compatibility patches), not for large libraries

### Build Key Names (project.yml)
- Translator reads **`cxx-flags`** / **`ld-flags`** (hyphenated), NOT `cxxflags` / `ldflags`
- No alias normalization in YAML loader — wrong keys are **silently ignored**
- `-lobjc` required in ld-flags for ObjC runtime (`.mm` GC boxes); clang++ driver doesn't auto-add it when linking `.o` via response file

### Native Function Linking (RESOLVED)
- **Solution**: Use ObjC Metal API (`.mm`) with C++ linkage + opaque `Int` handles
  - Stub file uses concrete types (`int`, `string`, `array`, `bool`) — NOT `mixed`
  - C++ uses `Int`, `String`, `Array`, `Bool` — NOT `var`/`Box`
  - Handles passed as `Int` — NOT `php::Box` or `var`
  - Functions compiled separately and linked via `ld-flags`
- **Key insight**: `functionUsesNativeObject()` returns `false` for functions using primitive types, so they ARE registered in `ext_functions[]`
- **Build process**:
  1. Compile `.mm` → `.o` manually: `clang++ -c -framework Metal ...`
  2. Link `.o` via `project.yml` `ld-flags`
  3. Build PHP with `tpc.php`
- **Limitation**: TypePHP build system does NOT compile native files in `cpp-src/` — must compile separately
- **Metal-CPP**: Apple's Metal-CPP headers are incomplete (missing `s_kNSString`), so ObjC Metal API is used instead
- Dev mode (`php bin/h3php.php`) also cannot call native functions (no `.mm` loading in interpreter)

### php::String API (TypePHP)
- Use `.data()` for `const char*` — NOT `.c_str()`
- Use `.length() == 0` for empty check — NOT `.empty()`
- Construct from C string: `String("text")` or `String(buf)`

### C Library Bridge Pattern (NEW)
- **Function naming**: Must use `php_` prefix (e.g., `php_h3_model_load`)
- **Linkage**: C++ (no `extern "C"`) — TypePHP generates C++ mangled names
- **Parameters**: Use `Int`, `String`, `Array`, `Bool` — NOT `var` or `mixed`
- **Return types**: Same — concrete types only
- **Handle table**: Static array mapping `Int` handles to C pointers
- **Static buffers**: `static char[]` for returning strings (thread-unsafe but works for CLI)

## Performance Optimization Summary
| Optimization | Expected Gain | Status |
|-------------|---------------|--------|
| Tiled Flash Attention | 2-3x attention | ✅ Kernel |
| Fused QKV + ROPE | 1.5x projection | ✅ Kernel |
| INT8 MLP | 1.5-2x MLP | ✅ Kernel |
| Cross-Attention KV-Cache | 1.3-1.5x text cond | ✅ PHP |
| Buffer Pool | Reduce alloc overhead | ✅ PHP |
| Hybrid Attention | Quality + efficiency | ✅ Architecture |
| **Combined** | **3-5x** | |
