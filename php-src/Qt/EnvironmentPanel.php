<?php

/**
 * H3PHP — Environment Panel.
 *
 * Status dashboard showing system capabilities and readiness.
 * Displays: Python, GPU, Disk, Memory, Network status.
 */

namespace H3Php\Qt;

use H3Php\Core\EnvironmentDetector;

class EnvironmentPanel
{
    /** Qt window/widget handle */
    private int $widget;

    /** Environment detector */
    private EnvironmentDetector $detector;

    /** Whether the panel is visible */
    private bool $visible = false;

    /** Status labels */
    private array $labels = [];

    public function __construct(EnvironmentDetector $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Show the environment panel.
     */
    public function show(): void
    {
        $this->widget = qt_window_create();
        qt_window_set_title($this->widget, 'Environment Status');
        qt_window_set_size($this->widget, 500, 400);
        qt_window_show($this->widget);

        $this->refresh();
        $this->visible = true;
    }

    /**
     * Refresh the panel with latest scan results.
     */
    public function refresh(): void
    {
        $results = $this->detector->scan();
        $score = $this->detector->getReadinessScore();
        $label = $this->detector->getReadinessLabel();

        // Clear old labels
        foreach ($this->labels as $handle) {
            if ($handle > 0) {
                qt_destroy($handle);
            }
        }
        $this->labels = [];

        // Overall status
        $overall = qt_label_create("System Readiness: {$label} ({$score}/100)");
        $this->labels[] = $overall;

        // Python
        $py = $results['python'];
        $pyStatus = $py['installed']
            ? "Python {$py['version']} ✓"
            : 'Python not found ✗';
        $this->labels[] = qt_label_create($pyStatus);

        // GPU
        $gpu = $results['gpu'];
        $gpuStatus = $gpu['available']
            ? 'GPU: ' . strtoupper($gpu['preferred']) . ' ✓'
            : 'GPU: Not detected ✗';
        $this->labels[] = qt_label_create($gpuStatus);

        // Disk
        $disk = $results['disk'];
        $diskStatus = $disk['sufficient']
            ? "Disk: {$disk['free_formatted']} free ✓"
            : "Disk: {$disk['free_formatted']} free (need 60GB) ✗";
        $this->labels[] = qt_label_create($diskStatus);

        // Memory
        $mem = $results['memory'];
        $memStatus = $mem['ram_sufficient']
            ? "RAM: {$mem['ram_total_formatted']} ✓"
            : "RAM: {$mem['ram_total_formatted']} (need 8GB) ✗";
        $this->labels[] = qt_label_create($memStatus);

        // Network
        $net = $results['network'];
        $netStatus = '';
        $netStatus .= $net['huggingface'] ? 'HuggingFace ✓ ' : 'HuggingFace ✗ ';
        $netStatus .= $net['modelscope'] ? 'ModelScope ✓ ' : 'ModelScope ✗ ';
        $netStatus .= $net['github'] ? 'GitHub ✓' : 'GitHub ✗';
        $this->labels[] = qt_label_create($netStatus);

        // Suggestions
        $suggestions = $this->detector->getFixSuggestions();
        if (!empty($suggestions)) {
            $this->labels[] = qt_label_create('Suggestions:');
            foreach ($suggestions as $s) {
                $this->labels[] = qt_label_create("  • {$s['message']}");
            }
        }
    }

    /**
     * Close the panel.
     */
    public function close(): void
    {
        if ($this->visible && $this->widget > 0) {
            qt_destroy($this->widget);
            $this->visible = false;
        }
    }

    /**
     * Check if the panel is visible.
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * Get the widget handle.
     */
    public function getWidgetHandle(): int
    {
        return $this->widget;
    }
}
