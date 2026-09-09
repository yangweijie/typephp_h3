<?php

/**
 * H3PHP — Model Manager.
 *
 * Unified model discovery and validation.
 * Scans both Native H3 model directories and ComfyUI model directories.
 *
 * Supported model layouts:
 * - Native H3: MODEL_DIR/FL2VA/transformer/, MODEL_DIR/FL2VA/video_vae/, etc.
 * - ComfyUI: comfyui/models/checkpoints/, comfyui/models/vae/, etc.
 */

namespace H3Php\Core;

class ModelManager
{
    /** Recommended disk budget for a full H3 model set (~50 GB) */
    public const RECOMMENDED_DISK_BYTES = 53687091200;

    /** Known model directory roots */
    private array $searchPaths = [];

    /** Discovered models */
    private array $models = [];

    /** Scan results from last scan() call */
    private array $scanResults = [];

    /**
     * @param array $searchPaths Initial model search directory paths
     */
    public function __construct(array $searchPaths = [])
    {
        $this->searchPaths = $searchPaths;
    }

    /**
     * Add a search path.
     */
    public function addSearchPath(string $path): self
    {
        if (is_dir($path) && !in_array($path, $this->searchPaths, true)) {
            $this->searchPaths[] = $path;
        }

        return $this;
    }

    /**
     * Scan all search paths for models.
     *
     * @return array Discovered models info
     */
    public function scan(): array
    {
        $this->models = [];
        $this->scanResults = [];

        foreach ($this->searchPaths as $path) {
            $this->scanDirectory($path);
        }

        return $this->models;
    }

    /**
     * Scan a single directory for models.
     */
    private function scanDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        // Detect layout type
        if ($this->isNativeH3Layout($path)) {
            $this->scanNativeH3($path);
        }

        if ($this->isComfyUILayout($path)) {
            $this->scanComfyUI($path);
        }
    }

    /**
     * Check if directory is Native H3 layout.
     * Native layout: contains FL2VA/ or Ref2VA/ subdirectories.
     */
    private function isNativeH3Layout(string $path): bool
    {
        return is_dir($path . '/FL2VA') || is_dir($path . '/Ref2VA');
    }

    /**
     * Check if directory is ComfyUI layout.
     * ComfyUI layout: contains models/ subdirectories like checkpoints/, vae/, etc.
     */
    private function isComfyUILayout(string $path): bool
    {
        $modelsDir = $path . '/models';
        if (!is_dir($modelsDir)) {
            return false;
        }

        // Check for known ComfyUI model subdirectories
        $indicators = ['checkpoints', 'vae', 'text_encoders', 'clip', 'loras', 'upscale_models'];

        foreach ($indicators as $dir) {
            if (is_dir($modelsDir . '/' . $dir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan Native H3 model directory.
     */
    private function scanNativeH3(string $path): void
    {
        $fl2va = $path . '/FL2VA';
        if (!is_dir($fl2va)) {
            return;
        }

        $transformerDir = $fl2va . '/transformer';
        if (is_dir($transformerDir)) {
            $configFile = $transformerDir . '/config.json';
            $this->models[] = [
                'type' => 'transformer',
                'backend' => 'native_h3',
                'path' => $transformerDir,
                'name' => basename($path) . '/transformer',
                'config_exists' => file_exists($configFile),
                'size' => $this->getDirectorySize($transformerDir),
                'files' => $this->countFiles($transformerDir),
            ];
        }

        $vaeDir = $fl2va . '/video_vae';
        if (is_dir($vaeDir)) {
            $this->models[] = [
                'type' => 'video_vae',
                'backend' => 'native_h3',
                'path' => $vaeDir,
                'name' => basename($path) . '/video_vae',
                'config_exists' => false,
                'size' => $this->getDirectorySize($vaeDir),
                'files' => $this->countFiles($vaeDir),
            ];
        }

        $tokenizerDir = $fl2va . '/tokenizer';
        if (is_dir($tokenizerDir)) {
            $this->models[] = [
                'type' => 'tokenizer',
                'backend' => 'native_h3',
                'path' => $tokenizerDir,
                'name' => basename($path) . '/tokenizer',
                'config_exists' => file_exists($tokenizerDir . '/tokenizer.json'),
                'size' => $this->getDirectorySize($tokenizerDir),
                'files' => $this->countFiles($tokenizerDir),
            ];
        }

        $audioVaeDir = $fl2va . '/audio_vae';
        if (is_dir($audioVaeDir)) {
            $this->models[] = [
                'type' => 'audio_vae',
                'backend' => 'native_h3',
                'path' => $audioVaeDir,
                'name' => basename($path) . '/audio_vae',
                'config_exists' => false,
                'size' => $this->getDirectorySize($audioVaeDir),
                'files' => $this->countFiles($audioVaeDir),
            ];
        }
    }

    /**
     * Scan ComfyUI model directory.
     */
    private function scanComfyUI(string $path): void
    {
        $modelsDir = $path . '/models';

        $typeMap = [
            'checkpoints' => 'checkpoint',
            'vae' => 'vae',
            'text_encoders' => 'text_encoder',
            'clip' => 'clip',
            'clip_vision' => 'clip_vision',
            'loras' => 'lora',
            'upscale_models' => 'upscaler',
            'controlnet' => 'controlnet',
            'embeddings' => 'embedding',
            'diffusion_models' => 'diffusion_model',
        ];

        foreach ($typeMap as $dirName => $modelType) {
            $typeDir = $modelsDir . '/' . $dirName;
            if (!is_dir($typeDir)) {
                continue;
            }

            $this->scanComfyUITypeDir($typeDir, $modelType, $path);
        }
    }

    /**
     * Scan a specific ComfyUI model type directory.
     */
    private function scanComfyUITypeDir(string $dir, string $modelType, string $rootPath): void
    {
        $files = scandir($dir);

        foreach ($files as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }

            $fullPath = $dir . '/' . $file;

            if (is_dir($fullPath)) {
                // Subdirectory (e.g., checkpoints/MiniMax-H3/)
                $this->scanComfyUITypeDir($fullPath, $modelType, $rootPath);
                continue;
            }

            // Check for model files
            if ($this->isModelFile($file)) {
                $this->models[] = [
                    'type' => $modelType,
                    'backend' => 'comfyui',
                    'path' => $fullPath,
                    'name' => $file,
                    'config_exists' => false,
                    'size' => filesize($fullPath),
                    'files' => 1,
                ];
            }
        }
    }

    /**
     * Check if a filename is a model file.
     */
    private function isModelFile(string $filename): bool
    {
        $extensions = ['.safetensors', '.ckpt', '.pt', '.pth', '.bin', '.gguf'];

        foreach ($extensions as $ext) {
            if (str_ends_with(strtolower($filename), $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all discovered models.
     */
    public function getModels(): array
    {
        return $this->models;
    }

    /**
     * Get models by type.
     *
     * @return array Models matching the type
     */
    public function getModelsByType(string $type): array
    {
        return array_filter($this->models, fn (array $m) => $m['type'] === $type);
    }

    /**
     * Get models by backend.
     *
     * @return array Models matching the backend
     */
    public function getModelsByBackend(string $backend): array
    {
        return array_filter($this->models, fn (array $m) => $m['backend'] === $backend);
    }

    /**
     * Find a specific model by name.
     */
    public function findByName(string $name): ?array
    {
        foreach ($this->models as $model) {
            if (str_contains($model['name'], $name)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Validate all discovered models.
     *
     * @return array Validation results per model
     */
    public function validate(): array
    {
        $results = [];

        foreach ($this->models as $model) {
            $result = [
                'model' => $model['name'],
                'valid' => true,
                'errors' => [],
                'warnings' => [],
            ];

            // Check path exists
            if (!file_exists($model['path'])) {
                $result['valid'] = false;
                $result['errors'][] = "Path not found: {$model['path']}";
            }

            // Check size
            if ($model['size'] <= 0) {
                $result['valid'] = false;
                $result['errors'][] = 'Model is empty (0 bytes)';
            }

            // Check config for transformers
            if ('transformer' === $model['type'] && !$model['config_exists']) {
                $result['warnings'][] = 'Missing config.json';
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Get total size of all discovered models.
     */
    public function getTotalSize(): int
    {
        return array_sum(array_column($this->models, 'size'));
    }

    /**
     * Core component types required for text-to-video generation.
     */
    public const REQUIRED_TYPES = ['transformer', 'video_vae', 'tokenizer'];

    /**
     * Map backend-specific types onto the canonical component types.
     */
    private const TYPE_ALIASES = [
        'checkpoint' => 'transformer',
        'diffusion_model' => 'transformer',
        'vae' => 'video_vae',
        'text_encoder' => 'tokenizer',
        'clip' => 'tokenizer',
    ];

    /**
     * Canonical component types that were not discovered by the last scan.
     *
     * @return string[]
     */
    public function getMissingTypes(): array
    {
        $found = [];
        foreach ($this->models as $model) {
            $type = $model['type'];
            $found[self::TYPE_ALIASES[$type] ?? $type] = true;
        }

        $missing = [];
        foreach (self::REQUIRED_TYPES as $type) {
            if (!isset($found[$type])) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    /**
     * Disk usage summary for the model storage.
     *
     * @return array{used: int, recommended: int, free: int, total: int, percent: float}
     */
    public function getDiskUsage(): array
    {
        $root = $this->searchPaths[0] ?? '.';
        $free = disk_free_space($root);
        $total = disk_total_space($root);

        $used = (float) $this->getTotalSize();
        $recommended = (float) self::RECOMMENDED_DISK_BYTES;

        return [
            'used' => (int) $used,
            'recommended' => self::RECOMMENDED_DISK_BYTES,
            'free' => false === $free ? 0 : (int) $free,
            'total' => false === $total ? 0 : (int) $total,
            'percent' => min(100.0, $used / $recommended * 100.0),
        ];
    }

    /**
     * Delete a discovered model (single file or whole directory).
     *
     * Refuses paths outside the registered search paths so a bad index
     * can never delete arbitrary user files.
     */
    public function removeModel(string $path): bool
    {
        $real = realpath($path);
        if (false === $real || !$this->isInsideSearchPaths($real)) {
            return false;
        }

        return is_dir($real) ? $this->deleteRecursive($real) : @unlink($real);
    }

    /**
     * Best-effort version label read from the model's config.json.
     * Returns '-' when no usable version field is present.
     */
    public static function getVersion(array $model): string
    {
        $path = $model['path'] ?? '';
        if ('' === $path) {
            return '-';
        }

        $config = is_dir($path) ? $path . '/config.json' : dirname($path) . '/config.json';
        if (!is_file($config)) {
            return '-';
        }

        $json = json_decode((string) file_get_contents($config), true);
        if (!is_array($json)) {
            return '-';
        }

        foreach (['version', 'model_type', 'architectures'] as $key) {
            $value = $json[$key] ?? null;
            if (is_string($value) && '' !== $value) {
                return $value;
            }
            if (is_array($value) && isset($value[0]) && is_string($value[0])) {
                return $value[0];
            }
        }

        return '-';
    }

    /**
     * Check whether an absolute path lives under a registered search path.
     */
    private function isInsideSearchPaths(string $real): bool
    {
        foreach ($this->searchPaths as $searchPath) {
            $base = realpath($searchPath);
            if (false !== $base && str_starts_with($real, (string) $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively delete a directory (children first).
     */
    private function deleteRecursive(string $path): bool
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        return @rmdir($path);
    }

    /**
     * Format bytes to human-readable string.
     */
    public static function formatSize(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return sprintf('%.1f %s', $size, $units[$unitIndex]);
    }

    /**
     * Get directory size recursively.
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Count files in directory recursively.
     */
    private function countFiles(string $path): int
    {
        $count = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Auto-detect model directories on the system.
     */
    public function autoDetect(): self
    {
        // Check common locations
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        $commonPaths = [
            // User models directory
            $home . '/models',
            $home . '/Models',
            $home . '/Documents/models',
            // ComfyUI in home
            $home . '/ComfyUI',
            $home . '/comfyui',
            $home . '/AI/ComfyUI',
            // macOS specific
            '/opt/models',
            // Linux specific
            '/usr/share/models',
            // Windows specific
            getenv('USERPROFILE') . '\\ComfyUI',
            'C:\\ComfyUI',
            'D:\\ComfyUI',
        ];

        foreach ($commonPaths as $path) {
            if (is_dir($path)) {
                $this->addSearchPath($path);
            }
        }

        return $this;
    }
}
