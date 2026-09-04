<?php

/**
 * H3PHP — Text-to-Video (FL2VA).
 *
 * Generates video from text prompt using the FL2VA model stream.
 * Optionally supports first-frame and last-frame conditioning.
 *
 * This is the primary generation mode for the H3 engine.
 */

namespace H3Php\Generator;

use H3Php\Cli\Application;

class TextToVideo
{
    private Application $app;
    private Pipeline $pipeline;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->pipeline = new Pipeline($app);
    }

    /**
     * Generate video from a text prompt.
     *
     * @param string $prompt The text prompt
     * @param Params $params Generation parameters
     *
     * @return bool Success
     */
    public function generate(string $prompt, Params $params): bool
    {
        // Validate this is a text-to-video request
        if (!empty($params->refImages) || !empty($params->refVideos) || !empty($params->refAudios)) {
            $this->app->error('TextToVideo does not support references. Use ReferenceToVideo instead.', 2);

            return false;
        }

        $this->app->header('FL2VA — Text to Video');
        $this->app->info("Model: FL2VA/transformer");
        $this->app->info("Canvas: {$params->width}x{$params->height}, {$params->frames} frames");
        $this->app->info("Steps: {$params->steps}, Layers: {$params->ditLayers}, Reuse: {$params->denoiseReuse}");
        $this->app->out('');

        // Execute the six-stage pipeline
        return $this->pipeline->execute($prompt, $params);
    }

    /**
     * Free resources.
     */
    public function free(): void
    {
        $this->pipeline->free();
    }
}
