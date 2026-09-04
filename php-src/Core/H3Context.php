<?php

/**
 * H3PHP — Engine Context.
 *
 * Manages the lifecycle of the MiniMax-H3 inference engine session.
 * Equivalent to h3_ctx in h3.c. Holds:
 *   - Metal device reference
 *   - Model directory path
 *   - Loaded model state
 *   - Generation parameters
 *   - Memory plan
 *
 * Lifecycle:
 *   1. Create with model directory
 *   2. Initialize Metal device
 *   3. Validate model structure
 *   4. Load weights (per-stage, not all at once)
 *   5. Generate
 *   6. Free resources
 */

namespace H3Php\Core;

use H3Php\Cli\Application;

class H3Context
{
    /** Model directory path */
    private string $modelDir;

    /** CLI application reference for output */
    private Application $app;

    /** Resolves per-component on-disk locations (honors model manifest) */
    private ModelLayout $layout;

    /** Metal device info (populated after initialization) */
    private array $deviceInfo = [];

    /** Model configuration (from transformer/config.json) */
    private ?array $modelConfig = null;

    /** Validation state */
    private bool $validated = false;

    /** Validation errors */
    private array $validationErrors = [];

    /** Whether Metal device is initialized */
    private bool $deviceInitialized = false;

    /** Model inventory cache */
    private array $inventory = [];

    public function __construct(string $modelDir, Application $app, ?string $manifestPath = null)
    {
        $this->modelDir = rtrim($modelDir, '/\\');
        $this->app = $app;
        $this->layout = new ModelLayout($this->modelDir, $manifestPath);
    }

    /**
     * Initialize the Metal GPU device.
     *
     * TODO: Replace with native Metal device query via cpp-src/metal_device.mm
     */
    public function initializeDevice(): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->app->error('Metal GPU requires macOS Apple Silicon', 1);
        }

        // Placeholder: actual implementation will call native Metal layer
        $this->deviceInfo = [
            'name' => '<pending Metal integration>',
            'architecture' => '<pending>',
            'physical_memory_gib' => 0,
            'recommended_working_set_gib' => 0,
            'max_buffer_length_gib' => 0,
            'apple_gpu_family' => 0,
            'metal4' => false,
            'unified_memory' => true,
        ];

        $this->deviceInitialized = true;

        return true;
    }

    /**
     * Validate the model directory structure.
     */
    public function validate(): bool
    {
        $loader = new ModelLoader($this->layout);
        $result = $loader->validate();

        $this->validated = $result['valid'];
        $this->validationErrors = $result['errors'];

        return $this->validated;
    }

    /**
     * Get validation errors.
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Scan and cache the model directory inventory.
     */
    public function scanInventory(): array
    {
        $loader = new ModelLoader($this->layout);
        $this->inventory = $loader->scanDirectory();

        return $this->inventory;
    }

    /**
     * Get the model directory path.
     */
    public function getModelDir(): string
    {
        return $this->modelDir;
    }

    /**
     * Get device information.
     */
    public function getDeviceInfo(): array
    {
        return $this->deviceInfo;
    }

    /**
     * Get model configuration.
     */
    public function getModelConfig(string $stream = 'FL2VA'): ?array
    {
        if (null === $this->modelConfig) {
            $loader = new ModelLoader($this->layout);
            $this->modelConfig = $loader->getModelConfig($stream);
        }

        return $this->modelConfig;
    }

    /**
     * Get the model layout resolver (honors the model manifest).
     */
    public function getLayout(): ModelLayout
    {
        return $this->layout;
    }

    /**
     * Check if device is initialized.
     */
    public function isDeviceInitialized(): bool
    {
        return $this->deviceInitialized;
    }

    /**
     * Check if model directory is validated.
     */
    public function isValidated(): bool
    {
        return $this->validated;
    }

    /**
     * Get the cached model inventory.
     */
    public function getInventory(): array
    {
        return $this->inventory;
    }

    /**
     * Auto-generate memory plan based on device capabilities.
     *
     * TODO: Implement memory planner based on h3.c's h3_memory_plan.c
     */
    public function autoMemoryPlan(): array
    {
        $info = $this->deviceInfo;
        $recommendedGib = $info['recommended_working_set_gib'] ?? 0;

        // Placeholder logic: actual implementation analyzes model sizes vs available memory
        return [
            'ssd_streaming' => $recommendedGib < 64,
            'int8_row_fc2' => $info['metal4'] ?? false,
            'video_vae_streaming' => $recommendedGib < 32,
            'encoder_streaming' => $recommendedGib < 16,
            'layers' => 50,
            'reason' => 'Auto: pending memory plan implementation',
        ];
    }

    /**
     * Free all resources.
     */
    public function free(): void
    {
        $this->deviceInitialized = false;
        $this->validated = false;
        $this->modelConfig = null;
        $this->inventory = [];
        $this->deviceInfo = [];
        $this->validationErrors = [];
    }
}
