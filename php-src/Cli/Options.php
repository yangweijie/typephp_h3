<?php

/**
 * H3PHP — Centralized CLI Option Schema.
 *
 * Single source of truth for all CLI flags. Drives:
 *   - argument parsing (see Application::parse())
 *   - --help output generation
 *   - Input validation
 *
 * Follows the pattern from aot-compiler's COMPILER_OPTIONS.
 */

namespace H3Php\Cli;

class Options
{
    /**
     * Complete option definitions, consumed by Application::parse().
     *
     * Each option: [prefix, longPrefix, description, castTo, defaultValue, noValue, multiple]
     *
     * @var array<string, array<string, mixed>>
     */
    public const array ALL = [
        // === Required / Primary ===
        'model-dir' => [
            'prefix' => 'd',
            'longPrefix' => 'model-dir',
            'description' => 'MiniMax-H3 local model directory (required)',
        ],
        'prompt' => [
            'prefix' => 'p',
            'longPrefix' => 'prompt',
            'description' => 'Generation prompt (triggers one-shot mode)',
        ],
        'output' => [
            'prefix' => 'o',
            'longPrefix' => 'output',
            'description' => 'Output MP4 path (one-shot mode)',
            'defaultValue' => 'outputs/h3.mp4',
        ],

        // === Model Layout ===
        'model-manifest' => [
            'longPrefix' => 'model-manifest',
            'description' => 'YAML overriding per-component paths (e.g. weights on SSD)',
        ],

        // === Canvas / Timing ===
        'width' => [
            'longPrefix' => 'width',
            'description' => 'Output width (multiple of 32)',
            'castTo' => 'int',
            'defaultValue' => 864,
        ],
        'height' => [
            'longPrefix' => 'height',
            'description' => 'Output height (multiple of 32)',
            'castTo' => 'int',
            'defaultValue' => 480,
        ],
        'render-width' => [
            'longPrefix' => 'render-width',
            'description' => 'Internal model render width (optional, lower = faster)',
            'castTo' => 'int',
            'defaultValue' => 0,
        ],
        'render-height' => [
            'longPrefix' => 'render-height',
            'description' => 'Internal model render height (optional, lower = faster)',
            'castTo' => 'int',
            'defaultValue' => 0,
        ],
        'frames' => [
            'longPrefix' => 'frames',
            'description' => 'Requested frame count (mutually exclusive with --seconds)',
            'castTo' => 'int',
            'defaultValue' => 56,
        ],
        'seconds' => [
            'longPrefix' => 'seconds',
            'description' => 'Duration in seconds at 24fps (converted to aligned frames)',
            'castTo' => 'float',
            'defaultValue' => 0,
        ],

        // === Sampling / Quality ===
        'steps' => [
            'longPrefix' => 'steps',
            'description' => 'Denoising passes (1-1000)',
            'castTo' => 'int',
            'defaultValue' => 20,
        ],
        'reuse' => [
            'longPrefix' => 'reuse',
            'description' => 'Denoiser reuse: 1=quality, 2=fast, 3=aggressive',
            'castTo' => 'int',
            'defaultValue' => 1,
        ],
        'layers' => [
            'longPrefix' => 'layers',
            'description' => 'Active DiT blocks: 50=exact, 45=fast, 40=aggressive (min 35)',
            'castTo' => 'int',
            'defaultValue' => 50,
        ],
        'core-reuse' => [
            'longPrefix' => 'core-reuse',
            'description' => 'Core refresh interval: 1=exact, 4=fast, 6=aggressive',
            'castTo' => 'int',
            'defaultValue' => 1,
        ],

        // === Speed / Optimization Switches ===
        'token-reduction' => [
            'longPrefix' => 'token-reduction',
            'description' => 'Pair video tokens in middle DiT blocks (alters composition)',
            'noValue' => true,
            'defaultValue' => false,
        ],
        'ssd-streaming' => [
            'longPrefix' => 'ssd-streaming',
            'description' => 'Stream BF16 DiT layers from SSD (reduces memory)',
            'noValue' => true,
            'defaultValue' => false,
        ],
        'use-int8-row-fc2' => [
            'longPrefix' => 'use-int8-row-fc2',
            'description' => 'Faster one-scale int8 FC2 (requires M5 Metal 4)',
            'noValue' => true,
            'defaultValue' => false,
        ],
        'use-reference-rope' => [
            'longPrefix' => 'use-reference-rope',
            'description' => 'Disable native 256 RoPE adaptation',
            'noValue' => true,
            'defaultValue' => false,
        ],

        // === Precision Switches (for parity testing) ===
        'use-slower-bf16-mlp' => [
            'longPrefix' => 'use-slower-bf16-mlp',
            'description' => 'Force close-reference BF16/MPS MLP',
            'noValue' => true,
            'defaultValue' => false,
        ],
        'use-slower-bf16-qkv' => [
            'longPrefix' => 'use-slower-bf16-qkv',
            'description' => 'Force close-reference BF16 QKV',
            'noValue' => true,
            'defaultValue' => false,
        ],
        'use-slower-bf16-attention-output' => [
            'longPrefix' => 'use-slower-bf16-attention-output',
            'description' => 'Force BF16 attention output',
            'noValue' => true,
            'defaultValue' => false,
        ],

        // === LoRA ===
        'lora' => [
            'longPrefix' => 'lora',
            'description' => 'Path to Turbo/distillation LoRA to merge into DiT',
        ],

        // === Seed ===
        'seed' => [
            'longPrefix' => 'seed',
            'description' => 'Random seed (default: 42 for one-shot, random for interactive)',
            'castTo' => 'int',
            'defaultValue' => 42,
        ],

        // === Conditioning / References ===
        'first-frame' => [
            'longPrefix' => 'first-frame',
            'description' => 'First-frame conditioning image (FL2VA path)',
        ],
        'last-frame' => [
            'longPrefix' => 'last-frame',
            'description' => 'Last-frame conditioning image (FL2VA path)',
        ],
        'ref-image' => [
            'longPrefix' => 'ref-image',
            'description' => 'Ordered Ref2VA image reference (repeatable, max 9)',
            'multiple' => true,
        ],
        'ref-image-size' => [
            'longPrefix' => 'ref-image-size',
            'description' => 'Image sizing: match (default) or max',
            'defaultValue' => 'match',
        ],
        'ref-video' => [
            'longPrefix' => 'ref-video',
            'description' => 'Video reference including embedded audio (repeatable)',
            'multiple' => true,
        ],
        'ref-silent-video' => [
            'longPrefix' => 'ref-silent-video',
            'description' => 'Video reference without audio (repeatable)',
            'multiple' => true,
        ],
        'ref-audio' => [
            'longPrefix' => 'ref-audio',
            'description' => 'Standalone audio clip reference (repeatable)',
            'multiple' => true,
        ],

        // === Output / Display ===
        'frames-dir' => [
            'longPrefix' => 'frames-dir',
            'description' => 'Write generated frames as PPM files to directory',
        ],
        'show' => [
            'longPrefix' => 'show',
            'description' => 'Display frame after each denoising step (Kitty/Ghostty/iTerm2)',
            'noValue' => true,
            'defaultValue' => false,
        ],
        'zoom' => [
            'longPrefix' => 'zoom',
            'description' => 'Terminal image zoom factor',
            'castTo' => 'int',
            'defaultValue' => 2,
        ],

        // === Info / Profiling ===
        'profile' => [
            'longPrefix' => 'profile',
            'description' => 'Print per-phase Metal timing and allocation data',
            'noValue' => true,
            'defaultValue' => false,
        ],
        'info' => [
            'longPrefix' => 'info',
            'description' => 'Inspect model/device without mapping weights',
            'noValue' => true,
            'defaultValue' => false,
        ],

        // === Super-Resolution ===
        'sr' => [
            'longPrefix' => 'sr',
            'description' => 'Enable Real-ESRGAN super-resolution of output',
            'noValue' => true,
            'defaultValue' => false,
        ],
        'sr-bin' => [
            'longPrefix' => 'sr-bin',
            'description' => 'Directory containing realesrgan-ncnn-vulkan binary',
        ],
        'sr-model-dir' => [
            'longPrefix' => 'sr-model-dir',
            'description' => 'Real-ESRGAN models directory',
        ],
        'sr-model' => [
            'longPrefix' => 'sr-model',
            'description' => 'SR model name',
            'defaultValue' => 'realesrgan-x4plus',
        ],
        'sr-target' => [
            'longPrefix' => 'sr-target',
            'description' => 'Final resolution after SR (WxH)',
        ],
        'sr-scale' => [
            'longPrefix' => 'sr-scale',
            'description' => 'Upscale factor when no --sr-target (2-4)',
            'castTo' => 'int',
            'defaultValue' => 4,
        ],

        // === Help ===
        'help' => [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Show this help message',
            'noValue' => true,
            'defaultValue' => false,
        ],
    ];

    /**
     * Get options grouped by category for help display.
     */
    public static function getCategories(): array
    {
        return [
            'Primary' => ['model-dir', 'prompt', 'output'],
            'Model Layout' => ['model-manifest'],
            'Canvas/Timing' => ['width', 'height', 'render-width', 'render-height', 'frames', 'seconds'],
            'Sampling/Quality' => ['steps', 'reuse', 'layers', 'core-reuse'],
            'Speed/Optimization' => ['token-reduction', 'ssd-streaming', 'use-int8-row-fc2', 'use-reference-rope'],
            'LoRA' => ['lora'],
            'Seed' => ['seed'],
            'Conditioning/References' => ['first-frame', 'last-frame', 'ref-image', 'ref-video', 'ref-silent-video', 'ref-audio'],
            'Output/Display' => ['frames-dir', 'show', 'zoom'],
            'Info/Profiling' => ['profile', 'info'],
            'Super-Resolution' => ['sr', 'sr-bin', 'sr-model-dir', 'sr-model', 'sr-target', 'sr-scale'],
            'Help' => ['help'],
        ];
    }

    /**
     * Return all option definitions with a loose element type.
     *
     * NOTE: not named `all()` because TypePHP mangles class constants and
     * methods into the same lowercase C++ symbol space; `ALL` + `all` would
     * collide (const vs method) and fail to compile.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return self::ALL;
    }

    /**
     * Get the default value for an option.
     */
    public static function getDefault(string $name): mixed
    {
        return self::ALL[$name]['defaultValue'] ?? null;
    }

    /**
     * Check if an option is a boolean flag (noValue).
     */
    public static function isFlag(string $name): bool
    {
        return self::ALL[$name]['noValue'] ?? false;
    }
}
