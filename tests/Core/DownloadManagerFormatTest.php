<?php

namespace H3Php\Tests\Core;

use H3Php\Core\DownloadManager;
use PHPUnit\Framework\TestCase;

/**
 * US-005 补齐：镜像选项、速度/ETA 格式化、按组件入队。
 */
final class DownloadManagerFormatTest extends TestCase
{
    public function testMirrorChoicesAreExposedForTheUi(): void
    {
        self::assertSame(['auto', 'huggingface', 'modelscope', 'xget'], DownloadManager::MIRROR_CHOICES);
    }

    public function testFormatSpeed(): void
    {
        self::assertSame('—', DownloadManager::formatSpeed(0.0));
        self::assertSame('1.0 MB/s', DownloadManager::formatSpeed(1024.0 * 1024.0));
    }

    public function testFormatEta(): void
    {
        self::assertSame('—', DownloadManager::formatEta(0));
        self::assertSame('45 s', DownloadManager::formatEta(45));
        self::assertSame('2 min', DownloadManager::formatEta(120));
        self::assertSame('1.5 h', DownloadManager::formatEta(5400));
    }

    public function testQueueComponentIgnoresUnknownTypes(): void
    {
        $manager = new DownloadManager(sys_get_temp_dir() . '/h3php_dl_' . uniqid());

        self::assertSame([], $manager->queueComponent('nope'));
    }
}
