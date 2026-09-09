<?php

/**
 * H3PHP — ComfyUI Backend.
 *
 * Cross-platform inference via ComfyUI + Python FFI.
 * Works on macOS, Windows, and Linux with NVIDIA/AMD GPUs.
 *
 * Architecture:
 *   PHP → Python FFI → ComfyUI Python API → PyTorch → GPU
 *
 * Requires:
 *   - Python 3.10+ with torch, comfyui packages
 *   - ComfyUI installed (git clone or portable)
 *   - H3 custom nodes installed in ComfyUI/custom_nodes/
 */

namespace H3Php\Core;

class ComfyUIBackend implements BackendInterface
{
    /** Path to ComfyUI installation */
    private string $comfyuiPath;

    /** Python executable path */
    private string $pythonPath;

    /** Whether model is loaded */
    private bool $modelLoaded = false;

    /** Loaded model directory */
    private string $modelDir = '';

    /**
     * @param string $comfyuiPath Path to ComfyUI installation directory
     * @param string $pythonPath Path to Python executable (default: 'python3')
     */
    public function __construct(
        string $comfyuiPath,
        string $pythonPath = 'python3',
    ) {
        $this->comfyuiPath = rtrim($comfyuiPath, '/\\');
        $this->pythonPath = $pythonPath;
    }

    public function getType(): BackendType
    {
        return BackendType::COMFYUI;
    }

    public function loadModel(string $modelDir, array $options = []): bool
    {
        $this->modelDir = rtrim($modelDir, '/\\');

        // TODO: Via Python FFI:
        //   1. Validate ComfyUI installation
        //   2. Import comfy.model_management
        //   3. Load H3 transformer + VAE into GPU memory
        //   4. Verify model integrity

        $this->modelLoaded = true;

        return true;
    }

    public function generate(string $prompt, array $params = []): string
    {
        if (!$this->modelLoaded) {
            throw new \RuntimeException('Model not loaded. Call loadModel() first.');
        }

        // TODO: Via Python FFI:
        //   1. Build ComfyUI workflow graph (LoadH3Model → TextEncode → KSampler → VAE → Save)
        //   2. Execute via comfy.client
        //   3. Return output file path

        throw new \RuntimeException('ComfyUI generation pending Python FFI integration');
    }

    public function cancel(string $jobId): void
    {
        // TODO: Call comfy.client.interrupt() via FFI
    }

    public function getStatus(): BackendStatus
    {
        if (!$this->isAvailable()) {
            return BackendStatus::notReady('Python or ComfyUI not found');
        }

        if (!$this->modelLoaded) {
            return BackendStatus::notReady('Model not loaded');
        }

        return BackendStatus::ready('ComfyUI ready', [
            'comfyui_path' => $this->comfyuiPath,
            'python_path' => $this->pythonPath,
            'model_dir' => $this->modelDir,
        ]);
    }

    public function isAvailable(): bool
    {
        if (!BackendType::COMFYUI->isAvailableOnCurrentPlatform()) {
            return false;
        }

        // Check Python executable exists
        $output = [];
        $exitCode = 0;
        exec("{$this->pythonPath} --version 2>&1", $output, $exitCode);

        return 0 === $exitCode;
    }

    public function free(): void
    {
        // TODO: Call comfy.model_management.cleanup_models() via FFI
        $this->modelLoaded = false;
        $this->modelDir = '';
    }

    /**
     * Get the ComfyUI installation path.
     */
    public function getComfyuiPath(): string
    {
        return $this->comfyuiPath;
    }

    /**
     * Get the Python executable path.
     */
    public function getPythonPath(): string
    {
        return $this->pythonPath;
    }
}
