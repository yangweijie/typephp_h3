<?php

/**
 * H3PHP — Setup Wizard.
 *
 * First-run guided setup wizard.
 * Pages: Welcome → Python → GPU → Models → Ready
 *
 * Each page rebuilds the whole window (simple & crash-free: no layout
 * mutation needed). Navigation is driven by button events routed through
 * Qt\Application's event loop (see GuiApp for the router).
 */

namespace H3Php\Qt;

use H3Php\Core\EnvironmentDetector;
use H3Php\Core\SettingsManager;

class SetupWizard
{
    /** Qt window handle */
    private int $window = 0;

    /** Current page index */
    private int $currentPage = 0;

    /** Total pages */
    private int $totalPages = 5;

    /** Environment detector */
    private EnvironmentDetector $detector;

    /** Settings manager */
    private SettingsManager $settings;

    /** Whether the wizard is visible */
    private bool $visible = false;

    /** Language selector combo handle (Welcome page) */
    private int $langCombo = 0;

    /** Model directory input handle (Models page) */
    private int $modelDirInput = 0;

    /** Available UI languages: code => display name */
    public const LANGUAGES = [
        'en' => 'English',
        'zh_CN' => '简体中文',
        'zh_TW' => '繁體中文',
        'ja' => '日本語',
        'ko' => '한국어',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'ru' => 'Русский',
        'es' => 'Español',
    ];

    public function __construct(EnvironmentDetector $detector, SettingsManager $settings)
    {
        $this->detector = $detector;
        $this->settings = $settings;
    }

    /**
     * Show the setup wizard (starts at page 0).
     */
    public function show(): void
    {
        $this->showPage(0);
        $this->visible = true;
    }

    /**
     * Show a specific wizard page (rebuilds the whole window).
     */
    private function showPage(int $page): void
    {
        $this->currentPage = $page;

        if ($this->window > 0) {
            qt_destroy($this->window);
        }

        $this->window = qt_window_create();
        qt_window_set_title($this->window, 'H3PHP Setup Wizard');
        qt_window_set_size($this->window, 720, 540);

        $central = qt_widget_create();
        $main = qt_layout_vbox_create();
        qt_widget_set_layout($central, $main);
        qt_layout_set_spacing($main, 10);
        qt_layout_set_margins($main, 16, 16, 16, 16);

        switch ($page) {
            case 0:
                $this->buildWelcome($main);
                break;
            case 1:
                $this->buildPython($main);
                break;
            case 2:
                $this->buildGPU($main);
                break;
            case 3:
                $this->buildModels($main);
                break;
            case 4:
                $this->buildReady($main);
                break;
        }

        $this->addNavBar($main, $page);
        qt_window_set_central_widget_handle($this->window, $central);
        qt_window_show($this->window);
    }

    /**
     * Add the navigation bar (Back / Next / Finish / Cancel) for a page.
     */
    private function addNavBar(int $main, int $page): void
    {
        $nav = qt_layout_hbox_create();
        qt_layout_add_stretch($nav);

        if ($page > 0) {
            $back = qt_button_create('Back');
            qt_button_set_on_click($back, 'wizard_back');
            qt_layout_add_widget($nav, $back);
        }

        if ($page < $this->totalPages - 1) {
            $next = qt_button_create('Next');
            qt_button_set_on_click($next, 'wizard_next');
            qt_layout_add_widget($nav, $next);
        } else {
            $finish = qt_button_create('Finish');
            qt_button_set_on_click($finish, 'wizard_finish');
            qt_layout_add_widget($nav, $finish);
        }

        $cancel = qt_button_create('Cancel');
        qt_button_set_on_click($cancel, 'wizard_cancel');
        qt_layout_add_widget($nav, $cancel);

        qt_layout_add_layout($main, $nav);
    }

    /**
     * Page 0: Welcome — intro, how-to, and language selector.
     */
    private function buildWelcome(int $main): void
    {
        qt_layout_add_widget($main, qt_label_create('Welcome to H3PHP'));
        qt_layout_add_widget($main, qt_label_create(
            'This wizard helps you set up H3PHP for MiniMax-H3 video generation.'
        ));
        qt_layout_add_widget($main, qt_label_create(
            "H3PHP is a cross-platform GUI supporting Native H3, ComfyUI, and HTTP backends.\n"
            . "Before we begin, let's check your system."
        ));

        qt_layout_add_widget($main, qt_label_create('How to use / 使用说明:'));
        qt_layout_add_widget($main, qt_label_create(
            "1. Select a model directory (Model Dir)\n"
            . "2. Enter a prompt describing the video\n"
            . "3. Set parameters (resolution, frames, steps)\n"
            . "4. Click 'Generate Video' to start\n"
            . "5. Watch progress in the log area"
        ));

        // Language selector row
        $langRow = qt_layout_hbox_create();
        qt_layout_add_widget($langRow, qt_label_create('Language:'));

        $names = array_values(self::LANGUAGES);
        $this->langCombo = qt_combo_box_create($names);

        $current = $this->settings->get('general.language', 'en');
        $codes = array_keys(self::LANGUAGES);
        $idx = array_search($current, $codes, true);
        if (false !== $idx) {
            qt_combo_box_set_current_index($this->langCombo, $idx);
        }

        qt_layout_add_widget($langRow, $this->langCombo);

        $apply = qt_button_create('Apply');
        qt_button_set_on_click($apply, 'wizard_apply_lang');
        qt_layout_add_widget($langRow, $apply);

        qt_layout_add_layout($main, $langRow);
    }

    /**
     * Page 1: Python detection.
     */
    private function buildPython(int $main): void
    {
        $python = $this->detector->detectPython();

        qt_layout_add_widget($main, qt_label_create('Python Detection'));

        if ($python['installed']) {
            qt_layout_add_widget($main, qt_label_create(
                "Python {$python['version']} found at:\n{$python['path']}"
            ));
        } else {
            qt_layout_add_widget($main, qt_label_create('Python not found. Please install Python 3.10+.'));
        }

        $pkgText = "Packages:\n";
        foreach ($python['packages'] as $pkg => $info) {
            $icon = $info['installed'] ? '✓' : '✗';
            $pkgText .= "  {$icon} {$pkg}: {$info['version']}\n";
        }
        qt_layout_add_widget($main, qt_label_create($pkgText));
    }

    /**
     * Page 2: GPU detection.
     */
    private function buildGPU(int $main): void
    {
        $gpu = $this->detector->detectGPU();

        qt_layout_add_widget($main, qt_label_create('GPU Detection'));

        if ($gpu['available']) {
            qt_layout_add_widget($main, qt_label_create(
                'GPU detected! Preferred: ' . strtoupper($gpu['preferred'])
            ));
            $devices = '';
            foreach ($gpu['devices'] as $dev) {
                $devices .= "  • {$dev['name']} ({$dev['vram']})\n";
            }
            qt_layout_add_widget($main, qt_label_create($devices));
        } else {
            qt_layout_add_widget($main, qt_label_create(
                'No GPU detected. Generation will use CPU (very slow).'
            ));
        }
    }

    /**
     * Page 3: Models storage.
     */
    private function buildModels(int $main): void
    {
        $disk = $this->detector->detectDiskSpace();

        qt_layout_add_widget($main, qt_label_create('Model Storage'));
        qt_layout_add_widget($main, qt_label_create(
            "Available disk space: {$disk['free_formatted']}\n"
            . "Required: ~60GB minimum\n"
            . ($disk['sufficient'] ? '✓ Sufficient space' : '✗ Insufficient space')
        ));

        qt_layout_add_widget($main, qt_label_create('Model directory:'));
        $this->modelDirInput = qt_line_edit_create(
            $this->settings->get('paths.model_dir') ?: '~/models'
        );
        qt_layout_add_widget($main, $this->modelDirInput);
    }

    /**
     * Page 4: Ready.
     */
    private function buildReady(int $main): void
    {
        $score = $this->detector->getReadinessScore();
        $label = $this->detector->getReadinessLabel();

        qt_layout_add_widget($main, qt_label_create('Setup Complete!'));
        qt_layout_add_widget($main, qt_label_create("System readiness: {$label} ({$score}/100)"));

        $suggestions = $this->detector->getFixSuggestions();
        $text = empty($suggestions)
            ? 'Your system is ready to use H3PHP!'
            : "Suggestions:\n";
        foreach ($suggestions as $s) {
            $text .= "  • {$s['message']}\n";
        }
        qt_layout_add_widget($main, qt_label_create($text));
    }

    /**
     * Apply the selected language (called from the wizard event router).
     */
    public function applyLanguage(): void
    {
        if ($this->langCombo <= 0) {
            return;
        }
        $text = qt_combo_box_get_current_text($this->langCombo);
        $code = array_search($text, self::LANGUAGES, true) ?: 'en';
        $this->settings->set('general.language', $code);
        $this->settings->save();
        qt_app_set_language($code);
    }

    /**
     * Go to the next page.
     */
    public function nextPage(): void
    {
        if ($this->currentPage < $this->totalPages - 1) {
            $this->showPage($this->currentPage + 1);
        }
    }

    /**
     * Go to the previous page.
     */
    public function prevPage(): void
    {
        if ($this->currentPage > 0) {
            $this->showPage($this->currentPage - 1);
        }
    }

    /**
     * Finish the wizard: persist settings and close.
     */
    public function finish(): void
    {
        if ($this->modelDirInput > 0) {
            $dir = qt_line_edit_get_text($this->modelDirInput);
            if ($dir) {
                $this->settings->set('paths.model_dir', $dir);
            }
        }
        $this->settings->set('general.setup_completed', true);
        $this->settings->save();
        $this->close();
    }

    /**
     * Close the wizard.
     */
    public function close(): void
    {
        if ($this->visible && $this->window > 0) {
            qt_destroy($this->window);
            $this->visible = false;
        }
    }

    /**
     * Check if the wizard is visible.
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * Get the current page index.
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }
}
