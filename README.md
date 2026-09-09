# H3PHP — MiniMax-H3 Video Generation Engine (PHP CLI)

A PHP CLI application that implements the complete MiniMax-H3 video generation engine, compilable to a standalone binary via [TypePHP](https://github.com/swoole/typephp) AOT compiler. Integrates the C reference implementation (`libh3.a`) for real model inference on Apple Silicon.

[中文](README_ZH.md) | English

## Features

- **Text-to-Video (FL2VA)**: Generate video from text prompts
- **Reference-to-Video (Ref2VA)**: Generate video with image/video/audio references
- **Interactive Mode**: REPL with `!` commands for parameter tuning
- **Cross-Platform**: macOS (Metal), Windows (CUDA/ComfyUI), Linux (CUDA/ROCm/ComfyUI)
- **Qt 6 GUI**: Cross-platform graphical interface with node editor
- **Metal GPU Acceleration**: Native Objective-C++ code for Apple Silicon
- **CUDA/ROCm Support**: ComfyUI backend for NVIDIA/AMD GPUs
- **C Library Integration**: Links `libh3.a` for production-grade inference
- **Real Model Weights**: Loads MiniMax-H3 safetensors (21GB transformer + VAE)
- **SSD Streaming**: Memory-constrained execution (model > device memory)
- **Six-Stage Pipeline**: Load → Conditioning → DiT Denoising → Decoding → Muxing → Super-Resolution
- **Standalone Binary**: Compiled via TypePHP — no PHP runtime needed at execution time
- **ModelScope Download**: Full repo download via CLI/Python SDK/Git LFS with resume
- **Hardware Recommendations**: Auto-detect hardware and suggest optimal model preset
- **Download Presets**: Minimal / Standard / Full configurations for different hardware
- **Multi-Source Downloads**: HuggingFace, ModelScope, CivitAI, GitHub with mirror support
- **Xget Acceleration**: Cloudflare Worker proxy for fast downloads in China

## Requirements

### All Platforms
- PHP 8.5+ (for development / interpreted mode)
- TypePHP AOT compiler (`tpc`) — installed via Composer
- FFmpeg (for video muxing)
- 8GB+ RAM (16GB+ recommended)
- 60GB+ free disk space (models)

### macOS (Native H3 Backend)
- macOS 14+ (Apple Silicon recommended)
- Xcode Command Line Tools
- [libh3.a](https://github.com/...) — C reference implementation static library

### Windows (ComfyUI Backend)
- Windows 10/11 (64-bit)
- Visual Studio 2022 (MSVC v143) or Build Tools
- Qt 6.8.0 (msvc2022_64)
- Python 3.10+ (for ComfyUI backend)
- CUDA Toolkit 12+ (for NVIDIA GPUs)

### Linux (ComfyUI Backend)
- Ubuntu 22.04+ / Fedora 38+
- gcc 11+ (C++17 support)
- Qt 6 development packages (`qt6-base-dev`)
- Python 3.10+ (for ComfyUI backend)
- CUDA Toolkit or ROCm (for GPU acceleration)

## Quick Start

### Development Mode (No Build Required)

```bash
# Install PHP dependencies
composer install

# Run directly with PHP interpreter
php bin/h3php.php -d /path/to/MiniMax-H3 --info

# One-shot generation
php bin/h3php.php -d /path/to/MiniMax-H3 \
    -p "A red fox walks through fresh snow." \
    --width 256 --height 256 --frames 25 --steps 3 \
    -o output.mp4
```

### Build Standalone Binary

#### macOS

```bash
# 1. Build libh3.a (C reference implementation)
cd /path/to/h3.c
make libh3.a

# 2. Install dependencies
composer install

# 3. Build standalone binary
./build_native.sh /path/to/h3.c
# or: H3_C_DIR=/path/to/h3.c composer run build

# 4. Run
./h3php -d /path/to/MiniMax-H3 --info
```

#### Windows

```bat
:: 1. Install dependencies
composer install

:: 2. Build standalone binary (Qt + MSVC)
build_windows.bat

:: With H3 C library:
build_windows.bat C:\path\to\h3.c

:: 3. Run
h3php.exe -d C:\path\to\MiniMax-H3 --info
```

**Windows Environment Variables:**
| Variable | Default | Description |
|----------|---------|-------------|
| `QT_DIR` | `C:\Qt\6.8.0\msvc2022_64` | Qt installation path |
| `H3_C_DIR` | — | Path to H3 C library (optional) |

#### Linux

```bash
# 1. Install dependencies
composer install

# 2. Build standalone binary (Qt + gcc)
chmod +x build_linux.sh
./build_linux.sh

# With H3 C library:
./build_linux.sh /path/to/h3.c

# 3. Run
./h3php -d /path/to/MiniMax-H3 --info
```

**Linux Environment Variables:**
| Variable | Default | Description |
|----------|---------|-------------|
| `QT_DIR` | `/usr` | Qt installation path |
| `H3_C_DIR` | — | Path to H3 C library (optional) |

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
├── build_native.sh          # macOS build script (Metal + libh3.a)
├── build_windows.bat        # Windows build script (Qt + MSVC)
├── build_linux.sh           # Linux build script (Qt + gcc)
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
│   │   ├── ProcessRunner.php  # FFmpeg + external tools
│   │   ├── DownloadManager.php  # Unified download orchestrator
│   │   ├── DownloadQueue.php    # Multi-connection concurrent downloads
│   │   ├── DownloadTask.php     # Single download task (resume + retry)
│   │   ├── ModelScopeDownloader.php  # ModelScope CLI/SDK/Git LFS
│   │   ├── ModelRecommender.php  # Hardware-based model recommendations
│   │   ├── DownloadPreset.php    # Model download presets
│   │   ├── ModelComponent.php    # Component metadata + validation
│   │   ├── EnvironmentDetector.php  # Hardware/software detection
│   │   ├── SettingsManager.php   # Persistent settings
│   │   └── ThemeManager.php      # UI theme management
│   ├── Generator/          # Generation pipelines
│   │   ├── Pipeline.php    # 6-stage orchestration
│   │   ├── TextToVideo.php # FL2VA mode
│   │   ├── ReferenceToVideo.php  # Ref2VA mode
│   │   └── Params.php      # Parameter validation
│   ├── Encoder/            # Text/vision encoders
│   ├── Inference/          # DiT + sampling
│   ├── VAE/                # Video/audio VAE
│   ├── Metal/              # Metal GPU wrappers
│   ├── Qt/                 # Qt GUI wrappers
│   └── Testing/            # Test helpers (excluded from build)
├── cpp-src/                 # C++ native layer
│   ├── metal_native.mm     # Metal device/buffer/pipeline (ObjC++)
│   ├── h3_native.mm        # C library bridge (libh3.a wrapper)
│   ├── qt_bridge.cc        # Qt ↔ PHP bridge (opaque handles)
│   └── qt_node_editor.cc   # Node editor (QGraphicsView)
├── python-src/              # Python bridge scripts
│   ├── comfyui_bridge.py    # ComfyUI JSON protocol bridge
│   └── modelscope_bridge.py # ModelScope download bridge
├── stubs/                   # FFI stub declarations
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
PHP sources + C++/ObjC++ sources
        ↓
TypePHP AOT Compiler (nikic/php-parser → C++17)
        ↓
┌─────────────────────────────────────────────┐
│ Platform-specific linker:                    │
│   macOS: Clang + Metal + libh3.a            │
│   Windows: MSVC + Qt 6 + CUDA (optional)    │
│   Linux: gcc + Qt 6 + CUDA/ROCm (optional)  │
└─────────────────────────────────────────────┘
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

## Download & Model Management

### Model Download Sources

| Source | Method | LFS | Resume | Speed |
|--------|--------|-----|--------|-------|
| ModelScope CLI | `modelscope download` | ✅ | ✅ | Fast |
| ModelScope SDK | `snapshot_download()` | ✅ | ✅ | Fast |
| Git LFS | `git clone` | ✅ | ✅ | Medium |
| HuggingFace | Direct HTTP | ❌ | ✅ | Varies |

### Download Presets

| Preset | VRAM | RAM | Size | Audio | Use Case |
|--------|------|-----|------|-------|----------|
| Minimal | 4GB | 8GB | ~25GB | ❌ | Basic text-to-video |
| Standard | 8GB | 16GB | ~26GB | ❌ | + Image references |
| Full | 16GB | 32GB | ~26GB | ✅ | + Audio synthesis |

### ModelScope Download

```bash
# Install ModelScope CLI (recommended)
pip install modelscope

# Download a model repo
modelscope download --model="Qwen/Qwen2.5-0.5B-Instruct" --local_dir ./model-dir

# Or use Python SDK
python -c "from modelscope import snapshot_download; snapshot_download('Qwen/Qwen2.5-0.5B-Instruct')"

# Or use Git LFS
git lfs install
git clone https://www.modelscope.cn/Qwen/Qwen2.5-0.5B-Instruct.git
```

### Xget Mirror (China Acceleration)

For users in China, enable Xget Cloudflare Worker mirror for faster downloads:

```php
// In your PHP code
$dm = new DownloadManager();
$dm->useXgetMirror();  // Uses https://xget.dev/hf-mirror for HuggingFace
```

Direct Xget CLI usage:
```bash
# HuggingFace via Cloudflare
xget hf://models/Qwen/Qwen3-VL-2B

# ModelScope via Cloudflare
xget ms://models/Qwen/Qwen3-VL-2B
```

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
| 18 | ✅ | Backend abstraction layer (Native H3 / ComfyUI / HTTP) |
| 19 | ✅ | Python FFI integration |
| 20 | ✅ | Qt GUI foundation |
| 21 | ✅ | Node editor (QGraphicsView) |
| 22 | ✅ | ComfyUI workflow integration |
| 23 | ✅ | Cross-platform build system |
| 24 | ✅ | Model Manager (unified discovery) |
| 25 | ✅ | Download Manager (multi-source) |
| 26 | ✅ | Environment detection & setup wizard |
| 27 | ✅ | Model Manager UI |
| 28 | ✅ | Testing & documentation |
| 29 | ✅ | Download optimization (ModelScope CLI/SDK + recommendations) |

**Total: 29 phases, 85 tests, 619 assertions**

## References

- [TypePHP](https://github.com/swoole/typephp) — PHP AOT compiler
- [php-metal-gpu](https://github.com/phpolygon/php-metal-gpu) — PHP Metal GPU extension
- [h3.c](https://github.com/...) — MiniMax-H3 C reference implementation
- [MiniMax-H3](https://github.com/MiniMaxAI) — Original model
- [OpenVDN](https://github.com/...) — Open-source VDN-H3 implementation
- [ModelScope](https://modelscope.cn) — Model repository with CLI/SDK download tools
- [ComfyUI](https://github.com/comfyanonymous/ComfyUI) — Cross-platform diffusion GUI
- [Xget](https://github.com/xget-dev/xget) — Cloudflare Worker download accelerator
- [transformers-torch-php](https://github.com/SyncFly/transformers-torch-php) — PHP bridge for transformer model downloads

## License

MIT
