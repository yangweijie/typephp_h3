<?php

/**
 * H3PHP — Video VAE Decoder.
 *
 * Decodes video latents to RGB frames.
 *
 * Architecture (from h3.c documentation):
 *   - 36-block tiled decoder
 *   - ~9 GiB resident memory (full) or ~0.25 GiB (streaming)
 *   - Input: 24-channel latent (spatial ratio 16)
 *   - Output: RGB24 frames
 *   - Tiled decoding for memory efficiency
 *
 * TODO: Implement actual VAE decode via Metal kernels.
 */

namespace H3Php\VAE;

use H3Php\Metal\Buffer;

class VideoVAE
{
    /** Latent channels */
    private int $latentChannels = 24;

    /** Spatial compression ratio */
    private int $spatialRatio = 16;

    public function __construct()
    {
    }

    /**
     * Decode video latents to RGB frames.
     *
     * @param Buffer $latent    Video latent (24 channels, compressed spatial)
     * @param int    $numFrames Number of frames
     * @param int    $height    Output height
     * @param int    $width     Output width
     *
     * @return array{frames: string[], height: int, width: int} RGB24 frame data
     */
    public function decode(Buffer $latent, int $numFrames, int $height, int $width): array
    {
        // TODO: Implement via Metal kernels
        // 1. Split latent into tiles (if streaming)
        // 2. For each tile:
        //    a. Run 36-block decoder
        //    b. Upsample by spatialRatio
        //    c. Convert to RGB24
        // 3. Concatenate tiles

        $frames = [];
        $frameSize = $height * $width * 3; // RGB24

        for ($i = 0; $i < $numFrames; ++$i) {
            // Placeholder: black frame
            $frames[] = str_repeat("\0", $frameSize);
        }

        return [
            'frames' => $frames,
            'height' => $height,
            'width' => $width,
        ];
    }

    /**
     * Get the spatial output size for a given latent size.
     */
    public function getOutputSize(int $latentHeight, int $latentWidth): array
    {
        return [
            'height' => $latentHeight * $this->spatialRatio,
            'width' => $latentWidth * $this->spatialRatio,
        ];
    }

    /**
     * Get the latent size for a given output size.
     */
    public function getLatentSize(int $outputHeight, int $outputWidth): array
    {
        return [
            'height' => $outputHeight / $this->spatialRatio,
            'width' => $outputWidth / $this->spatialRatio,
        ];
    }

    /**
     * Get the number of latent channels.
     */
    public function getLatentChannels(): int
    {
        return $this->latentChannels;
    }

    /**
     * Free VAE resources.
     */
    public function free(): void
    {
        // TODO: Free model weights, decoder state
    }
}
