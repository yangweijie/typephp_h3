<?php

/**
 * H3PHP — Qt Main Window.
 *
 * High-level wrapper around QMainWindow.
 * Provides menu creation, status bar, and child widget management.
 */

namespace H3Php\Qt;

class MainWindow
{
    /** Opaque window handle */
    private int $handle;

    /** Child widgets */
    private array $widgets = [];

    /** Whether the window has been created */
    private bool $created = false;

    /**
     * Create a new main window wrapper.
     *
     * Does NOT create the native window yet — call create() after the
     * QApplication has been initialized (see Qt\Application::init()).
     */
    public function __construct()
    {
    }

    /**
     * Create the native QMainWindow.
     * Must be called after Qt\Application::init() (QApplication must exist
     * before any QWidget is constructed).
     */
    public function create(): self
    {
        $this->handle = qt_window_create();
        $this->created = ($this->handle > 0);

        return $this;
    }

    /**
     * Set the window title.
     */
    public function setTitle(string $title): self
    {
        qt_window_set_title($this->handle, $title);

        return $this;
    }

    /**
     * Set the window size.
     */
    public function setSize(int $width, int $height): self
    {
        qt_window_set_size($this->handle, $width, $height);

        return $this;
    }

    /**
     * Show the window.
     */
    public function show(): self
    {
        qt_window_show($this->handle);

        return $this;
    }

    /**
     * Check if the window is visible.
     */
    public function isVisible(): bool
    {
        return qt_window_is_visible($this->handle);
    }

    /**
     * Set a central widget.
     *
     * @param string $type Widget type ('QLabel', 'QTextEdit', 'QWidget')
     * @param string $text Initial text content
     * @return int Widget handle
     */
    public function setCentralWidget(string $type, string $text = ''): int
    {
        $widgetHandle = qt_window_set_central_widget($this->handle, $type, $text);
        $this->widgets[] = $widgetHandle;

        return $widgetHandle;
    }

    /**
     * Create a menu bar with menus and actions.
     *
     * @param array $menus Menu definitions:
     *   [
     *     ['title' => 'File', 'items' => [
     *       ['label' => 'Open', 'action' => 'file_open'],
     *       ['label' => 'Exit', 'action' => 'app_exit'],
     *     ]],
     *     ['title' => 'Help', 'items' => [
     *       ['label' => 'About', 'action' => 'help_about'],
     *     ]],
     *   ]
     * @return int Menu bar handle
     */
    public function createMenuBar(array $menus): int
    {
        $menuHandle = qt_window_create_menu_bar($this->handle, $menus);
        $this->widgets[] = $menuHandle;

        return $menuHandle;
    }

    /**
     * Show a message box dialog.
     */
    public function messageBox(string $title, string $message, string $icon = 'info'): void
    {
        qt_message_box_show($this->handle, $title, $message, $icon);
    }

    /**
     * Show a file open dialog.
     */
    public function openFileDialog(string $title, string $filter = ''): string
    {
        return qt_file_dialog_open($this->handle, $title, $filter);
    }

    /**
     * Show a folder selection dialog.
     */
    public function openFolderDialog(string $title): string
    {
        return qt_folder_dialog_open($this->handle, $title);
    }

    /**
     * Get the native window handle.
     */
    public function getHandle(): int
    {
        return $this->handle;
    }

    /**
     * Check if the window was created successfully.
     */
    public function isCreated(): bool
    {
        return $this->created;
    }

    /**
     * Destroy the window and free resources.
     */
    public function destroy(): void
    {
        qt_destroy($this->handle);
        $this->created = false;
    }
}
