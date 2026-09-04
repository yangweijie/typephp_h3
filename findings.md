# H3PHP — Findings & Research

## TypePHP Build System
- **Build modes**: `bin` (executable), `ext` (PHP extension), `lib` (shared library)
- **Native sources**: `.php`, `.cpp`, `.cc`, `.c`, `.s`, `.S`, `.m`, `.mm` all recognized
- **Obj-C++ support**: `.mm` files compiled with `-x objective-c++` flag
- **C++ interop**: `php_` prefix functions + `php::Box` for GC-managed objects
- **Bidirectional**: PHP→C++ via stubs, C++→PHP via `php::call()`
- **Framework linking**: Via `cxxflags`/`ldflags` in project.yml

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

## aot-compiler Patterns
- **Dual entry**: `bin/tpc.php` (composer) + `cli.php` (dev wrapper)
- **Centralized options**: `Constants::COMPILER_OPTIONS` array drives parsing + completion
- **Subcommand dispatch**: Early returns in `main()` for special modes
- **Output**: All via CLImate with named styles (lightBlue, green, warning, error)

## php-metal-gpu Patterns
- **Namespace**: `Metal\` prefix for all classes
- **Factory pattern**: Device creates all resources
- **Error handling**: Unified `Metal\Exception`
- **Object lifecycle**: PHP GC + Obj-C ARC cooperation via `php::Box`

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

## VDN-H3 Repository Analysis (D:/git/python/vdn-minimax-h3)

### Model Dimensions (VERIFY — may differ from current H3PHP assumptions!)
| Parameter | H3PHP Initial | VDN-H3 Actual | Source |
|-----------|--------------|---------------|--------|
| hidden_size | 5376 | **5120** | encode_prompt.py:48 |
| num_attention_heads | 56 | **40** | inferred (5120/128) |
| attention_head_dim | 96 | **128** | delta_rule.py:37 |
| num_layers | 50 | **40** | configs |
| tokens_per_frame | 768 | **1008** | (48/2)*(84/2) |
| LATENT_H, LATENT_W | undefined | **48, 84** | render.py:27-28 |
| video_channels | 24 | **24** | render.py:102 ✅ |
| audio_channels | 2 (stereo) | **2** | render.py:29 ✅ |

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

## Design Patterns Used
1. **Centralized Options Schema** — Single source of truth for CLI flags
2. **Factory Pattern** — Device creates all Metal resources
3. **Singleton Cleanup Exit** — finally blocks ensure resource release
4. **Exception-based Error Handling** — Testable alternative to exit()
5. **Box Wrapping** — php::Box subclasses for GC-managed native objects
6. **Stub Declaration** — PHP functions declared in .stub.php, implemented in .mm
7. **Hybrid Attention** — Softmax window + linear far branch with gated fusion
8. **Delta Rule Scan** — Bidirectional state-space recurrence for linear attention

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
