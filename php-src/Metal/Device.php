<?php

/**
 * H3PHP — Metal Device (PHP Wrapper).
 *
 * PHP-side wrapper for the native Metal device.
 * Follows the factory pattern from php-metal-gpu.
 */

namespace H3Php\Metal;

class Device
{
    /** Opaque handle to the native Metal device */
    private mixed $handle;

    /**
     * Create a Metal device for the system default GPU.
     *
     * @throws \RuntimeException If Metal device creation fails
     */
    public function __construct()
    {
        $this->handle = h3_metal_device_create();

        if (false === $this->handle) {
            throw new \RuntimeException('Failed to create Metal device. Ensure you are running on macOS Apple Silicon.');
        }
    }

    /**
     * Get device information.
     *
     * @return array{name: string, architecture: string, physical_memory: int, recommended_working_set: int, max_buffer_length: int, apple_gpu_family: int, metal4: bool, unified_memory: bool}
     */
    public function getInfo(): array
    {
        return h3_metal_device_get_info($this->handle);
    }

    /**
     * Get the device name.
     */
    public function getName(): string
    {
        return h3_metal_device_get_name($this->handle);
    }

    /**
     * Check if the device supports Metal 4 features.
     */
    public function supportsMetal4(): bool
    {
        return h3_metal_device_supports_metal4($this->handle);
    }

    /**
     * Get the native handle (for passing to other Metal objects).
     */
    public function getHandle(): mixed
    {
        return $this->handle;
    }

    /**
     * Free the device resources.
     */
    public function free(): void
    {
        if (null !== $this->handle) {
            h3_metal_device_free($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->free();
    }
}
