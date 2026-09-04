<?php
/**
 * H3PHP — Reference-to-Video (Ref2VA)
 *
 * Generates video with ordered reference images, videos, and audio.
 * Uses the Ref2VA model stream.
 *
 * Reference types:
 *   - Image references (max 9): <Picture 1>, <Picture 2>, ...
 *   - Video references: <Video 1>, <Video 2>, ...
 *   - Audio references: <Audio 1>, <Audio 2>, ...
 *
 * Prompt uses placeholders like "<Picture 1>" to reference ordered inputs.
 */

namespace H3Php\Generator;

use H3Php\Cli\Application;

class ReferenceToVideo
{
    private Application $app;
    private Pipeline $pipeline;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->pipeline = new Pipeline($app);
    }

    /**
     * Generate video with references.
     *
     * @param string $prompt The text prompt with <Picture N> placeholders
     * @param Params $params Generation parameters (must include references)
     * @return bool Success
     */
    public function generate(string $prompt, Params $params): bool
    {
        // Validate this is a reference-to-video request
        $totalRefs = count($params->refImages) + count($params->refVideos) + count($params->refAudios);
        if ($totalRefs === 0) {
            $this->app->error('ReferenceToVideo requires at least one reference. Use TextToVideo for text-only.', 2);
            return false;
        }

        // Validate first/last frame not combined with references
        if ($params->firstFrame !== null || $params->lastFrame !== null) {
            $this->app->error('First/last frame cannot be combined with Ref2VA references.', 2);
            return false;
        }

        $this->app->header('Ref2VA — Reference to Video');
        $this->app->info("Model: Ref2VA/transformer");
        $this->app->info("Canvas: {$params->width}x{$params->height}, {$params->frames} frames");
        $this->app->info("References: {$totalRefs} total");
        $this->app->out('');

        // List references
        $refNum = 1;
        foreach ($params->refImages as $img) {
            $this->app->info("  <Picture {$refNum}> {$img}");
            $refNum++;
        }
        foreach ($params->refVideos as $vid) {
            $this->app->info("  <Video {$refNum}> {$vid}");
            $refNum++;
        }
        foreach ($params->refAudios as $aud) {
            $this->app->info("  <Audio {$refNum}> {$aud}");
            $refNum++;
        }
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
