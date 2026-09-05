# H3PHP — Task Plan

## Goal
Build a PHP CLI application (compiled to standalone binary via TypePHP) that implements the complete MiniMax-H3 video generation engine: text-to-video, image-referenced video, interactive CLI, and all internal commands — with hybrid attention architecture from VDN-H3.

## Architecture
- **Language**: PHP 8.4+ (business logic) + Objective-C++ (.mm for Metal GPU)
- **Build**: TypePHP AOT compiler → standalone binary (`-m bin`)
- **CLI**: Native PHP (no CLImate) with TTY-aware output
- **C++ Interop**: `php_` prefix ABI + opaque Int handles for Metal object lifetime
- **Attention**: Hybrid (softmax window + linear far branch) from VDN-H3
- **Inference Engine**: C reference implementation (libh3.a) for real model inference

## Phases

### Phase 1: Project Skeleton + CLI Framework — `complete`
| Task | Status |
|------|--------|
| project.yml (bin mode, Metal frameworks) | complete |
| bin/bootstrap.php + bin/h3php.php entry | complete |
| Cli/Application.php (native CLI) | complete |
| Cli/Options.php (centralized option schema) | complete |
| Cli/ProgressDisplay.php (stderr \r updates) | complete |
| php-src/main.php (main() dispatch) | complete |
| Core/ModelLoader.php (model dir scanning) | complete |
| Core/H3Context.php (engine context) | complete |
| Cli/InteractiveSession.php (REPL with 25+ commands) | complete |
| composer.json (dependencies) | complete |
| README.md | complete |

### Phase 2: Metal GPU Foundation — `complete`
| Task | Status |
|------|--------|
| metal.stub.php (device/buffer/pipeline/command_queue) | complete |
| cpp-src/metal_native.mm (ObjC Metal API) | complete |
| php-src/Metal/ (Device, Buffer, Pipeline, CommandQueue) | complete |

### Phase 3: Inference Engine Core — `complete` ⚠️ skeleton only
| Task | Status |
|------|--------|
| Encoder/Tokenizer.php | complete (unused - C library handles) |
| Encoder/TextEncoder.php | complete (unused - C library handles) |
| Encoder/VisionEncoder.php | complete (unused - C library handles) |
| Inference/DiT.php | complete (unused - C library handles) |
| Inference/Sampler.php | complete (unused - C library handles) |
| Inference/Scheduler.php | complete (unused - C library handles) |

### Phase 4: VAE + Output Pipeline — `complete` ⚠️ skeleton only
| Task | Status |
|------|--------|
| VAE/VideoVAE.php | complete (unused - C library handles) |
| VAE/AudioVAE.php | complete (unused - C library handles) |
| Core/ProcessRunner.php | complete (unused - C library handles) |

### Phase 5: Generation + Interactive Mode — `complete`
| Task | Status |
|------|--------|
| Generator/Params.php (h3_params + validation) | complete |
| Generator/Pipeline.php (C library bridge) | complete |
| Generator/TextToVideo.php (FL2VA mode) | complete |
| Generator/ReferenceToVideo.php (Ref2VA mode) | complete |

### Phase 6: Advanced Features — `complete`
| Task | Status |
|------|--------|
| Inference/LoRA.php (LoRA weight merging) | complete |
| config/defaults.yaml (default configuration) | complete |

### Phase 7: MSL Kernels + Tests + Build — `complete`
| Task | Status |
|------|--------|
| cpp-src/h3_dit_kernels.mm | complete |
| cpp-src/h3_vae_kernels.mm | complete |
| tests/ (85 tests, 619 assertions) | complete |

### Phase 8: Code Review Fixes — `complete`
| Task | Status |
|------|--------|
| Removed unused imports, resource leaks, error handling | complete |

### Phase 9: Performance Optimization — `complete`
| Task | Status |
|------|--------|
| Tiled Flash Attention, Fused QKV+ROPE, INT8 MLP | complete |

### Phase 10: VDN-H3 Research & Integration — `complete`
| Task | Status |
|------|--------|
| ModelConfig, FP32 precision islands, hybrid attention | complete |

### Phase 11: Hybrid Attention Architecture — `complete`
| Task | Status |
|------|--------|
| DeltaRule, FrameKDAAlpha, OutputGate, BidirectionalScan | complete |

### Phase 12: Dependency Removal (CLImate + symfony/yaml) — `complete`
| Task | Status |
|------|--------|
| Replace CLImate with native CLI, symfony/yaml with native parser | complete |

### Phase 13: Metal Native Layer — `complete`
| Task | Status |
|------|--------|
| metal_native.mm with ObjC Metal API + Int handles | complete |

### Phase 14: C Library Integration (libh3.a) — `complete`
| Task | Status |
|------|--------|
| h3_native.mm bridge, h3.stub.php, build system | complete |
| Pipeline.php rewritten for C library | complete |
| End-to-end verification | complete |

### Phase 15: CLI Parameter Exposure + Progress Fix — `complete`
| Task | Status |
|------|--------|
| Progress display fix (in-place update) | complete |
| width/height fix (256x256 + FFmpeg upscale) | complete |
| 14 new CLI parameters exposed | complete |
| README_ZH.md | complete |

### Phase 16: Security Hardening (P0-P3) — `complete` ✅ NEW
| Task | Status |
|------|--------|
| P0: handle_table thread safety (mutex) | complete |
| P0: last_error thread safety (thread_local) | complete |
| P0: chdir global state (env var fallback) | complete |
| P1: storeH exception safety (retain before lock) | complete |
| P1: @autoreleasepool on all ObjC functions | complete |
| P1: 22 params → Array (maintainability) | complete |
| P2: Library compilation cache | complete |
| P2: map → unordered_map | complete |
| P3: Hardcoded paths → constants | complete |

## Key Decisions
| Decision | Choice | Reason |
|----------|--------|--------|
| CLI framework | **Native PHP** (no CLImate) | AOT binary cannot include vendor |
| YAML manifest | **Native subset parser** | symfony/yaml uncompileable under TypePHP |
| Inference engine | **C library (libh3.a)** | Reuse reference implementation |
| Memory strategy | Manual SSD streaming | Auto planner has int8/SSD conflict bug |
| Render resolution | Always 256x256 internal + FFmpeg upscale | SSD streaming bug with non-256 resolutions |
| Thread safety | mutex + thread_local | Prevent data races |
| Parameter passing | Array (not 22 args) | Maintainability |
| ObjC memory | @autoreleasepool everywhere | Prevent leaks |
| Error handling | Exception-based | Testability |

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| Build exit=255 | 1 | Fixed project.yml key names + -lobjc |
| CLImate/yaml undefined | 1 | Native replacements |
| Duplicate function h3_model_load | 1 | Removed duplicate source |
| php::String::c_str() | 1 | Changed to .data() |
| Missing php_ prefix | 1 | Added prefix |
| Missing MPS frameworks | 1 | Added to linker |
| SSD+int8 conflict | 1 | Manual streaming config |
| OOM (exit 137) | 1 | Force SSD streaming |
| SSD SIGSEGV (non-256) | 1 | 256x256 + FFmpeg upscale |
| Duplicate property | 1 | Removed duplicate |
| Progress duplicate lines | 1 | In-place update |

## Test Results
```
OK (85 tests, 619 assertions)
```

## Performance Results
| Configuration | Resolution | Steps | Frames | Time |
|--------------|------------|-------|--------|------|
| Real model | 256×256 | 3 | 25 | **1:15** |
| Real model (upscaled) | 512×512 | 3 | 25 | **1:20** |
| Real model (upscaled) | 864×480 | 3 | 25 | **1:30** |

## Total Files: 85

## Unused Skeleton Classes (dead code)
These PHP classes are **no longer called** since switching to C library:
- `Encoder/Tokenizer.php`, `TextEncoder.php`, `VisionEncoder.php`
- `Inference/DiT.php`, `Sampler.php`, `Scheduler.php`
- `Inference/HybridAttention/` (all 5 files)
- `VAE/VideoVAE.php`, `AudioVAE.php`
- `Core/ProcessRunner.php`
- `Core/H3Context.php`
- `Metal/` (all 4 files - only used by tests)

**Note**: Kept for reference/testing but not in production code path.
