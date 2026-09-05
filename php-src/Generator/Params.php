<?php

/**
 * H3PHP — Generation Parameters.
 *
 * Parameter structure equivalent to h3_params in h3.h.
 * Holds all settings for a single generation request.
 *
 * Default values match h3.c's H3_PARAMS_DEFAULT.
 */

namespace H3Php\Generator;

class Params
{
    // Canvas
    public int $width = 864;
    public int $height = 480;
    public int $renderWidth = 0;
    public int $renderHeight = 0;
    public int $frames = 56;
    public float $seconds = 0;

    // Sampling
    public int $steps = 20;
    public int $denoiseReuse = 1;
    public int $ditLayers = 50;
    public int $coreReuse = 1;

    // Optimization
    public bool $tokenReduction = false;
    public bool $ssdStreaming = false;
    public bool $useInt8RowFc2 = false;
    public bool $useReferenceRope = false;

    // Precision (for parity testing)
    public bool $useSlowerBf16Mlp = false;
    public bool $useSlowerBf16Qkv = false;
    public bool $useSlowerBf16AttentionOutput = false;
    public bool $useSlowerRowMajorAttentionOutput = false;
    public bool $useSlowerUnfusedInt8Inputs = false;
    public bool $useSlowerUnfusedQkvRope = false;
    public bool $useSlowerScalarQkvRms = false;
    public bool $useSlowerUncachedInt8Scales = false;
    public bool $useSlowerDynamicFc1K = false;
    public bool $useSlowerGroupedQuantizer = false;

    // Streaming / Memory
    public bool $videoVaeStreaming = false;
    public bool $encoderStreaming = false;

    // Seed
    public int $seed = 42;

    // Conditioning
    public ?string $firstFrame = null;
    public ?string $lastFrame = null;
    public array $refImages = [];
    public array $refVideos = [];
    public array $refAudios = [];

    // LoRA
    public ?string $loraPath = null;

    // Output
    public string $outputPath = 'outputs/h3.mp4';
    public ?string $framesDir = null;
    public bool $show = false;
    public int $zoom = 2;

    // Profiling
    public bool $profile = false;

    // Super-resolution
    public bool $sr = false;
    public ?string $srBin = null;
    public ?string $srModelDir = null;
    public string $srModel = 'realesrgan-x4plus';
    public ?string $srTarget = null;
    public int $srScale = 4;

    // Memory
    public bool $memoryPlanAuto = true;

    // Preview
    public bool $previewDenoise = false;

    /**
     * Create Params from CLI Application parsed values.
     */
    public static function fromApplication(\H3Php\Cli\Application $app): self
    {
        $params = new self();

        $params->width = (int) $app->get('width');
        $params->height = (int) $app->get('height');
        $params->renderWidth = (int) $app->get('render-width');
        $params->renderHeight = (int) $app->get('render-height');
        $params->frames = (int) $app->get('frames');
        $params->seconds = (float) $app->get('seconds');
        $params->steps = (int) $app->get('steps');
        $params->denoiseReuse = (int) $app->get('reuse');
        $params->ditLayers = (int) $app->get('layers');
        $params->coreReuse = (int) $app->get('core-reuse');
        $params->tokenReduction = $app->flag('token-reduction');
        $params->ssdStreaming = $app->flag('ssd-streaming');
        $params->useInt8RowFc2 = $app->flag('use-int8-row-fc2');
        $params->useReferenceRope = $app->flag('use-reference-rope');
        $params->useSlowerBf16Mlp = $app->flag('use-slower-bf16-mlp');
        $params->useSlowerBf16Qkv = $app->flag('use-slower-bf16-qkv');
        $params->useSlowerBf16AttentionOutput = $app->flag('use-slower-bf16-attention-output');
        $params->useSlowerRowMajorAttentionOutput = $app->flag('use-slower-row-major-attention-output');
        $params->useSlowerUnfusedInt8Inputs = $app->flag('use-slower-unfused-int8-inputs');
        $params->useSlowerUnfusedQkvRope = $app->flag('use-slower-unfused-qkv-rope');
        $params->useSlowerScalarQkvRms = $app->flag('use-slower-scalar-qkv-rms');
        $params->useSlowerUncachedInt8Scales = $app->flag('use-slower-uncached-int8-scales');
        $params->useSlowerDynamicFc1K = $app->flag('use-slower-dynamic-fc1-k');
        $params->useSlowerGroupedQuantizer = $app->flag('use-slower-grouped-quantizer');
        $params->videoVaeStreaming = $app->flag('video-vae-streaming');
        $params->encoderStreaming = $app->flag('encoder-streaming');
        $params->memoryPlanAuto = $app->flag('memory-plan-auto');
        $params->previewDenoise = $app->flag('preview-denoise');
        $params->seed = (int) $app->get('seed');
        $params->firstFrame = $app->get('first-frame');
        $params->lastFrame = $app->get('last-frame');
        $params->refImages = (array) ($app->get('ref-image') ?? []);
        $params->refVideos = (array) ($app->get('ref-video') ?? []);
        $params->refAudios = (array) ($app->get('ref-audio') ?? []);
        $params->loraPath = $app->get('lora');
        $params->outputPath = (string) $app->get('output');
        $params->framesDir = $app->get('frames-dir');
        $params->show = $app->flag('show');
        $params->zoom = (int) $app->get('zoom');
        $params->profile = $app->flag('profile');
        $params->sr = $app->flag('sr');
        $params->srBin = $app->get('sr-bin');
        $params->srModelDir = $app->get('sr-model-dir');
        $params->srModel = (string) $app->get('sr-model');
        $params->srTarget = $app->get('sr-target');
        $params->srScale = (int) $app->get('sr-scale');

        return $params;
    }

    /**
     * Create Params from interactive session settings.
     */
    public static function fromInteractiveSettings(array $settings, array $references = []): self
    {
        $params = new self();

        $params->width = $settings['width'];
        $params->height = $settings['height'];
        $params->renderWidth = $settings['render_width'];
        $params->renderHeight = $settings['render_height'];
        $params->frames = $settings['frames'];
        $params->steps = $settings['steps'];
        $params->denoiseReuse = $settings['reuse'];
        $params->ditLayers = $settings['layers'];
        $params->coreReuse = $settings['core_reuse'];
        $params->tokenReduction = $settings['token_reduction'];
        $params->ssdStreaming = $settings['ssd_streaming'];
        $params->useInt8RowFc2 = $settings['int8_row_fc2'];
        $params->useReferenceRope = $settings['reference_rope'];
        $params->seed = $settings['seed'] ?? 42;
        $params->firstFrame = $settings['first_frame'];
        $params->lastFrame = $settings['last_frame'];
        $params->loraPath = $settings['lora'];

        // Extract references
        foreach ($references as $ref) {
            switch ($ref['type']) {
                case 'image':
                    $params->refImages[] = $ref['path'];
                    break;
                case 'video':
                    $params->refVideos[] = $ref['path'];
                    break;
                case 'audio':
                    $params->refAudios[] = $ref['path'];
                    break;
            }
        }

        return $params;
    }

    /**
     * Validate the parameters.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(): array
    {
        $errors = [];

        // Canvas validation
        if (0 !== $this->width % 32) {
            $errors[] = "Width must be a multiple of 32 (got {$this->width})";
        }
        if (0 !== $this->height % 32) {
            $errors[] = "Height must be a multiple of 32 (got {$this->height})";
        }
        if ($this->width * $this->height > 768 * 1344) {
            $errors[] = "Width*Height must be <= 768*1344 (got {$this->width}*{$this->height})";
        }

        // Frames validation
        if ($this->frames < 22 || $this->frames > 362) {
            $errors[] = "Frames must be between 22 and 362 (got {$this->frames})";
        }

        // Steps validation
        if ($this->steps < 1 || $this->steps > 1000) {
            $errors[] = "Steps must be between 1 and 1000 (got {$this->steps})";
        }

        // Layers validation
        if ($this->ditLayers < 35 || $this->ditLayers > 50) {
            $errors[] = "DiT layers must be between 35 and 50 (got {$this->ditLayers})";
        }

        // Reference validation
        $totalRefs = count($this->refImages) + count($this->refVideos) + count($this->refAudios);
        if ($totalRefs > 12) {
            $errors[] = "Maximum 12 total references (got {$totalRefs})";
        }
        if (count($this->refImages) > 9) {
            $errors[] = "Maximum 9 image references (got " . count($this->refImages) . ")";
        }

        // First/last frame cannot be combined with Ref2VA
        if ((null !== $this->firstFrame || null !== $this->lastFrame) && $totalRefs > 0) {
            $errors[] = "First/last frame cannot be combined with Ref2VA references";
        }

        // SR validation
        if ($this->sr) {
            if (null === $this->srBin || null === $this->srModelDir) {
                $errors[] = "Super-resolution requires --sr-bin and --sr-model-dir";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Align frame count to 5 + 17*n (h3.c frame alignment).
     */
    public function alignFrames(): int
    {
        $frames = $this->frames;
        if ($frames < 22) {
            return 22;
        }
        if ($frames > 362) {
            return 362;
        }

        return 5 + (int) round(($frames - 5) / 17) * 17;
    }
}
