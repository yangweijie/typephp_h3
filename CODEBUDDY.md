# CODEBUDDY.md This file provides guidance to CodeBuddy when working with code in this repository.

## Commands

### Setup & Dependencies
```bash
composer install
```
Requires PHP 8.5+, and `tpc` (TypePHP AOT compiler) on PATH for builds. `post-autoload-dump` runs `patch.php`, which copies `patches/` into `vendor/`. Metal GPU execution needs macOS Apple Silicon + Xcode CLT + FFmpeg; Windows/Linux use the ComfyUI or HTTP backend instead.

### Tests
Suite is **Pest** (class-based tests extending `PHPUnit\Framework\TestCase`, configured via `phpunit.xml`):
```bash
composer run test                                  # full suite (pest)
vendor/bin/pest tests/Generator/ParamsTest.php     # single file
vendor/bin/pest --filter=testInvalidWidth tests/Generator/ParamsTest.php
H3_MODEL_DIR=/path/to/MiniMax-H3 vendor/bin/pest   # enable tests needing real weights
```
Tests mirror `php-src` under `tests/` in the `H3Php\Tests\…` namespace. Weights are not vendored: tests needing a model tree must call `skip_without_model_dir()` / `markTestSkipped` when `H3_MODEL_DIR` is unset (helper in `tests/Pest.php`).

### Lint / Static Analysis
```bash
composer run analyse    # PHPStan level 5 over php-src + bin
```
Requires `phpstan/phpstan` (not in `require-dev` by default: `composer require --dev phpstan/phpstan`). `phpstan.neon` excludes the stub files (they only declare the native ABI) and ignores `h3_*` / `qt_*` "function not found" (implemented in `cpp-src/`). 18 pre-existing findings are frozen in `phpstan-baseline.neon`, so the command must stay at "No errors" — new findings fail the gate.

### Code Style
```bash
composer run cs-check   # PSR-12 + Symfony, dry-run
composer run cs-fix     # auto-fix
```

### Build Standalone Binary
```bash
composer run build            # macOS: build_native.sh (Metal + libh3.a)
composer run build:windows    # build_windows.bat (MSVC + Qt 6)
composer run build:linux      # build_linux.sh (gcc + Qt 6)
./build_native.sh /path/to/h3.c        # or H3_C_DIR=... composer run build
build_windows.bat C:\path\to\h3.c
```
`QT_DIR` defaults: macOS frameworks, Windows `D:\tools\Qt\6.9.3\msvc2022_64` (set `QT_DIR` to override), Linux `/usr`. `build_windows.bat` regenerates `project_windows.yml` from `QT_DIR`, then runs `windeployqt` to place Qt DLLs next to `h3php.exe`.

### Run (Development Mode)
```bash
php bin/h3php.php -d /path/to/MiniMax-H3 --info          # device + model inventory
php bin/h3php.php -d /path/to/MiniMax-H3 -p "a red fox in snow" --width 512 --height 512 --frames 22 --steps 20 -o outputs/fox.mp4
php bin/h3php.php --gui                                  # Qt GUI mode
php bin/h3php.php -d /path/to/MiniMax-H3 --model-manifest model_manifest.yaml -p "a red fox"
```
For the compiled binary replace `php bin/h3php.php` with `./h3php` (`h3php.exe`). `-d` is required except for `--help`/`--gui`; `-p` triggers one-shot mode, otherwise interactive REPL. The manifest (`fl2va`/`ref2va` → `transformer`/`tokenizer`/`text_encoder`/`video_vae`/`audio_vae`) lets components live on different disks; omitted keys fall back to `MODEL_DIR/<STREAM>/<component>` (parsed by `H3Php\Core\ModelLayout`).

### Windows binary runtime note
`cpp-src/php_ini_auto.cc` sets `php_ini_path_override` before `main()` so the exe loads `php.ini` from its own directory; without it PHP embed defaults `extension_dir` to a non-existent `C:\php\ext`. `php.ini` must point `extension_dir` at a real `ext/` (copy the tpc package's `ext/` next to the exe and use `ext`).

## Architecture

H3PHP is a PHP reimplementation of the MiniMax-H3 video generation engine. Its defining trait is the **dual execution model**: the same `php-src/` logic either runs interpreted under PHP (`bin/h3php.php`) for development, or is compiled ahead-of-time into a standalone binary by the TypePHP AOT compiler (PHP → C++17 → clang/MSVC/gcc). `bin/bootstrap.php` provides a dual autoloader (composer vendor vs. a minimal PSR-4 fallback) plus platform constants; `php-src/main.php` redefines those constants for bin builds, since bootstrap is not compiled.

**Build surface (important, non-obvious).** `project.yml` / `project_windows.yml` list `sources: php-src, cpp-src` only. Anything outside those two trees — notably the top-level `stubs/` directory — is **not compiled**. Stub declarations that the binary actually needs live inside `php-src/` (`h3.stub.php`, `metal.stub.php`, `qt_bridge.stub.php`, `qt_node_editor.stub.php`, `comfyui.stub.php`); `stubs/` is reference/archive. The Windows build also `ignore`s every `.mm` file (Metal is macOS-only) and links Qt `.lib`s directly; the macOS build compiles `.mm` directly (no manual `.o` step) and links Metal/Qt frameworks. `php-src/Testing` is excluded from both analysis and build.

**Entry & CLI layer (`php-src/Cli/`).** `Application` does argument parsing and styled output natively (no `league/climate` — it would not be compiled in). `Options::ALL` is the single source of truth for every flag: it drives registration, `--help` rendering (`getCategories()`), and validation. `main()` in `php-src/main.php` dispatches on `Application::getMode()` into `help`, `error-no-model`, `info`, `oneshot`, `search`, `download-preset`, `interactive`, and `gui`. Errors are *thrown* (`Cli\Exception`) rather than `exit()`ed, so tests can assert them; `bin/h3php.php` translates them to exit codes.

**Backend abstraction (`php-src/Core/`).** Platform differences are isolated behind `BackendInterface` with three implementations selected by `BackendType` through `BackendFactory`: `NativeH3Backend` (macOS/Metal + `libh3.a`), `ComfyUIBackend` (drives a local ComfyUI via `python-src/comfyui_bridge.py`, with `ComfyUISearcher`/`ComfyUIProcessManager`/`ComfyUISerializer`), and `HttpBackend` (remote API). Adding a backend means implementing the interface, adding an enum case, and a factory branch — no changes in the CLI or GUI.

**Generation orchestration (`php-src/Generator/`).** `Options` → `Params::fromApplication()` → `TextToVideo` or `ReferenceToVideo` (chosen by whether references exist) → `Pipeline::execute()`, which runs six stages: Load, Conditioning, DiT Denoising, Decoding, Muxing, Super-Resolution. `Pipeline` follows the single cleanup-exit pattern from h3.c (resources nulled up front, freed in `finally`). `Params` centralizes settings and enforces constraints in `validate()` (canvas multiples of 32, frames 22–362, layers 35–50) plus frame alignment via `alignFrames()` (`5 + 17·n`). DiT/VAE tensor math is still largely `TODO` — `Pipeline` advances progress markers but skips real compute.

**Engine lifecycle and model management.** `H3Context` (equivalent to `h3_ctx`) owns the device reference, model directory, config, validation state and memory plan. `ModelLoader` validates the on-disk tree and reads `transformer/config.json`; `ModelManager`/`ModelSearcher` handle discovery across sources; `ProcessRunner` wraps external processes (FFmpeg, Real-ESRGAN). The download stack is `DownloadManager` (orchestration, source recommendation, Xget mirror) → `DownloadQueue` (multi-connection) → `DownloadTask` (single file, resume + retry), with `ModelScopeDownloader`, `DownloadPreset` (minimal/standard/full), `EnvironmentDetector` and `ModelRecommender` for hardware-based choices. **GUI vs CLI threading:** `DownloadQueue::start()` is a blocking loop kept for CLI; GUI code must use `startAsync()` + `process()` (advance one frame) + `finish()`, driven by the event loop, otherwise the UI freezes.

**Native interop (the core cross-language contract).** PHP calls free functions prefixed `h3_` / `qt_`, *declared* in `*.stub.php` and *implemented* in `cpp-src/*.mm|*.cc`. Implementations use the `phpx.h` ABI (`php_h3_*` / `php_qt_*`), wrap native objects in `php::Box` subclasses so PHP GC owns lifetime, and call back into PHP via `php::call()` (progress, frame delivery, menu clicks). Thin PHP wrappers sit in `php-src/Metal/` and `php-src/Qt/`. Stub signatures and implementations must be kept in sync — mismatches only surface at compile/link or runtime, never in the IDE. Windows/x64 TypePHP type mapping: PHP `int` → C++ `int64_t` (never `int`, or you get LNK2019), PHP `float` → C++ `double`, and PHP `double` in stubs is mis-generated as `php::Object` — use `int` or `mixed` instead. PHPStan (`phpstan.neon`) ignores `h3_*` not-found errors.

**Qt GUI layer.** `php-src/Qt/*` are PHP wrappers over opaque integer handles returned by `cpp-src/qt_bridge.cc` and `qt_node_editor.cc`; `php-src/Gui/GuiApp.php` is the single orchestrator that owns `MainWindow` plus lazy dialogs (`SetupWizard`, `EnvironmentPanel`, `ModelManagerDialog`, `ModelRecommendationDialog`, `DownloadProgressDialog`, `SettingsDialog`, `NodeCanvas`). Three non-obvious rules: (1) `QApplication` must be created before any widget — so `MainWindow` only allocates its native window in `create()`, called after `Qt\Application::init()`; (2) dialogs must be kept as fields on `GuiApp`, otherwise PHP GC frees the native window; (3) the event loop is PHP-side (`Qt\Application::run()` pumps events at ~60fps), so periodic work uses `Application::onTick()` handlers instead of Qt timers — e.g. driving `DownloadQueue::process()` and refreshing progress dialogs. `GuiApp::onMenuClick()` is the single router for every `menu_click` event, including actions raised inside dialogs, so new dialog actions need a case there.

**Node/workflow layer.** `H3NodeLibrary` (available nodes), `WorkflowGraph` (graph model + validation) and `ComfyUISerializer` (export to ComfyUI JSON) back the `NodeCanvas` editor; `ModelComponent`/`ModelConfig` describe downloadable/loadable units.

**Mental model for edits:** flags flow `Options` → `Params` → `Pipeline` stages; GPU/UI work crosses the PHP↔C++ boundary through `h3_*`/`qt_*` stubs; tests mirror `php-src` under `tests/`. Adding a CLI flag ⇒ update `Options::ALL` + `getCategories()`, then `Params` fields and `fromApplication()`. Adding native capability ⇒ add the stub (inside `php-src/`), the `.cc`/`.mm` implementation, and the PHP wrapper together, then rebuild — C++ changes are not visible until `build_windows.bat` / `build_native.sh` is rerun on a machine with the right SDK.
