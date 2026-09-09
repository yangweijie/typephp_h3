<?php

/**
 * H3PHP — Model Recommender.
 *
 * Recommends model configurations based on detected hardware capabilities.
 * Considers VRAM, system RAM, disk space, and network speed to suggest
 * the optimal model preset for the user's machine.
 */

namespace H3Php\Core;

class ModelRecommender
{
    private EnvironmentDetector $env;

    /** @var array<string, DownloadPreset> */
    private array $presets;

    public function __construct(EnvironmentDetector $env)
    {
        $this->env = $env;
        $this->presets = DownloadPreset::allPresets();
    }

    /**
     * Get the recommended model preset based on hardware.
     *
     * @return array{preset: DownloadPreset, reason: string, warnings: string[]}
     */
    public function recommend(): array
    {
        $gpu = $this->env->detectGpu();
        $disk = $this->env->detectDiskSpace();
        $mem = $this->env->detectMemory();

        $vramGb = ($gpu['vram_mb'] ?? 0) / 1024;
        $ramGb = ($mem['total_mb'] ?? 0) / 1024;
        $diskFreeGb = ($disk['free_bytes'] ?? 0) / (1024 ** 3);

        $warnings = [];
        $reason = '';

        // Decision logic
        if ($vramGb >= 16 && $ramGb >= 32 && $diskFreeGb >= 80) {
            $preset = $this->presets['full'];
            $reason = 'High-end GPU (≥16GB VRAM), sufficient RAM (≥32GB) and disk (≥80GB) — full model set recommended for best quality.';
        } elseif ($vramGb >= 8 && $ramGb >= 16 && $diskFreeGb >= 50) {
            $preset = $this->presets['standard'];
            $reason = 'Mid-range GPU (≥8GB VRAM), adequate RAM (≥16GB) and disk (≥50GB) — standard model set for balanced quality/performance.';
        } elseif ($vramGb >= 4 && $ramGb >= 8 && $diskFreeGb >= 30) {
            $preset = $this->presets['minimal'];
            $reason = 'Entry-level GPU (≥4GB VRAM), basic RAM (≥8GB) and disk (≥30GB) — minimal model set for basic video generation.';
        } else {
            $preset = $this->presets['minimal'];
            $reason = 'Limited hardware detected — minimal model set recommended. Consider using ComfyUI with CPU mode or remote API.';

            if ($vramGb < 4) {
                $warnings[] = 'Low VRAM (' . number_format($vramGb, 1) . 'GB) — GPU inference may be slow. Consider using a remote API backend.';
            }
            if ($ramGb < 8) {
                $warnings[] = 'Low RAM (' . number_format($ramGb, 1) . 'GB) — model loading may fail. Close other applications.';
            }
            if ($diskFreeGb < 30) {
                $warnings[] = 'Low disk space (' . number_format($diskFreeGb, 1) . 'GB free) — need at least 30GB for minimal setup.';
            }
        }

        // Check if audio VAE is feasible
        if ($diskFreeGb < ($preset->getTotalSizeGb() + 0.5)) {
            $warnings[] = 'Not enough disk space for audio VAE — audio generation will be unavailable.';
        }

        return [
            'preset' => $preset,
            'reason' => $reason,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get all available presets with feasibility info.
     *
     * @return array<int, array{preset: DownloadPreset, feasible: bool, reason: string}>
     */
    public function getPresetsWithFeasibility(): array
    {
        $gpu = $this->env->detectGpu();
        $disk = $this->env->detectDiskSpace();
        $mem = $this->env->detectMemory();

        $vramGb = ($gpu['vram_mb'] ?? 0) / 1024;
        $ramGb = ($mem['total_mb'] ?? 0) / 1024;
        $diskFreeGb = ($disk['free_bytes'] ?? 0) / (1024 ** 3);

        $results = [];

        foreach ($this->presets as $key => $preset) {
            $requiredDisk = $preset->getTotalSizeGb();
            $requiredRam = $preset->getRequiredRamGb();
            $requiredVram = $preset->getRequiredVramGb();

            $feasible = true;
            $reasons = [];

            if ($diskFreeGb < $requiredDisk) {
                $feasible = false;
                $reasons[] = sprintf('Need %.1fGB disk, only %.1fGB free', $requiredDisk, $diskFreeGb);
            }
            if ($ramGb < $requiredRam) {
                $feasible = false;
                $reasons[] = sprintf('Need %.1fGB RAM, only %.1fGB available', $requiredRam, $ramGb);
            }
            if ($vramGb < $requiredVram) {
                $feasible = false;
                $reasons[] = sprintf('Need %.1fGB VRAM, only %.1fGB available', $requiredVram, $vramGb);
            }

            $results[] = [
                'preset' => $preset,
                'feasible' => $feasible,
                'reason' => $feasible ? 'Compatible with your hardware' : implode('; ', $reasons),
            ];
        }

        return $results;
    }

    /**
     * Get download source recommendation based on network.
     *
     * @return array{source: string, reason: string}
     */
    public function recommendSource(): array
    {
        $network = $this->env->detectNetwork();

        if (($network['modelscope_reachable'] ?? false) && !($network['huggingface_reachable'] ?? false)) {
            return [
                'source' => 'modelscope',
                'reason' => 'ModelScope is reachable but HuggingFace is not — using ModelScope mirror for faster downloads in China.',
            ];
        }

        if (($network['huggingface_reachable'] ?? false) && !($network['modelscope_reachable'] ?? false)) {
            return [
                'source' => 'huggingface',
                'reason' => 'HuggingFace is reachable but ModelScope is not — using HuggingFace for downloads.',
            ];
        }

        if (($network['modelscope_reachable'] ?? false) && ($network['huggingface_reachable'] ?? false)) {
            // Both reachable — pick faster one
            $msLatency = $network['modelscope_latency_ms'] ?? 999;
            $hfLatency = $network['huggingface_latency_ms'] ?? 999;

            if ($msLatency < $hfLatency) {
                return [
                    'source' => 'modelscope',
                    'reason' => sprintf('Both mirrors reachable — ModelScope is faster (%dms vs %dms).', $msLatency, $hfLatency),
                ];
            }

            return [
                'source' => 'huggingface',
                'reason' => sprintf('Both mirrors reachable — HuggingFace is faster (%dms vs %dms).', $hfLatency, $msLatency),
            ];
        }

        return [
            'source' => 'modelscope',
            'reason' => 'Network detection inconclusive — defaulting to ModelScope (better availability in China).',
        ];
    }
}
