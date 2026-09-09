<?php

/**
 * H3PHP — Download Manager.
 *
 * Unified download orchestrator for ComfyUI, model weights, and dependencies.
 * Supports multiple sources: HuggingFace, ModelScope, CivitAI, GitHub.
 * Features: resume, retry, checksum verification, proxy/mirror support.
 *
 * Enhanced with:
 * - ModelScope full repo download (CLI / Python SDK / Git LFS)
 * - Hardware-based model recommendations
 * - Download presets (minimal / standard / full)
 */

namespace H3Php\Core;

class DownloadManager
{
    private string $downloadDir;
    private DownloadQueue $queue;
    private array $mirrors = [];
    private ?string $proxy;
    private array $history = [];

    /** @var ModelScopeDownloader|null */
    private ?ModelScopeDownloader $modelScopeDownloader = null;

    /** @var ModelRecommender|null */
    private ?ModelRecommender $recommender = null;

    public function __construct(string $downloadDir = '', int $maxConcurrent = 3, ?string $proxy = null)
    {
        $this->downloadDir = $downloadDir ?: (getenv('HOME') . '/h3php/downloads');
        $this->queue = new DownloadQueue($maxConcurrent);
        $this->proxy = $proxy;

        $this->mirrors = [
            'huggingface' => 'https://huggingface.co',
            'huggingface_xget' => 'https://xget.dev/hf-mirror',  // Cloudflare Worker proxy for CN users
            'modelscope' => 'https://modelscope.cn',
            'modelscope_git' => 'https://www.modelscope.cn',
            'civitai' => 'https://civitai.com',
            'github' => 'https://github.com',
        ];
    }

    /**
     * Enable Xget mirror acceleration for China users.
     * Uses Cloudflare Worker to bypass GFW and accelerate downloads.
     *
     * Xget URLs:
     * - hf://models/{repo}  → HuggingFace via Cloudflare
     * - ms://models/{model} → ModelScope via Cloudflare
     */
    public function useXgetMirror(): self
    {
        $this->setMirror('huggingface', 'https://xget.dev/hf-mirror');

        return $this;
    }

    /**
     * Get the ModelScope full-repo downloader.
     */
    public function getModelScopeDownloader(): ModelScopeDownloader
    {
        if (null === $this->modelScopeDownloader) {
            $this->modelScopeDownloader = new ModelScopeDownloader($this->downloadDir . '/.cache');
        }

        return $this->modelScopeDownloader;
    }

    /**
     * Get the model recommender.
     */
    public function getRecommender(EnvironmentDetector $env): ModelRecommender
    {
        if (null === $this->recommender) {
            $this->recommender = new ModelRecommender($env);
        }

        return $this->recommender;
    }

    public function setMirror(string $source, string $url): self
    {
        $this->mirrors[$source] = rtrim($url, '/');

        return $this;
    }

    public function useModelScope(): self
    {
        $this->setMirror('huggingface', 'https://hf-mirror.com');

        return $this;
    }

    public function downloadComfyUI(string $destination): DownloadTask
    {
        $task = new DownloadTask(
            'comfyui_core',
            $this->mirrors['github'] . '/comfyanonymous/ComfyUI.git',
            $destination,
            null,
            'github',
            'ComfyUI Core',
        );

        $this->queue->add($task);

        return $task;
    }

    public function downloadFromHuggingFace(
        string $repo,
        string $filename,
        string $destination,
        ?string $checksum = null,
    ): DownloadTask {
        $url = $this->mirrors['huggingface'] . '/' . $repo . '/resolve/main/' . $filename;
        $taskId = 'hf_' . md5($repo . '/' . $filename);

        $task = new DownloadTask($taskId, $url, $destination, $checksum, 'huggingface', $filename);
        $this->queue->add($task);

        return $task;
    }

    public function downloadFromModelScope(
        string $repo,
        string $filename,
        string $destination,
        ?string $checksum = null,
    ): DownloadTask {
        $url = $this->mirrors['modelscope'] . '/' . $repo . '/resolve/master/' . $filename;
        $taskId = 'ms_' . md5($repo . '/' . $filename);

        $task = new DownloadTask($taskId, $url, $destination, $checksum, 'modelscope', $filename);
        $this->queue->add($task);

        return $task;
    }

    public function downloadDirect(
        string $url,
        string $destination,
        ?string $checksum = null,
        string $name = '',
    ): DownloadTask {
        $taskId = 'direct_' . md5($url);

        $task = new DownloadTask($taskId, $url, $destination, $checksum, 'direct', $name ?: basename($destination));
        $this->queue->add($task);

        return $task;
    }

    /**
     * Download all required H3 model weights.
     *
     * @return DownloadTask[] List of created tasks
     */
    public function downloadH3Models(string $destinationDir, string $source = 'huggingface'): array
    {
        $models = [
            ['repo' => 'MiniMaxAI/MiniMax-H3', 'file' => 'transformer/config.json'],
            ['repo' => 'MiniMaxAI/MiniMax-H3', 'file' => 'transformer/diffusion_pytorch_model.safetensors'],
            ['repo' => 'MiniMaxAI/MiniMax-H3', 'file' => 'video_vae/config.json'],
            ['repo' => 'MiniMaxAI/MiniMax-H3', 'file' => 'video_vae/diffusion_pytorch_model.safetensors'],
            ['repo' => 'MiniMaxAI/MiniMax-H3', 'file' => 'tokenizer/tokenizer.json'],
            ['repo' => 'MiniMaxAI/MiniMax-H3', 'file' => 'tokenizer/tokenizer_config.json'],
        ];

        $tasks = [];
        foreach ($models as $model) {
            $dest = $destinationDir . '/' . $model['file'];
            if ('modelscope' === $source) {
                $task = $this->downloadFromModelScope($model['repo'], $model['file'], $dest);
            } else {
                $task = $this->downloadFromHuggingFace($model['repo'], $model['file'], $dest);
            }
            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * Queue H3 model weights and begin downloading (non-blocking).
     *
     * Adds the six core MiniMax-H3 files to the queue and starts the
     * async event loop. The caller drives progress via process().
     *
     * @param string $destinationDir Target directory (defaults to downloadDir)
     * @param string $source 'huggingface' or 'modelscope'
     */
    public function startH3ModelDownload(string $destinationDir = '', string $source = 'huggingface'): void
    {
        $dest = $destinationDir ?: $this->downloadDir;
        $this->downloadH3Models($dest, $source);
        $this->startAsync();
    }

    /**
     * Start all queued downloads (blocking).
     */
    public function start(): void
    {
        $this->queue->start();
    }

    /**
     * Start all queued downloads without blocking.
     * The caller must drive progress via process() from its event loop.
     */
    public function startAsync(): void
    {
        $this->queue->startAsync();
    }

    /**
     * Advance downloads by one step.
     * Non-blocking: returns true while downloads are still in flight.
     */
    public function process(): bool
    {
        return $this->queue->process();
    }

    /**
     * Pause all downloads.
     */
    public function pause(): void
    {
        $this->queue->pause();
    }

    /**
     * Cancel all downloads.
     */
    public function cancelAll(): void
    {
        $this->queue->cancelAll();
    }

    /**
     * Get the download queue.
     */
    public function getQueue(): DownloadQueue
    {
        return $this->queue;
    }

    /**
     * Get all tasks.
     *
     * @return DownloadTask[]
     */
    public function getTasks(): array
    {
        return $this->queue->getTasks();
    }

    /**
     * Get overall download progress (0-100).
     */
    public function getOverallProgress(): float
    {
        return $this->queue->getOverallProgress();
    }

    /**
     * Get download statistics.
     */
    public function getStats(): array
    {
        return $this->queue->getStats();
    }

    /**
     * Set global progress callback.
     */
    public function onProgress(callable $callback): self
    {
        $this->queue->onProgress($callback);

        return $this;
    }

    /**
     * Get the download directory.
     */
    public function getDownloadDir(): string
    {
        return $this->downloadDir;
    }

    /**
     * Check if a file has already been downloaded.
     */
    public function isDownloaded(string $path): bool
    {
        return file_exists($path) && filesize($path) > 0;
    }

    /**
     * Download a full model repo from ModelScope using CLI/SDK.
     *
     * This is the recommended way to download entire model repositories
     * as it supports Git LFS, resume, and caching automatically.
     *
     * @param string $modelId ModelScope model ID (e.g., "Qwen/Qwen2.5-0.5B-Instruct")
     * @param string $localDir Target directory
     * @param callable|null $onProgress Progress callback (percent, message)
     * @return bool Success
     */
    public function downloadModelScopeRepo(string $modelId, string $localDir, ?callable $onProgress = null): bool
    {
        return $this->getModelScopeDownloader()->downloadRepo($modelId, $localDir, $onProgress);
    }

    /**
     * Download all models in a preset.
     *
     * @param DownloadPreset $preset The preset to download
     * @param string $baseDir Base directory for all model downloads
     * @param string $source Download source: 'modelscope' or 'huggingface'
     * @param callable|null $onProgress Progress callback (component_name, percent, message)
     * @return array<string, bool> Results per component ID
     */
    public function downloadPreset(DownloadPreset $preset, string $baseDir, string $source = 'modelscope', ?callable $onProgress = null): array
    {
        $results = [];

        foreach ($preset->components as $component) {
            $localDir = $baseDir . '/' . $component->id;

            if ($source === 'modelscope' && $this->getModelScopeDownloader()->isAvailable()) {
                // Use ModelScope CLI/SDK for full repo download
                $success = $this->downloadModelScopeRepo(
                    $component->modelscopeId,
                    $localDir,
                    function (float $percent, string $msg) use ($component, $onProgress) {
                        if (null !== $onProgress) {
                            $onProgress($component->id, $percent, $msg);
                        }
                    }
                );
            } else {
                // Fall back to direct HTTP download
                $success = $this->downloadFromUrl($component, $localDir);
            }

            $results[$component->id] = $success;
        }

        return $results;
    }

    /**
     * Get recommended preset for current hardware.
     *
     * @return array{preset: DownloadPreset, reason: string, warnings: string[]}
     */
    public function getRecommendation(EnvironmentDetector $env): array
    {
        return $this->getRecommender($env)->recommend();
    }

    /**
     * Get all presets with feasibility info.
     *
     * @return array<int, array{preset: DownloadPreset, feasible: bool, reason: string}>
     */
    public function getPresetsWithFeasibility(EnvironmentDetector $env): array
    {
        return $this->getRecommender($env)->getPresetsWithFeasibility();
    }

    /**
     * Recommend download source based on network.
     *
     * @return array{source: string, reason: string}
     */
    public function recommendSource(EnvironmentDetector $env): array
    {
        return $this->getRecommender($env)->recommendSource();
    }

    /**
     * Queue all components of a preset for async download.
     *
     * Unlike downloadPreset() (blocking), this only queues the tasks
     * and returns immediately. The caller drives progress via process().
     *
     * @param DownloadPreset $preset The preset to queue
     * @param string $baseDir Base directory for all model downloads
     * @param string $source Download source: 'modelscope' or 'huggingface'
     * @param callable|null $onProgress Progress callback (component_name, percent, message)
     */
    public function queuePreset(DownloadPreset $preset, string $baseDir, string $source = 'modelscope', ?callable $onProgress = null): void
    {
        foreach ($preset->components as $component) {
            $localDir = $baseDir . '/' . $component->id;
            $url = $this->mirrors['modelscope'] . '/' . $component->modelscopeId . '/resolve/master/';

            $this->downloadDirect($url, $localDir . '/model.safetensors', null, $component->name);
        }

        if (null !== $onProgress) {
            $this->onProgress($onProgress);
        }

        $this->startAsync();
    }

    /**
     * Download a single component via HTTP (fallback method).
     */
    private function downloadFromUrl(ModelComponent $component, string $localDir): bool
    {
        $url = $this->mirrors['modelscope'] . '/' . $component->modelscopeId . '/resolve/master/';

        $task = $this->downloadDirect($url, $localDir . '/model.safetensors', null, $component->name);
        $this->start();

        return 'completed' === $task->status;
    }
}