<?php

/**
 * H3PHP — DownloadQueue Test.
 *
 * Focus: non-blocking (event-loop driven) processing semantics used by the
 * Qt GUI, which drives downloads from its own event loop via process().
 * Uses an unreachable loopback address so tests never touch the network.
 */

use H3Php\Core\DownloadQueue;
use H3Php\Core\DownloadTask;

/**
 * Queue with one task pointing at an unreachable address.
 * maxRetries defaults to 0 so a failed task settles immediately.
 */
function h3TestQueue(string $tempDir, int $maxRetries = 0): DownloadQueue
{
    $queue = new DownloadQueue(2);
    $queue->add(new DownloadTask(
        'test_task',
        'http://127.0.0.1:59999/model.bin',
        $tempDir . '/model.bin',
        null,
        'direct',
        'model.bin',
        $maxRetries,
    ));

    return $queue;
}

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/h3php_dlq_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    foreach (glob($this->tempDir . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
});

test('process is a no-op before startAsync', function () {
    // The GUI tick calls process() every frame, including before any
    // download has been started — it must stay harmless.
    $queue = h3TestQueue($this->tempDir);

    expect($queue->process())->toBeFalse();
    expect($queue->process())->toBeFalse();
});

test('startAsync begins without blocking', function () {
    $queue = h3TestQueue($this->tempDir);
    $queue->startAsync();

    // startAsync() must return immediately: the task is in flight, not
    // settled. A blocking run would have finished it before we assert.
    expect($queue->getTasks()['test_task']->status)->toBe('downloading');
});

test('process drives downloads to completion and then stops', function () {
    $queue = h3TestQueue($this->tempDir);
    $queue->startAsync();

    // cURL reports connection failures asynchronously (it can take a second
    // or two), so allow a wall-clock budget rather than a fixed iteration count.
    $deadline = microtime(true) + 15.0;
    while ($queue->process()) {
        if (microtime(true) > $deadline) {
            $this->fail('process() never reported completion (would hang the event loop)');
        }
        usleep(1000);
    }

    expect($queue->getTasks()['test_task']->isFinished())->toBeTrue();
    expect($queue->process())->toBeFalse();
});

test('blocking start still runs to completion', function () {
    $queue = h3TestQueue($this->tempDir);

    // Blocking path (used by CLI callers): must return, not hang.
    $queue->start();

    expect($queue->getTasks()['test_task']->isFinished())->toBeTrue();
    expect($queue->process())->toBeFalse();
});

test('cancelAll stops processing', function () {
    $queue = h3TestQueue($this->tempDir);
    $queue->startAsync();
    $queue->cancelAll();

    expect($queue->process())->toBeFalse();
    expect($queue->getTasks()['test_task']->status)->toBe('cancelled');
});
