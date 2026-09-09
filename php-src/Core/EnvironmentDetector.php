<?php

/**
 * H3PHP — Environment Detector.
 *
 * Scans the system for:
 * - Python version + packages (torch, comfyui)
 * - GPU capabilities (CUDA / Metal / ROCm)
 * - Disk space (models need ~60GB)
 * - Memory (RAM + VRAM)
 * - Network connectivity (HuggingFace / ModelScope)
 */

namespace H3Php\Core;

class EnvironmentDetector
{
    /** Scan results */
    private array $results = [];

    /**
     * Run all environment checks.
     */
    public function scan(): array
    {
        $this->results = [
            'python' => $this->detectPython(),
            'gpu' => $this->detectGPU(),
            'disk' => $this->detectDiskSpace(),
            'memory' => $this->detectMemory(),
            'network' => $this->detectNetwork(),
            'os' => $this->detectOS(),
        ];

        return $this->results;
    }

    /**
     * Detect Python installation and packages.
     */
    public function detectPython(): array
    {
        $result = [
            'installed' => false,
            'version' => '',
            'path' => '',
            'packages' => [],
        ];

        // Find Python executable
        $pythonPaths = ['python3', 'python', 'python3.11', 'python3.10', 'python3.12'];

        foreach ($pythonPaths as $py) {
            $path = trim(shell_exec("where {$py} 2>nul") ?: shell_exec("which {$py} 2>/dev/null") ?: '');
            if (!empty($path)) {
                $result['path'] = explode("\n", $path)[0];
                break;
            }
        }

        if (empty($result['path'])) {
            return $result;
        }

        // Get version
        $versionOutput = shell_exec("{$result['path']} --version 2>&1");
        if (preg_match('/Python\s+([\d.]+)/', $versionOutput ?? '', $matches)) {
            $result['version'] = $matches[1];
            $result['installed'] = true;
        }

        // Check packages
        $packages = ['torch', 'torchvision', 'numpy', 'pillow', 'transformers', 'accelerate', 'comfyui'];
        foreach ($packages as $pkg) {
            $checkCmd = "{$result['path']} -c \"import {$pkg}; print({$pkg}.__version__)\" 2>&1";
            $pkgVersion = trim(shell_exec($checkCmd) ?: '');
            $result['packages'][$pkg] = [
                'installed' => !empty($pkgVersion) && !str_contains($pkgVersion, 'Error'),
                'version' => $pkgVersion,
            ];
        }

        return $result;
    }

    /**
     * Detect GPU capabilities.
     */
    public function detectGPU(): array
    {
        $result = [
            'available' => false,
            'devices' => [],
            'preferred' => 'cpu',
        ];

        // Check CUDA (NVIDIA)
        $cuda = $this->detectCUDA();
        if ($cuda['available']) {
            $result['available'] = true;
            $result['devices'] = $cuda['devices'];
            $result['preferred'] = 'cuda';
        }

        // Check Metal (macOS)
        $metal = $this->detectMetal();
        if ($metal['available']) {
            $result['available'] = true;
            $result['devices'] = array_merge($result['devices'], $metal['devices']);
            if ('cuda' !== $result['preferred']) {
                $result['preferred'] = 'metal';
            }
        }

        // Check ROCm (AMD)
        $rocm = $this->detectROCm();
        if ($rocm['available']) {
            $result['available'] = true;
            $result['devices'] = array_merge($result['devices'], $rocm['devices']);
            if ('cuda' !== $result['preferred']) {
                $result['preferred'] = 'rocm';
            }
        }

        return $result;
    }

    /**
     * Detect CUDA GPUs.
     */
    private function detectCUDA(): array
    {
        $result = ['available' => false, 'devices' => []];

        // Check nvidia-smi
        $nvidiaSmi = shell_exec('nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>/dev/null');
        if (!empty($nvidiaSmi)) {
            $result['available'] = true;
            $lines = explode("\n", trim($nvidiaSmi));
            foreach ($lines as $line) {
                $parts = str_getcsv($line);
                if (count($parts) >= 2) {
                    $result['devices'][] = [
                        'type' => 'cuda',
                        'name' => trim($parts[0]),
                        'vram' => trim($parts[1]),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Detect Metal GPUs (macOS).
     */
    private function detectMetal(): array
    {
        $result = ['available' => false, 'devices' => []];

        if (PHP_OS_FAMILY !== 'Darwin') {
            return $result;
        }

        // All Apple Silicon Macs support Metal
        $sysctl = shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null');
        if (str_contains($sysctl ?? '', 'Apple')) {
            $result['available'] = true;
            // Get memory (unified memory on Apple Silicon)
            $mem = shell_exec('sysctl -n hw.memsize 2>/dev/null');
            $result['devices'][] = [
                'type' => 'metal',
                'name' => trim($sysctl ?: 'Apple Silicon'),
                'vram' => $mem ? ModelManager::formatSize((int) $mem) : 'unknown',
            ];
        }

        return $result;
    }

    /**
     * Detect ROCm GPUs (AMD).
     */
    private function detectROCm(): array
    {
        $result = ['available' => false, 'devices' => []];

        // Check rocm-smi
        $rocmSmi = shell_exec('rocm-smi --showproductname 2>/dev/null');
        if (!empty($rocmSmi)) {
            $result['available'] = true;
            $result['devices'][] = [
                'type' => 'rocm',
                'name' => 'AMD GPU (ROCm)',
                'vram' => 'unknown',
            ];
        }

        return $result;
    }

    /**
     * Detect available disk space.
     */
    public function detectDiskSpace(): array
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        $modelDir = $home . '/models';

        $free = disk_free_space($modelDir) ?: disk_free_space($home) ?: 0;
        $total = disk_total_space($modelDir) ?: disk_total_space($home) ?: 0;

        return [
            'free_bytes' => $free,
            'total_bytes' => $total,
            'free_formatted' => ModelManager::formatSize($free),
            'total_formatted' => ModelManager::formatSize($total),
            'sufficient' => $free > 60 * 1024 * 1024 * 1024, // 60GB minimum
            'recommended' => $free > 100 * 1024 * 1024 * 1024, // 100GB recommended
        ];
    }

    /**
     * Detect system memory.
     */
    public function detectMemory(): array
    {
        $result = [
            'ram_total' => 0,
            'ram_available' => 0,
            'ram_sufficient' => false,
        ];

        if (PHP_OS_FAMILY === 'Darwin') {
            $memTotal = shell_exec('sysctl -n hw.memsize 2>/dev/null');
            $result['ram_total'] = (int) trim($memTotal ?: '0');
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $memInfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)/', $memInfo, $m)) {
                $result['ram_total'] = (int) $m[1] * 1024;
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $m)) {
                $result['ram_available'] = (int) $m[1] * 1024;
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $wmi = shell_exec('wmic memorychip get capacity 2>nul');
            if (preg_match_all('/\d+/', $wmi ?? '', $matches)) {
                $result['ram_total'] = array_sum($matches[0]);
            }
        }

        $result['ram_sufficient'] = $result['ram_total'] > 8 * 1024 * 1024 * 1024; // 8GB minimum
        $result['ram_total_formatted'] = ModelManager::formatSize($result['ram_total']);
        $result['ram_available_formatted'] = ModelManager::formatSize($result['ram_available']);

        return $result;
    }

    /**
     * Detect network connectivity.
     */
    public function detectNetwork(): array
    {
        $result = [
            'internet' => false,
            'huggingface' => false,
            'modelscope' => false,
            'github' => false,
        ];

        // Test internet connectivity
        $result['internet'] = $this->testConnection('google.com', 80);

        // Test HuggingFace
        $result['huggingface'] = $this->testConnection('huggingface.co', 443);

        // Test ModelScope
        $result['modelscope'] = $this->testConnection('modelscope.cn', 443);

        // Test GitHub
        $result['github'] = $this->testConnection('github.com', 443);

        return $result;
    }

    /**
     * Test connection to a host.
     */
    private function testConnection(string $host, int $port, int $timeout = 5): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($connection) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * Detect OS information.
     */
    public function detectOS(): array
    {
        return [
            'family' => PHP_OS_FAMILY,
            'name' => PHP_OS,
            'version' => php_uname('r'),
            'arch' => php_uname('m'),
            'is_macos' => PHP_OS_FAMILY === 'Darwin',
            'is_windows' => PHP_OS_FAMILY === 'Windows',
            'is_linux' => PHP_OS_FAMILY === 'Linux',
        ];
    }

    /**
     * Get overall readiness score (0-100).
     */
    public function getReadinessScore(): int
    {
        if (empty($this->results)) {
            $this->scan();
        }

        $score = 0;

        // Python installed (20 points)
        if ($this->results['python']['installed']) {
            $score += 20;
        }

        // GPU available (30 points)
        if ($this->results['gpu']['available']) {
            $score += 30;
        }

        // Disk space sufficient (20 points)
        if ($this->results['disk']['sufficient']) {
            $score += 20;
        }

        // Memory sufficient (15 points)
        if ($this->results['memory']['ram_sufficient']) {
            $score += 15;
        }

        // Network connectivity (15 points)
        if ($this->results['network']['internet']) {
            $score += 10;
        }
        if ($this->results['network']['huggingface'] || $this->results['network']['modelscope']) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Get readiness status label.
     */
    public function getReadinessLabel(): string
    {
        $score = $this->getReadinessScore();

        if ($score >= 80) {
            return 'Ready';
        }
        if ($score >= 50) {
            return 'Partial';
        }
        if ($score >= 20) {
            return 'Needs Setup';
        }

        return 'Not Ready';
    }

    /**
     * Get auto-fix suggestions.
     *
     * @return array List of suggested fixes
     */
    public function getFixSuggestions(): array
    {
        if (empty($this->results)) {
            $this->scan();
        }

        $suggestions = [];

        // Python not installed
        if (!$this->results['python']['installed']) {
            $suggestions[] = [
                'type' => 'error',
                'message' => 'Python not found. Install Python 3.10+ from python.org',
                'command' => 'brew install python@3.11',
            ];
        }

        // Missing packages
        $requiredPackages = ['torch', 'numpy', 'pillow'];
        foreach ($requiredPackages as $pkg) {
            if (!$this->results['python']['packages'][$pkg]['installed'] ?? false) {
                $suggestions[] = [
                    'type' => 'warning',
                    'message' => "Missing Python package: {$pkg}",
                    'command' => "pip install {$pkg}",
                ];
            }
        }

        // No GPU
        if (!$this->results['gpu']['available']) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'No GPU detected. Generation will be very slow on CPU.',
                'command' => '',
            ];
        }

        // Insufficient disk space
        if (!$this->results['disk']['sufficient']) {
            $suggestions[] = [
                'type' => 'error',
                'message' => 'Insufficient disk space. Need at least 60GB for models.',
                'command' => '',
            ];
        }

        // Network issues
        if (!$this->results['network']['huggingface'] && !$this->results['network']['modelscope']) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Cannot reach model download sources. Check network or use a mirror.',
                'command' => '',
            ];
        }

        return $suggestions;
    }

    /**
     * Get all scan results.
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
