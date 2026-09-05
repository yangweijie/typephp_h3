# H3PHP — MiniMax-H3 视频生成引擎（PHP CLI）

一个用 PHP 实现的完整 MiniMax-H3 视频生成引擎 CLI 应用，可通过 [TypePHP](https://github.com/swoole/typephp) AOT 编译器编译为独立二进制文件。集成 C 参考实现（`libh3.a`）用于 Apple Silicon 上的真实模型推理。

[English](README.md) | 中文

## 功能特性

- **文本生成视频（FL2VA）**：从文本提示生成视频
- **参考生成视频（Ref2VA）**：使用图像/视频/音频参考生成视频
- **交互模式**：REPL 界面，支持 `!` 命令调整参数
- **Metal GPU 加速**：原生 Objective-C++ 代码，支持 Apple Silicon
- **C 库集成**：链接 `libh3.a` 实现生产级推理
- **真实模型权重**：加载 MiniMax-H3 safetensors（21GB transformer + VAE）
- **SSD 流式传输**：内存受限执行（模型 > 设备内存时）
- **六阶段流水线**：加载 → 条件编码 → DiT 去噪 → 解码 → 封装 → 超分辨率
- **独立二进制**：通过 TypePHP 编译，执行时无需 PHP 运行时

## 系统要求

- PHP 8.4+（开发用）
- TypePHP 0.6.8+（编译用）
- macOS 14+ / Apple Silicon（Metal GPU 执行）
- Xcode Command Line Tools
- FFmpeg（视频封装）
- [libh3.a](https://github.com/...) — C 参考实现静态库

## 快速开始

### 1. 构建 libh3.a（C 参考实现）

```bash
cd /path/to/h3.c
make libh3.a
```

### 2. 构建 H3PHP 二进制

```bash
# 安装 PHP 依赖
composer install

# 编译为独立二进制
./build_native.sh
# 或：composer run build
```

### 3. 运行

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
├── build_native.sh          # 构建脚本（编译 .mm + 链接 libh3.a）
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
│   │   └── ProcessRunner.php  # FFmpeg + 外部工具
│   ├── Generator/          # 生成流水线
│   │   ├── Pipeline.php    # 6 阶段编排（C 库桥接）
│   │   ├── TextToVideo.php # FL2VA 模式
│   │   ├── ReferenceToVideo.php  # Ref2VA 模式
│   │   └── Params.php      # 参数验证
│   ├── Encoder/            # 文本/视觉编码器（PHP 骨架）
│   ├── Inference/          # DiT + 采样（PHP 骨架）
│   ├── VAE/                # 视频/音频 VAE（PHP 骨架）
│   └── Metal/              # Metal GPU 包装器
├── cpp-src/                 # Objective-C++ 原生层
│   ├── metal_native.mm     # Metal 设备/缓冲区/流水线（ObjC++）
│   ├── h3_native.mm        # C 库桥接（libh3.a 包装器）
│   └── metal_native.o      # 编译对象（预构建）
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
PHP 源文件 + .mm Objective-C++ 源文件
        ↓
TypePHP AOT 编译器（nikic/php-parser → C++17）
        ↓
Clang（Metal 框架）+ libh3.a + 对象缓存
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

**总计：14 阶段，85 测试，619 断言**

## 参考

- [TypePHP](https://github.com/swoole/typephp) — PHP AOT 编译器
- [php-metal-gpu](https://github.com/phpolygon/php-metal-gpu) — PHP Metal GPU 扩展
- [h3.c](https://github.com/...) — MiniMax-H3 C 参考实现
- [MiniMax-H3](https://github.com/MiniMaxAI) — 原始模型
- [OpenVDN](https://github.com/...) — 开源 VDN-H3 实现

## 许可证

MIT
