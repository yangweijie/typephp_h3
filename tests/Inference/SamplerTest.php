<?php

/**
 * H3PHP — Sampler Test.
 */

namespace H3Php\Tests\Inference;

use H3Php\Inference\Sampler;
use PHPUnit\Framework\TestCase;

class SamplerTest extends TestCase
{
    private Sampler $sampler;

    protected function setUp(): void
    {
        $this->sampler = new Sampler();
    }

    public function testSigmaScheduleLength(): void
    {
        $sigmas = $this->sampler->computeSigmas(20);
        $this->assertCount(21, $sigmas); // steps + 1
    }

    public function testSigmaScheduleDecreasing(): void
    {
        $sigmas = $this->sampler->computeSigmas(20);

        // Sigmas should be decreasing (high to low)
        for ($i = 0; $i < count($sigmas) - 1; ++$i) {
            $this->assertGreaterThanOrEqual(
                $sigmas[$i + 1],
                $sigmas[$i],
                "Sigma at index {$i} should be >= sigma at " . ($i + 1)
            );
        }
    }

    public function testVideoSigmas(): void
    {
        $sigmas = $this->sampler->computeVideoSigmas(20);
        $this->assertNotEmpty($sigmas);
        $this->assertCount(21, $sigmas);
    }

    public function testAudioSigmas(): void
    {
        $sigmas = $this->sampler->computeAudioSigmas(20);
        $this->assertNotEmpty($sigmas);
        $this->assertCount(21, $sigmas);
    }

    public function testVideoShiftDifferentFromAudio(): void
    {
        $videoSigmas = $this->sampler->computeVideoSigmas(10);
        $audioSigmas = $this->sampler->computeAudioSigmas(10);

        // Both start at 1.0, but video (shift=12) decreases faster in the middle
        // Check that the schedules differ at a midpoint
        $mid = intdiv(count($videoSigmas), 2);
        $this->assertNotEqualsWithDelta($videoSigmas[$mid], $audioSigmas[$mid], 0.01);
    }

    public function testEulerStep(): void
    {
        $latent = [1.0, 2.0, 3.0];
        $denoised = [0.5, 1.0, 1.5];
        $sigma = 1.0;
        $nextSigma = 0.5;

        $result = $this->sampler->eulerStep($latent, $sigma, $nextSigma, $denoised);

        // x_{t-1} = x_t + (sigma_t - sigma_{t-1}) * D(x_t, sigma_t)
        // dt = 1.0 - 0.5 = 0.5
        // result[0] = 1.0 + 0.5 * 0.5 = 1.25
        // result[1] = 2.0 + 0.5 * 1.0 = 2.5
        // result[2] = 3.0 + 0.5 * 1.5 = 3.75
        $this->assertEqualsWithDelta(1.25, $result[0], 0.001);
        $this->assertEqualsWithDelta(2.5, $result[1], 0.001);
        $this->assertEqualsWithDelta(3.75, $result[2], 0.001);
    }

    public function testNoiseToDenoised(): void
    {
        $latent = [1.0, 2.0, 3.0];
        $noisePred = [0.1, 0.2, 0.3];
        $sigma = 2.0;

        $result = $this->sampler->noiseToDenoised($latent, $sigma, $noisePred);

        // D(x, sigma) = x - sigma * noise_pred
        // result[0] = 1.0 - 2.0 * 0.1 = 0.8
        // result[1] = 2.0 - 2.0 * 0.2 = 1.6
        // result[2] = 3.0 - 2.0 * 0.3 = 2.4
        $this->assertEqualsWithDelta(0.8, $result[0], 0.001);
        $this->assertEqualsWithDelta(1.6, $result[1], 0.001);
        $this->assertEqualsWithDelta(2.4, $result[2], 0.001);
    }

    public function testGenerateNoiseDeterministic(): void
    {
        $noise1 = $this->sampler->generateNoise(42, 24, 8, 8);
        $noise2 = $this->sampler->generateNoise(42, 24, 8, 8);

        // Same seed should produce same noise
        $this->assertEquals($noise1, $noise2);
    }

    public function testGenerateNoiseDifferentSeeds(): void
    {
        $noise1 = $this->sampler->generateNoise(42, 24, 8, 8);
        $noise2 = $this->sampler->generateNoise(123, 24, 8, 8);

        // Different seeds should produce different noise
        $this->assertNotEquals($noise1, $noise2);
    }

    public function testGenerateNoiseSize(): void
    {
        $noise = $this->sampler->generateNoise(42, 24, 8, 8);
        $expectedSize = 24 * 8 * 8;
        $this->assertCount($expectedSize, $noise);
    }

    public function testSetSigmaShifts(): void
    {
        $this->sampler->setVideoSigmaShift(8.0);
        $this->sampler->setAudioSigmaShift(2.0);

        $videoSigmas = $this->sampler->computeVideoSigmas(10);
        $audioSigmas = $this->sampler->computeAudioSigmas(10);

        // With custom shifts, schedules should still be valid
        $this->assertNotEmpty($videoSigmas);
        $this->assertNotEmpty($audioSigmas);
    }
}
