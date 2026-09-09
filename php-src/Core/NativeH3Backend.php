<?php

/**
 * H3PHP — Native H3 Backend.
 *
 * Wraps the existing H3Context + C library (libh3.a) for Metal GPU inference.
 * macOS Apple Silicon only.
 *
 * This backend reuses the existing H3Context lifecycle:
 *   H3Context → initializeDevice → validate → scanInventory → generate
 */

namespace H3Php\Core;

use H3Php\Cli\Application;

class NativeH3Backend implements BackendInterface
{
    private ?H3Context $context = null;
    private Application $app;
    private bool $modelLoaded = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getType(): BackendType
    {
        return BackendType::NATIVE_H3;
    }

    public function loadModel(string $modelDir, array $options = []): bool
    {
        $manifestPath = $options['manifest'] ?? null;

        $this->context = new H3Context($modelDir, $this->app, $manifestPath);

        if (!$this->context->initializeDevice()) {
            return false;
        }

        if (!$this->context->validate()) {
            return false;
        }

        $this->context->scanInventory();
        $this->modelLoaded = true;

        return true;
    }

    public function generate(string $prompt, array $params = []): string
    {
        if (!$this->modelLoaded || null === $this->context) {
            throw new \RuntimeException('Model not loaded. Call loadModel() first.');
        }

        // TODO: Integrate with native h3.c generation pipeline
        // This will call into cpp-src/*.mm via h3_* stubs
        throw new \RuntimeException('Native H3 generation pending Metal pipeline integration');
    }

    public function cancel(string $jobId): void
    {
        // TODO: Implement via native h3_cancel() stub
    }

    public function getStatus(): BackendStatus
    {
        if (!$this->isAvailable()) {
            return BackendStatus::notReady('Native H3 requires macOS Apple Silicon');
        }

        if (null === $this->context) {
            return BackendStatus::notReady('Backend not initialized');
        }

        if (!$this->modelLoaded) {
            return BackendStatus::notReady('Model not loaded');
        }

        $deviceInfo = $this->context->getDeviceInfo();

        return BackendStatus::ready('Native H3 ready', [
            'device' => $deviceInfo['name'] ?? 'unknown',
            'model_dir' => $this->context->getModelDir(),
        ]);
    }

    public function isAvailable(): bool
    {
        return BackendType::NATIVE_H3->isAvailableOnCurrentPlatform();
    }

    public function free(): void
    {
        if (null !== $this->context) {
            $this->context->free();
            $this->context = null;
        }
        $this->modelLoaded = false;
    }
}
