<?php

/**
 * H3PHP — Download Progress Dialog.
 *
 * Qt dialog showing download progress with speed, ETA, and cancel.
 * Bridges DownloadManager events to Qt UI.
 */

namespace H3Php\Qt;

use H3Php\Core\DownloadManager;

class DownloadProgressDialog
{
    /** Qt dialog window handle */
    private int $window;

    /** Progress bar handle */
    private int $progressBar;

    /** Status label handle */
    private int $statusLabel;

    /** Cancel button handle */
    private int $cancelButton;

    /** Start button handle */
    private int $startButton;

    /** Download manager reference */
    private DownloadManager $manager;

    /** Whether the dialog is visible */
    private bool $visible = false;

    /** Whether the user has triggered a download */
    private bool $started = false;

    public function __construct(DownloadManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Show the download progress dialog.
     */
    public function show(): void
    {
        $this->window = qt_window_create();
        qt_window_set_title($this->window, 'Downloading Models');
        qt_window_set_size($this->window, 480, 200);
        qt_window_show($this->window);

        // Create progress bar
        $this->progressBar = qt_progress_bar_create(0, 100);

        // Create status label
        $this->statusLabel = qt_label_create('Preparing downloads...');

        // Create start button
        $this->startButton = qt_button_create('Start');
        qt_button_set_on_click($this->startButton, 'download_start');

        // Create cancel button
        $this->cancelButton = qt_button_create('Cancel');
        qt_button_set_on_click($this->cancelButton, 'download_cancel');

        $this->visible = true;
    }

    /**
     * Begin queuing and downloading H3 model weights.
     * Idempotent: subsequent calls are ignored.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }
        $this->started = true;

        qt_label_set_text($this->statusLabel, 'Queueing H3 models...');
        $this->manager->startH3ModelDownload();

        $stats = $this->manager->getStats();
        qt_label_set_text(
            $this->statusLabel,
            sprintf('Downloading %d files...', $stats['total'])
        );
    }

    /**
     * Update the progress display.
     */
    public function update(): void
    {
        if (!$this->visible) {
            return;
        }

        if (!$this->started) {
            qt_label_set_text($this->statusLabel, 'Click Start to download H3 models.');
            return;
        }

        $progress = $this->manager->getOverallProgress();
        $stats = $this->manager->getStats();

        qt_progress_bar_set_value($this->progressBar, (int) $progress);

        $status = sprintf(
            '%.0f%% complete — %d/%d files',
            $progress,
            $stats['completed'],
            $stats['total']
        );
        qt_label_set_text($this->statusLabel, $status);
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
