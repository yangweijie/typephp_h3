<?php

/**
 * H3PHP — Metal Buffer (PHP Wrapper).
 *
 * PHP-side wrapper for Metal buffers.
 * Maps Objective-C storage modes to PHP constants.
 */

namespace H3Php\Metal;

class Buffer
{
    /** Storage mode: CPU+GPU shared memory */
    public const STORAGE_SHARED = 0;
    /** Storage mode: managed for CPU/GPU coherence */
    public const STORAGE_MANAGED = 1;
    /** Storage mode: GPU-only private memory */
    public const STORAGE_PRIVATE = 2;
    /** Storage mode: memoryless (tile-only) */
    public const STORAGE_MEMORYLESS = 3;

    /** Opaque handle to the native Metal buffer */
    private mixed $handle;

    /**
     * Create a Metal buffer.
     *
     * @param Device $device  Parent device
     * @param int    $length  Buffer size in bytes
     * @param int    $options Storage mode (use class constants)
     *
     * @throws \RuntimeException If buffer creation fails
     */
    public function __construct(Device $device, int $length, int $options = self::STORAGE_SHARED)
    {
        $this->handle = h3_metal_buffer_create($device->getHandle(), $length, $options);

        if (false === $this->handle) {
            throw new \RuntimeException("Failed to create Metal buffer (length={$length})");
        }
    }

    /**
     * Get the buffer length in bytes.
     */
    public function getLength(): int
    {
        return h3_metal_buffer_get_length($this->handle);
    }

    /**
     * Read contents from the buffer.
     *
     * @param int $offset Start offset
     * @param int $length Number of bytes (0 = all)
     */
    public function getContents(int $offset = 0, int $length = 0): string
    {
        return h3_metal_buffer_get_contents($this->handle, $offset, $length);
    }

    /**
     * Write raw bytes to the buffer.
     *
     * @param string $data   Raw bytes
     * @param int    $offset Start offset
     */
    public function setContents(string $data, int $offset = 0): void
    {
        h3_metal_buffer_set_contents($this->handle, $data, $offset);
    }

    /**
     * Get the GPU address (for bindless parameters).
     */
    public function getGpuAddress(): int
    {
        return h3_metal_buffer_get_gpu_address($this->handle);
    }

    /**
     * Get the native handle.
     */
    public function getHandle(): mixed
    {
        return $this->handle;
    }

    /**
     * Free the buffer resources.
     */
    public function free(): void
    {
        if (null !== $this->handle) {
            h3_metal_buffer_free($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->free();
    }
}
