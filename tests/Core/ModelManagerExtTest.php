<?php

namespace H3Php\Tests\Core;

use H3Php\Core\ModelManager;
use PHPUnit\Framework\TestCase;

/**
 * US-004 补齐：磁盘占用统计、模型版本、单模型删除、缺失组件检测。
 */
final class ModelManagerExtTest extends TestCase
{
    private string $root = '';

    private string $transformerDir = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/h3php_mm_' . uniqid();
        $this->transformerDir = $this->root . '/FL2VA/transformer';

        mkdir($this->transformerDir, 0777, true);
        file_put_contents(
            $this->transformerDir . '/config.json',
            json_encode(['version' => '1.0', 'model_type' => 'h3_dit'])
        );
        file_put_contents($this->transformerDir . '/model.safetensors', str_repeat('x', 2048));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->root);
    }

    public function testScanReportsSizeBackendAndVersion(): void
    {
        $manager = new ModelManager([$this->root]);
        $models = $manager->scan();

        self::assertCount(1, $models);
        self::assertSame('transformer', $models[0]['type']);
        self::assertSame('native_h3', $models[0]['backend']);
        self::assertSame(2048 + strlen(json_encode(['version' => '1.0', 'model_type' => 'h3_dit'])), $models[0]['size']);
        self::assertSame('1.0', ModelManager::getVersion($models[0]));
    }

    public function testDiskUsageReportsUsedFreeAndRecommended(): void
    {
        $manager = new ModelManager([$this->root]);
        $manager->scan();

        $usage = $manager->getDiskUsage();

        self::assertGreaterThan(0, $usage['used']);
        self::assertSame(ModelManager::RECOMMENDED_DISK_BYTES, $usage['recommended']);
        self::assertGreaterThanOrEqual(0, $usage['free']);
        self::assertGreaterThan(0.0, $usage['percent']);
        self::assertLessThanOrEqual(100.0, $usage['percent']);
    }

    public function testMissingTypesDetectsAbsentCoreComponents(): void
    {
        $manager = new ModelManager([$this->root]);
        $manager->scan();

        $missing = $manager->getMissingTypes();

        self::assertNotContains('transformer', $missing);
        self::assertContains('video_vae', $missing);
        self::assertContains('tokenizer', $missing);
    }

    public function testRemoveModelDeletesOnlyInsideSearchPaths(): void
    {
        $manager = new ModelManager([$this->root]);
        $manager->scan();

        self::assertTrue($manager->removeModel($this->transformerDir));
        self::assertDirectoryDoesNotExist($this->transformerDir);

        $outside = sys_get_temp_dir() . '/h3php_outside_' . uniqid() . '.bin';
        file_put_contents($outside, 'keep me');

        self::assertFalse($manager->removeModel($outside));
        self::assertFileExists($outside);

        unlink($outside);
    }

    public function testGetVersionFallsBackToDash(): void
    {
        self::assertSame('-', ModelManager::getVersion([]));
        self::assertSame('-', ModelManager::getVersion(['path' => $this->root]));
    }
}
