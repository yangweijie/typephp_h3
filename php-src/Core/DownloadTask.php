<?php

/**
 * H3PHP — Download Task.
 *
 * Represents a single download job: URL → local path with checksum verification.
 */

namespace H3Php\Core;

class DownloadTask
{
    /** Unique task identifier */
    public string $id;

    /** Source URL */
    public string $url;

    /** Destination file path */
    public string $destination;

    /** Expected SHA256 checksum (optional) */
    public ?string $checksum;

    /** Total file size in bytes (0 if unknown) */
    public int $totalSize;

    /** Downloaded bytes */
    public int $downloadedBytes;

    /** Task status: pending, downloading, paused, completed, failed, cancelled */
    public string $status;

    /** Error message if failed */
    public string $error;

    /** Number of retry attempts */
    public int $retries;

    /** Maximum retry attempts */
    public int $maxRetries;

    /** Progress callback */
    public /* ?callable */ $onProgress;

    /** Completion callback */
    public /* ?callable */ $onComplete;

    /** Source type: huggingface, modelscope, civitai, github, direct */
    public string $source;

    /** Human-readable name */
    public string $name;

    public function __construct(
        string $id,
        string $url,
        string $destination,
        ?string $checksum = null,
        string $source = 'direct',
        string $name = '',
        int $maxRetries = 3,
    ) {
        $this->id = $id;
        $this->url = $url;
        $this->destination = $destination;
        $this->checksum = $checksum;
        $this->totalSize = 0;
        $this->downloadedBytes = 0;
        $this->status = 'pending';
        $this->error = '';
        $this->retries = 0;
        $this->maxRetries = $maxRetries;
        $this->onProgress = null;
        $this->onComplete = null;
        $this->source = $source;
        $this->name = $name ?: basename($destination);
    }

    /**
     * Get progress percentage (0-100).
     */
    public function getProgress(): float
    {
        if ($this->totalSize <= 0) {
            return 0;
        }

        return min(100, ($this->downloadedBytes / $this->totalSize) * 100);
    }

    /**
     * Check if the task is in a terminal state.
     */
    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }

    /**
     * Check if the task can be retried.
     */
    public function canRetry(): bool
    {
        return 'failed' === $this->status && $this->retries < $this->maxRetries;
    }

    /**
     * Verify the downloaded file's checksum.
     */
    public function verifyChecksum(): bool
    {
        if (null === $this->checksum || !file_exists($this->destination)) {
            return true; // No checksum to verify
        }

        $actual = hash_file('sha256', $this->destination);

        return hash_equals($this->checksum, $actual);
    }

    /**
     * Get formatted progress string.
     */
    public function getProgressString(): string
    {
        $progress = $this->getProgress();
        $downloaded = ModelManager::formatSize($this->downloadedBytes);
        $total = $this->totalSize > 0 ? ModelManager::formatSize($this->totalSize) : 'unknown';

        return sprintf('%s — %.1f%% (%s / %s)', $this->name, $progress, $downloaded, $total);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'destination' => $this->destination,
            'checksum' => $this->checksum,
            'total_size' => $this->totalSize,
            'downloaded_bytes' => $this->downloadedBytes,
            'status' => $this->status,
            'error' => $this->error,
            'retries' => $this->retries,
            'source' => $this->source,
            'name' => $this->name,
            'progress' => $this->getProgress(),
        ];
    }
}
