<?php

/**
 * H3PHP — Download Preset.
 *
 * Defines a model download preset: a curated set of model components
 * with metadata about hardware requirements and capabilities.
 */

namespace H3Php\Core;

class DownloadPreset
{
    public string $id;

    public string $label;

    public string $description;

    /** @var ModelComponent[] */
    public array $components;

    public float $requiredVramGb;

    public float $requiredRamGb;

    public float $totalSizeGb;

    public bool $supportsAudio;

    public bool $supportsUpscale;

    /**
     * @param string $id Preset identifier
     * @param string $label Human-readable name
     * @param string $description Short description
     * @param ModelComponent[] $components Model components included
     * @param float $requiredVramGb Minimum VRAM needed
     * @param float $requiredRamGb Minimum system RAM needed
     * @param bool $supportsAudio Whether audio generation is included
     * @param bool $supportsUpscale Whether super-resolution is included
     */
    public function __construct(
        string $id,
        string $label,
        string $description,
        array $components,
        float $requiredVramGb,
        float $requiredRamGb,
        bool $supportsAudio = false,
        bool $supportsUpscale = false,
    ) {
        $this->id = $id;
        $this->label = $label;
        $this->description = $description;
        $this->components = $components;
        $this->requiredVramGb = $requiredVramGb;
        $this->requiredRamGb = $requiredRamGb;
        $this->supportsAudio = $supportsAudio;
        $this->supportsUpscale = $supportsUpscale;
        $this->totalSizeGb = array_sum(array_map(fn (ModelComponent $c) => $c->sizeGb, $components));
    }

    public function getTotalSizeGb(): float
    {
        return $this->totalSizeGb;
    }

    public function getRequiredVramGb(): float
    {
        return $this->requiredVramGb;
    }

    public function getRequiredRamGb(): float
    {
        return $this->requiredRamGb;
    }

    /**
     * Get all available presets.
     *
     * @return array<string, DownloadPreset>
     */
    public static function allPresets(): array
    {
        return [
            'minimal' => new self(
                'minimal',
                'Minimal (Text→Video)',
                'Basic text-to-video generation. No audio, no upscaling.',
                [
                    new ModelComponent('transformer', 'H3 DiT Transformer', 14.2, 'modelscope/MiniMaxAI/H3-DiT'),
                    new ModelComponent('video_vae', 'H3 Video VAE', 2.1, 'modelscope/MiniMaxAI/H3-VideoVAE'),
                    new ModelComponent('text_encoder', 'Qwen3-VL Text Encoder', 8.4, 'modelscope/Qwen/Qwen3-VL-2B'),
                ],
                4.0,
                8.0,
                false,
                false,
            ),
            'standard' => new self(
                'standard',
                'Standard (Text+Image→Video)',
                'Text and image reference video generation. No audio.',
                [
                    new ModelComponent('transformer', 'H3 DiT Transformer', 14.2, 'modelscope/MiniMaxAI/H3-DiT'),
                    new ModelComponent('video_vae', 'H3 Video VAE', 2.1, 'modelscope/MiniMaxAI/H3-VideoVAE'),
                    new ModelComponent('text_encoder', 'Qwen3-VL Text Encoder', 8.4, 'modelscope/Qwen/Qwen3-VL-2B'),
                    new ModelComponent('vision_encoder', 'H3 Vision Encoder', 1.2, 'modelscope/MiniMaxAI/H3-VisionEncoder'),
                ],
                8.0,
                16.0,
                false,
                false,
            ),
            'full' => new self(
                'full',
                'Full (Text+Image+Audio→Video)',
                'Complete video generation with audio synthesis.',
                [
                    new ModelComponent('transformer', 'H3 DiT Transformer', 14.2, 'modelscope/MiniMaxAI/H3-DiT'),
                    new ModelComponent('video_vae', 'H3 Video VAE', 2.1, 'modelscope/MiniMaxAI/H3-VideoVAE'),
                    new ModelComponent('text_encoder', 'Qwen3-VL Text Encoder', 8.4, 'modelscope/Qwen/Qwen3-VL-2B'),
                    new ModelComponent('vision_encoder', 'H3 Vision Encoder', 1.2, 'modelscope/MiniMaxAI/H3-VisionEncoder'),
                    new ModelComponent('audio_vae', 'H3 Audio VAE (BigVGAN)', 0.3, 'modelscope/MiniMaxAI/H3-AudioVAE'),
                ],
                16.0,
                32.0,
                true,
                false,
            ),
        ];
    }

    /**
     * Convert to array for UI display.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'components' => array_map(fn (ModelComponent $c) => $c->toArray(), $this->components),
            'required_vram_gb' => $this->requiredVramGb,
            'required_ram_gb' => $this->requiredRamGb,
            'total_size_gb' => $this->totalSizeGb,
            'supports_audio' => $this->supportsAudio,
            'supports_upscale' => $this->supportsUpscale,
        ];
    }
}
