<?php

/**
 * H3PHP — Download Queue.
 *
 * Manages concurrent downloads with retry and resume support.
 * Processes tasks in parallel up to a configurable limit.
 */

namespace H3Php\Core;

class DownloadQueue
{
    /** All tasks indexed by ID */
    private array $tasks = [];

    /** Maximum concurrent downloads */
    private int $maxConcurrent;

    /** Currently active downloads [task_id => DownloadTask] */
    private array $active = [];

    /** cURL handles mapped by task ID [task_id => cURL handle] */
    private array $handles = [];

    /** Queue status */
    private bool $running = false;

    /** Global progress callback */
    private /* ?callable */ $onGlobalProgress;

    /** cURL multi handle for parallel downloads */
    private $multiHandle = null;

    /** Smoothed download speed in bytes/second */
    private float $speed = 0.0;

    /** Last speed sampling timestamp (microtime) */
    private float $lastSampleTime = 0.0;

    /** Downloaded bytes at the last speed sampling */
    private int $lastSampleBytes = 0;

    public function __construct(int $maxConcurrent = 3)
    {
        $this->maxConcurrent = $maxConcurrent;
        $this->onGlobalProgress = null;
    }

    /**
     * Add a task to the queue.
     */
    public function add(DownloadTask $task): self
    {
        $this->tasks[$task->id] = $task;

        return $this;
    }

    /**
     * Add multiple tasks.
     */
    public function addMany(array $tasks): self
    {
        foreach ($tasks as $task) {
            $this->add($task);
        }

        return $this;
    }

    /**
     * Start processing the queue (blocking).
     * Runs its own event loop until all downloads finish or are stopped.
     */
    public function start(): void
    {
        $this->startAsync();

        // Blocking event loop (CLI / non-GUI callers)
        while ($this->process()) {
            usleep(10000); // 10ms poll interval
        }
    }

    /**
     * Begin processing without entering an event loop.
     * The caller must drive progress by calling process() repeatedly
     * (e.g. from the Qt event-loop tick) so the UI stays responsive.
     */
    public function startAsync(): void
    {
        $this->running = true;
        $this->multiHandle = curl_multi_init();

        $this->fillActive();
    }

    /**
     * Advance active downloads by one step.
     * Non-blocking: returns true while downloads are still in flight.
     */
    public function process(): bool
    {
        if (!$this->running) {
            $this->finish();

            return false;
        }

        $this->processActive();
        $this->fillActive();
        $this->sampleSpeed();

        if (empty($this->active) && !$this->hasPending()) {
            $this->finish();

            return false;
        }

        return true;
    }

    /**
     * Release the cURL multi handle and mark the queue stopped.
     */
    private function finish(): void
    {
        if ($this->multiHandle) {
            curl_multi_close($this->multiHandle);
            $this->multiHandle = null;
        }

        $this->running = false;
    }

    /**
     * Pause all downloads.
     */
    public function pause(): void
    {
        $this->running = false;

        foreach ($this->active as $task) {
            $task->status = 'paused';
        }

        $this->speed = 0.0;
        $this->lastSampleTime = 0.0;
    }

    /**
     * Resume previously paused downloads.
     * Paused tasks go back to pending and the queue restarts.
     */
    public function resume(): void
    {
        if ($this->running) {
            return;
        }

        foreach ($this->tasks as $task) {
            if ('paused' === $task->status) {
                $task->status = 'pending';
            }
        }

        $this->speed = 0.0;
        $this->lastSampleTime = 0.0;
        $this->lastSampleBytes = $this->getDownloadedBytes();

        $this->startAsync();
    }

    /**
     * Whether the queue is currently paused (stopped with paused tasks).
     */
    public function isPaused(): bool
    {
        if ($this->running) {
            return false;
        }

        foreach ($this->tasks as $task) {
            if ('paused' === $task->status) {
                return true;
            }
        }

        return false;
    }

    /**
     * Smoothed download speed in bytes/second (0 while idle or unknown).
     */
    public function getSpeed(): float
    {
        return $this->running ? $this->speed : 0.0;
    }

    /**
     * Estimated seconds remaining (0 when speed or size is unknown).
     */
    public function getEtaSeconds(): int
    {
        $total = $this->getTotalBytes();
        $downloaded = $this->getDownloadedBytes();

        if ($this->speed <= 0.0 || $total <= $downloaded) {
            return 0;
        }

        return (int) round(($total - $downloaded) / $this->speed);
    }

    /**
     * Sum of bytes already downloaded across all tasks.
     */
    public function getDownloadedBytes(): int
    {
        $bytes = 0;
        foreach ($this->tasks as $task) {
            $bytes += $task->downloadedBytes;
        }

        return $bytes;
    }

    /**
     * Sum of expected bytes across all tasks (0 when sizes are unknown).
     */
    public function getTotalBytes(): int
    {
        $bytes = 0;
        foreach ($this->tasks as $task) {
            $bytes += $task->totalSize;
        }

        return $bytes;
    }

    /**
     * Sample download speed (smoothed) once per ~250 ms.
     */
    private function sampleSpeed(): void
    {
        $now = microtime(true);
        $bytes = $this->getDownloadedBytes();

        if ($this->lastSampleTime <= 0.0) {
            $this->lastSampleTime = $now;
            $this->lastSampleBytes = $bytes;

            return;
        }

        $elapsed = $now - $this->lastSampleTime;
        if ($elapsed < 0.25) {
            return;
        }

        $delta = $bytes - $this->lastSampleBytes;
        if ($delta > 0) {
            $instant = $delta / $elapsed;
            $this->speed = $this->speed > 0.0
                ? ($this->speed * 0.6) + ($instant * 0.4)
                : $instant;
        }

        $this->lastSampleTime = $now;
        $this->lastSampleBytes = $bytes;
    }

    /**
     * Cancel a specific task.
     */
    public function cancel(string $taskId): void
    {
        if (isset($this->tasks[$taskId])) {
            $this->tasks[$taskId]->status = 'cancelled';
        }
    }

    /**
     * Cancel all tasks.
     */
    public function cancelAll(): void
    {
        $this->running = false;

        foreach ($this->tasks as $task) {
            if (!$task->isFinished()) {
                $task->status = 'cancelled';
            }
        }
    }

    /**
     * Get all tasks.
     *
     * @return DownloadTask[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get tasks by status.
     *
     * @return DownloadTask[]
     */
    public function getTasksByStatus(string $status): array
    {
        return array_filter($this->tasks, fn (DownloadTask $t) => $t->status === $status);
    }

    /**
     * Get overall progress (0-100).
     */
    public function getOverallProgress(): float
    {
        if (empty($this->tasks)) {
            return 0;
        }

        $totalProgress = 0;
        foreach ($this->tasks as $task) {
            $totalProgress += $task->getProgress();
        }

        return $totalProgress / count($this->tasks);
    }

    /**
     * Set global progress callback.
     */
    public function onProgress(callable $callback): self
    {
        $this->onGlobalProgress = $callback;

        return $this;
    }

    /**
     * Check if there are pending tasks.
     */
    private function hasPending(): bool
    {
        foreach ($this->tasks as $task) {
            if ('pending' === $task->status || $task->canRetry()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fill active downloads up to max concurrent.
     */
    private function fillActive(): void
    {
        foreach ($this->tasks as $task) {
            if (count($this->active) >= $this->maxConcurrent) {
                break;
            }

            if ('pending' === $task->status || $task->canRetry()) {
                $this->startTask($task);
            }
        }
    }

    /**
     * Start downloading a task.
     */
    private function startTask(DownloadTask $task): void
    {
        $task->status = 'downloading';
        $this->active[$task->id] = $task;

        // Ensure destination directory exists
        $dir = dirname($task->destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $task->url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 0, // No timeout for large files
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'H3PHP/0.1.0',
        ]);

        // Resume support
        $resumeFrom = 0;
        if (file_exists($task->destination)) {
            $resumeFrom = filesize($task->destination);
            curl_setopt($ch, CURLOPT_RESUME_FROM, $resumeFrom);
            $task->downloadedBytes = $resumeFrom;
        }

        // Write callback
        $writeCallback = function ($ch, $data) use ($task, $resumeFrom) {
            $fileExists = file_exists($task->destination);
            $mode = $resumeFrom > 0 && $fileExists ? 'ab' : 'wb';
            $fp = fopen($task->destination, $mode);

            if (false === $fp) {
                $task->status = 'failed';
                $task->error = "Cannot write to: {$task->destination}";

                return -1; // Abort
            }

            $written = fwrite($fp, $data);
            fclose($fp);

            $task->downloadedBytes += $written;

            // Get total size from headers
            $task->totalSize = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

            // Progress callback
            if (null !== $task->onProgress) {
                $task->onProgress($task);
            }

            return $written;
        };

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $writeCallback);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);

        curl_multi_add_handle($this->multiHandle, $ch);

        // Map task ID to cURL handle for correct lookup on completion
        $this->handles[$task->id] = $ch;
    }

    /**
     * Process active downloads.
     */
    private function processActive(): void
    {
        if (!$this->multiHandle) {
            return;
        }

        $running = 0;
        curl_multi_exec($this->multiHandle, $running);

        // Check completed transfers
        while ($info = curl_multi_info_read($this->multiHandle)) {
            $ch = $info['handle'];
            $taskId = $this->findTaskByHandle($ch);

            if (null === $taskId) {
                continue;
            }

            $task = $this->tasks[$taskId];
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (CURLE_OK === $info['result'] && 200 === $httpCode) {
                // Verify checksum
                if ($task->verifyChecksum()) {
                    $task->status = 'completed';

                    if (null !== $task->onComplete) {
                        $task->onComplete($task);
                    }
                } else {
                    $task->status = 'failed';
                    $task->error = 'Checksum verification failed';
                    $task->retries++;
                }
            } else {
                $task->status = 'failed';
                $task->error = $error ?: "HTTP {$httpCode}";
                $task->retries++;
            }

            curl_multi_remove_handle($this->multiHandle, $ch);

            unset($this->active[$taskId], $this->handles[$taskId]);

            // Global progress callback
            if (null !== $this->onGlobalProgress) {
                $this->onGlobalProgress($this);
            }
        }
    }

    /**
     * Find task ID by cURL handle.
     */
    private function findTaskByHandle($ch): ?string
    {
        foreach ($this->handles as $taskId => $handle) {
            if ($handle === $ch) {
                return $taskId;
            }
        }

        return null;
    }

    /**
     * Get queue statistics.
     */
    public function getStats(): array
    {
        $stats = [
            'total' => count($this->tasks),
            'pending' => 0,
            'downloading' => 0,
            'completed' => 0,
            'failed' => 0,
            'paused' => 0,
            'cancelled' => 0,
        ];

        foreach ($this->tasks as $task) {
            $stats[$task->status]++;
        }

        $stats['overall_progress'] = $this->getOverallProgress();

        return $stats;
    }
}
