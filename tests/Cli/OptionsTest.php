<?php

/**
 * H3PHP — Options Test.
 */

namespace H3Php\Tests\Cli;

use H3Php\Cli\Options;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function testAllOptionsHaveRequiredFields(): void
    {
        foreach (Options::ALL as $name => $config) {
            $this->assertArrayHasKey('longPrefix', $config, "Option {$name} missing longPrefix");
            $this->assertArrayHasKey('description', $config, "Option {$name} missing description");
        }
    }

    public function testPrimaryOptionsExist(): void
    {
        $this->assertArrayHasKey('model-dir', Options::ALL);
        $this->assertArrayHasKey('prompt', Options::ALL);
        $this->assertArrayHasKey('output', Options::ALL);
    }

    public function testCanvasOptionsExist(): void
    {
        $this->assertArrayHasKey('width', Options::ALL);
        $this->assertArrayHasKey('height', Options::ALL);
        $this->assertArrayHasKey('frames', Options::ALL);
        $this->assertArrayHasKey('seconds', Options::ALL);
    }

    public function testSamplingOptionsExist(): void
    {
        $this->assertArrayHasKey('steps', Options::ALL);
        $this->assertArrayHasKey('reuse', Options::ALL);
        $this->assertArrayHasKey('layers', Options::ALL);
        $this->assertArrayHasKey('core-reuse', Options::ALL);
    }

    public function testDefaults(): void
    {
        $this->assertEquals(864, Options::getDefault('width'));
        $this->assertEquals(480, Options::getDefault('height'));
        $this->assertEquals(56, Options::getDefault('frames'));
        $this->assertEquals(20, Options::getDefault('steps'));
        $this->assertEquals(50, Options::getDefault('layers'));
        $this->assertEquals(42, Options::getDefault('seed'));
    }

    public function testBooleanFlags(): void
    {
        $this->assertTrue(Options::isFlag('help'));
        $this->assertTrue(Options::isFlag('info'));
        $this->assertTrue(Options::isFlag('profile'));
        $this->assertTrue(Options::isFlag('show'));
        $this->assertTrue(Options::isFlag('token-reduction'));
        $this->assertTrue(Options::isFlag('ssd-streaming'));
        $this->assertTrue(Options::isFlag('sr'));
    }

    public function testNonFlags(): void
    {
        $this->assertFalse(Options::isFlag('width'));
        $this->assertFalse(Options::isFlag('height'));
        $this->assertFalse(Options::isFlag('model-dir'));
        $this->assertFalse(Options::isFlag('prompt'));
    }

    public function testCategories(): void
    {
        $categories = Options::getCategories();
        $this->assertArrayHasKey('Primary', $categories);
        $this->assertArrayHasKey('Canvas/Timing', $categories);
        $this->assertArrayHasKey('Sampling/Quality', $categories);
        $this->assertArrayHasKey('Speed/Optimization', $categories);
    }
}
