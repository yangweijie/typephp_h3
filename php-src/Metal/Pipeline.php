<?php

/**
 * H3PHP — Metal Compute Pipeline (PHP Wrapper).
 *
 * PHP-side wrapper for Metal compute pipeline state.
 * Supports runtime MSL compilation and pre-compiled .metallib loading.
 */

namespace H3Php\Metal;

class Pipeline
{
    /** Opaque handle to the native pipeline state */
    private mixed $handle;

    /**
     * @param mixed $handle Opaque native pipeline-state handle
     */
    private function __construct(mixed $handle)
    {
        $this->handle = $handle;
    }

    /**
     * Create a compute pipeline from MSL shader source.
     *
     * @param Device $device       Parent device
     * @param string $shaderSource Metal Shading Language source code
     * @param string $functionName Kernel function name to use
     *
     * @throws \RuntimeException If pipeline creation fails
     */
    public static function create(Device $device, string $shaderSource, string $functionName): self
    {
        $handle = h3_metal_pipeline_create($device->getHandle(), $shaderSource, $functionName);

        if (false === $handle) {
            throw new \RuntimeException("Failed to create Metal pipeline for function '{$functionName}'");
        }

        return new self($handle);
    }

    /**
     * Create a compute pipeline from a pre-compiled .metalib file.
     *
     * @param Device $device       Parent device
     * @param string $metallibPath Path to .metallib file
     * @param string $functionName Kernel function name
     *
     * @throws \RuntimeException If pipeline loading fails
     */
    public static function fromFile(Device $device, string $metallibPath, string $functionName): self
    {
        $handle = h3_metal_pipeline_create_with_file($device->getHandle(), $metallibPath, $functionName);

        if (false === $handle) {
            throw new \RuntimeException("Failed to load Metal pipeline from '{$metallibPath}'");
        }

        return new self($handle);
    }

    /**
     * Get the maximum total threads per threadgroup.
     */
    public function getMaxThreadsPerThreadgroup(): int
    {
        return h3_metal_pipeline_get_max_threads_per_threadgroup($this->handle);
    }

    /**
     * Get the native handle.
     */
    public function getHandle(): mixed
    {
        return $this->handle;
    }

    /**
     * Free the pipeline resources.
     */
    public function free(): void
    {
        if (null !== $this->handle) {
            h3_metal_pipeline_free($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->free();
    }
}
