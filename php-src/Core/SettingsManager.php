<?php

/**
 * H3PHP — Settings Manager.
 *
 * Persistent application settings with JSON file storage.
 * Supports: general, paths, backend, download, display, and advanced sections.
 */

namespace H3Php\Core;

class SettingsManager
{
    /** Settings file path */
    private string $configPath;

    /** All settings keyed by section */
    private array $settings = [];

    /** Whether settings have been loaded */
    private bool $loaded = false;

    /** Default settings */
    private static array $defaults = [
        'general' => [
            'language' => 'en',
            'auto_update_check' => true,
            'startup_tips' => true,
            'setup_completed' => false,
        ],
        'paths' => [
            'model_dir' => '',
            'output_dir' => '',
            'comfyui_dir' => '',
            'download_dir' => '',
            'temp_dir' => '',
        ],
        'backend' => [
            'preferred_backend' => 'auto', // auto, native_h3, comfyui, http
            'device' => 'auto', // auto, cpu, cuda, metal
            'precision' => 'fp16', // fp32, fp16, bf16
            'memory_limit_gb' => 0, // 0 = auto
        ],
        'download' => [
            'max_concurrent' => 3,
            'preferred_source' => 'huggingface', // huggingface, modelscope, civitai
            'use_mirror' => false,
            'mirror_url' => '',
            'proxy_url' => '',
            'verify_checksums' => true,
        ],
        'display' => [
            'theme' => 'dark', // dark, light, system
            'font_size' => 12,
            'show_grid' => true,
            'snap_to_grid' => true,
            'grid_size' => 20,
            'animations' => true,
        ],
        'advanced' => [
            'log_level' => 'info', // debug, info, warn, error
            'enable_telemetry' => false,
            'experimental_features' => false,
            'cache_size_mb' => 1024,
        ],
    ];

    public function __construct(string $configPath = '')
    {
        $this->configPath = $configPath ?: $this->getDefaultConfigPath();
    }

    /**
     * Get the default config file path.
     */
    public static function getDefaultConfigPath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        return $home . '/.h3php/settings.json';
    }

    /**
     * Load settings from file.
     */
    public function load(): bool
    {
        if (!file_exists($this->configPath)) {
            $this->settings = self::$defaults;
            $this->loaded = true;

            return true;
        }

        $content = file_get_contents($this->configPath);
        if (false === $content) {
            return false;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return false;
        }

        // Merge with defaults (new keys get defaults)
        $defaults = is_array(self::$defaults) ? self::$defaults : [];
        $this->settings = $defaults;
        foreach ($data as $key => $value) {
            $this->settings[$key] = $value;
        }
        $this->loaded = true;

        return true;
    }

    /**
     * Save settings to file.
     */
    public function save(): bool
    {
        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return false !== file_put_contents($this->configPath, $json);
    }

    /**
     * Get a setting value.
     *
     * @param string $key Dot-notation key (e.g., 'backend.device')
     * @param mixed $default Default value if not found
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->loaded) {
            $this->load();
        }

        // Manual dot-notation split (avoids explode/foreach TypePHP issue)
        $dotPos = strpos($key, '.');
        if (false === $dotPos) {
            return $this->settings[$key] ?? $default;
        }

        $section = substr($key, 0, $dotPos);
        $subKey = substr($key, $dotPos + 1);

        if (!isset($this->settings[$section]) || !is_array($this->settings[$section])) {
            return $default;
        }

        return $this->settings[$section][$subKey] ?? $default;
    }

    /**
     * Set a setting value.
     *
     * @param string $key Dot-notation key
     * @param mixed $value Value to set
     */
    public function set(string $key, mixed $value): self
    {
        if (!$this->loaded) {
            $this->load();
        }

        // Manual dot-notation split (avoids explode/foreach TypePHP issue)
        $dotPos = strpos($key, '.');
        if (false === $dotPos) {
            $this->settings[$key] = $value;

            return $this;
        }

        $section = substr($key, 0, $dotPos);
        $subKey = substr($key, $dotPos + 1);

        if (!isset($this->settings[$section]) || !is_array($this->settings[$section])) {
            $this->settings[$section] = [];
        }

        $this->settings[$section][$subKey] = $value;

        return $this;
    }

    /**
     * Get all settings in a section.
     */
    public function getSection(string $section): array
    {
        return $this->settings[$section] ?? [];
    }

    /**
     * Set an entire section.
     */
    public function setSection(string $section, array $values): self
    {
        $this->settings[$section] = $values;

        return $this;
    }

    /**
     * Reset to defaults.
     */
    public function reset(): self
    {
        $this->settings = self::$defaults;

        return $this;
    }

    /**
     * Reset a section to defaults.
     */
    public function resetSection(string $section): self
    {
        if (isset(self::$defaults[$section])) {
            $this->settings[$section] = self::$defaults[$section];
        }

        return $this;
    }

    /**
     * Get all settings.
     */
    public function getAll(): array
    {
        return $this->settings;
    }

    /**
     * Validate settings.
     *
     * @return array List of validation errors
     */
    public function validate(): array
    {
        $errors = [];

        // Validate paths
        $modelDir = $this->get('paths.model_dir');
        if (!empty($modelDir) && !is_dir($modelDir)) {
            $errors[] = "Model directory not found: {$modelDir}";
        }

        $outputDir = $this->get('paths.output_dir');
        if (!empty($outputDir) && !is_dir($outputDir)) {
            $errors[] = "Output directory not found: {$outputDir}";
        }

        // Validate numeric ranges
        $maxConcurrent = $this->get('download.max_concurrent');
        if ($maxConcurrent < 1 || $maxConcurrent > 10) {
            $errors[] = 'max_concurrent must be between 1 and 10';
        }

        $gridSize = $this->get('display.grid_size');
        if ($gridSize < 5 || $gridSize > 100) {
            $errors[] = 'grid_size must be between 5 and 100';
        }

        return $errors;
    }

    /**
     * Export settings to a file.
     */
    public function export(string $path): bool
    {
        $json = json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return false !== file_put_contents($path, $json);
    }

    /**
     * Import settings from a file.
     */
    public function import(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return false;
        }

        // Manual merge (avoids array_merge TypePHP issue)
        $this->settings = is_array(self::$defaults) ? self::$defaults : [];
        foreach ($data as $k => $v) {
            $this->settings[$k] = $v;
        }
        $this->loaded = true;

        return true;
    }

    /**
     * Get the config file path.
     */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }
}
