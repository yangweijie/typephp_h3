<?php

/**
 * H3PHP — Backend Interface.
 *
 * Abstract contract that all inference backends must implement.
 * The application layer depends on this interface, not concrete backends.
 *
 * Lifecycle:
 *   1. create() with configuration
 *   2. check getStatus() for readiness
 *   3. call loadModel() to prepare weights
 *   4. call generate() for inference
 *   5. call free() to release resources
 */

namespace H3Php\Core;

interface BackendInterface
{
    /**
     * Get the backend type identifier.
     */
    public function getType(): BackendType;

    /**
     * Load model weights into memory.
     *
     * @param string $modelDir Path to model directory
     * @param array<string, mixed> $options Backend-specific options
     * @return bool True if model loaded successfully
     */
    public function loadModel(string $modelDir, array $options = []): bool;

    /**
     * Generate video from text prompt.
     *
     * @param string $prompt Text prompt for video generation
     * @param array<string, mixed> $params Generation parameters (width, height, frames, steps, etc.)
     * @return string Path to generated output file
     */
    public function generate(string $prompt, array $params = []): string;

    /**
     * Cancel an in-progress generation job.
     *
     * @param string $jobId Job identifier
     */
    public function cancel(string $jobId): void;

    /**
     * Get current backend status (readiness, capabilities, etc.).
     */
    public function getStatus(): BackendStatus;

    /**
     * Check if the backend is available on the current system.
     *
     * Performs runtime checks (e.g., Python installed for ComfyUI,
     * Metal support for Native H3, network for HTTP API).
     */
    public function isAvailable(): bool;

    /**
     * Free all resources held by this backend.
     */
    public function free(): void;
}
