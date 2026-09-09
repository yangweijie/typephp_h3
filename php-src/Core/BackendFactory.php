<?php

/**
 * H3PHP — Backend Factory.
 *
 * Creates and configures inference backends at runtime.
 * Central point for backend instantiation and selection.
 *
 * Usage:
 *   $backend = BackendFactory::create(BackendType::COMFYUI, ['comfyui_path' => '/path']);
 *   $backend->loadModel('/path/to/models');
 *   $output = $backend->generate('a red fox');
 */

namespace H3Php\Core;

use H3Php\Cli\Application;

class BackendFactory
{
    /**
     * Create a backend instance.
     *
     * @param BackendType $type Backend type to create
     * @param array<string, mixed> $config Backend-specific configuration
     * @return BackendInterface
     * @throws \InvalidArgumentException If backend type is unsupported or misconfigured
     */
    public static function create(BackendType $type, array $config = []): BackendInterface
    {
        return match ($type) {
            BackendType::NATIVE_H3 => self::createNativeH3($config),
            BackendType::COMFYUI => self::createComfyUI($config),
            BackendType::HTTP_API => self::createHttpApi($config),
        };
    }

    /**
     * Create Native H3 backend.
     *
     * Config options:
     *   - app: Application (required) — CLI app reference
     *
     * @param array<string, mixed> $config
     */
    private static function createNativeH3(array $config): NativeH3Backend
    {
        if (!isset($config['app']) || !$config['app'] instanceof Application) {
            throw new \InvalidArgumentException('NativeH3Backend requires "app" (Application) in config');
        }

        return new NativeH3Backend($config['app']);
    }

    /**
     * Create ComfyUI backend.
     *
     * Config options:
     *   - comfyui_path: string (required) — Path to ComfyUI installation
     *   - python_path: string (default: 'python3') — Python executable path
     *
     * @param array<string, mixed> $config
     */
    private static function createComfyUI(array $config): ComfyUIBackend
    {
        if (empty($config['comfyui_path'])) {
            throw new \InvalidArgumentException('ComfyUIBackend requires "comfyui_path" in config');
        }

        return new ComfyUIBackend(
            $config['comfyui_path'],
            $config['python_path'] ?? 'python3',
        );
    }

    /**
     * Create HTTP API backend.
     *
     * Config options:
     *   - base_url: string (required) — API base URL
     *   - api_key: string (default: '') — API key
     *   - timeout: int (default: 300) — Request timeout
     *
     * @param array<string, mixed> $config
     */
    private static function createHttpApi(array $config): HttpBackend
    {
        if (empty($config['base_url'])) {
            throw new \InvalidArgumentException('HttpBackend requires "base_url" in config');
        }

        return new HttpBackend(
            $config['base_url'],
            $config['api_key'] ?? '',
            $config['timeout'] ?? 300,
        );
    }

    /**
     * Auto-detect the best available backend for the current system.
     *
     * Priority:
     *   1. Native H3 (if macOS Apple Silicon)
     *   2. ComfyUI (if Python + ComfyUI found)
     *   3. HTTP API (always available, lowest priority)
     *
     * @param array<string, mixed> $config Base config (comfyui_path, base_url, etc.)
     * @return BackendInterface|null Best available backend, or null if none available
     */
    public static function autoDetect(array $config = []): ?BackendInterface
    {
        // Priority 1: Native H3 on macOS
        if (BackendType::NATIVE_H3->isAvailableOnCurrentPlatform() && isset($config['app'])) {
            return self::createNativeH3($config);
        }

        // Priority 2: ComfyUI if path provided and Python available
        if (!empty($config['comfyui_path'])) {
            $backend = self::createComfyUI($config);
            if ($backend->isAvailable()) {
                return $backend;
            }
        }

        // Priority 3: HTTP API if URL provided
        if (!empty($config['base_url'])) {
            return self::createHttpApi($config);
        }

        return null;
    }
}
