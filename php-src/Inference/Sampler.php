<?php

/**
 * H3PHP — Euler Sampler.
 *
 * Implements the Euler method for diffusion sampling.
 * The H3 model uses a shifted Euler schedule with separate
 * sigma schedules for video and audio.
 *
 * From h3.c documentation:
 *   - Video sigma shift: 12.0
 *   - Audio sigma shift: 3.0
 *   - Default steps: 20
 *   - Supports velocity field reuse for acceleration
 */

namespace H3Php\Inference;

class Sampler
{
    /** Video sigma schedule shift */
    private float $videoSigmaShift = 12.0;

    /** Audio sigma schedule shift */
    private float $audioSigmaShift = 3.0;

    /**
     * Compute the sigma schedule for denoising.
     *
     * Returns steps+1 sigma values, driving exactly `steps` model evaluations.
     * Uses the corrected NFE counting from OpenVDN patch 2026-08-27:
     * `linspace(1, 0, steps+1)` → steps+1 sigmas → steps model forwards.
     *
     * All computation in FP64 (PHP float), cast to FP32 for Metal buffers.
     *
     * @param int   $steps Number of denoising steps (model evaluations / NFEs)
     * @param float $shift Sigma schedule shift factor (12.0 video, 3.0 audio)
     *
     * @return float[] Sigma values from high to low (steps+1 elements)
     */
    public function computeSigmas(int $steps, float $shift = 12.0): array
    {
        $sigmas = [];

        // steps+1 points for exactly N model evaluations
        for ($i = 0; $i <= $steps; ++$i) {
            $t = $i / $steps;
            // Shifted schedule: sigma = shift * t / (1 + (shift-1) * t)
            $sigma = $shift * $t / (1.0 + ($shift - 1.0) * $t);
            $sigmas[] = $sigma;
        }

        // Reverse: start from high sigma (noise) to low sigma (clean)
        return array_reverse($sigmas);
    }

    /**
     * Compute the sigma schedule for video denoising.
     */
    public function computeVideoSigmas(int $steps): array
    {
        return $this->computeSigmas($steps, $this->videoSigmaShift);
    }

    /**
     * Compute the sigma schedule for audio denoising.
     */
    public function computeAudioSigmas(int $steps): array
    {
        return $this->computeSigmas($steps, $this->audioSigmaShift);
    }

    /**
     * Single Euler step: x_{t-1} = x_t + (sigma_t - sigma_{t-1}) * D(x_t, sigma_t).
     *
     * @param array $latent              Current latent state
     * @param float $sigma               Current sigma
     * @param float $nextSigma           Next sigma
     * @param array $denoisedModelOutput D(x_t, sigma_t) — model's denoised prediction
     *
     * @return array Updated latent
     */
    public function eulerStep(array $latent, float $sigma, float $nextSigma, array $denoisedModelOutput): array
    {
        $dt = $sigma - $nextSigma;
        $result = [];

        for ($i = 0; $i < count($latent); ++$i) {
            $result[$i] = $latent[$i] + $dt * $denoisedModelOutput[$i];
        }

        return $result;
    }

    /**
     * Convert noise prediction to denoised prediction.
     * D(x, sigma) = x - sigma * noise_pred.
     *
     * @param array $latent    Current noisy latent
     * @param float $sigma     Current sigma
     * @param array $noisePred Model's noise prediction (epsilon)
     *
     * @return array Denoised prediction
     */
    public function noiseToDenoised(array $latent, float $sigma, array $noisePred): array
    {
        $result = [];
        for ($i = 0; $i < count($latent); ++$i) {
            $result[$i] = $latent[$i] - $sigma * $noisePred[$i];
        }

        return $result;
    }

    /**
     * Generate initial noise latent.
     *
     * Uses mt_srand/mt_rand for deterministic noise generation.
     * NOT cryptographically secure — this is intentional for ML reproducibility.
     *
     * @param int $seed     Random seed
     * @param int $channels Number of latent channels
     * @param int $height   Latent height
     * @param int $width    Latent width
     *
     * @return array Noise latent (flat array)
     */
    public function generateNoise(int $seed, int $channels, int $height, int $width): array
    {
        // Seed the RNG (deterministic for reproducible inference)
        mt_srand($seed);

        $size = $channels * $height * $width;
        $noise = [];

        // Box-Muller transform for Gaussian noise
        for ($i = 0; $i < $size; $i += 2) {
            $u1 = mt_rand() / mt_getrandmax();
            $u2 = mt_rand() / mt_getrandmax();

            // Avoid log(0)
            $u1 = max($u1, 1e-10);

            $mag = sqrt(-2.0 * log($u1));
            $noise[$i] = $mag * cos(2.0 * M_PI * $u2);

            if ($i + 1 < $size) {
                $noise[$i + 1] = $mag * sin(2.0 * M_PI * $u2);
            }
        }

        return $noise;
    }

    /**
     * Set the video sigma shift.
     */
    public function setVideoSigmaShift(float $shift): void
    {
        $this->videoSigmaShift = $shift;
    }

    /**
     * Set the audio sigma shift.
     */
    public function setAudioSigmaShift(float $shift): void
    {
        $this->audioSigmaShift = $shift;
    }
}
