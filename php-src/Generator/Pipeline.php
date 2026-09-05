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
 *
 * The C library (libh3.a) handles all ML inference via Metal.
 * This PHP class orchestrates the flow and provides progress reporting.
 */

namespace H3Php\Generator;

use H3Php\Cli\Application;
use H3Php\Cli\ProgressDisplay;

class Pipeline
{
    private Application $app;
    private ProgressDisplay $progress;
    private int $modelHandle;

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

            $result = h3_model_generate(
                $this->modelHandle,
                $prompt,
                $params->outputPath,
                $params->width,
                $params->height,
                $params->frames,
                $params->steps,
                $params->seed,
                $params->denoiseReuse,
                $params->ditLayers,
                $params->ssdStreaming ? 1 : 0,
                $params->useInt8RowFc2 ? 1 : 0
            );

            if ($result !== 0) {
                $error = h3_get_last_error();
                throw new \RuntimeException("Generation failed: {$error}");
            }

            $this->progress->update('condition', 1, 1);
            $this->progress->update('denoise', $params->steps, $params->steps);
            $this->progress->update('decode', 1, 1);
            $this->progress->update('mux', 1, 1);

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
