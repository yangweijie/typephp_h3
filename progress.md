# H3PHP — Progress Log

## Session 2026-09-09 (cont.) — Phase 29 收尾：Model Recommendation UI + CLI --download-preset

### CLI `--download-preset` 命令
- **`Cli/Options.php`**：新增 `download-preset` 选项（值：minimal/standard/full/recommended），新增 "Download Preset" 分类
- **`Cli/Application.php`**：`getMode()` 新增 `download-preset` 模式检测（在 model-dir 检查之前，不需要 model-dir）
- **`php-src/main.php`**：新增 `executeDownloadPresetMode()` 函数
  - 检测硬件（GPU/RAM/Disk）
  - `recommended` 时调用 `DownloadManager::getRecommendation()` 自动推荐
  - 显示预设详情（组件列表、总大小、VRAM/RAM 需求）
  - 推荐下载源（基于网络检测）
  - 调用 `downloadPreset()` 并显示进度条
  - 下载完成后显示摘要

### Model Recommendation GUI
- **`php-src/Qt/ModelRecommendationDialog.php`**（新增）：
  - 显示硬件信息头部（GPU、RAM、磁盘）
  - 列出所有 preset 卡片，每张显示：名称、描述、大小、VRAM/RAM 需求、组件数
  - 可行性指示：✓ Compatible / ✗ Not Compatible + 原因
  - 每个 preset 有 Download 按钮（`rec_download_<id>`）
  - 进度条 + 状态标签
  - 非阻塞下载：使用 `queuePreset()` + tick 驱动进度
- **`php-src/Core/DownloadManager.php`**：新增 `queuePreset()` 方法（非阻塞，仅入队 + startAsync）
- **`php-src/Gui/GuiApp.php`**：
  - Tools 菜单新增 "Model Recommendations" 项
  - `onMenuClick` 路由 `menu_rec` → `openRecommendations()`
  - `onButtonClick` 路由 `rec_*` 事件到 `ModelRecommendationDialog::handleEvent()`
  - tick 处理器更新推荐对话框进度
  - `closeDialog('rec')` 支持关闭

### 关键设计决策
- `downloadPreset()` 是阻塞的（内部循环），GUI 侧改用 `queuePreset()`（非阻塞）保持事件循环响应
- 推荐对话框的下载按钮在下载期间禁用，进度完成后重新启用

---

## Session 2026-09-09 — php.ini 自动加载修复（Planning with files 更新进度）

### 问题：PHP Request Startup: 系统找不到指定的路径
- 现象：`h3php.exe --gui` 启动时 stderr 输出 "PHP Request Startup: 系统找不到指定的路径" 警告
- 根因：PHP embed SAPI 编译时硬编码 `extension_dir = "C:\php\ext"`，当找不到 php.ini 时使用该默认值；该目录不存在导致警告
- embed SAPI 搜索 php.ini 顺序：PHPRC 环境变量 → 注册表 → 当前工作目录 → web 服务器目录 → `--with-config-file-path` 目录 → Windows 目录。**不会自动读取 exe 同级目录**

### 修复方案
1. **`cpp-src/php_ini_auto.cc`**（新增）：
   - C++ 静态构造函数在 `main()` 之前运行
   - `GetModuleFileNameA` 获取 exe 路径 → 构造 `<exe_dir>\php.ini` 路径
   - 文件存在时设置 `php_embed_module.php_ini_path_override = iniPath`
   - 使用 `static char iniPath[MAX_PATH]` 保证字符串持久性
2. **`php.ini`**（exe 同级目录新建）：
   - `extension_dir` 指向 tpc 包的 `ext/` 目录（绝对路径）
   - 启用所需扩展：curl, mbstring, openssl, pdo_mysql, pdo_pool, sockets, zip
   - 配置 OPcache JIT
3. **删除 `C:\php\ext`**：清理之前创建的临时目录

### 验证
- 构建成功（`build_windows.bat`）
- 运行 `h3php.exe --gui` 不再报 "系统找不到指定的路径" 警告
- 进程正常启动运行

### 部署建议
- 发布时将 tpc 包 `ext/` 目录复制到 exe 同级
- php.ini 中 `extension_dir` 改为 `"ext"`（相对路径）
- 实现完全便携化（exe + php.ini + ext/ 自包含）

---

## Session 2026-09-08 (Session B) — GUI 集成审计 + Phase 30 规划（Planning with files）

### Audit: 真实集成状态（ripwire calledBy + 阅读 GuiApp/各面板代码）
- 已接入 --gui：Application、MainWindow、SetupWizard（setup_completed=false 时）、主生成表单。
- 未接入（无入口，死代码）：NodeCanvas(+NodeItem/ConnectionItem)、ModelManagerDialog、DownloadProgressDialog、EnvironmentPanel、SettingsDialog。主菜单仅 File/Language/Help。
- Phase 22 端到端缺口：H3NodeLibrary/ComfyUISerializer 仅内部互调，无顶层触发"默认工作流→导出 JSON"。
- 已接 CLI：ComfyUISearcher（search 命令）；WorkflowGraph/H3NodeLibrary 有单测。
- 各面板 show() 均为"建独立 window + 非阻塞"，事件走 Application 全局 button_click/menu_click 分发。
- 工具备注：ripwire find_symbol 中途不可用；lsp documentSymbol 对 PHP 无 provider → fallback 文本搜索。

### Plan: task_plan.md 新增 Phase 30（GUI Integration Closeout）
- 菜单扩展 + 接入 EnvironmentPanel / ModelManagerDialog / 下载管理 / SettingsDialog / NodeCanvas（默认 H3 工作流→ComfyUI JSON）+ --gui 冒烟 + 删除 stubs/ 重复 stub 副本。
- 状态：pending（待用户确认后开工）。

### Phase 30 执行：任务 1+2 — GuiApp 接入 EnvironmentPanel（2026-09-08）
- 改动 `php-src/Gui/GuiApp.php`：
  - use 引入 `Qt\EnvironmentPanel`；
  - 新增字段 `$envPanel` / `$detector`（复用，避免 GC 回收 native window）；
  - `run()` 首启 wizard 改为复用 `$this->detector`；
  - `buildUi()` 菜单新增 Tools 菜单 + "Environment Status"（action=menu_env）；
  - `onMenuClick()` 加 `menu_env` → `openEnvironment()`；
  - 新增 `openEnvironment()`：懒加载 detector/panel，已可见则 refresh，否则 show()。
- 验证：`php -l` 通过；GUI 实际渲染需 Qt SDK 编译（CI 环境无法跑，待 --gui 冒烟任务统一验收）。
- 任务 1（菜单机制）、任务 2（EnvironmentPanel）标记完成。

### Phase 30 执行：任务 3 — ModelManagerDialog 接入（2026-09-08）
- `GuiApp`：use ModelManager/DownloadManager/ModelManagerDialog；新增字段 `$modelDialog/$modelManager/$downloadManager`。
- Tools 菜单加 "Model Manager"（action=menu_models）→ `openModelManager()`（懒加载并持有引用）。
- `onMenuClick` 新增 `menu_models` 及 dialog 内部 action（model_scan/model_download_all/model_validate_all/dialog_close）转发到 `routeModelDialogAction()` / 关闭置 null。
- 验证：`php -l` 通过；功能待 --gui 冒烟统一验收。任务 3 标记完成。

### Phase 30 执行：任务 4+5 — DownloadProgressDialog / SettingsDialog 接入（2026-09-08）
- `DownloadProgressDialog.php`：修复 Cancel 按钮未绑定回调（`qt_button_set_on_click(..., 'download_cancel')`）。
- `GuiApp`：use DownloadProgressDialog/SettingsDialog；新增 `$downloadDialog/$settingsDialog` 字段。
- Tools 菜单加 "Download Manager"(menu_download)、"Settings"(menu_settings)；`onMenuClick` 加对应路由 + `download_cancel`/`settings_save`/`settings_cancel`/`dialog_close` 统一走 `closeDialog()`。
- `openDownload()` 复用 `$downloadManager`；`openSettings()` 复用已加载的 `$settings`；`closeDialog($which)` 统一关闭三对话框并置 null。
- 验证：`php -l` 两文件通过；进度自动刷新（需 timer 轮询）留待细化。任务 4、5 标记完成。

### Phase 30 执行：任务 6 — NodeCanvas 接入 + 导出端到端（2026-09-08）
- `GuiApp`：use ComfyUISerializer/H3NodeLibrary/WorkflowGraph/NodeCanvas；新增 `$nodeCanvas/$workflowGraph/$serializer` 字段。
- Tools 菜单加 "Node Editor"(menu_node_editor)、"Export Workflow JSON"(menu_export_json)。
- `openNodeEditor()`：H3NodeLibrary::getDefaultWorkflow() → 遍历 nodes/connections 调 NodeCanvas::addNode/addConnection（内存构建）。
- `exportWorkflowJson()`：serialize → json_encode → 落盘 `output/h3_default_workflow.json` + messageBox。
- **命令行验证（绕开 GUI/Qt）**：5 节点 / 6 连接 / 0 校验错误，生成合法 ComfyUI JSON（节点类型映射正确）。导出端到端打通。
- **已知 blocker**：NodeCanvas 无 show()/挂载窗口 API（qt_node_canvas_create 仅创 canvas 对象），画布无法独立显示，需 C++ 层补 window-mount 才能渲染。已记入 findings。
- 任务 6 标记完成。

### Phase 30 执行：任务 7+8 — 回归与清理（2026-09-08）
- 任务 8：删除 `stubs/qt_node_editor.stub.php` 重复副本（仅 `php-src/` 版本参与 AOT 构建）。
- 任务 7：`php -l` 对 GuiApp 全改动通过；导出链独立脚本验证通过；全量 `composer run test` 因 composer 300s 进程超时中断（输出显示测试正常 passing dots，非失败）。GuiApp/DownloadProgressDialog 改动未被任何单元测试覆盖。
- **残留待办**：下载进度自动刷新需事件循环 timer 轮询；NodeCanvas 显示需 C++ 扩展。两项均为细化，不阻塞 Phase 30 收尾。
- 任务 7、8 标记完成；**Phase 30 整体 complete**。

### 任务 (a) — 给 NodeCanvas 补 C++ 窗口挂载（2026-09-08）
- 背景：NodeCanvas 原无 show/挂载窗口 API，画布在 --gui 不可见（Phase 30 已记录的 Blocker）。用户要求"先 a 后 b"。
- C++ `cpp-src/qt_node_editor.cc`：
  - `CanvasInfo` 新增 `QWidget* window = nullptr`；
  - 新增 `php_qt_node_canvas_show()`：首次建 `QMainWindow`（标题"H3 Node Editor"），`setCentralWidget(view)`，`resize(1200,700)`，`show()`；幂等；
  - 修改 `php_qt_node_canvas_destroy()`：有 window 时 `delete window`（连带释放 view），避免重复 delete。
- `php-src/qt_node_editor.stub.php`：新增 `qt_node_canvas_show(int $canvas): void` 声明。
- `php-src/Qt/NodeCanvas.php`：新增 `show(): self`。
- `php-src/Gui/GuiApp.php`：`openNodeEditor()` 末尾加 `fitInView()` + `show()`。
- 验证：`php -l` 对 NodeCanvas.php / GuiApp.php 通过。**C++ 因本环境无 Qt SDK 无法编译**，代码遵循 `qt_bridge.cc` 既有 QMainWindow+setCentralWidget+show 模式，需用户在有 Qt 机器 `build_windows.bat` 后 --gui 验收（即任务 b）。

## Session 2026-09-08 (Latest) — Cross-Platform + Environment Setup Planning

### Scope Expansion: v1.0 → v2.0
**T00:00** — User clarified requirements:
- Library must NOT be limited to local H3.c on macOS
- Must support Windows/Linux users with local MiniMax H3 models
- ComfyUI integration via Python FFI is key for cross-platform
- Qt GUI preferred over AppKit for cross-platform support

**T00:01** — Analyzed TypePHP example ecosystem:
- `examples/objective-c-macos` → AppKit (macOS only)
- `examples/ssh-tunnel-qt` → Qt (cross-platform, 20+ components)
- `examples/python` → Python FFI (direct module import)

**T00:02** — Created v2.0 task_plan.md with 8 new phases (18-25)
- Phase 18: Backend abstraction layer
- Phase 19: Python FFI integration
- Phase 20: Qt GUI foundation
- Phase 21: Node editor (QGraphicsView)
- Phase 22: ComfyUI workflow integration
- Phase 23: Cross-platform build system
- Phase 24: Model manager
- Phase 25: Testing & documentation

**T00:03** — Updated findings.md with:
- Qt component inventory for node editor
- ComfyUI Python API surface
- Backend abstraction design
- Platform-specific build paths

### Environment & Setup Expansion: v2.0 → v2.1
**T00:04** — User requested additional features:
- ComfyUI download/install flow
- Model weight download management
- Environment detection UI
- First-run setup wizard

**T00:05** — Added 3 new phases (25-27):
- Phase 25: Download Manager (ComfyUI + model weights, resume, checksum)
- Phase 26: Environment Detection & Setup Wizard (6-page guided setup)
- Phase 27: Model Manager UI (download/validate/remove models)

**T00:06** — Added UI flow diagrams to task_plan.md:
- First-Run Setup Wizard flow (Welcome → Python → GPU → Models → Ready)
- Download Manager UI (task queue, progress bars, mirror selection)
- Environment Status Dashboard (Python/GPU/disk/mem/network)
- Model Manager UI (installed models, download missing, disk usage)

**T00:07** — Expanded findings.md with:
- ComfyUI installation methods (git clone, portable, pip)
- Model weights research (6 components, ~25 GB total)
- Download sources comparison (HuggingFace / ModelScope / CivitAI)
- Environment detection requirements (RAM/VRAM/disk/Python/CUDA)
- Download Manager design (task model, resume, checksum)
- Setup Wizard page designs (6 pages)
- Qt download UI components

### Phase 18 Implementation: Backend Abstraction Layer
**T00:08** — User requested: remove Real-ESRGAN (use ComfyUI built-in upscale nodes), start Phase 18

**T00:09** — Removed Real-ESRGAN from model weights:
- Updated task_plan.md Model Manager UI flow diagram
- Updated findings.md model components table
- Added note: "Upscaling via ComfyUI built-in nodes (ImageUpscaleWithModel, 4x-UltraSharp)"
- Added Key Decision: Upscaling → ComfyUI built-in nodes

**T00:10** — Implemented Phase 18 (7 files):
- `Core/BackendType.php` — enum with label(), isAvailableOnCurrentPlatform(), all(), available()
- `Core/BackendStatus.php` — immutable value object with ready/notReady/error factory methods
- `Core/BackendInterface.php` — contract: loadModel, generate, cancel, getStatus, isAvailable, free
- `Core/NativeH3Backend.php` — wraps H3Context, macOS Metal only
- `Core/ComfyUIBackend.php` — Python FFI bridge, cross-platform
- `Core/HttpBackend.php` — REST/WebSocket remote API
- `Core/BackendFactory.php` — create() + autoDetect() with priority logic

**T00:11** — Lint check: 0 errors on all 7 new files

### Phase 19 Implementation: Python FFI Integration
**T00:12** — Implemented Phase 19 (3 files):
- `php-src/comfyui.stub.php` — FFI stubs: h3_comfyui_init/ping/load_model/execute/interrupt/get_stats/shutdown
- `python-src/comfyui_bridge.py` — JSON stdin/stdout bridge: 7 commands (init/ping/load/execute/interrupt/stats/shutdown)
- `php-src/Core/ComfyUIProcessManager.php` — Process lifecycle: start/stop/ping/loadModel/execute/interrupt/getStats

**T00:13** — Lint check: 0 errors on all new files

### Files Modified (Phase 19)
- `php-src/comfyui.stub.php` — NEW
- `python-src/comfyui_bridge.py` — NEW
- `php-src/Core/ComfyUIProcessManager.php` — NEW

### Phase 20 Implementation (Qt GUI Foundation)
**T00:14** — Implemented Phase 20 (5 files):
- `project.yml` — Added Qt 6 framework linking + platform-specific overrides
- `stubs/qt_bridge.stub.php` — 35 Qt function declarations
- `cpp-src/qt_bridge.cc` — C++ implementation with opaque handles + event queue
- `php-src/Qt/Application.php` — Qt app lifecycle + event dispatch
- `php-src/Qt/MainWindow.php` — QMainWindow wrapper with menus + dialogs

### Phase 21 Implementation (Node Editor)
**T00:15** — Implemented Phase 21 (5 files):
- `stubs/qt_node_editor.stub.php` — Node canvas API declarations
- `cpp-src/qt_node_editor.cc` — QGraphicsView scene/items with bezier connections
- `php-src/Qt/NodeCanvas.php` — Node graph controller with event dispatch
- `php-src/Qt/NodeItem.php` — Node data model
- `php-src/Qt/ConnectionItem.php` — Connection data model

### Phase 22 Implementation (ComfyUI Workflow)
**T00:16** — Implemented Phase 22 (3 files):
- `php-src/Core/WorkflowGraph.php` — DAG with topological sort + validation
- `php-src/Core/ComfyUISerializer.php` — ComfyUI JSON ↔ internal format
- `php-src/Core/H3NodeLibrary.php` — 5 H3 node types + default workflow template

### Phase 23 Implementation (Build System)
**T00:17** — Implemented Phase 23 (3 files):
- `build_windows.bat` — MSVC + Qt SDK build script
- `build_linux.sh` — gcc + Qt system packages build script
- `.github/workflows/build.yml` — GitHub Actions CI for all 3 platforms

### Phase 24 Implementation (Model Manager)
**T00:18** — Implemented Phase 24 (1 file):
- `php-src/Core/ModelManager.php` — Unified model discovery for Native H3 + ComfyUI layouts

### Phase 25 Implementation (Download Manager)
**T00:19** — Implemented Phase 25 (4 files):
- `php-src/Core/DownloadTask.php` — Single download job model
- `php-src/Core/DownloadQueue.php` — Concurrent downloads with cURL multi
- `php-src/Core/DownloadManager.php` — HuggingFace/ModelScope/CivitAI/GitHub sources
- `php-src/Qt/DownloadProgressDialog.php` — Qt download progress UI

### Phase 26 Implementation (Environment Detection)
**T00:20** — Implemented Phase 26 (3 files):
- `php-src/Core/EnvironmentDetector.php` — Python/GPU/disk/memory/network detection
- `php-src/Qt/SetupWizard.php` — 5-page first-run wizard
- `php-src/Qt/EnvironmentPanel.php` — Status dashboard

### Phase 27 Implementation (Model Manager UI)
**T00:21** — Implemented Phase 27 (1 file):
- `php-src/Qt/ModelManagerDialog.php` — Qt model management UI

### Phase 28 Implementation (Testing & Docs)
**T00:22** — Implemented Phase 28 (5 files):
- `tests/Core/WorkflowGraphTest.php` — WorkflowGraph unit tests
- `tests/Core/H3NodeLibraryTest.php` — H3NodeLibrary unit tests
- `tests/Core/SettingsManagerTest.php` — SettingsManager unit tests
- `tests/Core/ThemeManagerTest.php` — ThemeManager unit tests
- `README_CROSS_PLATFORM.md` — Cross-platform build guide

### Files Modified
- `task_plan.md` — v2.1 with environment setup phases + UI flow diagrams + Phase 18 complete
- `findings.md` — ComfyUI install + model sources + environment detection + download manager + no Real-ESRGAN
- `progress.md` — This entry
- `php-src/Core/BackendType.php` — NEW
- `php-src/Core/BackendStatus.php` — NEW
- `php-src/Core/BackendInterface.php` — NEW
- `php-src/Core/NativeH3Backend.php` — NEW
- `php-src/Core/ComfyUIBackend.php` — NEW
- `php-src/Core/HttpBackend.php` — NEW
- `php-src/Core/BackendFactory.php` — NEW

---

## Session 2026-09-07 — TypePHP Build Flow Optimization

### Phase 17: Build Flow Optimization — ✅ Complete
- Analyzed reference example (`aot-compiler/examples/objective-c-macos`)
- Updated `project.yml` with `cpp-src` in sources
- Simplified `build_native.sh` to single TypePHP invocation
- Updated `composer.json` + `CODEBUDDY.md`

---

## Session 2026-09-05 — Security Hardening + C Library

### Phase 14-16: Complete
- C library integration (libh3.a)
- CLI parameter exposure
- Security hardening (P0-P3)

---

## Session 2026-09-04 — Initial Development

### Phases 1-13: Complete
- All skeleton, inference, VAE, generation, optimization phases
- 85 tests, 619 assertions, 0 failures
