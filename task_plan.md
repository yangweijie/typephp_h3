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
| Cli/Application.php (CLimate init + parsing) | complete |
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
| stubs/metal_device.stub.php | complete |
| stubs/metal_buffer.stub.php | complete |
| stubs/metal_pipeline.stub.php | complete |
| stubs/metal_command_queue.stub.php | complete |
| cpp-src/metal_device.mm | complete |
| cpp-src/metal_buffer.mm | complete |
| cpp-src/metal_command_queue.mm | complete |
| cpp-src/metal_pipeline.mm | complete |
| php-src/Metal/Device.php | complete |
| php-src/Metal/Buffer.php | complete |
| php-src/Metal/Pipeline.php | complete |
| php-src/Metal/CommandQueue.php | complete |

### Phase 3: Inference Engine Core — `complete`
| Task | Status |
|------|--------|
| Encoder/Tokenizer.php (BPE + special tokens) | complete |
| Encoder/TextEncoder.php (Qwen3-VL full + ClipProj) | complete |
| Encoder/VisionEncoder.php (Qwen vision tower + deepstack) | complete |
| Inference/DiT.php (50-block + acceleration) | complete |
| Inference/Sampler.php (Euler + shifted schedule) | complete |
| Inference/Scheduler.php (AdaLN + gate scores) | complete |

### Phase 4: VAE + Output Pipeline — `complete`
| Task | Status |
|------|--------|
| VAE/VideoVAE.php (36-block tiled decoder) | complete |
| VAE/AudioVAE.php (BigVGAN audio codec) | complete |
| Core/ProcessRunner.php (ffmpeg + ffprobe + SR) | complete |

### Phase 5: Generation + Interactive Mode — `complete`
| Task | Status |
|------|--------|
| Generator/Params.php (h3_params + validation) | complete |
| Generator/Pipeline.php (6-stage orchestration) | complete |
| Generator/TextToVideo.php (FL2VA mode) | complete |
| Generator/ReferenceToVideo.php (Ref2VA mode) | complete |
| Updated main.php with generator dispatch | complete |

### Phase 6: Advanced Features — `complete`
| Task | Status |
|------|--------|
| Inference/LoRA.php (LoRA weight merging) | complete |
| config/defaults.yaml (default configuration) | complete |

### Phase 7: MSL Kernels + Tests + Build — `complete`
| Task | Status |
|------|--------|
| cpp-src/h3_dit_kernels.mm (MSL: rms_norm, adaln, qkv, attention, mlp, euler) | complete |
| cpp-src/h3_vae_kernels.mm (MSL: conv3d, upsample, bigvgan, rgb24) | complete |
| stubs/h3_kernels.stub.php + h3_vae_kernels.stub.php | complete |
| tests/Cli/OptionsTest.php + ProgressDisplayTest.php | complete |
| tests/Generator/ParamsTest.php | complete |
| tests/Inference/SamplerTest.php + SchedulerTest.php | complete |
| phpunit.xml + phpstan.neon + .php-cs-fixer.dist.php | complete |
| bin/build.php + .gitignore | complete |

### Phase 8: Code Review Fixes — `complete`
| Task | Status |
|------|--------|
| Removed unused imports from main.php | complete |
| Refactored Pipeline execute() with finally cleanup | complete |
| Added @throws PHPDoc tags to 8 methods | complete |
| Added RNG documentation in Sampler | complete |
| Refactored Application::error() to throw Exception | complete |
| Created H3Php\Cli\Exception class | complete |
| Updated CLI entry point exception handling | complete |
| Added ProcessRunnerTest + PipelineTest | complete |

### Phase 9: Performance Optimization — `complete`
| Task | Status |
|------|--------|
| Tiled Flash Attention MSL kernel | complete |
| Fused QKV + ROPE MSL kernel | complete |
| INT8 Quantized MLP MSL kernel | complete |
| Cross-Attention KV-Cache (PHP) | complete |
| Buffer Pool (PHP) | complete |

### Phase 10: VDN-H3 Research & Integration — `complete`
| Task | Status |
|------|--------|
| ModelConfig with dimension verification (hidden=5120, heads=40, etc.) | complete |
| 5 FP32 precision islands documented | complete |
| RMSNorm FP32 accumulation kernel updated | complete |
| VDN-H3 analysis in findings.md | complete |

### Phase 11: Hybrid Attention Architecture — `complete`
| Task | Status |
|------|--------|
| Inference/HybridAttention/DeltaRule.php (VdnDelta Cholesky) | complete |
| Inference/HybridAttention/FrameKDAAlpha.php (per-frame decay gate) | complete |
| Inference/HybridAttention/OutputGate.php (sigmoid branch fusion) | complete |
| Inference/HybridAttention/BidirectionalScan.php (forward/reverse scan) | complete |
| Inference/HybridAttention/HybridAttention.php (main orchestrator) | complete |
| cpp-src/h3_hybrid_kernels.mm (frame_statistics, scan_step, epilogue) | complete |
| stubs/h3_hybrid_kernels.stub.php | complete |
| tests/Inference/HybridAttention/DeltaRuleTest.php (8 tests) | complete |
| tests/Inference/HybridAttention/HybridAttentionTest.php (9 tests) | complete |

### Phase 12: Dependency Removal (CLImate + symfony/yaml) — `complete`
| Task | Status |
|------|--------|
| Diagnose build exit=255 (project.yml key names) | complete |
| Fix project.yml: cxx-flags/ld-flags + add -lobjc | complete |
| Replace CLImate with native CLI (Application.php) | complete |
| Add TTY detection for clean redirected output | complete |
| Replace symfony/yaml with native parseManifest() | complete |
| Remove league/climate from composer.json | complete |
| Remove symfony/yaml from direct require | complete |
| Update CODEBUDDY.md + README.md docs | complete |
| Binary acceptance verification (7 phases) | complete |

### Phase 13: Metal Native Layer — `complete`
| Task | Status |
|------|--------|
| Diagnose native function linking issue | complete |
| Create metal.stub.php with concrete types | complete |
| Implement metal_native.mm (ObjC Metal API + Int handles) | complete |
| Separate compilation + manual linking | complete |
| Full pipeline test (load/condition/denoise/decode/mux) | complete |

### Phase 14: C Library Integration (libh3.a) — `complete` ✅ NEW
| Task | Status |
|------|--------|
| Analyze C reference implementation API (h3.h) | complete |
| Create h3_native.mm bridge (C++ linkage, php_ prefix) | complete |
| Create php-src/h3.stub.php function declarations | complete |
| Copy h3_shaders.metal to project directory | complete |
| Update build_native.sh for h3_native.mm compilation | complete |
| Update project.yml: link libh3.a + MPS frameworks | complete |
| Rewrite Pipeline.php to use h3_model_generate() | complete |
| Update main.php --info mode to use C library | complete |
| Fix C++ linkage (php_ prefix, Int/String types) | complete |
| Fix memory config (SSD streaming, avoid int8 conflict) | complete |
| End-to-end test: real model inference → MP4 output | complete |

## Key Decisions
| Decision | Choice | Reason |
|----------|--------|--------|
| CLI framework | **Native PHP** (no CLImate) | AOT binary cannot include vendor; TTY-aware output |
| YAML manifest | **Native subset parser** | symfony/yaml uncompileable under TypePHP (see findings) |
| Vendor patching | patch.php + patches/ dir | Whole-file copy, runs on post-autoload-dump |
| Option schema | Centralized array | Single source of truth |
| C++ interop | php_ ABI + opaque Int handles | TypePHP native, GC-safe |
| Metal code | .mm Objective-C++ | TypePHP native support |
| Progress display | stderr \r updates | h3.c format compatible |
| Build output | Single executable | TypePHP bin mode |
| Error handling | Exception-based | Testability (was exit()-based) |
| Resource cleanup | finally blocks | Single cleanup exit pattern |
| Attention type | Hybrid (softmax + linear) | VDN-H3 proven architecture |
| Delta rule | VdnDelta (Cholesky) | Exact joint solve, best accuracy |
| Precision islands | 5 FP32 locations | Prevent coherent error accumulation |
| Inference engine | **C library (libh3.a)** | Reuse reference implementation for real model inference |
| Memory strategy | Manual SSD streaming | Auto planner has int8/SSD conflict bug |

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| phpunit.xml referenced non-existent tests/Core dir | 1 | Removed from config |
| Test expected false but Application::error() exited | 1 | Refactored to throw Exception |
| @throws \Never caused deprecation warning | 1 | Changed return type to void |
| Pipeline resource leak on exception | 1 | Added finally cleanup block |
| FrameKDAAlpha underflow to 0.0 | 1 | Use moderate aLog/bias in test |
| Build exit=255: Metal/objc symbols undefined | 1 | Fixed project.yml key names (cxx-flags/ld-flags) + added -lobjc |
| CLImate class undefined in AOT binary | 1 | Rewrote Application.php with native parsing + output |
| symfony/yaml class undefined in AOT binary | 1 | Wrote native parseManifest() in ModelLayout |
| symfony/yaml 16 switch-case violations under TypePHP | 1 | Patched 16 cases (merged fall-throughs, added breaks) |
| symfony/yaml variable type instability (Parser.php:359) | 1 | Abandoned — C++ gen layer also broken (see findings) |
| symfony/yaml C++ generation failures (10+ errors) | 1 | Abandoned — TypePHP cannot compile symfony/yaml |
| Duplicate function h3_model_load | 1 | Removed duplicate source entry from project.yml |
| Undefined php::String::c_str() | 1 | Changed to .data() and .length() == 0 |
| Missing php_ prefix on h3 functions | 1 | Added php_ prefix to all h3_native.mm functions |
| Missing MPS frameworks | 1 | Added MetalPerformanceShaders + MetalPerformanceShadersGraph |
| Missing -licucore | 1 | Added to linker flags for ICU symbols |
| SSD streaming + int8 conflict | 1 | Disabled auto memory plan, manual streaming config |
| OOM (exit 137) without streaming | 1 | Force enable SSD + VAE + encoder streaming |

## Test Results
```
OK (85 tests, 619 assertions)
```

## Performance Results
| Configuration | Resolution | Steps | Frames | Time |
|--------------|------------|-------|--------|------|
| Test pattern (no model) | 256×256 | 3 | 25 | <1s |
| Real model (SSD streaming) | 256×256 | 3 | 25 | **1:15** |

## Total Files: 82
