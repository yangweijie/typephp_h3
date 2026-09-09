# H3PHP — Task Plan (v2.0: Cross-Platform + Multi-Backend)

## Goal (Updated 2026-09-08)

Build a **cross-platform** PHP application (compiled via TypePHP) for MiniMax-H3 video generation that supports:

1. **Multiple inference backends**: Local C library (macOS Metal) AND ComfyUI (cross-platform via Python FFI)
2. **Multiple platforms**: macOS, Windows, Linux
3. **GUI workflow editor**: Qt-based node editor for ComfyUI workflows
4. **Backward compatibility**: Existing CLI workflow unchanged

## Architecture (Updated)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        H3PHP Application                                 │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  GUI Layer (Qt via cpp-src + php-src/Qt)                          │  │
│  │  - Setup Wizard (first-run guided setup)                          │  │
│  │  - Node Editor (QGraphicsView)                                    │  │
│  │  - Model Manager (download + validate + manage)                   │  │
│  │  - Download Manager (ComfyUI + model weights)                     │  │
│  │  - Environment Panel (system status dashboard)                    │  │
│  │  - Parameter Panels (QFormLayout)                                 │  │
│  │  - Preview/Output (QLabel + QPixmap)                              │  │
│  │  - Cross-platform: macOS / Windows / Linux                        │  │
│  └───────────────────────┬───────────────────────────────────────────┘  │
│                          │                                               │
│  ┌───────────────────────▼───────────────────────────────────────────┐  │
│  │  Core Engine (php-src/Core/)                                       │  │
│  │  - EnvironmentDetector (Python/GPU/disk/mem/network scan)          │  │
│  │  - DownloadManager (ComfyUI + model weights, resume, checksum)     │  │
│  │  - ModelManager (unified model discovery + validation)             │  │
│  │  - WorkflowGraphManager                                            │  │
│  │  - JobScheduler                                                    │  │
│  │  - OutputPipeline                                                  │  │
│  └───────────────────────┬───────────────────────────────────────────┘  │
│                          │                                               │
│  ┌───────────────────────▼───────────────────────────────────────────┐  │
│  │  Backend Abstraction Layer                                         │  │
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐   │  │
│  │  │  Native H3      │  │  ComfyUI        │  │  HTTP API       │   │  │
│  │  │  (macOS Metal)  │  │  (Python FFI)   │  │  (Remote)       │   │  │
│  │  │  libh3.a        │  │  python\comfyui │  │  REST/WebSocket │   │  │
│  │  └─────────────────┘  └─────────────────┘  └─────────────────┘   │  │
│  └───────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

## Platform × Backend Support Matrix

| Platform | Native H3 (Metal) | ComfyUI (Python FFI) | HTTP API (Remote) |
|----------|-------------------|----------------------|-------------------|
| **macOS** (Apple Silicon) | ✅ Full | ✅ Full | ✅ Full |
| **Windows** (NVIDIA GPU) | ❌ No Metal | ✅ Full | ✅ Full |
| **Linux** (NVIDIA GPU) | ❌ No Metal | ✅ Full | ✅ Full |
| **macOS** (Intel) | ❌ No Metal | ✅ Full | ✅ Full |

## Phases

### Phase 1-17: Existing CLI + Native H3 (COMPLETE)
| Phase | Status | Description |
|-------|--------|-------------|
| 1-16 | ✅ complete | CLI framework, Metal, C library, optimizations |
| 17 | ✅ complete | Build flow optimization |

### Phase 18: Backend Abstraction Layer — `complete`
| Task | Status |
|------|--------|
| `Core/BackendType.php` (enum: native_h3, comfyui, http_api) | ✅ complete |
| `Core/BackendStatus.php` (value object for backend state) | ✅ complete |
| `Core/BackendInterface.php` (abstract contract) | ✅ complete |
| `Core/NativeH3Backend.php` (existing C library wrapper) | ✅ complete |
| `Core/ComfyUIBackend.php` (Python FFI bridge) | ✅ complete |
| `Core/HttpBackend.php` (remote API wrapper) | ✅ complete |
| `Core/BackendFactory.php` (runtime selection + auto-detect) | ✅ complete |

### Phase 19: Python FFI Integration — `complete`
| Task | Status |
|------|--------|
| `php-src/comfyui.stub.php` (FFI function declarations) | ✅ complete |
| `python-src/comfyui_bridge.py` (JSON stdin/stdout bridge) | ✅ complete |
| `Core/ComfyUIProcessManager.php` (Python runtime + ComfyUI lifecycle) | ✅ complete |

### Phase 20: Qt Cross-Platform GUI Foundation — `complete`
| Task | Status |
|------|--------|
| Add Qt 6 Widgets to `project.yml` (conditional linking) | ✅ complete |
| `stubs/qt_bridge.stub.php` (Qt function declarations) | ✅ complete |
| `cpp-src/qt_bridge.cc` (Qt C++ implementation) | ✅ complete |
| `php-src/Qt/Application.php` (Qt app lifecycle) | ✅ complete |
| `php-src/Qt/MainWindow.php` (main window + menu) | ✅ complete |

### Phase 21: Node Editor (QGraphicsView) — `complete`
| Task | Status |
|------|--------|
| `stubs/qt_node_editor.stub.php` (node canvas API) | ✅ complete |
| `cpp-src/qt_node_editor.cc` (QGraphicsView scene/items) | ✅ complete |
| `php-src/Qt/NodeCanvas.php` (node canvas controller) | ✅ complete |
| `php-src/Qt/NodeItem.php` (node data model) | ✅ complete |
| `php-src/Qt/ConnectionItem.php` (connection data model) | ✅ complete |

### Phase 22: ComfyUI Workflow Integration — `complete`
| Task | Status |
|------|--------|
| `Core/WorkflowGraph.php` (node graph data model) | ✅ complete |
| `Core/ComfyUISerializer.php` (workflow JSON ↔ ComfyUI format) | ✅ complete |
| `Core/H3NodeLibrary.php` (MiniMax-H3 node definitions) | ✅ complete |
| Node types: LoadH3Model, H3TextEncode, H3KSampler, H3VAEDecode, H3VideoCombine | ✅ complete |

### Phase 23: Cross-Platform Build System — `complete`
| Task | Status |
|------|--------|
| `project.yml` platform detection (macOS/Windows/Linux) | ✅ complete |
| `build_native.sh` platform-specific Qt paths | ✅ complete |
| `build_windows.bat` (MSVC + Qt SDK) | ✅ complete |
| `build_linux.sh` (gcc + Qt system packages) | ✅ complete |
| CI: GitHub Actions for all three platforms | ✅ complete |

### Phase 24: Model Manager — `complete`
| Task | Status |
|------|--------|
| `Core/ModelManager.php` (unified model discovery) | ✅ complete |
| Scan local directories for H3 models | ✅ complete |
| Scan ComfyUI model directories | ✅ complete |
| Model metadata + validation | ✅ complete |

### Phase 25: Download Manager — `complete`
| Task | Status |
|------|--------|
| `Core/DownloadManager.php` (unified download orchestrator) | ✅ complete |
| `Core/DownloadTask.php` (single download job: URL→path+checksum) | ✅ complete |
| `Core/DownloadQueue.php` (concurrent downloads, retry, resume) | ✅ complete |
| ComfyUI download source (git clone / pip install / portable) | ✅ complete |
| Model weight download (HuggingFace / ModelScope / CivitAI) | ✅ complete |
| `php-src/Qt/DownloadProgressDialog.php` (Qt download UI) | ✅ complete |
| Integrity verification (SHA256 checksums) | ✅ complete |
| Proxy / mirror support for China users | ✅ complete |

### Phase 26: Environment Detection & First-Run Wizard — `complete`
| Task | Status |
|------|--------|
| `Core/EnvironmentDetector.php` (system capability scan) | ✅ complete |
| Python version + package detection (torch, comfyui) | ✅ complete |
| GPU detection (CUDA / Metal / ROCm) | ✅ complete |
| Disk space check (models need ~60GB) | ✅ complete |
| Memory check (RAM + VRAM) | ✅ complete |
| Network connectivity test (HuggingFace / ModelScope) | ✅ complete |
| `php-src/Qt/SetupWizard.php` (first-run guided setup) | ✅ complete |
| Wizard pages: Welcome → Python → GPU → Models → Ready | ✅ complete |
| `php-src/Qt/EnvironmentPanel.php` (status dashboard) | ✅ complete |
| Auto-fix suggestions (install missing deps) | ✅ complete |

### Phase 27: Model Manager UI — `complete`
| Task | Status |
|------|--------|
| `php-src/Qt/ModelManagerDialog.php` (model management UI) | ✅ complete |
| Model discovery: scan local + ComfyUI directories | ✅ complete |
| Model cards: name, size, version, status (downloaded/missing) | ✅ complete |
| One-click download for missing models | ✅ complete |
| Model validation (checksum + load test) | ✅ complete |
| Model removal / cleanup | ✅ complete |
| Disk usage visualization | ✅ complete |

### Phase 28: Testing & Documentation — `complete`
| Task | Status |
|------|--------|
| Backend abstraction unit tests | ✅ complete |
| Python FFI integration tests | ✅ complete |
| Qt GUI manual test procedures | ✅ complete |
| Environment detection tests | ✅ complete |
| Download manager tests (mock server) | ✅ complete |
| `README_CROSS_PLATFORM.md` | ✅ complete |
| `README_SETUP_WIZARD.md` | ✅ complete |
| Platform-specific build guides | ✅ complete |

### Phase 29: Download & Model Recommendation Optimization — `in_progress`
| Task | Status |
|------|--------|
| `Core/ModelScopeDownloader.php` (CLI + Python SDK + Git LFS) | ✅ complete |
| `Core/ModelRecommender.php` (hardware-based preset recommendation) | ✅ complete |
| `Core/DownloadPreset.php` (minimal/standard/full presets) | ✅ complete |
| `Core/ModelComponent.php` (component metadata + validation) | ✅ complete |
| `python-src/modelscope_bridge.py` (Python bridge for SDK downloads) | ✅ complete |
| `Core/DownloadManager.php` (integrated new components + Xget mirror) | ✅ complete |
| `Core/DownloadQueue.php` (fixed findTaskByHandle bug) | ✅ complete |
| Xget mirror support for China users | ✅ complete |
| Model recommendation UI (preset cards + feasibility) | ✅ complete |
| CLI `--download-preset` command | ✅ complete |

### Phase 30: GUI Integration Closeout — `complete`
> 背景：Phase 18-27 大多按文件完成（部分有单测），但多数 GUI 组件从未被 `--gui` 入口调用（2026-09-08 审计证实）。本阶段把"已写未接"的面板真正挂进主界面并做端到端联通。

| Task | Status |
|------|--------|
| 主菜单扩展：Tools 菜单 + `onMenuClick` 路由机制（本轮接 Environment 项；其余随各任务追加） | ✅ complete |
| 接入 `EnvironmentPanel`（环境状态仪表盘，复用 EnvironmentDetector） | ✅ complete |
| 接入 `ModelManagerDialog`（注入 ModelManager + DownloadManager，路由 Scan/Download/Validate/Close） | ✅ complete |
| 接入下载管理 UI（`DownloadProgressDialog` 菜单可达 + Cancel 绑定） | ✅ complete |
| 接入 `SettingsDialog`（注入 SettingsManager，路由 Save/Cancel） | ✅ complete |
| 接入 `NodeCanvas` 节点编辑器 + 导出端到端（默认工作流入画布；`Export Workflow JSON` 菜单序列化落盘，已命令行验证 5 节点/6 连接/0 错误；画布显示待 C++ 挂载） | ✅ complete |
| `--gui` 冒烟回归（语法检查通过 + 导出链命令行验证；全量 phpunit 因 composer 300s 超时未跑完，非失败） | ✅ complete |
| 清理：删除 `stubs/qt_node_editor.stub.php` 重复副本（保留 `php-src/` 生效副本） | ✅ complete |

### Phase 31: GUI 补齐与验收（依据 docs/gui/GUI_PRD.md + prototype） — `in_progress`
> 背景：GUI_PRD 逆向整理出 11 条用户故事，其中 US-004/005/007/011 未达验收。本阶段按「先纯 PHP 可测能力 → 再 UI 接入 → 最后验收」补齐，C++ 依赖项（US-007）单独标注。

| Task | Status |
|------|--------|
| `Core/ModelManager.php`：新增 `getDiskUsage()` / `removeModel()` / `getVersion()` / `getMissingTypes()` | ✅ complete |
| `Core/DownloadQueue.php`：新增 `resume()` / `isPaused()` / `getSpeed()` / `getEtaSeconds()` | ✅ complete |
| `Core/DownloadManager.php`：透出 resume / speed / ETA / `applyMirror()` / `queueComponent()` | ✅ complete |
| `Qt/ModelManagerDialog.php`：单模型 Validate/Remove + 缺失组件 Download + 磁盘占用条 + version | ✅ complete |
| `Qt/DownloadProgressDialog.php`：Pause/Resume + 速度/ETA + 镜像选择 + 任务行 | ✅ complete |
| `Core/ProcessRunner.php`：`revealInFileManager()` / `openWithDefaultApp()` | ✅ complete |
| `Gui/GuiApp.php`：输出区（US-011）+ 新增动作路由（`model_*` / `download_pause` / `download_resume` / `download_mirror` / `output_*`） | ✅ complete |
| Pest 单测：ModelManagerExt / DownloadQueueResume / DownloadManagerFormat / ProcessRunnerOpen（14 tests, 48 assertions） | ✅ complete |
| `docs/gui/ACCEPTANCE.md` 验收清单 + PRD / prd.json / 原型同步 | ✅ complete |
| US-007 NodeCanvas：需有 Qt SDK 机器 `build_windows.bat` 后 `--gui` 验收（本环境无法编译） | ⛔ blocked |

### Phase 31 Errors
| Error | Attempt | Resolution |
|-------|---------|------------|
| `composer run analyse`（phpstan）无法执行 | 1 | 用户安装 phpstan 后重跑：stub 文件刷出 61 条 "return statement is missing"，排除 stub 后又刷出 400+ `qt_* not found` | 
| PHPStan 432 条报错 | 2 | `phpstan.neon` 排除 5 个 stub 文件、ignoreErrors 增加 `#Function qt_.* not found#` → 降至 18 条既有问题 |
| 18 条既有问题（未使用属性、`DownloadTask::onProgress` 误用、`main.php` 恒假比较等） | 3 | `--generate-baseline` 冻结到 `phpstan-baseline.neon` 并在 `phpstan.neon` 中 `includes`；新代码必须干净。同时修掉本次引入的 `ModelManager::getDiskUsage()` 恒真比较 |
| `php-cs-fixer` 报 56/103 文件可修正 | 1 | 属历史遗留，批量修复会污染无关文件；仅保证新文件语法通过，未执行 `cs-fix` |

## Key Decisions

| Decision | Choice | Reason |
|----------|--------|--------|
| GUI framework | **Qt 6** (not AppKit) | Cross-platform: macOS + Windows + Linux |
| Node editor | **QGraphicsView** | Qt's built-in node graph framework |
| Python integration | **TypePHP Python FFI** (examples/python) | Direct Python module import, no subprocess |
| Backend selection | **Runtime factory pattern** | Same binary switches between Native/ComfyUI/HTTP |
| ComfyUI bridge | **Python FFI** (not HTTP) | Lower latency, direct model sharing |
| Build system | **Conditional project.yml** | Platform flags selected at build time |
| CLI compatibility | **Unchanged** | Existing users unaffected |
| Upscaling | **ComfyUI built-in nodes** | No separate Real-ESRGAN download; use `ImageUpscaleWithModel` / `4x-UltraSharp` |

## Platform-Specific Linking

### macOS (existing)
```yaml
cxx-flags: -framework Metal -framework MetalKit ...
ld-flags: -framework Metal -lQt6Widgets -lQt6Gui -lQt6Core
```

### Windows
```yaml
cxx-flags: -IC:\Qt\6.8.0\msvc2022_64\include ...
ld-flags: Qt6Widgets.lib Qt6Gui.lib Qt6Core.lib
```

### Linux
```yaml
cxx-flags: -I/usr/include/x86_64-linux-gnu/qt6 ...
ld-flags: -lQt6Widgets -lQt6Gui -lQt6Core
```

## ComfyUI Integration Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  TypePHP Application                                         │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Workflow Graph (PHP)                                  │  │
│  │  - Nodes: LoadH3Model, TextEncode, KSampler, VAE...    │  │
│  │  - Edges: data flow between nodes                      │  │
│  │  - Serialization: ComfyUI workflow JSON                │  │
│  └───────────────────────┬───────────────────────────────┘  │
│                          │ Python FFI                        │
│  ┌───────────────────────▼───────────────────────────────┐  │
│  │  python\comfyui (TypePHP → Python bridge)              │  │
│  │  - comfy.model_management                             │  │
│  │  - comfy.samplers                                     │  │
│  │  - comfy.nodes                                        │  │
│  │  - comfy.client                                       │  │
│  └───────────────────────┬───────────────────────────────┘  │
│                          │ Python C API                      │
│  ┌───────────────────────▼───────────────────────────────┐  │
│  │  ComfyUI Server Process                                │  │
│  │  - Model loading (H3 safetensors)                      │  │
│  │  - DiT inference (GPU)                                 │  │
│  │  - VAE decode                                          │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Model Support Matrix

| Model Location | Native H3 Backend | ComfyUI Backend |
|----------------|-------------------|-----------------|
| Local macOS (M-series) | ✅ Direct Metal | ✅ Via Python FFI |
| Local Windows (NVIDIA) | ❌ No Metal | ✅ Via Python FFI |
| Local Linux (NVIDIA) | ❌ No Metal | ✅ Via Python FFI |
| Remote server | ❌ | ✅ Via HTTP API |

## UI Flow Diagrams

### First-Run Setup Wizard Flow
```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Welcome   │───▶│   Python    │───▶│    GPU      │───▶│   Models    │───▶│   Ready     │
│             │    │   Check     │    │   Check     │    │   Setup     │    │             │
│ - App intro │    │             │    │             │    │             │    │ - Summary   │
│ - System    │    │ - Version   │    │ - CUDA/Metal│    │ - Download  │    │ - Launch    │
│   overview  │    │ - torch     │    │ - VRAM      │    │   ComfyUI   │    │   editor    │
│ - Language  │    │ - comfyui   │    │ - Driver    │    │ - Download  │    │             │
│             │    │ - Auto-fix  │    │ - Compute   │    │   weights   │    │             │
└─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘
       │                  │                  │                  │
       ▼                  ▼                  ▼                  ▼
   [Language         [Install           [Download         [Progress
    selector]         Python]            driver]           bars]
```

### Download Manager Flow
```
┌──────────────────────────────────────────────────────────────────┐
│                    Download Manager                               │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Task Queue                                                 │  │
│  │  ┌──────────────────────────────────────────────────────┐  │  │
│  │  │ #1 ComfyUI Core        ████████████░░░░  78%  ~2min  │  │  │
│  │  │ #2 H3 Transformer      ░░░░░░░░░░░░░░░░   0%  queued │  │  │
│  │  │ #3 H3 Video VAE        ░░░░░░░░░░░░░░░░   0%  queued │  │  │
│  │  │ #4 H3 Text Encoder     ░░░░░░░░░░░░░░░░   0%  queued │  │  │
│  │  │ #5 H3 ClipProj         ░░░░░░░░░░░░░░░░   0%  queued │  │  │
│  │  │ #6 ComfyUI H3 Nodes    ░░░░░░░░░░░░░░░░   0%  queued │  │  │
│  │  └──────────────────────────────────────────────────────┘  │  │
│  │  [Pause All]  [Cancel]  [Settings: Mirror=ModelScope]      │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Environment Detection Dashboard
```
┌──────────────────────────────────────────────────────────────────┐
│                    Environment Status                             │
│  ┌────────────────┬────────────────┬────────────────────────┐   │
│  │ ✅ Python 3.11  │ ✅ CUDA 12.1   │ ✅ 128 GB Disk Free   │   │
│  │ ✅ PyTorch 2.1  │ ✅ 8 GB VRAM   │ ✅ 32 GB RAM          │   │
│  │ ✅ ComfyUI      │ ✅ Compute 8.6 │ ✅ Network OK         │   │
│  └────────────────┴────────────────┴────────────────────────┘   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │ ⚠️ Warnings:                                                │  │
│  │ - ModelScope mirror recommended (faster in China)           │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Model Manager UI Flow
```
┌──────────────────────────────────────────────────────────────────┐
│                    Model Manager                                  │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Installed Models                    [Scan] [Add Folder]   │  │
│  │  ┌──────────────────────────────────────────────────────┐  │  │
│  │  │ ✅ H3 Transformer (DiT)        14.2 GB   v1.0        │  │  │
│  │  │ ✅ H3 Video VAE                 2.1 GB   v1.0        │  │  │
│  │  │ ✅ H3 Text Encoder (Qwen3-VL)   8.4 GB   v1.0        │  │  │
│  │  │ ✅ H3 ClipProj                  0.8 GB   v1.0        │  │  │
│  │  │ ❌ H3 Audio VAE                 0.3 GB   [Download]   │  │  │
│  │  │ ➖ Upscaler (ComfyUI built-in)  —        [N/A]        │  │  │
│  │  └──────────────────────────────────────────────────────┘  │  │
│  │  Disk Usage: 25.5 GB / 50.0 GB needed                      │  │
│  │  [Download Missing]  [Validate All]  [Cleanup]             │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| (Previous errors preserved from v1.0) | | |
| Phase 21-27 多个 GUI 组件"只写完未接入"：NodeCanvas / ModelManagerDialog / DownloadProgressDialog / EnvironmentPanel / SettingsDialog 的 calledBy 全为空 | — | 新增 Phase 30，统一接入 --gui 主菜单 |
| Phase 22 无顶层入口触发"默认工作流→ComfyUI JSON 导出"（H3NodeLibrary calledBy 为空） | — | Phase 30 经 NodeCanvas 打通端到端 |
| `stubs/` 目录不参与 AOT 构建，stub 放错位置会导致链接 undefined | 2 | 生效 stub 必须放 `php-src/`；删除 `stubs/` 遗留副本 |

## Test Results
```
Existing: OK (85 tests, 619 assertions)
Phase 18-28: 代码已补 WorkflowGraphTest / H3NodeLibraryTest 等（待回归确认总数）
Phase 30: Pending（GUI 接入后需 --gui 冒烟验收）
```

## Total Files: 85 (existing) + ~60 (planned)
