# CODEBUDDY.md This file provides guidance to CodeBuddy when working with code in this repository.

## Commands

### Setup & Dependencies
Install PHP dependencies (symfony/yaml is pulled in transitively via swoole/typephp; phpunit, phpstan, php-cs-fixer):
```bash
composer install
```
Requires PHP 8.4+, and `tpc` (TypePHP AOT compiler) on PATH for builds. Metal GPU execution requires macOS Apple Silicon + Xcode Command Line Tools + FFmpeg.

### Lint / Static Analysis
Run PHPStan (level 5) over `php-src` and `bin`:
```bash
composer run analyse
```

### Code Style
Check style (PSR-12 + Symfony rules) without modifying:
```bash
composer run cs-check
```
Auto-fix:
```bash
composer run cs-fix
```

### Tests
Run the full PHPUnit suite:
```bash
composer run test
```
Run a single test file:
```bash
phpunit --configuration=phpunit.xml tests/Generator/ParamsTest.php
```
Run a single test method by name filter:
```bash
phpunit --configuration=phpunit.xml --filter testInvalidWidth tests/Generator/ParamsTest.php
```
Tests are PHPUnit `TestCase` subclasses under `tests/` with namespace `H3Php\Tests\…`. Pure-PHP tests need no GPU.

### Build Standalone Binary
Compile PHP + Objective-C++ sources into a standalone executable via TypePHP (no PHP runtime at runtime):
```bash
composer run build      # equivalent to: tpc -j8 -m bin -o h3php project.yml
```

### Run (Development Mode)
Run with the PHP interpreter (no compile needed):
```bash
php bin/h3php.php -d /path/to/MiniMax-H3 -p "a red fox in snow" --width 512 --height 512 --frames 22 --steps 20 -o outputs/fox.mp4
php bin/h3php.php -d /path/to/MiniMax-H3 --info     # device + model inventory, no weights
```
When components live on different disks (e.g. heavy weights on an SSD, small files on the system disk), point each at its real path with a YAML manifest instead of rearranging the directory tree:
```bash
php bin/h3php.php -d /path/to/MiniMax-H3 --model-manifest model_manifest.yaml -p "a red fox"
```
Manifest keys (`fl2va` / `ref2va`, each with `transformer`/`tokenizer`/`text_encoder`/`video_vae`/`audio_vae`) take absolute or model-dir-relative paths; omitted keys fall back to `MODEL_DIR/<STREAM>/<component>`. Parsed by `H3Php\Core\ModelLayout`.
For the compiled binary, replace `php bin/h3php.php` with `./h3php`. `-d` (model dir) is required; `-p` triggers one-shot mode, otherwise interactive REPL.

## Architecture

H3PHP is a PHP CLI reimplementation of the MiniMax-H3 video generation engine. Its defining trait is the **dual execution model**: the same `php-src/` logic runs either interpreted under PHP (`bin/h3php.php`) for development, or compiled ahead-of-time into a standalone macOS binary via the TypePHP AOT compiler (PHP → C++17 → clang, `project.yml` defines sources/ignore list). `bin/bootstrap.php` abstracts this with a dual autoloader (composer vendor vs. a minimal PSR-4 fallback), plus platform/version constants.

**CLI layer (`php-src/Cli/`).** `Application` handles argument parsing and styled output natively (no `league/climate` — it is not compiled into the standalone binary). The single source of truth for every flag is `Options::ALL` in `Options.php` — this one schema drives argument registration, `--help` rendering, and validation. `main.php` dispatches into four modes (`help`, `info`, `oneshot`, `interactive`) via `Application::getMode()`. Errors are *thrown* (`Cli\Exception`) rather than `exit()`'d, so the entry point (`bin/h3php.php`) can assert behavior in tests; it catches and translates the exception to an exit code.

**Generation orchestration (`php-src/Generator/`).** `main.php` builds a `Params` (equivalent to `h3_params` in h3.c) via `Params::fromApplication()`, then selects `TextToVideo` or `ReferenceToVideo` based on whether references exist. Both delegate to `Pipeline::execute()`, which runs the six stages — Load, Conditioning, DiT Denoising, Decoding, Muxing, Super-Resolution — using the **single cleanup-exit pattern** from h3.c (resources nulled up front, freed in `finally`). `Params` centralizes all generation settings and enforces constraints in `validate()` (canvas multiples of 32, frame bounds 22–362, layer bounds 35–50) and frame alignment via `alignFrames()` (`5 + 17·n`).

**Engine lifecycle (`php-src/Core/`).** `H3Context` (equivalent to `h3_ctx`) owns the Metal device reference, model directory, model config, validation state, and memory plan. `ModelLoader` scans/validates the on-disk model tree and reads `transformer/config.json`. `ProcessRunner` wraps external processes (FFmpeg, Real-ESRGAN). `H3Context` methods are mostly placeholders pending native integration, but define the lifecycle contract all stages depend on.

**Native GPU interop (the core cross-language contract).** PHP classes call free functions prefixed `h3_` (e.g. `h3_metal_device_create`). These are *declared* in `stubs/*.stub.php` and *implemented* in `cpp-src/*.mm` (Objective-C++) using the `phpx.h` ABI (`php_h3_*` functions). The `.mm` files wrap MTL objects in `php::Box` subclasses so the PHP GC manages native lifetime; C++ reaches back into PHP via `php::call()` (progress/frame callbacks). `php-src/Metal/` (`Device`, `Buffer`, `BufferPool`, `CommandQueue`, `Pipeline`) are thin PHP wrappers over these stubs. Keep stub signatures and `.mm` implementations in sync — mismatches surface only at compile/runtime, not in the IDE. PHPStan is configured (`phpstan.neon`) to ignore `h3_*` and `Metal\*` "function not found" errors, and `php-src/Testing` is excluded from analysis and the build.

**Domain stages.** `Encoder/` (Tokenizer, TextEncoder for Qwen3-VL, VisionEncoder, audio) produces conditioning embeddings. `Inference/` is the diffusion core: `DiT` (50-block transformer), `Sampler`/`Scheduler` (Euler steps), `KVCache`, `LoRA`, and `HybridAttention/` (frame-wise delta-rule attention). `VAE/` decodes latents to pixels (`VideoVAE`, tiled) and audio (`AudioVAE`, BigVGAN). Much of the compute-heavy DiT/VAE logic is currently stubbed with `TODO`s — `Pipeline` advances progress markers but skips real tensor work — so the architecture is a working skeleton with the native/Metal path partially wired (device query works) and the denoising/decode path to be filled in.

**Mental model for edits:** flags flow `Options` → `Params` → `Pipeline` stages; GPU work crosses the PHP↔`.mm` boundary through `h3_*` stubs; tests live mirroring `php-src` under `tests/` with `H3Php\Tests\…` namespaces. When adding a CLI flag, update `Options::ALL` and `getCategories()`, then `Params` fields and `fromApplication()`. When adding native capability, add the stub, the `.mm` impl, and a PHP wrapper together.
