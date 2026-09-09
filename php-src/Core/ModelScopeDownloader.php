<?php

/**
 * H3PHP — ModelScope Downloader.
 *
 * Downloads models from ModelScope using the official CLI or Python SDK.
 * Supports full repo download (Git LFS), resume, and caching.
 *
 * Download strategies (in priority order):
 * 1. `modelscope download` CLI — fastest, supports resume and LFS
 * 2. Python SDK `snapshot_download()` — same capabilities, programmatic
 * 3. `git clone` with LFS — fallback when modelscope tools unavailable
 */

namespace H3Php\Core;

class ModelScopeDownloader
{
    private string $cacheDir;

    private ?string $pythonPath;

    private ?string $modelscopeCliPath;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
        $this->pythonPath = $this->detectPython();
        $this->modelscopeCliPath = $this->detectModelScopeCli();
    }

    /**
     * Check if any ModelScope download method is available.
     */
    public function isAvailable(): bool
    {
        return null !== $this->modelscopeCliPath || null !== $this->pythonPath;
    }

    /**
     * Get the best available download method.
     */
    public function getPreferredMethod(): string
    {
        if (null !== $this->modelscopeCliPath) {
            return 'cli';
        }

        if (null !== $this->pythonPath && $this->hasModelScopePackage()) {
            return 'sdk';
        }

        if ($this->hasGitLfs()) {
            return 'git';
        }

        return 'none';
    }

    /**
     * Download a full model repo from ModelScope.
     *
     * @param string $modelId ModelScope model ID (e.g., "Qwen/Qwen2.5-0.5B-Instruct")
     * @param string $localDir Target directory
     * @param callable|null $onProgress Progress callback (percent: float, message: string)
     * @return bool Success
     */
    public function downloadRepo(string $modelId, string $localDir, ?callable $onProgress = null): bool
    {
        $method = $this->getPreferredMethod();

        return match ($method) {
            'cli' => $this->downloadViaCli($modelId, $localDir, $onProgress),
            'sdk' => $this->downloadViaSdk($modelId, $localDir, $onProgress),
            'git' => $this->downloadViaGit($modelId, $localDir, $onProgress),
            default => false,
        };
    }

    /**
     * Download using ModelScope CLI.
     */
    private function downloadViaCli(string $modelId, string $localDir, ?callable $onProgress): bool
    {
        $cmd = sprintf(
            '%s download --model="%s" --local_dir %s 2>&1',
            escapeshellarg($this->modelscopeCliPath),
            escapeshellarg($modelId),
            escapeshellarg($localDir)
        );

        return $this->runWithProgress($cmd, $onProgress);
    }

    /**
     * Download using ModelScope Python SDK.
     */
    private function downloadViaSdk(string $modelId, string $localDir, ?callable $onProgress): bool
    {
        $bridgePath = $this->findBridgeScript();

        if (null !== $bridgePath) {
            // Use the dedicated bridge script (better error handling + JSON output)
            $cmd = sprintf(
                '%s %s download %s %s --cache-dir %s 2>&1',
                escapeshellarg($this->pythonPath),
                escapeshellarg($bridgePath),
                escapeshellarg($modelId),
                escapeshellarg($localDir),
                escapeshellarg($this->cacheDir)
            );
        } else {
            // Fallback: inline Python one-liner
            $script = sprintf(
                'from modelscope import snapshot_download; '
                . 'snapshot_download("%s", cache_dir="%s")',
                addslashes($modelId),
                addslashes($this->cacheDir)
            );
            $cmd = sprintf('%s -c %s 2>&1', escapeshellarg($this->pythonPath), escapeshellarg($script));
        }

        return $this->runWithProgress($cmd, $onProgress);
    }

    /**
     * Find the modelscope_bridge.py script.
     */
    private function findBridgeScript(): ?string
    {
        $candidates = [
            __DIR__ . '/../../python-src/modelscope_bridge.py',
            dirname(__DIR__, 2) . '/python-src/modelscope_bridge.py',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Download using Git LFS clone.
     */
    private function downloadViaGit(string $modelId, string $localDir, ?callable $onProgress): bool
    {
        if (null !== $onProgress) {
            $onProgress(0, 'Installing Git LFS...');
        }

        // Ensure Git LFS is installed
        exec('git lfs install 2>&1', $output, $ret);

        $url = "https://www.modelscope.cn/{$modelId}.git";
        $cmd = sprintf(
            'git clone %s %s 2>&1',
            escapeshellarg($url),
            escapeshellarg($localDir)
        );

        return $this->runWithProgress($cmd, $onProgress);
    }

    /**
     * Get download info (size, files) without downloading.
     *
     * @return array{model_id: string, files: array<array{name: string, size: int, sha256: string|null}>, total_size: int}|null
     */
    public function getRepoInfo(string $modelId): ?array
    {
        if (null === $this->pythonPath) {
            return null;
        }

        $script = sprintf(
            'from modelscope import model_file_download; '
            . 'import json, os; '
            . 'from huggingface_hub import HfApi; '
            . 'api = HfApi(); '
            . 'try: '
            . '  info = api.repo_info("%s", repo_type="model"); '
            . '  files = [{"name": f.rfilename, "size": f.size or 0} for f in (info.siblings or [])]; '
            . '  print(json.dumps({"model_id": "%s", "files": files, "total_size": sum(f["size"] for f in files)})); '
            . 'except Exception as e: '
            . '  print(json.dumps({"error": str(e)}))',
            addslashes($modelId),
            addslashes($modelId)
        );

        $cmd = sprintf('%s -c %s 2>&1', escapeshellarg($this->pythonPath), escapeshellarg($script));
        exec($cmd, $output, $ret);

        if (0 !== $ret || empty($output)) {
            return null;
        }

        $data = json_decode(implode("\n", $output), true);

        if (JSON_ERROR_NONE !== json_last_error() || isset($data['error'])) {
            return null;
        }

        return $data;
    }

    /**
     * Detect Python interpreter.
     */
    private function detectPython(): ?string
    {
        $candidates = ['python3', 'python'];

        // Also check common virtual env locations
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        if ($home) {
            $candidates[] = $home . '/.h3python_env/bin/python';
            $candidates[] = $home . '/.h3python_env/Scripts/python.exe';
        }

        foreach ($candidates as $cmd) {
            exec("where {$cmd} 2>nul || which {$cmd} 2>/dev/null", $output, $ret);
            if (0 === $ret && !empty($output)) {
                return trim($output[0]);
            }
        }

        return null;
    }

    /**
     * Detect ModelScope CLI tool.
     */
    private function detectModelScopeCli(): ?string
    {
        $candidates = ['modelscope'];

        foreach ($candidates as $cmd) {
            exec("where {$cmd} 2>nul || which {$cmd} 2>/dev/null", $output, $ret);
            if (0 === $ret && !empty($output)) {
                return trim($output[0]);
            }
        }

        return null;
    }

    /**
     * Check if modelscope Python package is installed.
     */
    private function hasModelScopePackage(): bool
    {
        if (null === $this->pythonPath) {
            return false;
        }

        $cmd = sprintf('%s -c "import modelscope; print(modelscope.__version__)" 2>&1', escapeshellarg($this->pythonPath));
        exec($cmd, $output, $ret);

        return 0 === $ret && !empty($output);
    }

    /**
     * Check if Git LFS is available.
     */
    private function hasGitLfs(): bool
    {
        exec('git lfs version 2>&1', $output, $ret);

        return 0 === $ret;
    }

    /**
     * Run a shell command and parse progress output.
     */
    private function runWithProgress(string $cmd, ?callable $onProgress): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);

        $output = '';

        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            if (false === $line) {
                continue;
            }
            $output .= $line;

            if (null !== $onProgress) {
                $percent = $this->parseProgressFromOutput($line);
                $onProgress($percent, trim($line));
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return 0 === $exitCode;
    }

    /**
     * Parse progress percentage from command output.
     */
    private function parseProgressFromOutput(string $line): float
    {
        // ModelScope CLI progress: "Downloading [filename]: 45%"
        if (preg_match('/(\d+(?:\.\d+)?)%/', $line, $matches)) {
            return (float) $matches[1];
        }

        // Git LFS progress: "Objects: 45% (9/20)"
        if (preg_match('/(\d+)%/', $line, $matches)) {
            return (float) $matches[1];
        }

        return 0;
    }

    /**
     * Get diagnostics info for display in UI.
     */
    public function getDiagnostics(): array
    {
        return [
            'python_path' => $this->pythonPath,
            'modelscope_cli' => $this->modelscopeCliPath,
            'preferred_method' => $this->getPreferredMethod(),
            'has_modelscope_package' => null !== $this->pythonPath && $this->hasModelScopePackage(),
            'has_git_lfs' => $this->hasGitLfs(),
            'cache_dir' => $this->cacheDir,
        ];
    }
}
