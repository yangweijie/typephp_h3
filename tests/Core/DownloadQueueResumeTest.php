<?php

namespace H3Php\Tests\Core;

use H3Php\Core\DownloadQueue;
use H3Php\Core\DownloadTask;
use PHPUnit\Framework\TestCase;

/**
 * US-005 补齐：暂停 / 恢复 / 速度 / ETA。
 *
 * Uses an unreachable loopback address so no outbound traffic is generated;
 * cURL reports connection failures asynchronously (~1-2 s), therefore the
 * loop below is bounded by a wall-clock budget.
 */
final class DownloadQueueResumeTest extends TestCase
{
    public function testPauseMarksActiveTasksPausedAndResumeRequeuesThem(): void
    {
        $queue = new DownloadQueue(1);
        $task = new DownloadTask(
            'resume_task',
            'http://127.0.0.1:59999/file.bin',
            sys_get_temp_dir() . '/h3php_resume_' . uniqid() . '/f.bin',
            null,
            'direct',
            'resume_task'
        );

        $queue->add($task);
        $queue->startAsync();

        self::assertSame('downloading', $task->status);
        self::assertFalse($queue->isPaused());

        $queue->pause();
        self::assertTrue($queue->isPaused());
        self::assertSame('paused', $task->status);
        self::assertSame(0.0, $queue->getSpeed());

        $queue->resume();
        self::assertFalse($queue->isPaused());
        self::assertContains($task->status, ['pending', 'downloading']);

        $queue->cancelAll();
        $queue->process(); // releases the cURL multi handle
        self::assertSame('cancelled', $task->status);
    }

    public function testByteCountersSpeedAndEtaAreZeroWhileIdle(): void
    {
        $queue = new DownloadQueue(1);

        self::assertSame(0, $queue->getDownloadedBytes());
        self::assertSame(0, $queue->getTotalBytes());
        self::assertSame(0.0, $queue->getSpeed());
        self::assertSame(0, $queue->getEtaSeconds());
        self::assertFalse($queue->isPaused());
    }
}
