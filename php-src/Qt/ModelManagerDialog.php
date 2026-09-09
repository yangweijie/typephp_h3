<?php

/**
 * H3PHP — Model Manager Dialog.
 *
 * Qt dialog for managing AI models.
 * Features: model discovery, cards, download, validation, removal.
 */

namespace H3Php\Qt;

use H3Php\Core\ModelManager;
use H3Php\Core\DownloadManager;

class ModelManagerDialog
{
    /** Qt window handle */
    private int $window;

    /** Model manager */
    private ModelManager $modelManager;

    /** Download manager */
    private DownloadManager $downloadManager;

    /** Whether the dialog is visible */
    private bool $visible = false;

    /** Model list widget handles */
    private array $modelWidgets = [];

    /** Progress bar for downloads */
    private int $progressBar;

    /** Status label */
    private int $statusLabel;

    public function __construct(ModelManager $modelManager, DownloadManager $downloadManager)
    {
        $this->modelManager = $modelManager;
        $this->downloadManager = $downloadManager;
    }

    /**
     * Show the model manager dialog.
     */
    public function show(): void
    {
        $this->window = qt_window_create();
        qt_window_set_title($this->window, 'Model Manager');
        qt_window_set_size($this->window, 700, 500);
        qt_window_show($this->window);

        // Create menu bar
        $menus = [
            [
                'title' => 'File',
                'items' => [
                    ['label' => 'Scan', 'action' => 'model_scan'],
                    ['label' => 'Close', 'action' => 'dialog_close'],
                ],
            ],
            [
                'title' => 'Actions',
                'items' => [
                    ['label' => 'Download All', 'action' => 'model_download_all'],
                    ['label' => 'Validate All', 'action' => 'model_validate_all'],
                ],
            ],
        ];
        qt_window_create_menu_bar($this->window, $menus);

        // Create progress bar
        $this->progressBar = qt_progress_bar_create(0, 100);

        // Create status label
        $this->statusLabel = qt_label_create('Click Scan to discover models.');

        // Scan for models
        $this->scanModels();

        $this->visible = true;
    }

    /**
     * Scan for models and update the display.
     */
    public function scanModels(): void
    {
        qt_label_set_text($this->statusLabel, 'Scanning...');

        $models = $this->modelManager->scan();

        // Clear old widgets
        foreach ($this->modelWidgets as $handle) {
            if ($handle > 0) {
                qt_destroy($handle);
            }
        }
        $this->modelWidgets = [];

        // Display models
        $totalSize = 0;
        foreach ($models as $model) {
            $totalSize += $model['size'];
            $sizeStr = ModelManager::formatSize($model['size']);
            $status = $model['config_exists'] ? '✓' : '';
            $label = qt_label_create(
                "{$model['name']} ({$sizeStr}) [{$model['backend']}] {$status}"
            );
            $this->modelWidgets[] = $label;
        }

        $totalStr = ModelManager::formatSize($totalSize);
        qt_label_set_text(
            $this->statusLabel,
            sprintf('Found %d models (%s total)', count($models), $totalStr)
        );
    }

    /**
     * Download all missing models.
     */
    public function downloadAll(): void
    {
        qt_label_set_text($this->statusLabel, 'Downloading models...');
        qt_progress_bar_set_value($this->progressBar, 0);

        // Start downloads (non-blocking: the GUI event loop drives progress)
        $this->downloadManager->startAsync();

        // Update progress
        $this->updateProgress();
    }

    /**
     * Validate all models.
     */
    public function validateAll(): void
    {
        qt_label_set_text($this->statusLabel, 'Validating models...');

        $results = $this->modelManager->validate();

        $valid = 0;
        $invalid = 0;
        foreach ($results as $result) {
            if ($result['valid']) {
                $valid++;
            } else {
                $invalid++;
            }
        }

        qt_label_set_text(
            $this->statusLabel,
            sprintf('Validation: %d valid, %d invalid', $valid, $invalid)
        );
    }

    /**
     * Update download progress.
     */
    public function updateProgress(): void
    {
        $progress = $this->downloadManager->getOverallProgress();
        qt_progress_bar_set_value($this->progressBar, (int) $progress);

        $stats = $this->downloadManager->getStats();
        qt_label_set_text(
            $this->statusLabel,
            sprintf(
                '%.0f%% — %d/%d files',
                $progress,
                $stats['completed'],
                $stats['total']
            )
        );
    }

    /**
     * Close the dialog.
     */
    public function close(): void
    {
        if ($this->visible && $this->window > 0) {
            qt_destroy($this->window);
            $this->visible = false;
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
