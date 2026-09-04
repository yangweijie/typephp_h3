<?php

/**
 * H3PHP — ModelConfig Test.
 */

namespace H3Php\Tests\Core;

use H3Php\Core\ModelConfig;
use PHPUnit\Framework\TestCase;

class ModelConfigTest extends TestCase
{
    public function testVdnH3Defaults(): void
    {
        $cfg = ModelConfig::vdnH3();

        $this->assertEquals(5120, $cfg->hiddenSize);
        $this->assertEquals(40, $cfg->numHeads);
        $this->assertEquals(128, $cfg->attentionHeadDim);
        $this->assertEquals(40, $cfg->numLayers);
        $this->assertEquals(1008, $cfg->tokensPerFrame);
    }

    public function testH3cDefaults(): void
    {
        $cfg = ModelConfig::h3c();

        $this->assertEquals(5376, $cfg->hiddenSize);
        $this->assertEquals(56, $cfg->numHeads);
        $this->assertEquals(96, $cfg->attentionHeadDim);
        $this->assertEquals(50, $cfg->numLayers);
        $this->assertEquals(768, $cfg->tokensPerFrame);
    }

    public function testFromModelDir(): void
    {
        $tempDir = sys_get_temp_dir() . '/h3php_model_' . uniqid();
        mkdir($tempDir . '/FL2VA/transformer', 0755, true);

        // Write a config.json
        $config = [
            'hidden_size' => 5120,
            'num_attention_heads' => 40,
            'attention_head_dim' => 128,
            'num_layers' => 40,
        ];
        file_put_contents(
            $tempDir . '/FL2VA/transformer/config.json',
            json_encode($config)
        );

        $cfg = ModelConfig::fromModelDir($tempDir);

        $this->assertEquals(5120, $cfg->hiddenSize);
        $this->assertEquals(40, $cfg->numHeads);
        $this->assertEquals(128, $cfg->attentionHeadDim);
        $this->assertEquals(40, $cfg->numLayers);

        // Cleanup
        unlink($tempDir . '/FL2VA/transformer/config.json');
        rmdir($tempDir . '/FL2VA/transformer');
        rmdir($tempDir . '/FL2VA');
        rmdir($tempDir);
    }

    public function testFromModelDirMissing(): void
    {
        $cfg = ModelConfig::fromModelDir('/nonexistent/path');

        // Should return defaults
        $this->assertEquals(5120, $cfg->hiddenSize);
        $this->assertEquals(40, $cfg->numHeads);
    }

    public function testPrecisionConstants(): void
    {
        $cfg = new ModelConfig();

        $this->assertEquals(12.0, $cfg->videoSigmaShift);
        $this->assertEquals(3.0, $cfg->audioSigmaShift);
        $this->assertEquals(10000.0, $cfg->ropeTheta);
        $this->assertEquals(1e-6, $cfg->adalnEpsilon);
    }

    public function testVideoLatentParams(): void
    {
        $cfg = new ModelConfig();

        $this->assertEquals(48, $cfg->latentHeight);
        $this->assertEquals(84, $cfg->latentWidth);
        $this->assertEquals(24, $cfg->videoChannels);
        $this->assertEquals(16, $cfg->spatialRatio);
        $this->assertEquals([1, 2, 2], $cfg->patchSize);
    }
}
