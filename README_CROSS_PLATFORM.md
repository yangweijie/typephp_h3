# H3PHP — Cross-Platform Guide

H3PHP runs on macOS, Windows, and Linux with a unified Qt 6 GUI.

## Platforms

| Platform | Backend | GPU | Status |
|----------|---------|-----|--------|
| macOS | Native H3 | Metal | ✅ Primary |
| Windows | ComfyUI / HTTP | CUDA | ✅ Supported |
| Linux | ComfyUI / HTTP | CUDA / ROCm | ✅ Supported |

## Requirements

### All Platforms
- PHP 8.5+ (for development / interpreted mode)
- TypePHP AOT compiler (`tpc`)
- Qt 6.8+ (Widgets, Gui, Core modules)
- 8GB+ RAM
- 60GB+ free disk space (models)

### macOS
- macOS 14+ (Apple Silicon recommended)
- Xcode Command Line Tools
- Qt 6 via Homebrew: `brew install qt@6`

### Windows
- Windows 10/11 (64-bit)
- Visual Studio 2022 (MSVC v143)
- Qt 6.8.0 (msvc2022_64)
- CUDA Toolkit 12+ (for NVIDIA GPUs)

### Linux
- Ubuntu 22.04+ / Fedora 38+
- gcc 11+ (C++17)
- Qt 6 development packages: `sudo apt install qt6-base-dev`
- CUDA Toolkit or ROCm (for AMD GPUs)

## Building

### Development Mode (All Platforms)

```bash
composer install
php bin/h3php.php -d /path/to/MiniMax-H3 --info
```

### macOS (Native H3 + Metal)

**Prerequisites:**
- macOS 14+ (Apple Silicon recommended)
- Xcode Command Line Tools
- libh3.a built from C reference implementation

```bash
# 1. Build libh3.a
cd /path/to/h3.c
make libh3.a

# 2. Install dependencies
composer install

# 3. Build standalone binary
./build_native.sh /path/to/h3.c
# or: composer run build:macos

# 4. Run
./h3php -d /path/to/MiniMax-H3 --info
```

### Windows (ComfyUI + CUDA)

**Prerequisites:**
- Windows 10/11 (64-bit)
- Visual Studio 2022 (MSVC v143) or Build Tools
- Qt 6.8.0 (msvc2022_64) — set `QT_DIR` if non-default
- Python 3.10+ with ComfyUI installed
- CUDA Toolkit 12+ (for NVIDIA GPUs)

```bat
:: 1. Install dependencies
composer install

:: 2. Build standalone binary
build_windows.bat
:: or: composer run build:windows

:: With custom Qt path:
set QT_DIR=D:\Qt\6.8.0\msvc2022_64
build_windows.bat

:: 3. Run
h3php.exe -d C:\path\to\MiniMax-H3 --info
```

**Windows Environment Variables:**
| Variable | Default | Description |
|----------|---------|-------------|
| `QT_DIR` | `C:\Qt\6.8.0\msvc2022_64` | Qt 6 installation path |
| `H3_C_DIR` | — | Path to H3 C library (optional) |

### Linux (ComfyUI + CUDA/ROCm)

**Prerequisites:**
- Ubuntu 22.04+ / Fedora 38+
- gcc 11+ (C++17 support)
- Qt 6 development packages
- Python 3.10+ with ComfyUI installed
- CUDA Toolkit or ROCm (for GPU acceleration)

```bash
# 1. Install Qt development packages (Ubuntu)
sudo apt install qt6-base-dev

# 2. Install dependencies
composer install

# 3. Build standalone binary
chmod +x build_linux.sh
./build_linux.sh
# or: composer run build:linux

# With custom Qt path:
QT_DIR=/opt/Qt/6.8.0/gcc_64 ./build_linux.sh

# 4. Run
./h3php -d /path/to/MiniMax-H3 --info
```

**Linux Environment Variables:**
| Variable | Default | Description |
|----------|---------|-------------|
| `QT_DIR` | `/usr` | Qt 6 installation path |
| `H3_C_DIR` | — | Path to H3 C library (optional) |

### Build Troubleshooting

| Issue | Solution |
|-------|----------|
| `tpc: command not found` | Run `composer install` to install TypePHP |
| `Qt6Widgets.dll not found` (Windows) | Run `windeployqt` or copy Qt DLLs next to executable |
| `libQt6Core.so.6: cannot open` (Linux) | Install Qt 6 runtime: `sudo apt install qt6-base-dev` |
| `libh3.a not found` | Build it first: `cd h3.c && make libh3.a` |
| MSVC not found (Windows) | Run from "Developer Command Prompt for VS 2022" |
| `metal_native.mm: No such file` | macOS only — Windows/Linux use ComfyUI backend |

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  TypePHP Application (PHP → C++17 → binary)                  │
├─────────────────────────────────────────────────────────────┤
│  Qt GUI Layer                                                │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │ MainWin  │ │ NodeEdit │ │ ModelMgr │ │ Settings │       │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
├─────────────────────────────────────────────────────────────┤
│  Core Logic (PHP)                                            │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  │ Workflow │ │ Download │ │ ModelMgr │ │ EnvDetec │       │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
├─────────────────────────────────────────────────────────────┤
│  Backend Abstraction                                         │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐         │
│  │ NativeH3     │ │ ComfyUI      │ │ HTTP         │         │
│  │ (Metal)      │ │ (Python FFI) │ │ (Remote)     │         │
│  └──────────────┘ └──────────────┘ └──────────────┘         │
└─────────────────────────────────────────────────────────────┘
```

## Backend Selection

The backend is selected at runtime via `BackendFactory`:

1. **Auto** (default): Uses Native H3 on macOS, ComfyUI on Windows/Linux
2. **Native H3**: Direct Metal GPU execution (macOS only)
3. **ComfyUI**: Python FFI bridge to ComfyUI server
4. **HTTP**: Remote API to a running H3PHP instance

## Qt Bridge Pattern

Communication between PHP and Qt uses opaque handles and an event queue:

- PHP calls `qt_*` functions (implemented in C++ via `php_` prefix ABI)
- Qt enqueues events (menu clicks, node drags, etc.)
- PHP polls events via `qt_app_poll_event()` and dispatches to handlers

This pattern ensures PHP owns all business logic while Qt handles only UI.
