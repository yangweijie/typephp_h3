# H3PHP 项目长期记忆

## TypePHP Windows x64 原生桥接约定
- PHP stub 中 `int` 参数/返回值，TypePHP 在 Windows x64 一律生成为 C++ `__int64`（即 `int64_t`）。C++ 实现必须用 `int64_t`，不能用 `int`，否则链接报 LNK2019。
- PHP `float` → C++ `double`（不是 `float`）。
- PHP `double` 在 stub 中会被 TypePHP 错误生成为 `php::Object`，应避免在 stub 用 `double`。改用 `int`（需精度时 cast）或 `mixed`（`php::Variant`）。
- Qt/原生 stub 必须放在 `php-src/` 下才会被编译进 h3php.exe；`stubs/` 目录不被构建包含（曾导致 `qt_window_create undefined`）。
- 修改 C++ 后需重新 `build_windows.bat`；`QBoxLayout` 才有 `addStretch`/`addLayout`（`QLayout` 没有，其 `addItem` 是 protected）。

## Qt GUI 初始化顺序（重要）
- `QApplication` 必须在任何 `QWidget` 构造之前创建（Qt 硬性要求）。
- `MainWindow::__construct()` 不能直接 `qt_window_create()`；延迟到 `create()` 方法，在 `Qt\Application::init()`（即 `qt_app_init()`）之后再调用。
- 其他对话框（SetupWizard/SettingsDialog/ModelManagerDialog 等）在 `show()` 里才建窗口，而 `show()` 在 init 之后，没有问题。

## NodeCanvas 显示（已补 show API）
- `NodeCanvas` 原本只有 `qt_node_canvas_create()`，view 无父窗口、不 `show()`，画布不可见。
- 已补 `qt_node_canvas_show()`（C++：首次建 `QMainWindow` 把 `view` 设为中心部件并 `show()`，幂等）；stub + `NodeCanvas::show()` + `GuiApp::openNodeEditor()` 末尾 `fitInView()`+`show()` 已接通。
- **仍需重新编译**：本环境（Windows）无 Qt SDK，C++ 改动未编译验证，需在有 Qt 的机器跑 `build_windows.bat`（或对应平台构建）后方可 `--gui` 看到节点编辑器。

## 事件循环 tick 轮询机制
- `Qt\Application::run()` 是 PHP 侧自循环（`while(running){ pump(); usleep(16000); }`，非阻塞 `exec()`）。`pump()` = `processEvents()` + `poll_event()` + 分发。
- 新增 `Application::onTick(callable)`：每帧 `pump()` 后调用所有 tick handler，用于事件循环内周期性轮询刷新。
- `GuiApp::start()` 已注册 tick：下载对话框可见时每帧调 `DownloadProgressDialog::update()`，实现进度自动刷新（利用现有 60fps 自循环，无需 Qt QTimer / 无 C++ 改动）。

## PHP 版本要求与 8.5 废弃项
- `composer.json` 要求 `php: ">=8.5 <8.6"`（2026-09-09 从 8.4 提升）。
- 已按 PHP 8.5 官方 deprecated 清单全量清理：`curl_close()`×2、`imagedestroy()`、`ord()` 传多字节。
- `curl_multi_close()` **未**被 PHP 8.5 废弃，仍须调用释放 multi handle。
- `Encoder/Tokenizer` 用 `mb_str_split()` 分词 + `ord($char[0])` 取首字节，对中文等多字节字符是既有语义（官方称"indicative of a bug"），清理时仅消除废弃、未改行为。

## 下载推进：事件循环驱动（非阻塞）
- `DownloadQueue::start()` 原为**阻塞 while 循环**，会冻结 Qt 事件循环；已在 GUI 侧弃用（`ModelManagerDialog::downloadAll()` 改调 `startAsync()`）。
- 现有 API：`startAsync()`（启动不入循环）+ `process()`（推进一帧，返回是否仍在进行）+ `finish()`；`start()` = `startAsync()` + `while(process()) usleep(10000)`，**阻塞语义保留给 CLI**。
- `DownloadManager` 透出 `startAsync()` / `process()`；`GuiApp` tick 每帧 `process()` 推进 + 刷新 `downloadDialog`/`modelDialog`（后者 `updateProgress()` 为 public）。
- 测试要点：cURL 连接失败是异步上报（约 1~2 秒），循环上限须用墙钟预算而非固定迭代次数。

## GuiApp 菜单/对话框路由模式
- `GuiApp::onMenuClick` 接收所有 `menu_click` 事件（包括主窗口与各对话框内部菜单的 action）；对话框内部 action（如 `model_scan`/`settings_save`/`download_cancel`）需在此加 case 转发到对应 dialog 实例方法。
- 各面板 `show()` 是非阻塞独立窗口；`GuiApp` 必须持有对象引用（如 `$modelDialog`），否则 PHP GC 可能回收 native window 句柄。
- Phase 30 已完成：EnvironmentPanel / ModelManagerDialog / DownloadProgressDialog（修复 Cancel 未绑定）/ SettingsDialog / NodeCanvas+导出 均已从 Tools 菜单可达。
