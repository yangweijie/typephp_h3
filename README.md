# H3PHP — MiniMax-H3 Video Generation Engine (PHP CLI)

A PHP CLI application that implements the complete MiniMax-H3 video generation engine, compilable to a standalone binary via [TypePHP](https://github.com/swoole/typephp) AOT compiler. Integrates the C reference implementation (`libh3.a`) for real model inference on Apple Silicon.

[中文](README_ZH.md) | English

## Features

- **Text-to-Video (FL2VA)**: Generate video from text prompts
- **Reference-to-Video (Ref2VA)**: Generate video with image/video/audio references
- **Interactive Mode**: REPL with `!` commands for parameter tuning
- **Metal GPU Acceleration**: Native Objective-C++ code for Apple Silicon
- **C Library Integration**: Links `libh3.a` for production-grade inference
- **Real Model Weights**: Loads MiniMax-H3 safetensors (21GB transformer + VAE)
- **SSD Streaming**: Memory-constrained execution (model > device memory)
- **Six-Stage Pipeline**: Load → Conditioning → DiT Denoising → Decoding → Muxing → Super-Resolution
- **Standalone Binary**: Compiled via TypePHP — no PHP runtime needed at execution time

## Requirements

- PHP 8.4+ (for development)
- TypePHP 0.6.8+ (for building)
- macOS 14+ / Apple Silicon (for Metal GPU execution)
- Xcode Command Line Tools
- FFmpeg (for video muxing)
- [libh3.a](https://github.com/...) — C reference implementation static library

## Quick Start

### Build libh3.a (C Reference Implementation)

```bash
cd /path/to/h3.c
make libh3.a
```

### Build H3PHP Binary

```bash
# Install PHP dependencies
composer install

# Build standalone binary
./build_native.sh
# or: composer run build
```

### Run

```bash
# Device + model info
./h3php -d /path/to/MiniMax-H3-Convrot --info

# One-shot generation (256×256, 3 steps)
./h3php -d /path/to/MiniMax-H3-Convrot \
    -p "A red fox walks through fresh snow in a pine forest." \
    --width 256 --height 256 --frames 25 --steps 3 \
    -o output.mp4

# Full quality (864×480, 20 steps)
./h3php -d /path/to/MiniMax-H3-Convrot \
    -p "A beautiful sunset over the ocean." \
    --width 864 --height 480 --frames 56 --steps 20 \
    -o output.mp4

# Interactive session
./h3php -d /path/to/MiniMax-H3-Convrot --width 512 --height 512 --steps 6
```

## Model Setup

### Directory Structure

```
MiniMax-H3-Convrot/
+-- FL2VA/
|   +-- transformer/
|   |   +-- config.json
|   |   +-- minimax_h3_fastvideo_4step.safetensors  (21 GB)
|   |   +-- time_embedder.safetensors  (60 MB)
|   +-- tokenizer/
|   |   +-- tokenizer.json
|   +-- video_vae/
|   |   +-- source/model.safetensors  (4.8 GB)
|   +-- audio_vae/
|       +-- model.safetensors  (577 MB)
```

### ClipProj Text Encoder (External)

Set environment variables or use defaults:
```bash
export H3_CLIPPROJ_DIR=/path/to/Qwen3-VL-4B-Instruct-int8-convrot
export H3_CLIPPROJ_PROJ=/path/to/ClipProj-MiniMax-H3
```

## Project Structure

```
typephp_h3/
├── project.yml              # TypePHP build configuration
├── build_native.sh          # Build script (compiles .mm + links libh3.a)
├── composer.json            # PHP dependencies
├── h3_shaders.metal         # Metal compute shaders (from C reference)
├── bin/
│   ├── bootstrap.php        # Autoloader + constants
│   └── h3php.php           # CLI entry point
├── php-src/                 # PHP business logic
│   ├── main.php            # Main orchestration
│   ├── h3.stub.php         # C library bridge stubs
│   ├── metal.stub.php      # Metal native function stubs
│   ├── Cli/                # CLI framework
│   │   ├── Application.php # Native CLI (argument parsing + styled output)
│   │   ├── Options.php     # Centralized option schema
│   │   ├── InteractiveSession.php  # REPL mode
│   │   └── ProgressDisplay.php     # Progress rendering
│   ├── Core/               # Engine core
│   │   ├── H3Context.php   # Engine lifecycle
│   │   ├── ModelLoader.php # Model validation
│   │   ├── ModelLayout.php # Manifest parsing
│   │   └── ProcessRunner.php  # FFmpeg + external tools
│   ├── Generator/          # Generation pipelines
│   │   ├── Pipeline.php    # 6-stage orchestration (C library bridge)
│   │   ├── TextToVideo.php # FL2VA mode
│   │   ├── ReferenceToVideo.php  # Ref2VA mode
│   │   └── Params.php      # Parameter validation
│   ├── Encoder/            # Text/vision encoders (PHP skeleton)
│   ├── Inference/          # DiT + sampling (PHP skeleton)
│   ├── VAE/                # Video/audio VAE (PHP skeleton)
│   └── Metal/              # Metal GPU wrappers
├── cpp-src/                 # Objective-C++ native layer
│   ├── metal_native.mm     # Metal device/buffer/pipeline (ObjC++)
│   ├── h3_native.mm        # C library bridge (libh3.a wrapper)
│   └── metal_native.o      # Compiled object (pre-built)
├── config/
│   └── defaults.yaml        # Default configuration
└── tests/                   # PHPUnit tests (85 tests, 619 assertions)
```

## CLI Usage

```
h3php -d MODEL_DIR -p "prompt" [options]     # One-shot generation
h3php -d MODEL_DIR [options]                  # Interactive session
h3php -d MODEL_DIR --info                     # Device + model info
h3php --help                                  # Show usage
```

### Key Options

| Flag | Default | Description |
|------|---------|-------------|
| `-d PATH` | — | Model directory (required) |
| `-p TEXT` | — | Prompt (triggers one-shot mode) |
| `-o PATH` | outputs/h3.mp4 | Output MP4 path |
| `--width N` | 864 | Output width (multiple of 32) |
| `--height N` | 480 | Output height (multiple of 32) |
| `--frames N` | 56 | Frame count (22-362) |
| `--steps N` | 20 | Denoising steps (1-1000) |
| `--reuse N` | 1 | Denoiser reuse (1=quality, 3=fast) |
| `--layers N` | 50 | DiT blocks (50=exact, 40=fast) |
| `--core-reuse N` | 1 | Core refresh interval |
| `--seed N` | 42 | Random seed |
| `--ssd-streaming` | — | Enable SSD weight streaming |
| `--sr` | — | Enable super-resolution |
| `--info` | — | Device + model info |

## Interactive Commands

| Command | Description |
|---------|-------------|
| `!help` | Show all commands |
| `!status` | Show current settings |
| `!seed [N\|random]` | Set/show seed |
| `!steps [N]` | Denoising steps (1-1000) |
| `!reuse [N]` | Denoiser reuse (1-32) |
| `!layers [N]` | DiT blocks (35-50) |
| `!size [WxH]` | Output size |
| `!frames [N]` | Frame count |
| `!seconds [N]` | Duration at 24fps |
| `!token-reduction [on\|off]` | Toggle token reduction |
| `!ssd-streaming [on\|off]` | Toggle SSD streaming |
| `!first [PATH\|clear]` | First frame conditioning |
| `!last [PATH\|clear]` | Last frame conditioning |
| `!ref-image PATH` | Add image reference |
| `!refs [clear]` | List/clear references |
| `!again` | Repeat last prompt |
| `!cache [clear]` | Show/clear cache |
| `!memory-plan [auto\|off]` | Memory plan |
| `!quit` | Exit session |

## Architecture

### Build Pipeline

```
PHP sources + .mm Objective-C++ sources
        ↓
TypePHP AOT Compiler (nikic/php-parser → C++17)
        ↓
Clang (Metal frameworks) + libh3.a + object caches
        ↓
Standalone executable (embedded PHP runtime)
```

### Generation Pipeline (Six Stages)

1. **Load**: Load model via `h3_load_dir()` — validates structure, probes Metal device
2. **Conditioning**: Tokenize + encode text (Qwen3-VL-4B via ClipProj)
3. **DiT Denoising**: 50-block diffusion transformer on Metal GPU (Euler steps)
4. **Decoding**: Video VAE (tiled CNN) → RGB frames + Audio VAE → PCM
5. **Muxing**: FFmpeg H.264 + AAC → MP4
6. **Super-Resolution**: Optional Real-ESRGAN upscaling

### C Library Bridge

```
PHP Pipeline.php → h3_model_load/generate/free()
        ↓
cpp-src/h3_native.mm (ObjC++ bridge, C++ linkage)
        ↓
libh3.a (C reference implementation)
        ├── h3.c — Main inference loop
        ├── h3_gpu.m — Metal command encoding
        ├── h3_safetensors.c — Weight loading
        ├── h3_dit.c — DiT forward pass
        ├── h3_video_vae.c — VAE decode
        └── h3_ffmpeg.c — FFmpeg muxing
```

### Memory Management

| Component | Size | Streaming |
|-----------|------|-----------|
| Transformer (50 blocks) | ~21 GB | SSD streaming (2 blocks resident) |
| Video VAE | ~4.8 GB | Weight streaming |
| Audio VAE | ~577 MB | Full resident |
| Text Encoder (ClipProj) | ~4.6 GB | Released after conditioning |
| **Peak (M4 16GB)** | **~2 GB** | ✅ Fits unified memory |

### C++ Interop

- **PHP → C++**: `php_` prefix functions declared in `.stub.php` files
- **C++ → PHP**: `php::call()` for callbacks (progress, frame delivery)
- **Object lifetime**: Opaque `Int` handles (Metal) + handle table (C library)
- **String handling**: `php::String.data()` for `const char*` access

## Performance

| Device | Resolution | Steps | Frames | Time | FPS |
|--------|------------|-------|--------|------|-----|
| Apple M4 (16GB) | 256×256 | 3 | 25 | 1:15 | ~0.3 |
| Apple M4 (16GB) | 256×256 | 20 | 25 | ~10min | ~0.04 |
| Apple M4 (16GB) | 864×480 | 20 | 56 | ~30min | ~0.03 |

*Bottleneck: SSD weight streaming I/O + text encoding*

## Implementation Phases

| Phase | Status | Description |
|-------|--------|-------------|
| 1 | ✅ | Project skeleton + CLI framework |
| 2 | ✅ | Metal GPU foundation |
| 3 | ✅ | Inference engine core (DiT, encoders) |
| 4 | ✅ | VAE + output pipeline |
| 5 | ✅ | Generation + interactive mode |
| 6 | ✅ | Advanced features (LoRA, SR, optimization) |
| 7 | ✅ | MSL kernels + tests + build |
| 8 | ✅ | Code review fixes |
| 9 | ✅ | Performance optimization |
| 10 | ✅ | VDN-H3 research & integration |
| 11 | ✅ | Hybrid attention architecture |
| 12 | ✅ | Dependency removal (CLImate + symfony/yaml) |
| 13 | ✅ | Metal native layer |
| 14 | ✅ | C library integration (libh3.a) |

**Total: 14 phases, 85 tests, 619 assertions**

## References

- [TypePHP](https://github.com/swoole/typephp) — PHP AOT compiler
- [php-metal-gpu](https://github.com/phpolygon/php-metal-gpu) — PHP Metal GPU extension
- [h3.c](https://github.com/...) — MiniMax-H3 C reference implementation
- [MiniMax-H3](https://github.com/MiniMaxAI) — Original model
- [OpenVDN](https://github.com/...) — Open-source VDN-H3 implementation

## License

MIT
