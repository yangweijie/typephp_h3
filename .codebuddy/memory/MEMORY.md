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

## 测试框架：Pest（非 phpunit 直调）
- 2026-09-09 迁移完成：`composer test` = `pest`（pestphp/pest 5.1.4，底层 PHPUnit 13）。类测试在 `H3Php\Tests\*`，直接继承 `PHPUnit\Framework\TestCase`。
- `tests/Pest.php` **不要** `pest()->extend(Tests\TestCase)`（无该 autoload，会致命报错）；提供 `model_dir()` / `skip_without_model_dir()`。
- 需真实权重的测试用 `H3_MODEL_DIR` 环境变量（等同 CLI `-d`），未设置则 skip。

## 工具链现状（重要）
- PHPStan 已由用户手动安装（不在 `require-dev`，新环境需 `composer require --dev phpstan/phpstan`）；`composer run analyse` = level 5 over `php-src`+`bin`，当前 **[OK] No errors**。
- `phpstan.neon` 约定：**排除 5 个 `php-src/*.stub.php`**（stub 只有声明、空函数体会刷 61 条 `return statement is missing`）；`ignoreErrors` 忽略 `h3_*` / `qt_*` not found（实现在 `cpp-src/`）；`includes: phpstan-baseline.neon` 冻结 18 条既有发现。
- **陷阱**：只排除 stub 而不加 `qt_*` ignore，`qt_*` 会变未定义 → 报错从 94 涨到 432；两条必须配套改。
- 期望：`analyse` 恒为 `[OK] No errors`，新代码的错误会直接失败（baseline 只冻结既有项）。
- `php-cs-fixer --dry-run` 长期报 56/103 文件可修正（历史遗留），不要批量 `cs-fix`，会污染无关文件。
- `php-cs-fixer --dry-run` 长期报 56/103 文件可修正（历史遗留），不要批量 `cs-fix`，会污染无关文件。
- 验收优先级：Pest 单测 > `php -l` > 手工 `--gui`（GUI 代码无 Qt 跑不起来）。

## GUI 补齐约定（Phase 31）
- 模型管理器/下载对话框的 widget 必须挂到 **central widget + vbox 布局**上（早期版本只 create 不 add，窗口是空的）。
- `ModelManager::removeModel()` 只允许删除**已注册搜索路径内**的路径——这是防越界删除的硬约束，别绕过。
- 新增按钮一律在 `GuiApp` 单点路由；行内动作用前缀匹配：`model_validate_<i>` / `model_remove_<i>` / `model_download_missing_<type>`。

## Qt 翻译机制限制（重要）
- `qt_app_set_language()` 加载的 `.qm` 只翻译 Qt 内部 `tr()` 字符串；PHP 侧 `qt_label_create()` 的裸字面量**永远不会被翻译**——语言切换必须走应用级字典。
- 已落地 `Core/Translator`（EN 源串 + zh_CN；`t()` 未知 key→key、未知语言→en）；`SetupWizard` 全页接入，`applyLanguage()` 切换后 `showPage()` 重建当前页，界面立即变中文；`GuiApp::changeLanguage()` 同步调 `Translator::setLanguage()`。
- 其余对话框（主窗口/模型管理器等）文案仍为英文源串，后续迁移统一改 `Translator::t('panel.key')`。

## 构建可见面 / 平台差异
- `project.yml` 与 `project_windows.yml` 的 sources 只有 `php-src` + `cpp-src`：顶层 `stubs/` 目录**不参与编译**，真正生效的 stub 在 `php-src/`（h3/metal/qt_bridge/qt_node_editor/comfyui.stub.php）。
- Windows 构建忽略所有 `.mm`（Metal 仅 macOS）；`build_windows.bat` 用 `QT_DIR` 生成 `project_windows.yml` 并跑 `windeployqt`。
