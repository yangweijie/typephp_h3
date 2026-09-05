<?php

/**
 * H3PHP — Six-Stage Generation Pipeline.
 *
 * Orchestrates the complete video generation pipeline:
 *   1. Load      — Load model via C library (libh3.a)
 *   2. Condition — Tokenize, encode text/vision/audio → embeddings (C library)
 *   3. Denoise   — DiT iterative denoising via Metal kernels (C library)
 *   4. Decode    — Video VAE → RGB frames (C library)
 *   5. Mux       — FFmpeg H.264 + AAC → MP4 (C library)
 *   6. Upscale   — FFmpeg lanczos upscale to target resolution (PHP)
 *
 * The C library (libh3.a) handles all ML inference via Metal.
 * This PHP class orchestrates the flow and provides progress reporting.
 *
 * Note: SSD streaming has a bug with non-256 resolutions (causes SIGSEGV).
 * Workaround: always render at 256x256, then upscale via FFmpeg.
 */

namespace H3Php\Generator;

use H3Php\Cli\Application;
use H3Php\Cli\ProgressDisplay;

class Pipeline
{
    private Application $app;
    private ProgressDisplay $progress;
    private int $modelHandle;

    /** Internal render resolution (limited by SSD streaming bug) */
    private const RENDER_WIDTH = 256;
    private const RENDER_HEIGHT = 256;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->progress = new ProgressDisplay();
        $this->modelHandle = -1;
    }

    /**
     * Execute the full generation pipeline.
     *
     * @param string $prompt The text prompt
     * @param Params $params Generation parameters
     *
     * @throws \RuntimeException If model directory is invalid
     *
     * @return bool Success
     */
    public function execute(string $prompt, Params $params): bool
    {
        // Validate parameters
        $validation = $params->validate();
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $error) {
                $this->app->error($error, 2);
            }

            return false;
        }

        $this->app->info("Prompt: {$prompt}");
        $this->app->info("Output: {$params->outputPath}");
        $this->app->out('');

        // Align frames
        $params->frames = $params->alignFrames();

        // Save target resolution for upscaling
        $targetWidth = $params->width;
        $targetHeight = $params->height;

        // Always render at 256x256 to work around SSD streaming bug
        $renderWidth = self::RENDER_WIDTH;
        $renderHeight = self::RENDER_HEIGHT;

        // Temporary output path for 256x256 render
        $tempPath = $params->outputPath . '.256x256.mp4';

        try {
            // === Stage 1: Load Model ===
            $this->progress->update('load', 0, 1);
            $modelDir = $this->app->get('model-dir');
            $this->modelHandle = h3_model_load($modelDir);

            if ($this->modelHandle < 0) {
                $error = h3_get_last_error();
                throw new \RuntimeException("Failed to load model: {$error}");
            }

            // Log device info
            $deviceName = h3_model_get_device_name($this->modelHandle);
            $this->app->info("Device: {$deviceName}");
            $this->progress->update('load', 1, 1);

            // === Stage 2-5: Condition → Denoise → Decode → Mux ===
            // All handled by the C library in a single call
            $this->progress->update('condition', 0, 1);
            $this->progress->update('denoise', 0, $params->steps);
            $this->progress->update('decode', 0, 1);
            $this->progress->update('mux', 0, 1);

            // P1: Pack all parameters into Array for maintainability
            $genParams = [
                'prompt' => $prompt,
                'output_path' => $tempPath,
                'width' => $renderWidth,
                'height' => $renderHeight,
                'frames' => $params->frames,
                'steps' => $params->steps,
                'seed' => $params->seed,
                'denoise_reuse' => $params->denoiseReuse,
                'dit_layers' => $params->ditLayers,
                'ssd_streaming' => 1,  // Required for memory (model > device RAM)
                'use_int8_row_fc2' => $params->useInt8RowFc2 ? 1 : 0,
                'use_slower_bf16_mlp' => $params->useSlowerBf16Mlp ? 1 : 0,
                'use_slower_bf16_qkv' => $params->useSlowerBf16Qkv ? 1 : 0,
                'use_slower_bf16_attention_output' => $params->useSlowerBf16AttentionOutput ? 1 : 0,
                'use_slower_row_major_attention_output' => $params->useSlowerRowMajorAttentionOutput ? 1 : 0,
                'use_slower_unfused_int8_inputs' => $params->useSlowerUnfusedInt8Inputs ? 1 : 0,
                'use_slower_unfused_qkv_rope' => $params->useSlowerUnfusedQkvRope ? 1 : 0,
                'use_slower_scalar_qkv_rms' => $params->useSlowerScalarQkvRms ? 1 : 0,
                'use_slower_uncached_int8_scales' => $params->useSlowerUncachedInt8Scales ? 1 : 0,
                'use_slower_dynamic_fc1_k' => $params->useSlowerDynamicFc1K ? 1 : 0,
                'use_slower_grouped_quantizer' => $params->useSlowerGroupedQuantizer ? 1 : 0,
                'video_vae_streaming' => $params->videoVaeStreaming ? 1 : 0,
                'encoder_streaming' => $params->encoderStreaming ? 1 : 0,
                'memory_plan_auto' => $params->memoryPlanAuto ? 1 : 0,
                'preview_denoise' => $params->previewDenoise ? 1 : 0,
            ];

            $result = h3_model_generate($this->modelHandle, $genParams);

            if ($result !== 0) {
                $error = h3_get_last_error();
                throw new \RuntimeException("Generation failed: {$error}");
            }

            $this->progress->update('condition', 1, 1);
            $this->progress->update('denoise', $params->steps, $params->steps);
            $this->progress->update('decode', 1, 1);
            $this->progress->update('mux', 1, 1);

            // === Stage 6: Upscale to target resolution ===
            if ($targetWidth !== $renderWidth || $targetHeight !== $renderHeight) {
                $this->progress->update('upscale', 0, 1);
                $this->upscaleVideo($tempPath, $params->outputPath, $targetWidth, $targetHeight);
                $this->progress->update('upscale', 1, 1);
            } else {
                // No upscaling needed, just rename
                rename($tempPath, $params->outputPath);
            }

            $this->progress->finish();
            $this->app->success("Done -> {$params->outputPath}");

            return true;
        } catch (\Exception $e) {
            $this->progress->finish();
            $this->app->error("Pipeline failed: {$e->getMessage()}", 1);

            return false;
        }
    }

    /**
     * Upscale video from 256x256 to target resolution using FFmpeg.
     */
    private function upscaleVideo(string $inputPath, string $outputPath, int $targetWidth, int $targetHeight): void
    {
        $cmd = sprintf(
            'ffmpeg -y -i %s -vf "scale=%d:%d:flags=lanczos" -c:v libx264 -preset medium -crf 18 -pix_fmt yuv420p -c:a copy %s 2>&1',
            escapeshellarg($inputPath),
            $targetWidth,
            $targetHeight,
            escapeshellarg($outputPath)
        );

        exec($cmd, $output, $exitCode);

        // Clean up temp file
        @unlink($inputPath);

        if ($exitCode !== 0) {
            throw new \RuntimeException("Upscale failed: " . implode("\n", $output));
        }
    }

    /**
     * Get the progress display instance.
     */
    public function getProgress(): ProgressDisplay
    {
        return $this->progress;
    }

    /**
     * Free all pipeline resources.
     */
    public function free(): void
    {
        if ($this->modelHandle >= 0) {
            h3_model_free($this->modelHandle);
            $this->modelHandle = -1;
        }
        $this->progress->finish();
    }
}
