# H3PHP GUI 原始需求整理（PRD）

> 需求来源（均为仓库内既有文档，非新编）：`task_plan.md` v2.0/v2.1（Phase 18–30）、`findings.md`（Setup Wizard Page Design / Download Manager Design / Environment Detection Requirements / Model Manager UI Flow / UI Flow Diagrams）、`progress.md`（2026-09-08 GUI 集成审计与 Phase 30 接入记录）、`README.md`（Features / Project Structure），并对照当前实现 `php-src/Gui/GuiApp.php`、`php-src/Qt/*`、`php-src/Core/*`。
>
> 配套可点击原型：`docs/gui/prototype/index.html`。

## 1. 背景与目标（v2.0 原始诉求）

2026-09-08 用户明确的范围扩张（`progress.md` Session 2026-09-08 / `task_plan.md` Goal）：

1. 不能只支持 macOS + 本地 `h3.c`，必须支持 Windows / Linux 用户使用本地 MiniMax-H3 模型；
2. 通过 **Python FFI 集成 ComfyUI** 实现跨平台推理；
3. GUI 框架选 **Qt 6 而非 AppKit**（跨平台）；
4. **保留既有 CLI 工作流不变**（`task_plan.md` Key Decisions：CLI compatibility = Unchanged）。

后续追加需求（v2.1）：ComfyUI 下载/安装流程、模型权重下载管理、环境检测 UI、首次启动向导。

## 2. 用户角色与场景

| 角色 | 场景 |
|------|------|
| 首次安装者 | 双击 exe 后被引导完成 Python/GPU/磁盘/模型检查并一键下载缺失组件 |
| 本地创作者（macOS M 系） | 选模型目录 → 填 prompt → 调参 → 生成 MP4（Native H3 / Metal 后端） |
| 本地创作者（Windows / Linux NVIDIA） | 同上，走 ComfyUI 后端；需要下载并管理 25GB 级权重 |
| 高级用户 | 用节点编辑器编排 H3 工作流并导出为 ComfyUI JSON |

## 3. 需求范围总览

| # | 模块 | 原始需求（出处） | 承载实现 | 状态 |
|---|------|------------------|----------|------|
| US-001 | 首次启动向导 | `findings.md` Setup Wizard Page Design；`task_plan.md` P26「Welcome → Python → GPU → Models → Ready」 | `Qt/SetupWizard.php`、`Core/EnvironmentDetector.php` | ✅ 已接入 |
| US-002 | 主窗口生成表单 | `task_plan.md` GUI Layer「Parameter Panels (QFormLayout)」 | `Gui/GuiApp.php::buildUi()` | ✅ 已接入 |
| US-003 | 环境状态仪表盘 | `findings.md` Environment Detection Requirements；UI Flow Diagram | `Qt/EnvironmentPanel.php` | ✅ 已接入 |
| US-004 | 模型管理器 | `task_plan.md` P27 + Model Manager UI Flow（卡片、磁盘占用、下载缺失、校验、清理） | `Qt/ModelManagerDialog.php`、`Core/ModelManager.php` | ✅ 已补齐 |
| US-005 | 下载管理器 | `findings.md` Download Manager Design + Download Manager Flow（队列、进度、Pause/Cancel、镜像、断点续传、SHA256） | `Qt/DownloadProgressDialog.php`、`Core/DownloadQueue.php` | ✅ 已补齐 |
| US-006 | 模型推荐 | `task_plan.md` P29「Model recommendation UI (preset cards + feasibility)」 | `Qt/ModelRecommendationDialog.php`、`Core/DownloadPreset.php` | ✅ 已接入 |
| US-007 | 节点编辑器 | `task_plan.md` P21（QGraphicsView）+ P22（H3 节点） | `Qt/NodeCanvas.php`、`cpp-src/qt_node_editor.cc` | ⚠️ 待编译验收 |
| US-008 | 工作流导出 | `task_plan.md` P30「Export Workflow JSON 端到端」 | `Core/ComfyUISerializer.php`、`GuiApp::exportWorkflowJson()` | ✅ 已验证 |
| US-009 | 设置 | `Core/SettingsManager.php` 持久化字段 | `Qt/SettingsDialog.php` | ✅ 已接入 |
| US-010 | 主菜单与路由 | `task_plan.md` P30「Tools 菜单 + onMenuClick 路由」 | `GuiApp::buildUi()` 菜单 + `onMenuClick()` | ✅ 已接入 |
| US-011 | 预览/输出 | `task_plan.md` GUI Layer「Preview/Output (QLabel + QPixmap)」 | `GuiApp` 输出行 + `ProcessRunner` | ⚠️ 部分（无缩略图） |

## 4. 用户故事与验收标准

### US-001 首次启动向导（P1）
作为**首次安装者**，我希望首次启动时被引导完成环境检查与模型准备，以便不用手工排查依赖。
- 向导共 5 页：Welcome → Python Detection → GPU Detection → Model Storage → Setup Complete
- Welcome 页含语言选择器（EN/中文）+ Apply，并显示「How to use / 使用说明」步骤
- Python 页展示版本/路径与 torch、comfyui 等包检测结果；未安装时提示 Python 3.10+
- GPU 页展示 GPU 名称、VRAM、首选后端（CUDA/Metal/ROCm）；无 GPU 时提示将走 CPU
- Model Storage 页展示可用磁盘空间与模型目录输入框（默认 `~/models`）
- 末页展示 `System readiness: <label> (<score>/100)`，导航为 Back / Next / Finish / Cancel
- 完成后 `setup_completed` 落盘，二次启动不再弹窗

### US-002 主窗口生成表单（P1）
作为**创作者**，我希望在一个表单里完成选模型、写 prompt、调参数并生成，以便快速出片。
- 模型目录行：`Model Dir` 输入框 + Browse 按钮（action `browse_model`）
- Prompt 多行输入
- 参数：Width（`512/640/864/1024/1280`）、Height（`384/480/576/720/768`）、Frames（`22/39/56/73/90/107`）、Steps（`10/15/20/25/30/50`）、Output（默认 `outputs/h3.mp4`）、Seed（默认 `42`）
- `Generate` 按钮；生成期间禁用，完成后恢复
- 进度条 + 状态标签（初始 `Ready. Select a model directory to begin.`）+ Log 输出区

### US-003 环境状态仪表盘（P2）
作为**用户**，我希望一眼看到系统是否具备生成条件，以便知道该补什么。
- 顶部 `System Readiness: <label> (<score>/100)`
- 分类卡片：Python（版本/路径/包）、GPU（名称/VRAM/首选后端）、Disk（可用空间）、Memory（RAM）、Network（HF/ModelScope 连通性）
- 每项带 ✅/⚠️/❌ 状态标记
- 有 `Suggestions:` 区块逐条列出修复建议（如推荐 ModelScope 镜像）

### US-004 模型管理器（P2）
作为**用户**，我希望查看本机已装/缺失的模型并一键补齐，以便不用手工下权重。
- 菜单 File(Close) / Actions(Download All, Validate All)
- 扫描本地目录 + ComfyUI 模型目录，结果以模型卡片展示：名称、大小、后端、配置是否齐全（✓）
- 底部状态：`Found N models (X GB total)` + 进度条
- Download All 触发非阻塞下载（`DownloadManager::startAsync()`），由事件循环驱动进度刷新
- Validate All 输出 valid/invalid 计数
- 补齐（Phase 31）：单模型 `Validate` / `Remove` 按钮（`model_validate_<i>` / `model_remove_<i>`）、缺失组件行 `Download`（`model_download_missing_<type>`）、磁盘占用进度条与文案、卡片 `v<version>`

### US-005 下载管理器（P2）
作为**用户**，我希望看到每个下载任务的进度并能暂停/取消，以便掌控 25GB 级下载。
- 任务队列表：序号、组件名、进度条、百分比、状态/剩余时间
- 典型任务：#1 ComfyUI Core、#2 H3 Transformer、#3 H3 Video VAE、#4 H3 Text Encoder、#5 H3 ClipProj、#6 ComfyUI H3 Nodes
- `Start` / `Cancel` 按钮（Cancel 绑定 `download_cancel`）
- 状态标签：`Preparing downloads...` → 进行中 → 完成/失败
- 底层要求：HTTP Range 断点续传、`.partial` 临时文件、完成后原子 rename、SHA256 校验
- 补齐（Phase 31）：`Pause` / `Resume`（`DownloadQueue::pause()`/`resume()`）、速度 `<size>/s` 与 `ETA`、镜像下拉（auto / huggingface / modelscope / xget → `applyMirror()`）、逐任务状态行

### US-006 模型推荐（P3）
作为**硬件不确定的用户**，我希望系统按我的 GPU/RAM/磁盘推荐下载方案，以便不浪费磁盘。
- 头部展示硬件画像（GPU、RAM、磁盘）
- Preset 卡片：Minimal / Standard / Full，含名称、描述、总大小、VRAM/RAM 需求、组件数
- 可行性指示：`✓ Compatible` / `✗ Not Compatible` + 原因
- 每张卡片 Download 按钮（`rec_download_<id>`），不可行时文案变为 `Download (may not work)`
- 下载期间按钮禁用，完成后恢复；底部进度条 + `Select a preset to download.` 状态标签

### US-007 节点编辑器（P3）
作为**高级用户**，我希望用节点画布编排 H3 工作流，以便自定义生成流程。
- `QGraphicsView` 画布，独立窗口标题 `H3 Node Editor`，默认 1200×700
- 节点库 5 类（颜色取自 `H3NodeLibrary`）：Load H3 Model(`#2d4a7a`)、H3 Text Encode(`#3a5a3a`)、H3 KSampler(`#5a3a5a`)、H3 VAE Decode(`#5a4a2a`)、H3 Video Combine(`#4a2a2a`)
- 端口按 `inputs/outputs` 渲染（model/vae/clip/conditioning/latent/image），连线为贝塞尔曲线
- 默认工作流 5 节点 / 6 连接，0 校验错误
- ⚠️ `qt_node_canvas_show()` 已补 C++ + stub + `NodeCanvas::show()`，本机无 Qt SDK 未编译验证

### US-008 工作流导出（P3）
作为**高级用户**，我希望把画布工作流导出成 ComfyUI 可导入的 JSON。
- 菜单 `Tools → Export Workflow JSON`（action `menu_export_json`）
- 序列化为 ComfyUI 格式（节点类型映射：`LoadH3Model→H3ModelLoader`、`H3TextEncode→CLIPTextEncode`、`H3KSampler→KSampler`、`H3VAEDecode→VAEDecode`、`H3VideoCombine→VHS_VideoCombine`）
- 落盘 `output/h3_default_workflow.json` 并弹结果提示
- 命令行已验证：5 节点 / 6 连接 / 0 错误

### US-009 设置（P3）
作为**用户**，我希望配置语言、更新检查与目录，以便持久化我的偏好。
- 字段：Language(`en`/`zh`)、Check for updates(`Yes`/`No`)、Model Directory、Output Directory
- Save 落盘 `SettingsManager`；Cancel 放弃
- 语言切换调用 `qt_app_set_language()`

### US-010 主菜单与事件路由（P1）
作为**用户**，我希望所有功能都能从主菜单进入，以便不用记 CLI 参数。
- 菜单：File(Select Model Dir, Exit) / Language(Change Language...) / Tools(Environment Status, Model Manager, Model Recommendations, Download Manager, Settings, Node Editor, Export Workflow JSON) / Help(How to use, About)
- 所有动作在 `GuiApp::onMenuClick()` 单点路由，含对话框内部 action（`model_scan`、`model_download_all`、`model_validate_all`、`settings_save`、`download_cancel`、`dialog_close`）
- 对话框对象必须由 `GuiApp` 持有引用，避免 PHP GC 回收 native window

### US-011 预览/输出（P4，部分实现）
作为**创作者**，我希望在生成后直接在界面里预览视频/帧，以便不必去文件夹找文件。
- 主窗口输出行显示 `Last output: <path>`，生成完成时写入（`GuiApp::finishGeneration()`）
- `Reveal` 在文件管理器中定位文件、`Play` 用系统默认应用打开（`ProcessRunner::revealInFileManager()` / `openWithDefaultApp()`）
- ⚠️ 缩略图预览（`QLabel + QPixmap`）仍需新增 C++ 接口，本环境无 Qt SDK 未实现

## 5. 关键约束与决策（沿用原始决策）

| 项 | 决策 | 原因 |
|----|------|------|
| GUI 框架 | Qt 6（非 AppKit） | 跨平台 |
| 节点画布 | QGraphicsView | Qt 内置图形框架 |
| 后端选择 | 运行时工厂（`BackendFactory`） | 同一二进制切换 Native/ComfyUI/HTTP |
| ComfyUI 桥接 | Python FFI | 低延迟、共享模型 |
| 放大 | ComfyUI 内置 upscale 节点 | 不再单独下载 Real-ESRGAN |
| CLI | 保持兼容 | 不影响既有用户 |
| GUI 线程模型 | PHP 侧自循环 + `onTick()` | 下载/进度必须由事件循环驱动，禁止阻塞循环 |

## 6. 已知缺口（原型未覆盖之外的实现债）

1. NodeCanvas 画布渲染需在有 Qt SDK 的机器编译后验收（`qt_node_canvas_show`）；
2. 下载速度/暂停/镜像选择未进 UI；
3. 模型卡片的单卡下载、删除、磁盘可视化未实现；
4. Preview/Output（US-011）完全缺失；
5. `task_plan.md` P30 之后提到的 `WorkflowGraphManager` / `JobScheduler` / `OutputPipeline` 在原计划中列出但无实现。

## 7. 原型页面索引

| 页面 | 对应用户故事 |
|------|--------------|
| `prototype/index.html` | 总览 |
| `prototype/01-setup-wizard.html` | US-001 |
| `prototype/02-main-window.html` | US-002、US-010 |
| `prototype/03-model-manager.html` | US-004 |
| `prototype/04-download-manager.html` | US-005 |
| `prototype/05-environment-panel.html` | US-003 |
| `prototype/06-node-editor.html` | US-007 |
| `prototype/07-model-recommendation.html` | US-006 |
| `prototype/08-settings.html` | US-009 |
