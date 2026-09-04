<?php

/**
 * H3PHP — Metal Buffer Pool.
 *
 * Manages a pool of reusable Metal buffers to reduce allocation overhead.
 * Frequently allocated and freed buffers (e.g., intermediate tensors during
 * DiT inference) can be reused across denoising steps.
 *
 * Performance impact: eliminates per-step allocation/deallocation overhead.
 * For 20 denoising steps with 50 layers, this saves ~1000 buffer allocations.
 */

namespace H3Php\Metal;

class BufferPool
{
    /** @var Device Metal device */
    private Device $device;

    /** @var array<int, Buffer[]> Pool of available buffers by size */
    private array $available = [];

    /** @var Buffer[] All buffers managed by this pool */
    private array $allBuffers = [];

    /** @var int Maximum pool size (total bytes) */
    private int $maxPoolSize;

    /** @var int Current pool size (total bytes) */
    private int $currentSize = 0;

    /** @var int Number of allocations saved by pooling */
    private int $hits = 0;

    /** @var int Number of new allocations (pool miss) */
    private int $misses = 0;

    /**
     * @param Device $device      Metal device
     * @param int    $maxPoolSize Maximum pool size in bytes (default: 256 MiB)
     */
    public function __construct(Device $device, int $maxPoolSize = 256 * 1024 * 1024)
    {
        $this->device = $device;
        $this->maxPoolSize = $maxPoolSize;
    }

    /**
     * Acquire a buffer of the given size.
     * Returns a pooled buffer if available, or creates a new one.
     *
     * @param int $length  Buffer size in bytes
     * @param int $options Storage mode
     *
     * @return PooledBuffer Wrapper that returns buffer to pool on free()
     */
    public function acquire(int $length, int $options = Buffer::STORAGE_SHARED): PooledBuffer
    {
        // Round up to nearest power of 2 for better reuse
        $bucketSize = $this->nextPowerOf2($length);

        if (isset($this->available[$bucketSize]) && !empty($this->available[$bucketSize])) {
            ++$this->hits;
            $buffer = array_pop($this->available[$bucketSize]);
            $this->currentSize -= $bucketSize;

            return new PooledBuffer($this, $buffer, $bucketSize);
        }

        ++$this->misses;
        $buffer = new Buffer($this->device, $length, $options);
        $this->allBuffers[] = $buffer;

        return new PooledBuffer($this, $buffer, $bucketSize);
    }

    /**
     * Return a buffer to the pool.
     */
    public function release(Buffer $buffer, int $bucketSize): void
    {
        // Don't pool if at capacity
        if ($this->currentSize + $bucketSize > $this->maxPoolSize) {
            // Remove from allBuffers and let GC handle it
            $this->removeFromAllBuffers($buffer);

            return;
        }

        if (!isset($this->available[$bucketSize])) {
            $this->available[$bucketSize] = [];
        }
        $this->available[$bucketSize][] = $buffer;
        $this->currentSize += $bucketSize;
    }

    /**
     * Get pool statistics.
     */
    public function getStats(): array
    {
        $totalBuffers = 0;
        foreach ($this->available as $buffers) {
            $totalBuffers += count($buffers);
        }

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'hit_rate' => ($this->hits + $this->misses) > 0
                ? $this->hits / ($this->hits + $this->misses)
                : 0.0,
            'pooled_buffers' => $totalBuffers,
            'pool_size_bytes' => $this->currentSize,
            'max_size_bytes' => $this->maxPoolSize,
        ];
    }

    /**
     * Clear all pooled buffers.
     */
    public function clear(): void
    {
        $this->available = [];
        $this->currentSize = 0;
        $this->allBuffers = [];
    }

    /**
     * Free all resources.
     */
    public function free(): void
    {
        $this->clear();
    }

    /**
     * Get the next power of 2 >= n.
     */
    private function nextPowerOf2(int $n): int
    {
        --$n;
        $n |= $n >> 1;
        $n |= $n >> 2;
        $n |= $n >> 4;
        $n |= $n >> 8;
        $n |= $n >> 16;

        return $n + 1;
    }

    /**
     * Remove a buffer from the allBuffers list.
     */
    private function removeFromAllBuffers(Buffer $buffer): void
    {
        $this->allBuffers = array_filter(
            $this->allBuffers,
            fn ($b) => $b !== $buffer
        );
    }
}

/**
 * Pooled buffer wrapper that returns buffer to pool when freed.
 */
class PooledBuffer
{
    private BufferPool $pool;
    private Buffer $buffer;
    private int $bucketSize;
    private bool $released = false;

    public function __construct(BufferPool $pool, Buffer $buffer, int $bucketSize)
    {
        $this->pool = $pool;
        $this->buffer = $buffer;
        $this->bucketSize = $bucketSize;
    }

    /**
     * Get the underlying buffer.
     */
    public function getBuffer(): Buffer
    {
        return $this->buffer;
    }

    /**
     * Get buffer length.
     */
    public function getLength(): int
    {
        return $this->buffer->getLength();
    }

    /**
     * Read buffer contents.
     */
    public function getContents(int $offset = 0, int $length = 0): string
    {
        return $this->buffer->getContents($offset, $length);
    }

    /**
     * Write buffer contents.
     */
    public function setContents(string $data, int $offset = 0): void
    {
        $this->buffer->setContents($data, $offset);
    }

    /**
     * Get GPU address.
     */
    public function getGpuAddress(): int
    {
        return $this->buffer->getGpuAddress();
    }

    /**
     * Get native handle.
     */
    public function getHandle(): mixed
    {
        return $this->buffer->getHandle();
    }

    /**
     * Release buffer back to pool.
     */
    public function release(): void
    {
        if (!$this->released) {
            $this->released = true;
            $this->pool->release($this->buffer, $this->bucketSize);
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
