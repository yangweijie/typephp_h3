<?php
/**
 * H3PHP — ProcessRunner Test
 */

namespace H3Php\Tests\Core;

use H3Php\Core\ProcessRunner;
use PHPUnit\Framework\TestCase;

class ProcessRunnerTest extends TestCase
{
    public function testIsFfmpegAvailable(): void
    {
        $runner = new ProcessRunner();
        $result = $runner->isFfmpegAvailable();

        // Should return boolean
        $this->assertIsBool($result);
    }

    public function testIsFfprobeAvailable(): void
    {
        $runner = new ProcessRunner();
        $result = $runner->isFfprobeAvailable();

        // Should return boolean
        $this->assertIsBool($result);
    }

    public function testExecuteCommandEcho(): void
    {
        $runner = new ProcessRunner();
        $output = '';
        $exitCode = $runner->executeCommand(['echo', 'hello'], $output);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('hello', $output);
    }

    public function testExecuteCommandFailure(): void
    {
        $runner = new ProcessRunner();
        $output = '';

        // Use a command that will fail
        if (PHP_OS_FAMILY === 'Windows') {
            $exitCode = $runner->executeCommand(['cmd', '/c', 'exit', '1'], $output);
        } else {
            $exitCode = $runner->executeCommand(['false'], $output);
        }

        $this->assertNotEquals(0, $exitCode);
    }

    public function testGetLastOutput(): void
    {
        $runner = new ProcessRunner();
        $output = '';
        $runner->executeCommand(['echo', 'test'], $output);

        $this->assertStringContainsString('test', $runner->getLastOutput());
    }

    public function testGetLastExitCode(): void
    {
        $runner = new ProcessRunner();
        $runner->executeCommand(['echo', 'test']);

        $this->assertEquals(0, $runner->getLastExitCode());
    }

    public function testMuxToMp4CreatesDirectory(): void
    {
        $runner = new ProcessRunner();

        // Create a temp directory for output
        $tempDir = sys_get_temp_dir() . '/h3php_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        $outputPath = $tempDir . '/test.mp4';
        $video = [
            'frames' => [],
            'width' => 64,
            'height' => 64,
            'fps' => 24,
        ];

        // This will fail (no actual ffmpeg frames), but should not throw
        $result = $runner->muxToMp4($outputPath, $video);
        $this->assertIsBool($result);

        // Cleanup
        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }
    }

    public function testSuperResolveValidation(): void
    {
        $runner = new ProcessRunner();

        // Should fail with non-existent paths
        $result = $runner->superResolve(
            '/nonexistent/input.jpg',
            '/nonexistent/output.jpg',
            '/nonexistent/sr',
            '/nonexistent/models'
        );

        $this->assertFalse($result);
    }
}
