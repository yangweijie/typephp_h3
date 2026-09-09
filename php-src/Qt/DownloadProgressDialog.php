<?php

/**
 * H3PHP — Download Progress Dialog.
 *
 * Qt dialog showing per-task progress with speed, ETA, mirror selection,
 * pause/resume and cancel. Driven by the GUI event loop (non-blocking).
 */

namespace H3Php\Qt;

use H3Php\Core\DownloadManager;

class DownloadProgressDialog
{
    /** Qt dialog window handle */
    private int $window = 0;

    /** Progress bar handle */
    private int $progressBar = 0;

    /** Status label handle */
    private int $statusLabel = 0;

    /** Cancel button handle */
    private int $cancelButton = 0;

    /** Start button handle */
    private int $startButton = 0;

    /** Pause button handle */
    private int $pauseButton = 0;

    /** Resume button handle */
    private int $resumeButton = 0;

    /** Mirror combo box handle */
    private int $mirrorCombo = 0;

    /** Layout holding one label per download task */
    private int $taskLayout = 0;

    /** Task label handles keyed by task id */
    private array $taskLabels = [];

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
        qt_window_set_title($this->window, 'Download Manager');
        qt_window_set_size($this->window, 620, 420);

        $central = qt_widget_create();
        $main = qt_layout_vbox_create();
        qt_layout_set_spacing($main, 8);
        qt_layout_set_margins($main, 12, 12, 12, 12);
        qt_widget_set_layout($central, $main);

        $this->statusLabel = qt_label_create('Preparing downloads...');
        qt_label_set_word_wrap($this->statusLabel, true);
        qt_layout_add_widget($main, $this->statusLabel);

        $this->progressBar = qt_progress_bar_create(0, 100);
        qt_progress_bar_set_text_visible($this->progressBar, true);
        qt_layout_add_widget($main, $this->progressBar);

        // === Mirror selection ===
        $mirrorRow = qt_layout_hbox_create();
        qt_layout_set_spacing($mirrorRow, 6);
        qt_layout_add_widget($mirrorRow, qt_label_create('Mirror:'));

        $this->mirrorCombo = qt_combo_box_create(DownloadManager::MIRROR_CHOICES);
        qt_combo_box_set_on_current_index_changed($this->mirrorCombo, 'download_mirror');
        qt_layout_add_widget($mirrorRow, $this->mirrorCombo);
        qt_layout_add_layout($main, $mirrorRow);

        // === Per-task rows ===
        $this->taskLayout = qt_layout_vbox_create();
        qt_layout_set_spacing($this->taskLayout, 2);
        qt_layout_add_layout($main, $this->taskLayout);
        qt_layout_add_stretch($main);

        // === Buttons ===
        $btnRow = qt_layout_hbox_create();
        qt_layout_set_spacing($btnRow, 6);

        $this->startButton = qt_button_create('Start');
        qt_button_set_on_click($this->startButton, 'download_start');
        qt_layout_add_widget($btnRow, $this->startButton);

        $this->pauseButton = qt_button_create('Pause');
        qt_button_set_on_click($this->pauseButton, 'download_pause');
        qt_layout_add_widget($btnRow, $this->pauseButton);

        $this->resumeButton = qt_button_create('Resume');
        qt_button_set_on_click($this->resumeButton, 'download_resume');
        qt_layout_add_widget($btnRow, $this->resumeButton);

        $this->cancelButton = qt_button_create('Cancel');
        qt_button_set_on_click($this->cancelButton, 'download_cancel');
        qt_layout_add_widget($btnRow, $this->cancelButton);

        qt_layout_add_layout($main, $btnRow);

        qt_window_set_central_widget_handle($this->window, $central);
        qt_window_show($this->window);

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

        $this->applyMirror();
        qt_label_set_text($this->statusLabel, 'Queueing H3 models...');
        $this->manager->startH3ModelDownload();

        $stats = $this->manager->getStats();
        $this->setStatus(sprintf('Downloading %d files...', $stats['total']));
    }

    /**
     * Pause all downloads.
     */
    public function pause(): void
    {
        $this->manager->pause();
        $this->setStatus('Paused');
    }

    /**
     * Resume paused downloads.
     */
    public function resume(): void
    {
        $this->manager->resume();
        $this->setStatus('Resuming...');
    }

    /**
     * Apply the mirror currently selected in the combo box.
     */
    public function applyMirror(): void
    {
        if ($this->mirrorCombo <= 0) {
            return;
        }

        $choice = qt_combo_box_get_current_text($this->mirrorCombo);
        $this->manager->applyMirror($choice);
    }

    /**
     * Update the progress display (called from the event-loop tick).
     */
    public function update(): void
    {
        if (!$this->visible) {
            return;
        }

        if (!$this->started) {
            $this->setStatus('Click Start to download H3 models.');

            return;
        }

        $progress = $this->manager->getOverallProgress();
        $stats = $this->manager->getStats();
        $speed = DownloadManager::formatSpeed($this->manager->getSpeed());
        $eta = DownloadManager::formatEta($this->manager->getEtaSeconds());

        qt_progress_bar_set_value($this->progressBar, (int) $progress);

        $state = $this->manager->isPaused() ? ' (paused)' : '';
        $this->setStatus(sprintf(
            '%.0f%% — %d/%d files — %s — ETA %s%s',
            $progress,
            $stats['completed'],
            $stats['total'],
            $speed,
            $eta,
            $state
        ));

        $this->updateTaskRows();
    }

    /**
     * Close the dialog.
     */
    public function close(): void
    {
        if ($this->visible && $this->window > 0) {
            foreach ($this->taskLabels as $handle) {
                if ($handle > 0) {
                    qt_destroy($handle);
                }
            }
            $this->taskLabels = [];

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
     * Create (once) and refresh one label per download task.
     */
    private function updateTaskRows(): void
    {
        $tasks = $this->manager->getTasks();

        if (count($tasks) !== count($this->taskLabels)) {
            foreach ($this->taskLabels as $handle) {
                if ($handle > 0) {
                    qt_destroy($handle);
                }
            }
            $this->taskLabels = [];

            foreach ($tasks as $task) {
                $label = qt_label_create('');
                qt_layout_add_widget($this->taskLayout, $label);
                $this->taskLabels[$task->id] = $label;
            }
        }

        foreach ($tasks as $task) {
            $label = $this->taskLabels[$task->id] ?? 0;
            if ($label <= 0) {
                continue;
            }

            qt_label_set_text(
                $label,
                sprintf(
                    '# %s — %.0f%% — %s',
                    $task->name,
                    $task->getProgress(),
                    $task->status
                )
            );
        }
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
