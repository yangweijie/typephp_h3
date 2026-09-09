<?php

/**
 * H3PHP — Backend Type Enumeration.
 *
 * Defines available inference backends for video generation.
 * Used by BackendFactory for runtime selection.
 */

namespace H3Php\Core;

enum BackendType: string
{
    /** Native H3 C library with Metal GPU (macOS Apple Silicon only) */
    case NATIVE_H3 = 'native_h3';

    /** ComfyUI via Python FFI (cross-platform: macOS, Windows, Linux) */
    case COMFYUI = 'comfyui';

    /** Remote HTTP API (REST/WebSocket) */
    case HTTP_API = 'http_api';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::NATIVE_H3 => 'Native H3 (Metal)',
            self::COMFYUI => 'ComfyUI (Python FFI)',
            self::HTTP_API => 'HTTP API (Remote)',
        };
    }

    /**
     * Check if this backend is available on the current platform.
     */
    public function isAvailableOnCurrentPlatform(): bool
    {
        return match ($this) {
            self::NATIVE_H3 => PHP_OS_FAMILY === 'Darwin',
            self::COMFYUI => true,  // Cross-platform via Python
            self::HTTP_API => true, // Network-based
        };
    }

    /**
     * Get all backend types.
     *
     * @return self[]
     */
    public static function all(): array
    {
        return [self::NATIVE_H3, self::COMFYUI, self::HTTP_API];
    }

    /**
     * Get backends available on current platform.
     *
     * @return self[]
     */
    public static function available(): array
    {
        return array_filter(self::all(), fn (self $type) => $type->isAvailableOnCurrentPlatform());
    }
}
