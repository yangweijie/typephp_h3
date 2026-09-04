<?php
/**
 * H3PHP — Pipeline Test
 */

namespace H3Php\Tests\Generator;

use H3Php\Cli\Application;
use H3Php\Generator\Params;
use H3Php\Generator\Pipeline;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    private function createApplication(string $modelDir): Application
    {
        $app = new Application();
        // Simulate parsed arguments
        $app = $app->parse(2, ['h3php', '-d', $modelDir]);
        return $app;
    }

    public function testPipelineCreate(): void
    {
        // Create a minimal model directory structure
        $tempDir = sys_get_temp_dir() . '/h3php_model_' . uniqid();
        mkdir($tempDir . '/FL2VA/transformer', 0755, true);
        mkdir($tempDir . '/FL2VA/tokenizer', 0755, true);
        file_put_contents($tempDir . '/FL2VA/transformer/config.json', '{}');
        file_put_contents($tempDir . '/FL2VA/tokenizer/tokenizer.json', '{}');

        $app = $this->createApplication($tempDir);
        $pipeline = new Pipeline($app);

        $this->assertInstanceOf(Pipeline::class, $pipeline);

        // Cleanup
        $pipeline->free();
        $this->removeDir($tempDir);
    }

    public function testPipelineExecuteInvalidParams(): void
    {
        $tempDir = sys_get_temp_dir() . '/h3php_model_' . uniqid();
        mkdir($tempDir . '/FL2VA/transformer', 0755, true);
        mkdir($tempDir . '/FL2VA/tokenizer', 0755, true);
        file_put_contents($tempDir . '/FL2VA/transformer/config.json', '{}');
        file_put_contents($tempDir . '/FL2VA/tokenizer/tokenizer.json', '{}');

        $app = $this->createApplication($tempDir);
        $pipeline = new Pipeline($app);

        $params = new Params();
        $params->width = 100; // Invalid: not multiple of 32

        // Application::error() throws for testability
        $this->expectException(\H3Php\Cli\Exception::class);
        $this->expectExceptionMessage('Width must be a multiple of 32');

        $pipeline->execute("test prompt", $params);

        $pipeline->free();
        $this->removeDir($tempDir);
    }

    public function testPipelineGetProgress(): void
    {
        $tempDir = sys_get_temp_dir() . '/h3php_model_' . uniqid();
        mkdir($tempDir . '/FL2VA/transformer', 0755, true);
        mkdir($tempDir . '/FL2VA/tokenizer', 0755, true);
        file_put_contents($tempDir . '/FL2VA/transformer/config.json', '{}');
        file_put_contents($tempDir . '/FL2VA/tokenizer/tokenizer.json', '{}');

        $app = $this->createApplication($tempDir);
        $pipeline = new Pipeline($app);

        $progress = $pipeline->getProgress();
        $this->assertInstanceOf(\H3Php\Cli\ProgressDisplay::class, $progress);

        $pipeline->free();
        $this->removeDir($tempDir);
    }

    public function testPipelineFree(): void
    {
        $tempDir = sys_get_temp_dir() . '/h3php_model_' . uniqid();
        mkdir($tempDir . '/FL2VA/transformer', 0755, true);
        mkdir($tempDir . '/FL2VA/tokenizer', 0755, true);
        file_put_contents($tempDir . '/FL2VA/transformer/config.json', '{}');
        file_put_contents($tempDir . '/FL2VA/tokenizer/tokenizer.json', '{}');

        $app = $this->createApplication($tempDir);
        $pipeline = new Pipeline($app);

        // Should not throw
        $pipeline->free();
        $this->assertTrue(true);

        $this->removeDir($tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
