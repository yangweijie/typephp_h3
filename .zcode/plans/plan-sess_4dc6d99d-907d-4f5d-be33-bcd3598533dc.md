# MiniMax-H3 PHP CLI 独立二进制 — 实施计划

## 项目概述

基于 TypePHP AOT 编译器，用 PHP + Objective-C++ (.mm) 实现 MiniMax-H3 推理引擎的完整功能，编译为独立二进制文件。最终不依赖外部 h3.c 项目。

## 架构设计

```
D:/git/php/typephp_h3/
├── project.yml                  # TypePHP 构建配置 (bin 模式, Metal 框架)
├── bin/
│   └── h3php.php               # CLI 入口 (composer bin)
├── php-src/                     # PHP 业务逻辑层
│   ├── main.php                # 主调度 (仿 compiler.php)
│   ├── Cli/
│   │   ├── Application.php     # CLImate 应用 + 参数解析
│   │   ├── Options.php         # 集中选项模式 (仿 COMPILER_OPTIONS)
│   │   ├── InteractiveSession.php  # 交互式 REPL (仿 h3_cli.c)
│   │   └── ProgressDisplay.php # 进度显示 (\r 回车更新)
│   ├── Core/
│   │   ├── H3Context.php       # 引擎上下文 (h3_ctx 等价)
│   │   ├── ModelLoader.php     # 模型目录验证 + safetensors 解析
│   │   ├── Config.php          # 配置管理 (project.yml 风格)
│   │   └── ProcessRunner.php   # ffmpeg 子进程管理
│   ├── Generator/
│   │   ├── TextToVideo.php     # FL2VA 文生视频流程
│   │   ├── ReferenceToVideo.php # Ref2VA 参考视频流程
│   │   ├── Pipeline.php        # 六阶段管线编排
│   │   └── Params.php          # h3_params 参数结构
│   ├── Encoder/
│   │   ├── TextEncoder.php     # Qwen3-VL 文本编码
│   │   ├── VisionEncoder.php   # Qwen 视觉塔
│   │   └── Tokenizer.php       # 分词器
│   ├── Inference/
│   │   ├── DiT.php             # 扩散变换器 (50-block)
│   │   ├── Sampler.php         # Euler 采样器
│   │   ├── Scheduler.php       # AdaLN 调度
│   │   └── LoRA.php            # LoRA 合并
│   ├── VAE/
│   │   ├── VideoVAE.php        # 视频 VAE 解码
│   │   └── AudioVAE.php        # 音频 VAE 编解码
│   └── Metal/
│       ├── Device.php          # Metal 设备 PHP 接口
│       ├── Buffer.php          # Metal 缓冲区 PHP 接口
│       ├── Pipeline.php        # 计算管线 PHP 接口
│       └── CommandQueue.php    # 命令队列 PHP 接口
├── cpp-src/                     # Objective-C++ 原生层
│   ├── metal_device.mm         # Metal 设备实现
│   ├── metal_buffer.mm         # Metal 缓冲区实现
│   ├── metal_pipeline.mm       # 计算管线 + MSL 内核
│   ├── metal_command_queue.mm  # 命令队列实现
│   ├── h3_dit_kernels.mm       # DiT Metal 计算内核
│   ├── h3_vae_kernels.mm       # VAE Metal 计算内核
│   ├── h3_encoder_kernels.mm   # 编码器 Metal 计算内核
│   ├── h3_mux.mm               # FFmpeg muxing 实现
│   └── stubs/
│       └── *.stub.php          # PHP 函数声明
├── stubs/                       # 原生函数 PHP 桩声明
│   ├── metal_device.stub.php
│   ├── metal_buffer.stub.php
│   ├── metal_pipeline.stub.php
│   └── h3_inference.stub.php
├── config/
│   └── defaults.yaml            # 默认配置
└── tests/                       # 测试
```

## 技术方案

### 1. TypePHP 构建集成 (project.yml)
```yaml
name: h3php
build-mode: bin
cxx-std: c++17
cxxflags: |
  -framework Metal
  -framework MetalKit
  -framework Foundation
  -framework Accelerate
ldflags: |
  -framework Metal
  -framework MetalKit
  -framework Foundation
  -framework Accelerate
sources:
  - php-src
  - cpp-src
  - bin/h3php.php
```

### 2. PHP/C++ 互操作模式
- **C++ → PHP**: 使用 `php_` 前缀 ABI 暴露 Metal 函数
- **PHP → C++**: 通过 stub 声明调用原生函数
- **对象生命周期**: Metal 对象用 `php::Box` 子类包装，PHP GC 管理
- **回调**: C++ 通过 `php::call()` 回调 PHP 进度函数

### 3. CLI 架构 (仿 aot-compiler)
- `league/climate` 处理参数解析 + 彩色输出
- `Options.php` 集中定义所有 CLI 标志
- `main()` 函数子命令分发：`--info` / one-shot / interactive
- 进度显示解析 h3.c 的 `\r%-25s %4d/%-4d` 格式

### 4. 交互式会话 (仿 h3_cli.c)
- `h3>` 提示符 + linenoise 行编辑
- `!seed`, `!steps`, `!size`, `!reuse`, `!layers` 等内部命令
- `!first`, `!last`, `!ref-image` 参考图管理
- `!again`, `!cache`, `!memory-plan` 高级功能

## 分阶段实施

### Phase 1: 项目骨架 + CLI 框架 (基础)
- [ ] 创建 project.yml (bin 模式, Metal 框架链接)
- [ ] 创建 bin/h3php.php 入口 + bootstrap
- [ ] 实现 Cli/Application.php (CLImate 初始化)
- [ ] 实现 Cli/Options.php (集中选项定义, 完整 h3 参数)
- [ ] 实现 main() 调度: --info / one-shot / interactive 三种模式
- [ ] 实现 --info 模式: 设备信息 + 模型目录验证
- [ ] 实现 --help 输出

### Phase 2: Metal GPU 基础层
- [ ] cpp-src/metal_device.mm — 设备枚举 + 能力查询
- [ ] cpp-src/metal_buffer.mm — 缓冲区创建 + 数据传输
- [ ] cpp-src/metal_command_queue.mm — 命令队列 + 编码器
- [ ] cpp-src/metal_pipeline.mm — 计算管线 + MSL 内核编译
- [ ] 对应 stubs/*.stub.php 声明
- [ ] php-src/Metal/*.php 包装类

### Phase 3: 推理引擎核心
- [ ] php-src/Core/ModelLoader.php — safetensors 解析 + 权重映射
- [ ] php-src/Encoder/Tokenizer.php — 分词器
- [ ] php-src/Encoder/TextEncoder.php — Qwen3-VL 文本编码
- [ ] php-src/Encoder/VisionEncoder.php — 视觉塔
- [ ] php-src/Inference/DiT.php — 50-block 扩散变换器
- [ ] php-src/Inference/Sampler.php — Euler 采样器
- [ ] php-src/Inference/Scheduler.php — AdaLN 调度
- [ ] cpp-src/h3_dit_kernels.mm — DiT Metal 内核 (attention, MLP, etc.)

### Phase 4: VAE + 输出管线
- [ ] php-src/VAE/VideoVAE.php — 视频 VAE 解码
- [ ] php-src/VAE/AudioVAE.php — 音频 VAE 编解码
- [ ] cpp-src/h3_vae_kernels.mm — VAE Metal 内核
- [ ] php-src/Core/ProcessRunner.php — FFmpeg 子进程
- [ ] cpp-src/h3_mux.mm — H.264 + AAC MP4 muxing

### Phase 5: 生成流程 + 交互模式
- [ ] php-src/Generator/TextToVideo.php — FL2VA 完整流程
- [ ] php-src/Generator/ReferenceToVideo.php — Ref2VA 完整流程
- [ ] php-src/Generator/Pipeline.php — 六阶段编排
- [ ] php-src/Cli/InteractiveSession.php — 交互式 REPL
- [ ] 实现所有 ! 内部命令

### Phase 6: 高级功能 + 优化
- [ ] php-src/Inference/LoRA.php — LoRA 合并
- [ ] 加速策略: reuse, core-reuse, layer pruning, token reduction
- [ ] SSD streaming 权重流式加载
- [ ] int8 TensorOps 路径
- [ ] 内存规划器 (memory planner)
- [ ] 超分辨率 (Real-ESRGAN 集成)
- [ ] 配置文件支持 (config/defaults.yaml)

## 关键设计决策

| 决策 | 选择 | 理由 |
|------|------|------|
| CLI 框架 | league/climate | aot-compiler 验证过的成熟方案 |
| 选项定义 | 集中 Options 数组 | 单一真相源, 驱动解析 + 补全 + help |
| C++ 互操作 | php_ ABI + php::Box | TypePHP 原生支持, GC 安全 |
| Metal 代码 | .mm Objective-C++ | TypePHP 原生支持, 直接访问 Metal API |
| 进度显示 | stderr \r 更新 | 与 h3.c 格式兼容 |
| 交互模式 | PHP 实现 linenoise | 避免额外 C 依赖, PHP 流式 I/O |
| 构建输出 | 单文件可执行文件 | TypePHP bin 模式, 嵌入 PHP 运行时 |

## 风险与注意事项

1. **规模**: h3.c 是生产级 ML 引擎, 完整移植工作量极大 (估计人月级别)
2. **Metal 内核**: MSL 着色器需要手动移植为 Metal 内核
3. **性能**: PHP 层编排 + C++ 层计算, 需要精心设计边界避免频繁跨语言调用
4. **平台限制**: macOS Apple Silicon 独占
5. **TypePHP 版本**: 需要 PHP 8.4+ 和 TypePHP 0.6.8

## 下一步

从 Phase 1 开始实施: 创建项目骨架、project.yml 配置、CLI 框架和入口点。