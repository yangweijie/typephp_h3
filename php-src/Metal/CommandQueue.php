<?php

/**
 * H3PHP — Metal Command Queue (PHP Wrapper).
 *
 * PHP-side wrapper for Metal command queue, command buffers, and compute encoders.
 * Implements the encode-dispatch-commit-wait pattern.
 */

namespace H3Php\Metal;

class CommandQueue
{
    /** Opaque handle to the native command queue */
    private mixed $handle;

    /**
     * Create a Metal command queue.
     */
    public function __construct(Device $device)
    {
        $this->handle = h3_metal_command_queue_create($device->getHandle());

        if (false === $this->handle) {
            throw new \RuntimeException('Failed to create Metal command queue');
        }
    }

    /**
     * Get the native handle.
     */
    public function getHandle(): mixed
    {
        return $this->handle;
    }

    /**
     * Free the command queue resources.
     */
    public function free(): void
    {
        if (null !== $this->handle) {
            h3_metal_command_queue_free($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->free();
    }
}

/**
 * Metal Command Buffer — single-use command container.
 */
class CommandBuffer
{
    private mixed $handle;

    public function __construct(CommandQueue $queue)
    {
        $this->handle = h3_metal_command_buffer_create($queue->getHandle());

        if (false === $this->handle) {
            throw new \RuntimeException('Failed to create Metal command buffer');
        }
    }

    public function getHandle(): mixed
    {
        return $this->handle;
    }

    /**
     * Commit the command buffer for GPU execution.
     */
    public function commit(): void
    {
        h3_metal_command_buffer_commit($this->handle);
    }

    /**
     * Wait until the command buffer has completed execution.
     */
    public function waitUntilCompleted(): void
    {
        h3_metal_command_buffer_wait($this->handle);
    }

    /**
     * Commit and wait in one call (synchronous execution).
     */
    public function commitAndWait(): void
    {
        $this->commit();
        $this->waitUntilCompleted();
    }

    public function free(): void
    {
        if (null !== $this->handle) {
            h3_metal_command_buffer_free($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->free();
    }
}

/**
 * Metal Compute Encoder — records compute dispatch commands.
 */
class ComputeEncoder
{
    private mixed $handle;

    public function __construct(CommandBuffer $buffer)
    {
        $this->handle = h3_metal_compute_encoder_create($buffer->getHandle());

        if (false === $this->handle) {
            throw new \RuntimeException('Failed to create Metal compute encoder');
        }
    }

    public function getHandle(): mixed
    {
        return $this->handle;
    }

    /**
     * Set the compute pipeline state.
     */
    public function setPipeline(Pipeline $pipeline): void
    {
        h3_metal_compute_encoder_set_pipeline($this->handle, $pipeline->getHandle());
    }

    /**
     * Set a buffer argument.
     */
    public function setBuffer(Buffer $buffer, int $index, int $offset = 0): void
    {
        h3_metal_compute_encoder_set_buffer($this->handle, $buffer->getHandle(), $index, $offset);
    }

    /**
     * Set inline bytes (<4KB) as an argument.
     */
    public function setBytes(string $data, int $index): void
    {
        h3_metal_compute_encoder_set_bytes($this->handle, $data, $index);
    }

    /**
     * Dispatch threads.
     *
     * @param int $gridX Grid width
     * @param int $gridY Grid height
     * @param int $gridZ Grid depth
     * @param int $tgX   Threadgroup width
     * @param int $tgY   Threadgroup height
     * @param int $tgZ   Threadgroup depth
     */
    public function dispatchThreads(
        int $gridX, int $gridY, int $gridZ,
        int $tgX, int $tgY, int $tgZ,
    ): void {
        h3_metal_compute_encoder_dispatch($this->handle, $gridX, $gridY, $gridZ, $tgX, $tgY, $tgZ);
    }

    /**
     * End encoding.
     */
    public function endEncoding(): void
    {
        h3_metal_compute_encoder_end($this->handle);
    }
}
