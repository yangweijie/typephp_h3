# H3PHP — MiniMax-H3 Video Generation Engine (PHP CLI)

A PHP CLI application that implements the complete MiniMax-H3 video generation engine, compilable to a standalone binary via [TypePHP](https://github.com/swoole/typephp) AOT compiler.

## Features

- **Text-to-Video (FL2VA)**: Generate video from text prompts
- **Reference-to-Video (Ref2VA)**: Generate video with image/video/audio references
- **Interactive Mode**: REPL with `!` commands for parameter tuning
- **Metal GPU Acceleration**: Native Objective-C++ code for Apple Silicon
- **Six-Stage Pipeline**: Load → Conditioning → DiT Denoising → Decoding → Muxing → Super-Resolution
- **Standalone Binary**: Compiled via TypePHP — no PHP runtime needed at execution time

## Requirements

- PHP 8.4+ (for development)
- TypePHP 0.6.8+ (for building)
- macOS 14+ / Apple Silicon (for Metal GPU execution)
- Xcode Command Line Tools
- FFmpeg (for video muxing)

## Quick Start

### Development Mode (with PHP interpreter)

```bash
# Install dependencies
composer install

# Show help
php bin/h3php.php --info -d /path/to/MiniMax-H3

# One-shot generation
php bin/h3php.php -d /path/to/MiniMax-H3 \
    -p "A red fox walks through fresh snow in a pine forest." \
    --width 512 --height 512 --frames 22 --steps 20 \
    -o outputs/fox.mp4

# Interactive session
php bin/h3php.php -d /path/to/MiniMax-H3 --width 512 --height 512 --steps 6
```

### Build Standalone Binary

```bash
# Compile to standalone binary via TypePHP
composer run build
# or directly:
tpc -j8 -m bin -o h3php project.yml

# Run the compiled binary
./h3php -d /path/to/MiniMax-H3 -p "A beautiful sunset over the ocean."
```

## Project Structure

```
typephp_h3/
├── project.yml              # TypePHP build configuration
├── composer.json            # PHP dependencies
├── bin/
│   ├── bootstrap.php        # Autoloader + constants
│   └── h3php.php           # CLI entry point
├── php-src/                 # PHP business logic
│   ├── main.php            # Main orchestration
│   ├── Cli/                # CLI framework
│   │   ├── Application.php # Native CLI (argument parsing + styled output)
│   │   ├── Options.php     # Centralized option schema
│   │   ├── InteractiveSession.php  # REPL mode
│   │   └── ProgressDisplay.php     # Progress rendering
│   ├── Core/               # Engine core
│   │   ├── H3Context.php   # Engine lifecycle
│   │   └── ModelLoader.php # Model validation
│   ├── Generator/          # Generation pipelines
│   ├── Encoder/            # Text/vision encoders
│   ├── Inference/          # DiT + sampling
│   ├── VAE/                # Video/audio VAE
│   └── Metal/              # Metal GPU wrappers
├── cpp-src/                 # Objective-C++ native layer
│   ├── metal_device.mm     # Metal device
│   ├── metal_buffer.mm     # Metal buffers
│   ├── metal_pipeline.mm   # Compute pipelines
│   └── h3_dit_kernels.mm   # DiT Metal kernels
├── stubs/                   # PHP stubs for native functions
└── config/
    └── defaults.yaml        # Default configuration
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
| `--width N` | 864 | Output width |
| `--height N` | 480 | Output height |
| `--frames N` | 56 | Frame count |
| `--steps N` | 20 | Denoising steps |
| `--reuse N` | 1 | Denoiser reuse (1=quality, 3=fast) |
| `--layers N` | 50 | DiT blocks (50=exact, 40=fast) |
| `--core-reuse N` | 1 | Core refresh interval |
| `--seed N` | 42 | Random seed |
| `--profile` | — | Show timing info |
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
Clang (Metal frameworks) + object caches
        ↓
Standalone executable (embedded PHP runtime)
```

### Generation Pipeline (Six Stages)

1. **Load**: Validate model, parse safetensors headers, probe Metal device
2. **Conditioning**: Tokenize, encode text (Qwen3-VL), vision (Qwen tower), audio
3. **DiT Denoising**: 50-block diffusion transformer, 20 Euler steps
4. **Decoding**: Video VAE (tiled) + Audio VAE (BigVGAN)
5. **Muxing**: FFmpeg H.264 + AAC → MP4
6. **Super-Resolution**: Optional Real-ESRGAN upscaling

### C++ Interop

- **PHP → C++**: `php_` prefix functions declared in `.stub.php` files
- **C++ → PHP**: `php::call()` for callbacks (progress, frame delivery)
- **Object lifetime**: `php::Box` subclasses for GC-managed Metal objects

## Implementation Phases

| Phase | Status | Description |
|-------|--------|-------------|
| 1 | ✅ | Project skeleton + CLI framework |
| 2 | ⏳ | Metal GPU foundation |
| 3 | ⏳ | Inference engine core (DiT, encoders) |
| 4 | ⏳ | VAE + output pipeline |
| 5 | ⏳ | Generation + interactive mode |
| 6 | ⏳ | Advanced features (LoRA, SR, optimization) |

## References

- [TypePHP](https://github.com/swoole/typephp) — PHP AOT compiler
- [php-metal-gpu](https://github.com/phpolygon/php-metal-gpu) — PHP Metal GPU extension
- [h3.c](https://github.com/...) — MiniMax-H3 C reference implementation
- [MiniMax-H3](https://github.com/MiniMaxAI) — Original model

## License

MIT
