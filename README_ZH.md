# H3PHP — MiniMax-H3 视频生成引擎（PHP CLI）

一个用 PHP 实现的完整 MiniMax-H3 视频生成引擎 CLI 应用，可通过 [TypePHP](https://github.com/swoole/typephp) AOT 编译器编译为独立二进制文件。支持 macOS（Metal）、Windows（CUDA/ComfyUI）、Linux（CUDA/ROCm/ComfyUI）三大平台。

[English](README.md) | 中文

## 功能特性

- **文本生成视频（FL2VA）**：从文本提示生成视频
- **参考生成视频（Ref2VA）**：使用图像/视频/音频参考生成视频
- **交互模式**：REPL 界面，支持 `!` 命令调整参数
- **跨平台**：macOS（Metal）、Windows（CUDA/ComfyUI）、Linux（CUDA/ROCm/ComfyUI）
- **Qt 6 图形界面**：跨平台 GUI，内置节点编辑器
- **Metal GPU 加速**：原生 Objective-C++ 代码，支持 Apple Silicon
- **CUDA/ROCm 支持**：通过 ComfyUI 后端支持 NVIDIA/AMD GPU
- **C 库集成**：链接 `libh3.a` 实现生产级推理
- **真实模型权重**：加载 MiniMax-H3 safetensors（21GB transformer + VAE）
- **SSD 流式传输**：内存受限执行（模型 > 设备内存时）
- **六阶段流水线**：加载 → 条件编码 → DiT 去噪 → 解码 → 封装 → 超分辨率
- **独立二进制**：通过 TypePHP 编译，执行时无需 PHP 运行时
- **ModelScope 下载**：通过 CLI/Python SDK/Git LFS 完整下载模型仓库，支持断点续传
- **硬件推荐**：自动检测硬件并推荐最优模型配置
- **下载预设**：最小化 / 标准 / 完整 三种配置适配不同硬件
- **多源下载**：HuggingFace、ModelScope、CivitAI、GitHub，支持镜像加速
- **Xget 加速**：Cloudflare Worker 代理，为中国用户提供高速下载

## 系统要求

### 所有平台
- PHP 8.5+（开发 / 解释模式）
- TypePHP AOT 编译器（`tpc`）— 通过 Composer 安装
- FFmpeg（视频封装）
- 8GB+ 内存（推荐 16GB+）
- 60GB+ 可用磁盘空间（模型）

### macOS（Native H3 后端）
- macOS 14+（推荐 Apple Silicon）
- Xcode Command Line Tools
- [libh3.a](https://github.com/...) — C 参考实现静态库

### Windows（ComfyUI 后端）
- Windows 10/11（64 位）
- Visual Studio 2022（MSVC v143）或 Build Tools
- Qt 6.8.0（msvc2022_64）
- Python 3.10+（ComfyUI 后端）
- CUDA Toolkit 12+（NVIDIA GPU）

### Linux（ComfyUI 后端）
- Ubuntu 22.04+ / Fedora 38+
- gcc 11+（C++17 支持）
- Qt 6 开发包（`qt6-base-dev`）
- Python 3.10+（ComfyUI 后端）
- CUDA Toolkit 或 ROCm（GPU 加速）

## 快速开始

### 开发模式（无需构建）

```bash
# 安装 PHP 依赖
composer install

# 直接通过 PHP 解释器运行
php bin/h3php.php -d /path/to/MiniMax-H3 --info

# 单次生成
php bin/h3php.php -d /path/to/MiniMax-H3 \
    -p "一只红狐穿过松树林中的新雪。" \
    --width 256 --height 256 --frames 25 --steps 3 \
    -o output.mp4
```

### 构建独立二进制

#### macOS

```bash
# 1. 构建 libh3.a（C 参考实现）
cd /path/to/h3.c
make libh3.a

# 2. 安装依赖
composer install

# 3. 构建独立二进制
./build_native.sh /path/to/h3.c
# 或：H3_C_DIR=/path/to/h3.c composer run build

# 4. 运行
./h3php -d /path/to/MiniMax-H3 --info
```

#### Windows

```bat
:: 1. 安装依赖
composer install

:: 2. 构建独立二进制（Qt + MSVC）
build_windows.bat

:: 带 H3 C 库：
build_windows.bat C:\path\to\h3.c

:: 3. 运行
h3php.exe -d C:\path\to\MiniMax-H3 --info
```

**Windows 环境变量：**
| 变量 | 默认值 | 说明 |
|------|--------|------|
| `QT_DIR` | `C:\Qt\6.8.0\msvc2022_64` | Qt 安装路径 |
| `H3_C_DIR` | — | H3 C 库路径（可选） |

#### Linux

```bash
# 1. 安装依赖
composer install

# 2. 构建独立二进制（Qt + gcc）
chmod +x build_linux.sh
./build_linux.sh

# 带 H3 C 库：
./build_linux.sh /path/to/h3.c

# 3. 运行
./h3php -d /path/to/MiniMax-H3 --info
```

**Linux 环境变量：**
| 变量 | 默认值 | 说明 |
|------|--------|------|
| `QT_DIR` | `/usr` | Qt 安装路径 |
| `H3_C_DIR` | — | H3 C 库路径（可选） |

### 运行

```bash
# 查看设备和模型信息
./h3php -d /path/to/MiniMax-H3-Convrot --info

# 单次生成（256×256，3 步）
./h3php -d /path/to/MiniMax-H3-Convrot \
    -p "一只红狐穿过松树林中的新雪。" \
    --width 256 --height 256 --frames 25 --steps 3 \
    -o output.mp4

# 全质量生成（864×480，20 步）
./h3php -d /path/to/MiniMax-H3-Convrot \
    -p "海洋上美丽的日落。" \
    --width 864 --height 480 --frames 56 --steps 20 \
    -o output.mp4

# 交互模式
./h3php -d /path/to/MiniMax-H3-Convrot --width 512 --height 512 --steps 6
```

## 模型配置

### 目录结构

```
MiniMax-H3-Convrot/
+-- FL2VA/
|   +-- transformer/
|   |   +-- config.json
|   |   +-- minimax_h3_fastvideo_4step.safetensors  (21 GB)
|   |   +-- time_embedder.safetensors  (60 MB)
|   +-- tokenizer/
|   |   +-- tokenizer.json
|   +-- video_vae/
|   |   +-- source/model.safetensors  (4.8 GB)
|   +-- audio_vae/
|       +-- model.safetensors  (577 MB)
```

### ClipProj 文本编码器（外部）

设置环境变量或使用默认值：
```bash
export H3_CLIPPROJ_DIR=/path/to/Qwen3-VL-4B-Instruct-int8-convrot
export H3_CLIPPROJ_PROJ=/path/to/ClipProj-MiniMax-H3
```

## 项目结构

```
typephp_h3/
├── project.yml              # TypePHP 构建配置
├── build_native.sh          # macOS 构建脚本（Metal + libh3.a）
├── build_windows.bat        # Windows 构建脚本（Qt + MSVC）
├── build_linux.sh           # Linux 构建脚本（Qt + gcc）
├── composer.json            # PHP 依赖
├── h3_shaders.metal         # Metal 计算着色器（来自 C 参考实现）
├── bin/
│   ├── bootstrap.php        # 自动加载器 + 常量
│   └── h3php.php           # CLI 入口
├── php-src/                 # PHP 业务逻辑
│   ├── main.php            # 主调度
│   ├── h3.stub.php         # C 库桥接桩
│   ├── metal.stub.php      # Metal 原生函数桩
│   ├── Cli/                # CLI 框架
│   │   ├── Application.php # 原生 CLI（参数解析 + 样式化输出）
│   │   ├── Options.php     # 集中式选项模式
│   │   ├── InteractiveSession.php  # REPL 模式
│   │   └── ProgressDisplay.php     # 进度渲染
│   ├── Core/               # 引擎核心
│   │   ├── H3Context.php   # 引擎生命周期
│   │   ├── ModelLoader.php # 模型验证
│   │   ├── ModelLayout.php # 清单解析
│   │   ├── ProcessRunner.php  # FFmpeg + 外部工具
│   │   ├── DownloadManager.php  # 统一下载调度器
│   │   ├── DownloadQueue.php    # 多连接并发下载
│   │   ├── DownloadTask.php     # 单任务下载（断点续传 + 重试）
│   │   ├── ModelScopeDownloader.php  # ModelScope CLI/SDK/Git LFS
│   │   ├── ModelRecommender.php  # 基于硬件的模型推荐
│   │   ├── DownloadPreset.php    # 模型下载预设
│   │   ├── ModelComponent.php    # 组件元数据 + 校验
│   │   ├── EnvironmentDetector.php  # 硬件/软件检测
│   │   ├── SettingsManager.php   # 持久化设置
│   │   └── ThemeManager.php      # UI 主题管理
│   ├── Generator/          # 生成流水线
│   │   ├── Pipeline.php    # 6 阶段编排
│   │   ├── TextToVideo.php # FL2VA 模式
│   │   ├── ReferenceToVideo.php  # Ref2VA 模式
│   │   └── Params.php      # 参数验证
│   ├── Encoder/            # 文本/视觉编码器
│   ├── Inference/          # DiT + 采样
│   ├── VAE/                # 视频/音频 VAE
│   ├── Metal/              # Metal GPU 包装器
│   ├── Qt/                 # Qt GUI 包装器
│   └── Testing/            # 测试辅助（构建时排除）
├── cpp-src/                 # C++ 原生层
│   ├── metal_native.mm     # Metal 设备/缓冲区/流水线（ObjC++）
│   ├── h3_native.mm        # C 库桥接（libh3.a 包装器）
│   ├── qt_bridge.cc        # Qt ↔ PHP 桥接（不透明句柄）
│   └── qt_node_editor.cc   # 节点编辑器（QGraphicsView）
├── python-src/              # Python 桥接脚本
│   ├── comfyui_bridge.py    # ComfyUI JSON 协议桥接
│   └── modelscope_bridge.py # ModelScope 下载桥接
├── stubs/                   # FFI 桩声明
├── config/
│   └── defaults.yaml        # 默认配置
└── tests/                   # PHPUnit 测试（85 测试，619 断言）
```

## CLI 用法

```
h3php -d MODEL_DIR -p "提示" [选项]     # 单次生成
h3php -d MODEL_DIR [选项]                # 交互模式
h3php -d MODEL_DIR --info                # 设备 + 模型信息
h3php --help                             # 显示用法
```

### 主要选项

| 标志 | 默认值 | 说明 |
|------|--------|------|
| `-d PATH` | — | 模型目录（必需） |
| `-p TEXT` | — | 提示（触发单次模式） |
| `-o PATH` | outputs/h3.mp4 | 输出 MP4 路径 |
| `--width N` | 864 | 输出宽度（32 的倍数） |
| `--height N` | 480 | 输出高度（32 的倍数） |
| `--frames N` | 56 | 帧数（22-362） |
| `--steps N` | 20 | 去噪步数（1-1000） |
| `--reuse N` | 1 | 去噪器复用（1=质量，3=快速） |
| `--layers N` | 50 | DiT 块数（50=精确，40=快速） |
| `--core-reuse N` | 1 | 核心刷新间隔 |
| `--seed N` | 42 | 随机种子 |
| `--ssd-streaming` | — | 启用 SSD 权重流式传输 |
| `--sr` | — | 启用超分辨率 |
| `--info` | — | 设备 + 模型信息 |

## 交互命令

| 命令 | 说明 |
|------|------|
| `!help` | 显示所有命令 |
| `!status` | 显示当前设置 |
| `!seed [N\|random]` | 设置/显示种子 |
| `!steps [N]` | 去噪步数（1-1000） |
| `!reuse [N]` | 去噪器复用（1-32） |
| `!layers [N]` | DiT 块数（35-50） |
| `!size [WxH]` | 输出尺寸 |
| `!frames [N]` | 帧数 |
| `!seconds [N]` | 24fps 下的时长 |
| `!token-reduction [on\|off]` | 切换 token 缩减 |
| `!ssd-streaming [on\|off]` | 切换 SSD 流式传输 |
| `!first [PATH\|clear]` | 首帧条件 |
| `!last [PATH\|clear]` | 末帧条件 |
| `!ref-image PATH` | 添加图像参考 |
| `!refs [clear]` | 列出/清除参考 |
| `!again` | 重复上次提示 |
| `!cache [clear]` | 显示/清除缓存 |
| `!memory-plan [auto\|off]` | 内存规划 |
| `!quit` | 退出会话 |

## 架构

### 构建流水线

```
PHP 源文件 + C++/ObjC++ 源文件
        ↓
TypePHP AOT 编译器（nikic/php-parser → C++17）
        ↓
┌─────────────────────────────────────────────┐
│ 平台特定链接器：                              │
│   macOS: Clang + Metal + libh3.a            │
│   Windows: MSVC + Qt 6 + CUDA（可选）       │
│   Linux: gcc + Qt 6 + CUDA/ROCm（可选）     │
└─────────────────────────────────────────────┘
        ↓
独立可执行文件（嵌入式 PHP 运行时）
```

### 生成流水线（六阶段）

1. **加载**：通过 `h3_load_dir()` 加载模型 — 验证结构，探测 Metal 设备
2. **条件编码**：分词 + 编码文本（Qwen3-VL-4B via ClipProj）
3. **DiT 去噪**：50 块扩散 transformer，Metal GPU 执行（Euler 步）
4. **解码**：视频 VAE（分块 CNN）→ RGB 帧 + 音频 VAE → PCM
5. **封装**：FFmpeg H.264 + AAC → MP4
6. **超分辨率**：可选 Real-ESRGAN 放大

### C 库桥接

```
PHP Pipeline.php → h3_model_load/generate/free()
        ↓
cpp-src/h3_native.mm（ObjC++ 桥接，C++ 链接）
        ↓
libh3.a（C 参考实现）
        ├── h3.c — 主推理循环
        ├── h3_gpu.m — Metal 命令编码
        ├── h3_safetensors.c — 权重加载
        ├── h3_dit.c — DiT 前向传播
        ├── h3_video_vae.c — VAE 解码
        └── h3_ffmpeg.c — FFmpeg 封装
```

### 内存管理

| 组件 | 大小 | 流式策略 |
|------|------|----------|
| Transformer（50 块） | ~21 GB | SSD 流式（仅 2 块驻留） |
| 视频 VAE | ~4.8 GB | 权重流式 |
| 音频 VAE | ~577 MB | 完全驻留 |
| 文本编码器（ClipProj） | ~4.6 GB | 条件编码后释放 |
| **峰值（M4 16GB）** | **~2 GB** | ✅ 适配统一内存 |

### C++ 互操作

- **PHP → C++**：`php_` 前缀函数，在 `.stub.php` 文件中声明
- **C++ → PHP**：`php::call()` 用于回调（进度、帧传递）
- **对象生命周期**：不透明 `Int` 句柄（Metal）+ 句柄表（C 库）
- **字符串处理**：`php::String.data()` 获取 `const char*` 访问

## 性能

| 设备 | 分辨率 | 步数 | 帧数 | 时间 | FPS |
|------|--------|------|------|------|-----|
| Apple M4 (16GB) | 256×256 | 3 | 25 | 1:15 | ~0.3 |
| Apple M4 (16GB) | 256×256 | 20 | 25 | ~10分钟 | ~0.04 |
| Apple M4 (16GB) | 864×480 | 20 | 56 | ~30分钟 | ~0.03 |

*瓶颈：SSD 权重流式 I/O + 文本编码*

## 下载与模型管理

### 模型下载源

| 来源 | 方式 | LFS | 断点续传 | 速度 |
|------|------|-----|----------|------|
| ModelScope CLI | `modelscope download` | ✅ | ✅ | 快 |
| ModelScope SDK | `snapshot_download()` | ✅ | ✅ | 快 |
| Git LFS | `git clone` | ✅ | ✅ | 中 |
| HuggingFace | 直连 HTTP | ❌ | ✅ | 视网络而定 |

### 下载预设

| 预设 | 显存 | 内存 | 大小 | 音频 | 适用场景 |
|------|------|------|------|------|----------|
| 最小化 | 4GB | 8GB | ~25GB | ❌ | 基础文本生成视频 |
| 标准 | 8GB | 16GB | ~26GB | ❌ | + 图像参考 |
| 完整 | 16GB | 32GB | ~26GB | ✅ | + 音频合成 |

### ModelScope 下载

```bash
# 安装 ModelScope CLI（推荐）
pip install modelscope

# 下载模型仓库
modelscope download --model="Qwen/Qwen2.5-0.5B-Instruct" --local_dir ./model-dir

# 或使用 Python SDK
python -c "from modelscope import snapshot_download; snapshot_download('Qwen/Qwen2.5-0.5B-Instruct')"

# 或使用 Git LFS
git lfs install
git clone https://www.modelscope.cn/Qwen/Qwen2.5-0.5B-Instruct.git
```

### Xget 镜像（中国加速）

为中国用户启用 Xget Cloudflare Worker 镜像加速下载：

```php
// 在 PHP 代码中
$dm = new DownloadManager();
$dm->useXgetMirror();  // 使用 https://xget.dev/hf-mirror 加速 HuggingFace
```

直接使用 Xget CLI：
```bash
# HuggingFace 通过 Cloudflare 加速
xget hf://models/Qwen/Qwen3-VL-2B

# ModelScope 通过 Cloudflare 加速
xget ms://models/Qwen/Qwen3-VL-2B
```

## 实现阶段

| 阶段 | 状态 | 说明 |
|------|------|------|
| 1 | ✅ | 项目骨架 + CLI 框架 |
| 2 | ✅ | Metal GPU 基础 |
| 3 | ✅ | 推理引擎核心（DiT、编码器） |
| 4 | ✅ | VAE + 输出流水线 |
| 5 | ✅ | 生成 + 交互模式 |
| 6 | ✅ | 高级功能（LoRA、SR、优化） |
| 7 | ✅ | MSL 内核 + 测试 + 构建 |
| 8 | ✅ | 代码审查修复 |
| 9 | ✅ | 性能优化 |
| 10 | ✅ | VDN-H3 研究与集成 |
| 11 | ✅ | 混合注意力架构 |
| 12 | ✅ | 依赖移除（CLImate + symfony/yaml） |
| 13 | ✅ | Metal 原生层 |
| 14 | ✅ | C 库集成（libh3.a） |
| 18 | ✅ | 后端抽象层（Native H3 / ComfyUI / HTTP） |
| 19 | ✅ | Python FFI 集成 |
| 20 | ✅ | Qt GUI 基础 |
| 21 | ✅ | 节点编辑器（QGraphicsView） |
| 22 | ✅ | ComfyUI 工作流集成 |
| 23 | ✅ | 跨平台构建系统 |
| 24 | ✅ | 模型管理器（统一发现） |
| 25 | ✅ | 下载管理器（多源） |
| 26 | ✅ | 环境检测与设置向导 |
| 27 | ✅ | 模型管理器 UI |
| 28 | ✅ | 测试与文档 |
| 29 | ✅ | 下载优化（ModelScope CLI/SDK + 推荐） |

**总计：29 阶段，85 测试，619 断言**

## 参考

- [TypePHP](https://github.com/swoole/typephp) — PHP AOT 编译器
- [php-metal-gpu](https://github.com/phpolygon/php-metal-gpu) — PHP Metal GPU 扩展
- [h3.c](https://github.com/...) — MiniMax-H3 C 参考实现
- [MiniMax-H3](https://github.com/MiniMaxAI) — 原始模型
- [OpenVDN](https://github.com/...) — 开源 VDN-H3 实现
- [ModelScope](https://modelscope.cn) — 模型仓库，提供 CLI/SDK 下载工具
- [ComfyUI](https://github.com/comfyanonymous/ComfyUI) — 跨平台扩散 GUI
- [Xget](https://github.com/xget-dev/xget) — Cloudflare Worker 下载加速器
- [transformers-torch-php](https://github.com/SyncFly/transformers-torch-php) — PHP 桥接 transformer 模型下载

## 许可证

MIT
