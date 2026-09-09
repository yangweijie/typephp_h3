<?php

/**
 * H3PHP — HTTP API Backend.
 *
 * Remote inference via REST/WebSocket API.
 * Useful for:
 *   - Using a remote server with powerful GPUs
 *   - Cloud-based MiniMax-H3 services
 *   - Fallback when local GPU unavailable
 */

namespace H3Php\Core;

class HttpBackend implements BackendInterface
{
    /** API base URL */
    private string $baseUrl;

    /** API key for authentication */
    private string $apiKey;

    /** Request timeout in seconds */
    private int $timeout;

    /** Whether model is loaded (remote-side) */
    private bool $modelLoaded = false;

    /**
     * @param string $baseUrl API base URL (e.g., 'https://api.example.com/v1')
     * @param string $apiKey API key for authentication
     * @param int $timeout Request timeout in seconds (default: 300)
     */
    public function __construct(
        string $baseUrl,
        string $apiKey = '',
        int $timeout = 300,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    public function getType(): BackendType
    {
        return BackendType::HTTP_API;
    }

    public function loadModel(string $modelDir, array $options = []): bool
    {
        // For HTTP backend, "load model" means verifying remote model availability
        $modelId = $options['model_id'] ?? 'MiniMax-H3-4B';

        // TODO: POST /models/load {"model_id": "..."}
        // For now, just mark as loaded
        $this->modelLoaded = true;

        return true;
    }

    public function generate(string $prompt, array $params = []): string
    {
        if (!$this->modelLoaded) {
            throw new \RuntimeException('Model not loaded. Call loadModel() first.');
        }

        // TODO: POST /generate {"prompt": "...", "params": {...}}
        // Poll GET /jobs/{id} until complete
        // Return downloaded output file path

        throw new \RuntimeException('HTTP API generation pending endpoint implementation');
    }

    public function cancel(string $jobId): void
    {
        // TODO: POST /jobs/{id}/cancel
    }

    public function getStatus(): BackendStatus
    {
        if (!$this->isAvailable()) {
            return BackendStatus::notReady('HTTP API unreachable');
        }

        if (!$this->modelLoaded) {
            return BackendStatus::notReady('Model not loaded');
        }

        return BackendStatus::ready('HTTP API ready', [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
        ]);
    }

    public function isAvailable(): bool
    {
        if (!BackendType::HTTP_API->isAvailableOnCurrentPlatform()) {
            return false;
        }

        // Simple connectivity check
        $ch = curl_init("{$this->baseUrl}/health");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return 200 === $httpCode || 404 === $httpCode; // 404 = server up, no health endpoint
    }

    public function free(): void
    {
        $this->modelLoaded = false;
    }

    /**
     * Get the API base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
