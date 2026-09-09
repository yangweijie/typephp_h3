<?php

/**
 * H3PHP — Model Recommendation Dialog.
 *
 * Qt dialog that shows model presets with hardware feasibility.
 * Users can see which presets are compatible and download them.
 */

namespace H3Php\Qt;

use H3Php\Core\DownloadManager;
use H3Php\Core\EnvironmentDetector;

class ModelRecommendationDialog
{
    /** Qt window handle */
    private int $window = 0;

    /** Environment detector */
    private EnvironmentDetector $env;

    /** Download manager */
    private DownloadManager $downloadManager;

    /** Whether the dialog is visible */
    private bool $visible = false;

    /** Status label handle */
    private int $statusLabel = 0;

    /** Progress bar handle */
    private int $progressBar = 0;

    /** Download button handles keyed by preset ID */
    private array $downloadButtons = [];

    public function __construct(EnvironmentDetector $env, DownloadManager $downloadManager)
    {
        $this->env = $env;
        $this->downloadManager = $downloadManager;
    }

    /**
     * Show the recommendation dialog.
     */
    public function show(): void
    {
        $this->window = qt_window_create();
        qt_window_set_title($this->window, 'Model Recommendations');
        qt_window_set_size($this->window, 600, 500);

        // Main vertical layout
        $mainWidget = qt_widget_create();
        $mainLayout = qt_layout_vbox_create();
        qt_layout_set_spacing($mainLayout, 8);
        qt_layout_set_margins($mainLayout, 12, 12, 12, 12);
        qt_widget_set_layout($mainWidget, $mainLayout);

        // === Hardware Info Header ===
        $gpu = $this->env->detectGpu();
        $mem = $this->env->detectMemory();
        $disk = $this->env->detectDiskSpace();

        $hwText = sprintf(
            'Hardware: %s | RAM: %s | Disk: %s free',
            $gpu['name'] ?? 'Unknown GPU',
            $mem['ram_total_formatted'] ?? 'Unknown',
            $disk['free_formatted'] ?? 'Unknown'
        );
        $hwLabel = qt_label_create($hwText);
        qt_label_set_word_wrap($hwLabel, true);
        qt_layout_add_widget($mainLayout, $hwLabel);

        // === Preset Cards ===
        $presets = $this->downloadManager->getPresetsWithFeasibility($this->env);

        foreach ($presets as $info) {
            $preset = $info['preset'];
            $feasible = $info['feasible'];
            $reason = $info['reason'];

            // Card container
            $cardLayout = qt_layout_vbox_create();
            qt_layout_set_spacing($cardLayout, 4);

            // Title row
            $titleText = $preset->label . ($feasible ? '  ✓ Compatible' : '  ✗ Not Compatible');
            $titleLabel = qt_label_create($titleText);
            qt_layout_add_widget($cardLayout, $titleLabel);

            // Description
            $descLabel = qt_label_create('  ' . $preset->description);
            qt_layout_add_widget($cardLayout, $descLabel);

            // Requirements
            $reqText = sprintf(
                '  Size: %.1f GB | VRAM: %.1f GB | RAM: %.1f GB | Components: %d',
                $preset->totalSizeGb,
                $preset->requiredVramGb,
                $preset->requiredRamGb,
                count($preset->components)
            );
            $reqLabel = qt_label_create($reqText);
            qt_layout_add_widget($cardLayout, $reqLabel);

            // Feasibility reason
            if (!$feasible) {
                $reasonLabel = qt_label_create('  ⚠ ' . $reason);
                qt_layout_add_widget($cardLayout, $reasonLabel);
            }

            // Download button
            $btnRow = qt_layout_hbox_create();
            $downloadBtn = qt_button_create($feasible ? 'Download' : 'Download (may not work)');
            qt_button_set_on_click($downloadBtn, 'rec_download_' . $preset->id);
            qt_layout_add_widget($btnRow, $downloadBtn);
            qt_layout_add_stretch($btnRow);
            qt_layout_add_layout($cardLayout, $btnRow);

            $this->downloadButtons[$preset->id] = $downloadBtn;

            qt_layout_add_layout($mainLayout, $cardLayout);
        }

        // === Progress Bar ===
        $this->progressBar = qt_progress_bar_create(0, 100);
        qt_progress_bar_set_text_visible($this->progressBar, true);
        qt_progress_bar_set_format($this->progressBar, '%p%');
        qt_progress_bar_set_value($this->progressBar, 0);
        qt_layout_add_widget($mainLayout, $this->progressBar);

        // === Status Label ===
        $this->statusLabel = qt_label_create('Select a preset to download.');
        qt_label_set_word_wrap($this->statusLabel, true);
        qt_layout_add_widget($mainLayout, $this->statusLabel);

        // === Close Button ===
        $closeRow = qt_layout_hbox_create();
        qt_layout_add_stretch($closeRow);
        $closeBtn = qt_button_create('Close');
        qt_button_set_on_click($closeBtn, 'rec_close');
        qt_layout_add_widget($closeRow, $closeBtn);
        qt_layout_add_layout($mainLayout, $closeRow);

        // Set central widget and show
        qt_window_set_central_widget_handle($this->window, $mainWidget);
        qt_window_show($this->window);

        $this->visible = true;
    }

    /**
     * Handle a button click event from this dialog.
     */
    public function handleEvent(array $event): bool
    {
        $callbackId = $event['callback_id'] ?? '';

        if ('rec_close' === $callbackId) {
            $this->close();

            return true;
        }

        if (str_starts_with($callbackId, 'rec_download_')) {
            $presetId = substr($callbackId, 14);
            $this->downloadPreset($presetId);

            return true;
        }

        return false;
    }

    /**
     * Download a preset by ID (non-blocking: queues + starts async).
     */
    private function downloadPreset(string $presetId): void
    {
        $presets = \H3Php\Core\DownloadPreset::allPresets();
        if (!isset($presets[$presetId])) {
            return;
        }

        $preset = $presets[$presetId];

        // Disable all download buttons during download
        foreach ($this->downloadButtons as $btn) {
            qt_widget_set_enabled($btn, false);
        }

        qt_label_set_text($this->statusLabel, "Queueing {$preset->label}...");
        qt_progress_bar_set_value($this->progressBar, 0);

        // Queue preset for async download (non-blocking)
        $this->downloadManager->queuePreset(
            $preset,
            $this->downloadManager->getDownloadDir(),
            'modelscope',
            function (string $componentId, float $percent, string $msg): void {
                qt_progress_bar_set_value($this->progressBar, (int) $percent);
                qt_label_set_text(
                    $this->statusLabel,
                    sprintf('%s: %.0f%% — %s', $componentId, $percent, $msg)
                );
            }
        );
    }

    /**
     * Update progress (called from GuiApp tick).
     */
    public function updateProgress(): void
    {
        if (!$this->visible || $this->progressBar <= 0) {
            return;
        }

        $progress = $this->downloadManager->getOverallProgress();
        qt_progress_bar_set_value($this->progressBar, (int) $progress);

        // Re-enable download buttons when all downloads are done
        if ($progress >= 100 || 0 === $progress) {
            foreach ($this->downloadButtons as $btn) {
                qt_widget_set_enabled($btn, true);
            }
        }
    }

    /**
     * Close the dialog.
     */
    public function close(): void
    {
        if ($this->visible && $this->window > 0) {
            qt_destroy($this->window);
            $this->visible = false;
            $this->window = 0;
        }
    }

    /**
     * Check if the dialog is visible.
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * Get the window handle.
     */
    public function getWindowHandle(): int
    {
        return $this->window;
    }
}
