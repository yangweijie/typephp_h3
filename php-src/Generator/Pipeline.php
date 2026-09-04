<?php

/**
 * H3PHP — Six-Stage Generation Pipeline.
 *
 * Orchestrates the complete video generation pipeline:
 *   1. Load      — Validate model, parse safetensors, probe Metal device
 *   2. Condition — Tokenize, encode text/vision/audio → embeddings
 *   3. Denoise   — DiT iterative denoising (Euler steps)
 *   4. Decode    — Video VAE + Audio VAE → RGB frames + PCM
 *   5. Mux       — FFmpeg H.264 + AAC → MP4
 *   6. SR        — Optional Real-ESRGAN super-resolution
 *
 * Follows h3.c's single cleanup exit pattern: all resources initialized
 * to null, any failure jumps to cleanup.
 */

namespace H3Php\Generator;

use H3Php\Cli\Application;
use H3Php\Cli\ProgressDisplay;
use H3Php\Core\H3Context;
use H3Php\Encoder\TextEncoder;
use H3Php\Encoder\Tokenizer;
use H3Php\Encoder\VisionEncoder;
use H3Php\Inference\DiT;
use H3Php\Metal\Device;
use H3Php\VAE\AudioVAE;
use H3Php\VAE\VideoVAE;

class Pipeline
{
    private Application $app;
    private ProgressDisplay $progress;
    private H3Context $context;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->progress = new ProgressDisplay();
        $this->context = new H3Context($app->get('model-dir'), $app, $app->get('model-manifest'));
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

        // Resources to clean up on failure (single cleanup exit pattern from h3.c)
        $device = null;
        $tokenizer = null;
        $textEncoder = null;
        $visionEncoder = null;
        $dit = null;
        $videoVae = null;
        $audioVae = null;

        try {
            // === Stage 1: Load ===
            $this->progress->update('load', 0, 1);
            $device = new Device();
            $this->context->initializeDevice();
            $this->context->validate();
            $this->progress->update('load', 1, 1);

            // === Stage 2: Conditioning ===
            $this->progress->update('condition', 0, 1);
            $tokenizer = $this->loadTokenizer($params);
            $textEncoder = new TextEncoder($device, $tokenizer);
            $textResult = $textEncoder->encode($prompt);

            if (!empty($params->refImages)) {
                $visionEncoder = new VisionEncoder($device);
                // TODO: Encode reference images
            }
            $this->progress->update('condition', 1, 1);

            // === Stage 3: DiT Denoising ===
            $this->progress->update('denoise', 0, $params->steps);
            $dit = new DiT(
                $params->ditLayers,
                $params->denoiseReuse
            );
            // TODO: Create noise latent and run denoising
            // $result = $dit->denoise($noiseLatent, $textResult['embeddings'], null, $params->steps);
            $this->progress->update('denoise', $params->steps, $params->steps);

            // === Stage 4: Decoding ===
            $this->progress->update('decode', 0, 1);
            $videoVae = new VideoVAE();
            // TODO: Decode video latent to RGB frames
            $audioVae = new AudioVAE($device);
            // TODO: Decode audio latent to PCM
            $this->progress->update('decode', 1, 1);

            // === Stage 5: Muxing ===
            $this->progress->update('mux', 0, 1);
            // TODO: Mux RGB + PCM to MP4 via FFmpeg
            $this->progress->update('mux', 1, 1);

            // === Stage 6: Super-Resolution (optional) ===
            if ($params->sr && $params->srBin && $params->srModelDir) {
                $this->progress->update('sr', 0, 1);
                try {
                    // TODO: Run Real-ESRGAN
                    $this->progress->update('sr', 1, 1);
                } catch (\Exception $e) {
                    $this->app->warning("Super-resolution failed: {$e->getMessage()} (falling back to low-res)");
                }
            }

            $this->progress->finish();
            $this->app->success("Done -> {$params->outputPath}");

            return true;
        } catch (\Exception $e) {
            $this->progress->finish();
            $this->app->error("Pipeline failed: {$e->getMessage()}", 1);

            return false;
        } finally {
            // Clean up all resources (single cleanup exit pattern)
            if (null !== $audioVae) {
                $audioVae->free();
            }
            if (null !== $videoVae) {
                $videoVae->free();
            }
            if (null !== $dit) {
                $dit->free();
            }
            if (null !== $visionEncoder) {
                $visionEncoder->free();
            }
            if (null !== $textEncoder) {
                $textEncoder->free();
            }
        }
    }

    /**
     * Load the tokenizer for the given parameters.
     */
    private function loadTokenizer(Params $params): Tokenizer
    {
        $tokenizerPath = $this->context->getLayout()->tokenizerPath('FL2VA');

        if (!file_exists($tokenizerPath)) {
            throw new \RuntimeException("Tokenizer not found: {$tokenizerPath}");
        }

        return new Tokenizer($tokenizerPath);
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
        $this->context->free();
        $this->progress->finish();
    }
}
