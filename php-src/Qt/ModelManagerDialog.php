<?php

/**
 * H3PHP — Model Manager Dialog.
 *
 * Qt dialog for managing AI models.
 * Features: model discovery, per-model cards (version / size / status),
 * single-component download, validation, removal, disk usage.
 */

namespace H3Php\Qt;

use H3Php\Core\DownloadManager;
use H3Php\Core\ModelManager;

class ModelManagerDialog
{
    /** Qt window handle */
    private int $window = 0;

    /** Model manager */
    private ModelManager $modelManager;

    /** Download manager */
    private DownloadManager $downloadManager;

    /** Whether the dialog is visible */
    private bool $visible = false;

    /** Widget handles created for the model list (destroyed on refresh) */
    private array $modelWidgets = [];

    /** Last scan snapshot: index => model array */
    private array $models = [];

    /** Progress bar for downloads */
    private int $progressBar = 0;

    /** Status label */
    private int $statusLabel = 0;

    /** Disk usage progress bar */
    private int $diskBar = 0;

    /** Disk usage label */
    private int $diskLabel = 0;

    /** Layout holding the model rows */
    private int $listLayout = 0;

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
        qt_window_set_size($this->window, 760, 560);

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

        // Central widget + main layout
        $central = qt_widget_create();
        $main = qt_layout_vbox_create();
        qt_layout_set_spacing($main, 8);
        qt_layout_set_margins($main, 12, 12, 12, 12);
        qt_widget_set_layout($central, $main);

        $this->statusLabel = qt_label_create('Click Scan to discover models.');
        qt_label_set_word_wrap($this->statusLabel, true);
        qt_layout_add_widget($main, $this->statusLabel);

        $this->progressBar = qt_progress_bar_create(0, 100);
        qt_progress_bar_set_text_visible($this->progressBar, true);
        qt_layout_add_widget($main, $this->progressBar);

        $this->diskBar = qt_progress_bar_create(0, 100);
        qt_progress_bar_set_text_visible($this->diskBar, true);
        qt_layout_add_widget($main, $this->diskBar);

        $this->diskLabel = qt_label_create('Disk usage: —');
        qt_layout_add_widget($main, $this->diskLabel);

        $this->listLayout = qt_layout_vbox_create();
        qt_layout_set_spacing($this->listLayout, 4);
        qt_layout_add_layout($main, $this->listLayout);

        qt_layout_add_stretch($main);
        qt_window_set_central_widget_handle($this->window, $central);
        qt_window_show($this->window);

        $this->visible = true;

        $this->scanModels();
    }

    /**
     * Scan for models and rebuild the list.
     */
    public function scanModels(): void
    {
        $this->setStatus('Scanning...');

        $this->models = array_values($this->modelManager->scan());

        $this->clearRows();
        $this->renderRows();
        $this->renderDiskUsage();

        $total = ModelManager::formatSize($this->modelManager->getTotalSize());
        $this->setStatus(sprintf('Found %d models (%s total)', count($this->models), $total));
    }

    /**
     * Queue every missing core component for download.
     */
    public function downloadAll(): void
    {
        $missing = $this->modelManager->getMissingTypes();

        if ([] === $missing) {
            $this->setStatus('All core components present — nothing to download.');

            return;
        }

        foreach ($missing as $type) {
            $this->downloadManager->queueComponent($type);
        }

        $this->setStatus('Downloading: ' . implode(', ', $missing));
        $this->updateProgress();
    }

    /**
     * Queue a single missing component for download.
     */
    public function downloadMissing(string $type): void
    {
        $tasks = $this->downloadManager->queueComponent($type);

        if ([] === $tasks) {
            $this->setStatus("Unknown component: {$type}");

            return;
        }

        $this->setStatus("Queued {$type} (" . count($tasks) . ' files)');
        $this->updateProgress();
    }

    /**
     * Validate all models.
     */
    public function validateAll(): void
    {
        $results = $this->modelManager->validate();

        $valid = 0;
        $invalid = 0;
        $warnings = 0;
        foreach ($results as $result) {
            if ($result['valid']) {
                $valid++;
            } else {
                $invalid++;
            }
            $warnings += count($result['warnings']);
        }

        $this->setStatus(sprintf(
            'Validation: %d valid, %d invalid, %d warnings',
            $valid,
            $invalid,
            $warnings
        ));
    }

    /**
     * Validate a single model row.
     */
    public function validateModel(int $index): void
    {
        $model = $this->models[$index] ?? null;
        if (null === $model) {
            return;
        }

        $issues = [];
        if (!file_exists($model['path'])) {
            $issues[] = 'path missing';
        }
        if ($model['size'] <= 0) {
            $issues[] = 'empty (0 bytes)';
        }
        if ('transformer' === $model['type'] && !$model['config_exists']) {
            $issues[] = 'no config.json';
        }

        if ([] === $issues) {
            $this->setStatus("✓ {$model['name']} — valid");
        } else {
            $this->setStatus("✗ {$model['name']} — " . implode(', ', $issues));
        }
    }

    /**
     * Remove a single model from disk, then refresh the list.
     */
    public function removeModel(int $index): void
    {
        $model = $this->models[$index] ?? null;
        if (null === $model) {
            return;
        }

        if ($this->modelManager->removeModel($model['path'])) {
            $this->setStatus("Removed {$model['name']}");
        } else {
            $this->setStatus("Could not remove {$model['name']} (outside search paths)");
        }

        $this->scanModels();
    }

    /**
     * Update download progress (driven by the GUI event loop).
     */
    public function updateProgress(): void
    {
        $progress = $this->downloadManager->getOverallProgress();
        qt_progress_bar_set_value($this->progressBar, (int) $progress);

        $stats = $this->downloadManager->getStats();
        if ($stats['total'] > 0) {
            $this->setStatus(sprintf(
                '%.0f%% — %d/%d files (%s)',
                $progress,
                $stats['completed'],
                $stats['total'],
                DownloadManager::formatSpeed($this->downloadManager->getSpeed())
            ));
        }
    }

    /**
     * Close the dialog.
     */
    public function close(): void
    {
        if ($this->visible && $this->window > 0) {
            $this->clearRows();
            qt_destroy($this->window);
            $this->window = 0;
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

    /**
     * Render one row per discovered model plus rows for missing components.
     */
    private function renderRows(): void
    {
        foreach ($this->models as $index => $model) {
            $row = qt_layout_hbox_create();
            qt_layout_set_spacing($row, 6);

            $text = sprintf(
                '%s  •  %s  •  %s  •  v%s  •  %s',
                $model['name'],
                ModelManager::formatSize($model['size']),
                $model['backend'],
                ModelManager::getVersion($model),
                $model['config_exists'] ? '✓' : '—'
            );

            $label = qt_label_create($text);
            qt_layout_add_widget($row, $label);
            qt_layout_add_stretch($row);

            $validateBtn = qt_button_create('Validate');
            qt_button_set_on_click($validateBtn, 'model_validate_' . $index);
            qt_layout_add_widget($row, $validateBtn);

            $removeBtn = qt_button_create('Remove');
            qt_button_set_on_click($removeBtn, 'model_remove_' . $index);
            qt_layout_add_widget($row, $removeBtn);

            $this->modelWidgets[] = $label;
            $this->modelWidgets[] = $validateBtn;
            $this->modelWidgets[] = $removeBtn;

            qt_layout_add_layout($this->listLayout, $row);
        }

        foreach ($this->modelManager->getMissingTypes() as $type) {
            $row = qt_layout_hbox_create();
            qt_layout_set_spacing($row, 6);

            $label = qt_label_create("⚠ Missing component: {$type}");
            qt_layout_add_widget($row, $label);
            qt_layout_add_stretch($row);

            $downloadBtn = qt_button_create('Download');
            qt_button_set_on_click($downloadBtn, 'model_download_missing_' . $type);
            qt_layout_add_widget($row, $downloadBtn);

            $this->modelWidgets[] = $label;
            $this->modelWidgets[] = $downloadBtn;

            qt_layout_add_layout($this->listLayout, $row);
        }
    }

    /**
     * Refresh the disk usage bar and label.
     */
    private function renderDiskUsage(): void
    {
        $usage = $this->modelManager->getDiskUsage();

        qt_progress_bar_set_value($this->diskBar, (int) $usage['percent']);
        qt_label_set_text(
            $this->diskLabel,
            sprintf(
                'Disk usage: %s of %s recommended (%s free)',
                ModelManager::formatSize($usage['used']),
                ModelManager::formatSize($usage['recommended']),
                ModelManager::formatSize($usage['free'])
            )
        );
    }

    /**
     * Destroy the widgets of the previous render.
     */
    private function clearRows(): void
    {
        foreach ($this->modelWidgets as $handle) {
            if ($handle > 0) {
                qt_destroy($handle);
            }
        }

        $this->modelWidgets = [];
    }

    /**
     * Update the status label.
     */
    private function setStatus(string $status): void
    {
        if ($this->statusLabel > 0) {
            qt_label_set_text($this->statusLabel, $status);
        }
    }
}
