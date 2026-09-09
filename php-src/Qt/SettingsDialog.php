<?php

/**
 * H3PHP — Settings Dialog.
 *
 * Qt dialog for editing application settings.
 * Sections: General, Paths, Backend, Download, Display, Advanced.
 */

namespace H3Php\Qt;

use H3Php\Core\SettingsManager;

class SettingsDialog
{
    /** Qt dialog window handle */
    private int $window;

    /** Settings manager */
    private SettingsManager $settings;

    /** Whether the dialog is visible */
    private bool $visible = false;

    /** Widget handles for form fields */
    private array $fields = [];

    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Show the settings dialog.
     */
    public function show(): void
    {
        $this->window = qt_window_create();
        qt_window_set_title($this->window, 'Settings');
        qt_window_set_size($this->window, 640, 480);
        qt_window_show($this->window);

        // Create menu bar with section tabs
        $this->createMenuBar();

        // Create form fields
        $this->createGeneralSection();

        $this->visible = true;
    }

    /**
     * Create the menu bar.
     */
    private function createMenuBar(): void
    {
        $menus = [
            [
                'title' => 'File',
                'items' => [
                    ['label' => 'Save', 'action' => 'settings_save'],
                    ['label' => 'Cancel', 'action' => 'settings_cancel'],
                ],
            ],
        ];

        qt_window_create_menu_bar($this->window, $menus);
    }

    /**
     * Create general settings section.
     */
    private function createGeneralSection(): void
    {
        // Language
        $langLabel = qt_label_create('Language:');
        $this->fields['language'] = qt_combo_box_create(['en', 'zh']);

        // Auto-update check
        $updateLabel = qt_label_create('Check for updates:');
        $this->fields['auto_update'] = qt_combo_box_create(['Yes', 'No']);

        // Model directory
        $modelLabel = qt_label_create('Model Directory:');
        $this->fields['model_dir'] = qt_line_edit_create('Path to model directory...');

        // Output directory
        $outputLabel = qt_label_create('Output Directory:');
        $this->fields['output_dir'] = qt_line_edit_create('Path to output directory...');
    }

    /**
     * Save settings from form fields.
     */
    public function save(): bool
    {
        // Read values from form fields and save
        $this->settings->set('general.language', qt_line_edit_get_text($this->fields['language'] ?? 0));
        $this->settings->set('paths.model_dir', qt_line_edit_get_text($this->fields['model_dir'] ?? 0));
        $this->settings->set('paths.output_dir', qt_line_edit_get_text($this->fields['output_dir'] ?? 0));

        return $this->settings->save();
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
