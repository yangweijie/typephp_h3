# H3PHP GUI 验收清单（Phase 31）

对应用户故事见 `docs/gui/GUI_PRD.md`，机读状态见 `agents/prd.json`。

## 1. 自动化验收（当前环境可直接跑）

```bash
composer install
vendor/bin/pest                                   # 全量：141 tests / 784 assertions
vendor/bin/pest tests/Core/ModelManagerExtTest.php # US-004
vendor/bin/pest tests/Core/DownloadQueueResumeTest.php tests/Core/DownloadManagerFormatTest.php  # US-005
vendor/bin/pest tests/Core/ProcessRunnerOpenTest.php # US-011
php -l php-src/Gui/GuiApp.php                      # GUI 层仅能做语法检查（需 Qt 才能运行）
composer run analyse                               # PHPStan level 5：No errors
```

| 测试 | 覆盖点 |
|------|--------|
| `ModelManagerExtTest` | 扫描结果的 size/backend/version、`getDiskUsage()`、`getMissingTypes()`、`removeModel()` 仅允许删除搜索路径内 |
| `DownloadQueueResumeTest` | `pause()` 把 active 任务置 paused、`isPaused()`、`resume()` 重新入队、空闲时速度/ETA 为 0 |
| `DownloadManagerFormatTest` | `MIRROR_CHOICES`、`formatSpeed()`、`formatEta()`、未知组件 `queueComponent()` 返回空 |
| `ProcessRunnerOpenTest` | Windows/macOS/Linux 三平台的 reveal / open 命令，缺失文件不启动进程 |

最近一次全量结果：`Tests: 146, Assertions: 797, PHPUnit Warnings: 2, Notices: 5, Skipped: 1`（warning 为 `pcntl` 缺失导致的时间限制提示，非失败）。`composer run analyse` → `[OK] No errors`。

PHPStan 配置要点：`phpstan.neon` 排除 `php-src/*.stub.php`（stub 只有声明、函数体为空，否则会刷出 "return statement is missing"），忽略 `h3_*` / `qt_*` 未定义函数（实现在 `cpp-src/`），并用 `phpstan-baseline.neon` 冻结 18 条既有发现——**新代码必须干净**，新增错误会直接让 `analyse` 失败。

## 2. 手工验收（需装有 Qt 6 的机器编译后执行）

```bat
build_windows.bat          :: 或 ./build_native.sh（macOS）、./build_linux.sh
h3php.exe --gui
```

| # | 步骤 | 期望 |
|---|------|------|
| 1 | 首次启动 | 弹出 Setup Wizard，5 页可前进/后退，完成后写入 `setup_completed` |
| 1a | Welcome 页选「简体中文」→ Apply | **当前页立即整体切换为中文**（含 Back/Next/Cancel 按钮与窗口标题），且设置持久化；重开向导仍为中文 |
| 2 | Tools ▸ Model Manager | 列出模型行（名称 · 大小 · backend · v版本 · ✓）；缺失组件行带 Download |
| 3 | 点击某行 `Validate` | 状态栏显示 `✓ <name> — valid` 或具体原因 |
| 4 | 点击某行 `Remove` | 文件/目录被删除，列表自动刷新；非搜索路径内拒绝删除 |
| 5 | 点击缺失组件 `Download` | 状态栏显示 `Queued <type> (N files)`，下载进度条随 tick 前进 |
| 6 | 查看磁盘条 | 文案 `Disk usage: X of 50.0 GB recommended (Y free)`，进度条按比例 |
| 7 | Tools ▸ Download Manager | 任务行显示 `# 名称 — 进度 — 状态`；状态行含百分比/速度/ETA |
| 8 | 点 `Start` → `Pause` | 状态行出现 `(paused)`，任务状态为 paused |
| 9 | 点 `Resume` | 任务重新进入 downloading/pending，速度恢复 |
| 10 | 切换 `Mirror` 下拉 | `applyMirror()` 生效（后续任务走所选镜像） |
| 11 | 点 `Cancel` | 队列取消并关闭对话框 |
| 12 | 主窗口生成完成后 | `Last output:` 显示输出路径；`Reveal` 打开文件管理器、`Play` 用默认应用播放 |
| 13 | Tools ▸ Node Editor / Export Workflow JSON | 画布可见；导出 `output/h3_default_workflow.json`（5 节点 / 6 连接 / 0 错误） |
| 14 | 各对话框开关多次 | 无崩溃；对象由 `GuiApp` 持有，不出现 native window 被回收 |

## 3. 阻塞项

| 项 | 原因 | 解除条件 |
|----|------|----------|
| US-007 NodeCanvas 画布渲染 | `qt_node_canvas_show()` 需 Qt SDK 编译，本环境无 Qt | 在有 Qt 6 的机器执行 `build_windows.bat` 后按步骤 13 验收 |
| US-011 缩略图预览 | 需新增 `qt_label_set_pixmap` 等 C++ 接口 | 新增 stub + `cpp-src/qt_bridge.cc` 实现后编译验证 |

## 4. 已知遗留（不阻塞）

- `php-cs-fixer` 报告 56/103 文件可修正（既有历史遗留，非本次改动引入），未批量修正以免影响无关文件。
- PHPStan baseline 中的 18 条既有发现（未使用属性、`DownloadTask::onProgress()` 误当方法调用、`main.php` 恒假比较等）建议后续单独清理。
