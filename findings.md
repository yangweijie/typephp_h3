# H3PHP — Findings & Research (v2.0: Cross-Platform + Multi-Backend)

## TypePHP Capabilities Summary

### Confirmed Supported Bridges
| Bridge | Example Location | Status |
|--------|------------------|--------|
| **AppKit** (macOS) | `examples/objective-c-macos` | ✅ Full |
| **Qt** (cross-platform) | `examples/ssh-tunnel-qt` | ✅ Full |
| **Python FFI** | `examples/python` | ✅ Full |
| **UIKit** (iOS) | `examples/objective-c-macos/ios-src` | ✅ Full |

### Qt Components Available (from ssh-tunnel-qt)
| Component | Purpose for H3PHP |
|-----------|-------------------|
| `QGraphicsView` | Node editor canvas |
| `QGraphicsScene` | Node graph scene |
| `QGraphicsItem` | Node rendering |
| `QGraphicsPathItem` | Bezier connections |
| `QMainWindow` | App window |
| `QTableWidget` | Node list |
| `QPlainTextEdit` | Log/output |
| `QPushButton` | Toolbar |
| `QLineEdit` | Parameter input |
| `QComboBox` | Node type selection |
| `QSpinBox` | Numeric params |
| `QCheckBox` | Boolean params |
| `QDialog` | Node edit dialogs |
| `QFormLayout` | Parameter panels |
| `QSplitter` | Resizable panels |
| `QProcess` | ComfyUI process mgmt |
| `QFileDialog` | File selection |
| `QTimer` | Progress polling |

### Python FFI Capabilities (from examples/python)
| Capability | Syntax |
|------------|--------|
| Import module | `use python\sys;` |
| Access constant | `use const python\math\pi;` |
| Call function | `use function python\platform\python_version;` |
| Create Python objects | `python\tuple([3, 8])` |
| Access properties | `sys\version_info->major` |
| Comparison | `sys\version_info < python\tuple([3, 8])` |

---

## Cross-Platform Build Findings

### Qt Installation Paths

#### macOS
```bash
# Homebrew
brew install qt@6
# Path: /opt/homebrew/opt/qt6/

# Or system package
# Framework flags in project.yml
```

#### Windows
```powershell
# Qt Online Installer
# Default: C:\Qt\6.8.0\msvc2022_64\
# Build: build_windows.bat
# Deploy: windeployqt.exe h3php.exe
```

#### Linux (Ubuntu/Debian)
```bash
sudo apt install qt6-base-dev
# Path: /usr/include/x86_64-linux-gnu/qt6/
# Build: build_linux.sh
```

### TypePHP Project.yml Platform Detection
```yaml
# Conditional flags based on build host
# macOS: -framework Qt6Widgets
# Windows: Qt6Widgets.lib
# Linux: -lQt6Widgets
```

---

## ComfyUI Integration Research

### ComfyUI Python API Surface
```python
# Core modules accessible via FFI
import comfy.model_management      # GPU memory management
import comfy.samplers               # KSampler, etc.
import comfy.nodes                  # Node definitions
import comfy.client                 # Queue management
import comfy.utils                  # Helper utilities
import folder_paths                 # Model directory scanning
```

### ComfyUI Workflow JSON Format
```json
{
  "last_node_id": 10,
  "last_link_id": 15,
  "nodes": [
    {"id": 1, "type": "CheckpointLoaderSimple", "outputs": [...], "pos": [0, 0]},
    {"id": 2, "type": "CLIPTextEncode", "inputs": [...], "pos": [200, 0]},
    ...
  ],
  "links": [
    {"id": 1, "source": 1, "source_slot": 0, "target": 3, "target_slot": 0},
    ...
  ]
}
```

### MiniMax-H3 ComfyUI Nodes (Community)
| Node | Function |
|------|----------|
| `H3ModelLoader` | Load transformer + VAE |
| `H3TextEncode` | ClipProj text encoding |
| `H3KSampler` | DiT denoising loop |
| `H3VAEDecode` | Video VAE decode |
| `H3VideoCombine` | Output MP4 |

---

## Backend Abstraction Design

### Interface Contract
```php
interface H3BackendInterface {
    public function loadModel(string $modelDir): H3ModelHandle;
    public function generate(
        H3ModelHandle $handle,
        string $prompt,
        array $params
    ): H3Result;
    public function cancel(string $jobId): void;
    public function getStatus(): BackendStatus;
}
```

### Factory Pattern
```php
class BackendFactory {
    public static function create(BackendType $type): H3BackendInterface {
        return match($type) {
            BackendType::NATIVE_H3 => new NativeH3Backend(),
            BackendType::COMFYUI => new ComfyUIBackend(),
            BackendType::HTTP_API => new HttpBackend(),
        };
    }
}
```

---

## SSH Tunnel Qt Example: Architecture Pattern

### Event-Driven Loop
```php
while (qt_tunnel_is_open($window)) {
    qt_tunnel_process_events($window);  // 16ms event loop
    while (true) {
        $event = qt_tunnel_poll_event($window);
        if ($event === []) break;
        handle_event($event);
    }
}
```

### PHP → C++ Bridge Pattern
```cpp
// stub declaration
function qt_tunnel_start_process(mixed $window, string $id, string $program, array $arguments): bool {}

// implementation
Bool php_qt_tunnel_start_process(var box, String id, String program, Array arguments) {
    return windowBox(box)->startProcess(toQString(id), toQString(program), arguments);
}
```

---

## Model Directory Structure (Unified)

### Native H3 Format
```
MODEL_DIR/
+-- FL2VA/
|   +-- transformer/config.json
|   +-- tokenizer/tokenizer.json
|   +-- video_vae/source/
|   +-- audio_vae/
+-- Ref2VA/ (optional)
```

### ComfyUI Format
```
comfyui/models/
+-- checkpoints/MiniMax-H3/
|   +-- minimax_h3_fastvideo_4step.safetensors
+-- vae/
|   +-- h3_video_vae.safetensors
+-- text_encoders/
|   +-- qwen3_vl_4b_int8/
+-- clip_proj/
    +-- clipproj_minimax_h3.safetensors
```

---

## Findings from Qt Example (ssh-tunnel-qt)

### Key Architecture Decisions
1. **PHP owns all business logic** — Qt only handles UI + event loop
2. **Opaque handles** — Int IDs for C++ objects (GC-safe)
3. **Event queue pattern** — C++ enqueues events, PHP polls and dispatches
4. **Array for params** — Single `php::Array` instead of 22 args
5. **Cross-platform resource icons** — `.qrc` resource file, embedded at compile time

### Build Configuration
```yaml
name: ssh_tunnel_manager
mode: bin
cxx-std: c++17
sources:
  - main.php
  - app
  - php-src
  - cpp-src
resource:
  icon: icon/ssh_tunnel_manager.ico
```

---

## Python FFI Findings

### Type Mapping
| PHP Type | Python Type |
|----------|-------------|
| `string` | `str` |
| `int` | `int` |
| `float` | `float` |  
| `bool` | `bool` |
| `array` | `list` / `tuple` |
| `null` | `None` |

### ComfyUI-Specific Access Pattern (Planned)
```php
// Direct Python module access
use function comfy\model_management\get_torch_device;
use function comfy\samplers\sample;
use function comfy\nodes\resolve_queue;

$device = get_torch_device();  // Returns 'cuda:0' or 'mps'
```

---

## Security Considerations for Multi-Backend

### ComfyUI Python FFI
- Sandboxed Python execution (no arbitrary code execution)
- Only pre-declared modules accessible
- No `eval()` or `exec()` exposed

### HTTP API Backend
- Configurable timeout
- HTTPS support
- API key authentication

---

## ComfyUI Installation Research

### Installation Methods Comparison

| Method | Pros | Cons | Recommended For |
|--------|------|------|-----------------|
| **Git clone + pip** | Latest version, easy update | Requires git + pip | Developers |
| **Portable (Windows)** | No install, USB portable | Manual updates | End users |
| **ComfyUI Manager** | One-click node install | Requires existing install | All users |

### Git Clone + pip (Recommended for Wizard)
```bash
# 1. Clone ComfyUI core
git clone https://github.com/comfyanonymous/ComfyUI.git
cd ComfyUI

# 2. Install dependencies
pip install -r requirements.txt

# 3. Install H3 custom nodes
cd custom_nodes
git clone https://github.com/kijai/ComfyUI-MiniMax-H3.git
cd ComfyUI-MiniMax-H3
pip install -r requirements.txt
```

### Windows Portable Package
```
ComfyUI_windows_portable/
├── run_nvidia_gpu.bat    # NVIDIA
├── run_cpu.bat           # CPU fallback
├── ComfyUI/
├── python_embeded/
└── models/
```

### ComfyUI Directory Structure (Post-Install)
```
ComfyUI/
├── main.py                  # Entry point
├── requirements.txt         # Python deps
├── models/
│   ├── checkpoints/         # DiT models
│   ├── vae/                 # VAE models
│   ├── clip/                # Text encoders
│   ├── clip_vision/         # Vision encoders
│   ├── upscale_models/      # SR models
│   └── unet/                # Alternative location
├── custom_nodes/
│   └── ComfyUI-MiniMax-H3/  # H3-specific nodes
├── output/                  # Generated files
├── temp/                    # Temp files
└── user/                    # User config
```

### ComfyUI Python Dependencies (Critical)
```
torch>=2.1.0
torchvision
torchaudio
numpy
pillow
pyyaml
scipy
tqdm
psutil
```

---

## Model Weights Research

### MiniMax-H3 Model Components

| Component | Size | Format | Source |
|-----------|------|--------|--------|
| **H3 Transformer (DiT)** | ~14 GB | safetensors | HuggingFace / ModelScope |
| **H3 Video VAE** | ~2 GB | safetensors | HuggingFace / ModelScope |
| **H3 Text Encoder (Qwen3-VL)** | ~8 GB | safetensors | HuggingFace / ModelScope |
| **H3 ClipProj** | ~800 MB | safetensors | HuggingFace / ModelScope |
| **H3 Audio VAE (BigVGAN)** | ~300 MB | safetensors | HuggingFace / ModelScope |
| **Total** | ~25 GB | | |

> **Note:** Upscaling is handled by ComfyUI's built-in upscale nodes (e.g., `ImageUpscaleWithModel`, `UltimateSDUpscale`, `4x-UltraSharp`). No separate Real-ESRGAN model download needed.

### Download Sources

| Source | URL | Speed (China) | Speed (Global) | Reliability |
|--------|-----|---------------|----------------|-------------|
| **HuggingFace** | huggingface.co | Slow (CDN) | Fast | High |
| **ModelScope** | modelscope.cn | Fast | Medium | High |
| **CivitAI** | civitai.com | Medium | Fast | Medium |

### HuggingFace Model Paths
```
MiniMax-H3/
├── MiniMax-H3-4B/           # 4B parameter model
│   ├── transformer/         # DiT weights
│   ├── video_vae/           # Video VAE
│   ├── tokenizer/           # Tokenizer config
│   └── config.json          # Model config
└── MiniMax-H3-4B-FastVideo/ # Distilled 4-step version
    └── ...
```

### ModelScope Model Paths
```
MiniMaxAI/
├── MiniMax-H3-4B/
│   ├── transformer/diffusion_pytorch_model.safetensors
│   ├── video_vae/diffusion_pytorch_model.safetensors
│   └── tokenizer/
└── MiniMax-H3-4B-FastVideo/
    └── ...
```

### ComfyUI H3 Custom Nodes
```
ComfyUI-MiniMax-H3/
├── __init__.py
├── nodes/
│   ├── h3_model_loader.py
│   ├── h3_text_encode.py
│   ├── h3_ksampler.py
│   ├── h3_vae_decode.py
│   └── h3_video_combine.py
├── workflows/
│   ├── h3_text_to_video.json
│   └── h3_reference_to_video.json
└── requirements.txt
```

---

## Environment Detection Requirements

### Minimum System Requirements
| Resource | Minimum | Recommended |
|----------|---------|-------------|
| **RAM** | 16 GB | 32 GB |
| **VRAM** | 8 GB | 12 GB+ |
| **Disk (models)** | 30 GB free | 60 GB free |
| **Disk (temp)** | 10 GB free | 20 GB free |
| **Python** | 3.10 | 3.11 |
| **CUDA** | 11.8 | 12.1+ |
| **PyTorch** | 2.0 | 2.1+ |

### Platform-Specific GPU Detection

#### Windows (NVIDIA)
```powershell
# NVIDIA-smi
nvidia-smi --query-gpu=name,memory.total,driver_version --format=csv
# Output: NVIDIA RTX 4090, 24576 MiB, 536.23
```

#### Linux (NVIDIA)
```bash
nvidia-smi --query-gpu=name,memory.total,driver_version --format=csv
```

#### macOS (Apple Silicon)
```bash
# Metal support check
system_profiler SPHardwareDataType | grep "Chip"
# Apple M1/M2/M3/M4 → Metal supported
```

### Python Environment Detection
```python
import sys
import importlib

def check_environment():
    results = {
        'python_version': sys.version,
        'torch': _check_module('torch'),
        'cuda_available': False,
        'metal_available': False,
    }
    if results['torch']:
        import torch
        results['cuda_available'] = torch.cuda.is_available()
        results['metal_available'] = torch.backends.mps.is_available()
        results['cuda_version'] = torch.version.cuda
    return results
```

---

## Download Manager Design

### Download Task Model
```php
class DownloadTask {
    public string $id;           // Unique ID
    public string $url;          // Source URL
    public string $targetPath;   // Local destination
    public int $totalSize;       // Bytes (0 if unknown)
    public int $downloadedSize;  // Bytes downloaded
    public ?string $sha256;      // Expected checksum
    public DownloadState $state; // pending|downloading|paused|completed|error
    public int $retryCount;      // Retry attempts
    public string $errorMessage; // Last error
}
```

### Download Sources Configuration
```php
class DownloadSource {
    // HuggingFace
    public const HF_BASE = 'https://huggingface.co';
    // ModelScope (China mirror)
    public const MS_BASE = 'https://modelscope.cn';
    // Auto-select based on connectivity test
    public static function selectBest(): string { ... }
}
```

### Resume Support
- HTTP Range header for partial downloads
- `.partial` temp file during download
- Atomic rename on completion
- Checksum verification post-download

### Qt Download UI Components
| Component | Class | Purpose |
|-----------|-------|---------|
| Progress bar | `QProgressBar` | Per-task progress |
| Task list | `QTableWidget` | All downloads |
| Speed label | `QLabel` | MB/s display |
| Pause button | `QPushButton` | Pause/resume |
| Cancel button | `QPushButton` | Cancel download |
| Settings | `QDialog` | Mirror/concurrency |

---

## Setup Wizard Page Design

### Page 1: Welcome
- App logo + name
- Language selector (EN/中文)
- "What is H3PHP" brief intro
- [Next] button

### Page 2: Python Check
- Detect Python version
- Detect PyTorch
- Detect CUDA/Metal
- Status: ✅/❌/⚠️
- [Install Python] button if missing
- [Next] button

### Page 3: GPU Check
- GPU name + VRAM
- Compute capability
- Driver version
- Expected performance estimate
- [Next] button

### Page 4: Disk Space Check
- Required: ~60 GB
- Available: scan target drive
- [Change Path] button
- [Next] button

### Page 5: Model Setup
- Option A: Download all (~25 GB)
- Option B: Download minimal (~14 GB, DiT only)
- Option C: Use existing models
- Mirror selection: Auto / HuggingFace / ModelScope
- [Start Download] button

### Page 6: Ready
- Summary of what was installed
- [Launch Editor] button

---

## Previous Findings (Preserved)

### h3.c Engine Architecture
- Two paths: FL2VA (text→video) and Ref2VA (reference→video)
- Six stages: Load → Conditioning → DiT → Decoding → Muxing → SR
- 26.8GB total model size, SSD streaming for memory-constrained devices

### VDN-H3 Hybrid Attention
- Softmax window + linear far branch
- 5 FP32 precision islands required
- Separate video/audio scheduling (shift=12.0 / shift=3.0)

### TypePHP Limitations
- Switch/case must end with return/break/continue/throw
- Variable type fixed on first assignment
- Vendor libs (symfony/yaml) cannot be AOT-compiled

---

## GUI Integration Audit (2026-09-08)

### `--gui` 实际装配（php-src/Gui/GuiApp.php）
- 已接入：Application → MainWindow::create() → (首启) SetupWizard 阻塞循环 → buildUi() 生成主表单 → qt->run() 全局事件循环。
- 事件模型：qt->on('button_click'|'menu_click'|'text_changed'|'combo_changed')，回调按 callback_id/action 字符串路由（如 wizard_*、menu_*、browse_model、generate）。
- 主菜单仅：File(Select Model Dir/Exit)、Language(Change Language)、Help(How to use/About)。

### 未接入组件（代码存在但 calledBy 为空）
| 组件 | 构造签名 | show() 行为 |
|------|----------|-------------|
| `Qt\EnvironmentPanel` | (EnvironmentDetector) | 独立 window，非阻塞 |
| `Qt\ModelManagerDialog` | (ModelManager, DownloadManager) | 独立 window，非阻塞 |
| `Qt\DownloadProgressDialog` | (DownloadManager) | 独立 window，非阻塞 |
| `Qt\SettingsDialog` | (SettingsManager) | 独立 window，非阻塞 |
| `Qt\NodeCanvas` | () | 调 qt_node_canvas_create（须 QApplication 已建） |
| `Qt\NodeItem` / `ConnectionItem` | 纯数据模型 | — |

### 重复 stub 副本
- `stubs/qt_node_editor.stub.php` 与 `php-src/qt_node_editor.stub.php` 同尺寸并存；仅 `php-src/` 被 project.yml（sources: php-src）纳入 AOT 构建。`stubs/` 副本可删。

### 注意
- 接入这些面板需保持对象引用（防 native window 被 PHP GC 回收）。
- 面板内部按钮若注册带前缀的 callback_id，需在 GuiApp 事件路由加对应 case（参照 wizard_* 模式）。

### Blocker：NodeCanvas 无显示 API（2026-09-08 → 已补 show API，待编译）
- 原状态：`NodeCanvas` 构造仅调 `qt_node_canvas_create()`，**无 show() / 挂载窗口 API**，画布在 --gui 不可见。
- 已解决（2026-09-08 任务 a）：新增 `qt_node_canvas_show()`（C++ 首次建 `QMainWindow` 把 `view` 设为中心部件并 `show()`，幂等）+ stub 声明 + `NodeCanvas::show()` + `GuiApp::openNodeEditor()` 调 `fitInView()`+`show()`。
- **遗留**：C++ 改动本环境（Windows）无 Qt SDK 无法编译验证，需在有 Qt 的机器 `build_windows.bat` 后 `--gui` 验收（即任务 b）。
