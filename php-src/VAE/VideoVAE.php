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
        // TODO: Implement actual VAE decode via Metal kernels with model weights
        // For now, generate test pattern frames based on latent statistics
        // This validates the full pipeline: latent → RGB frames → MP4

        $frames = [];
        $frameSize = $height * $width * 3; // RGB24

        // Read latent data for seeding the test pattern
        $latentData = $latent->getContents();
        $latentSeed = crc32(substr($latentData, 0, 1024));

        for ($f = 0; $f < $numFrames; ++$f) {
            $frame = $this->generateTestPattern($f, $numFrames, $height, $width, $latentSeed);
            $frames[] = $frame;
        }

        return [
            'frames' => $frames,
            'height' => $height,
            'width' => $width,
        ];
    }

    /**
     * Generate a test pattern frame (color bars + animation).
     */
    private function generateTestPattern(int $frameIdx, int $totalFrames, int $height, int $width, int $seed): string
    {
        $image = imagecreatetruecolor($width, $height);
        if (false === $image) {
            return str_repeat("\0", $height * $width * 3);
        }

        // Animation phase (0 to 2π across frames)
        $phase = 2.0 * M_PI * $frameIdx / $totalFrames;

        // Color bars with animation
        $numBars = 8;
        $barWidth = (int) ($width / $numBars);
        $colors = [
            [255, 255, 255], // White
            [255, 255, 0],   // Yellow
            [0, 255, 255],   // Cyan
            [0, 255, 0],     // Green
            [255, 0, 255],   // Magenta
            [255, 0, 0],     // Red
            [0, 0, 255],     // Blue
            [0, 0, 0],       // Black
        ];

        for ($bar = 0; $bar < $numBars; ++$bar) {
            // Animate brightness with phase offset per bar
            $brightness = 0.7 + 0.3 * sin($phase + $bar * M_PI / 4);
            $r = (int) ($colors[$bar][0] * $brightness);
            $g = (int) ($colors[$bar][1] * $brightness);
            $b = (int) ($colors[$bar][2] * $brightness);
            $color = imagecolorallocate($image, $r, $g, $b);

            $x1 = $bar * $barWidth;
            $x2 = ($bar + 1) * $barWidth;
            if ($bar === $numBars - 1) {
                $x2 = $width; // Last bar fills remainder
            }
            imagefilledrectangle($image, $x1, 0, $x2 - 1, $height - 1, $color);
        }

        // Add a moving gradient overlay at bottom 20%
        $overlayY = (int) ($height * 0.8);
        for ($y = $overlayY; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $gradPhase = sin($phase + $x / $width * 2 * M_PI);
                $r = (int) (128 + 127 * $gradPhase);
                $g = (int) (128 + 127 * sin($phase + $x / $width * 4 * M_PI));
                $b = (int) (128 + 127 * cos($phase));
                $color = imagecolorallocate($image, $r, $g, $b);
                imagesetpixel($image, $x, $y, $color);
            }
        }

        // Extract RGB24 data
        $raw = '';
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($image, $x, $y);
                $raw .= chr(($rgb >> 16) & 0xFF);
                $raw .= chr(($rgb >> 8) & 0xFF);
                $raw .= chr($rgb & 0xFF);
            }
        }

        imagedestroy($image);

        return $raw;
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
