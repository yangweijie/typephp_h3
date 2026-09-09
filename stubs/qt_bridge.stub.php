<?php

/**
 * H3PHP — Qt Bridge Stubs.
 *
 * PHP declarations for Qt GUI functions.
 * Implemented in cpp-src/qt_bridge.cc via php_ prefix ABI.
 *
 * Pattern from TypePHP ssh-tunnel-qt example:
 * - PHP owns all business logic
 * - Qt only handles UI + event loop
 * - Opaque int handles for C++ objects (GC-safe)
 * - Event queue: C++ enqueues, PHP polls and dispatches
 */

/**
 * Initialize Qt application.
 * Must be called before any other Qt function.
 * Returns 0 on success, non-zero on failure.
 */
function qt_app_init(): int {}

/**
 * Run the Qt event loop.
 * Blocks until quit_app() is called.
 * Returns exit code.
 */
function qt_app_exec(): int {}

/**
 * Process pending events without blocking.
 * Returns true if events were processed.
 */
function qt_app_process_events(): bool {}

/**
 * Quit the Qt application.
 * Call this to exit the event loop started by qt_app_exec().
 */
function qt_app_quit(): void {}

/**
 * Create a new QMainWindow.
 * Returns an opaque window handle (int), or 0 on failure.
 */
function qt_window_create(): int {}

/**
 * Set window title.
 *
 * @param int $window Window handle from qt_window_create()
 */
function qt_window_set_title(int $window, string $title): void {}

/**
 * Set window size.
 *
 * @param int $window Window handle
 * @param int $width Width in pixels
 * @param int $height Height in pixels
 */
function qt_window_set_size(int $window, int $width, int $height): void {}

/**
 * Show the window.
 *
 * @param int $window Window handle
 */
function qt_window_show(int $window): void {}

/**
 * Check if window is still visible (not closed by user).
 *
 * @param int $window Window handle
 */
function qt_window_is_visible(int $window): bool {}

/**
 * Set central widget.
 *
 * @param int $window Window handle
 * @param string $widgetType Widget type name ('QLabel', 'QWidget', etc.)
 * @param string $text Initial text (for QLabel)
 * @return int Widget handle
 */
function qt_window_set_central_widget(int $window, string $widgetType, string $text = ''): int {}

/**
 * Create a menu bar with menus.
 *
 * @param int $window Window handle
 * @param array $menus Array of menu definitions [['title' => 'File', 'items' => [['label' => 'Open', 'action' => 'open']]]]
 * @return int Menu bar handle
 */
function qt_window_create_menu_bar(int $window, array $menus): int {}

/**
 * Poll for next event from Qt event queue.
 * Returns event array or null if no events.
 * Event format: ['type' => 'menu_click', 'action' => 'open', ...]
 */
function qt_app_poll_event(): ?array {}

/**
 * Post a custom event to be picked up by qt_app_poll_event().
 *
 * @param string $eventType Event type identifier
 * @param array $data Event data
 */
function qt_app_post_event(string $eventType, array $data): void {}

/**
 * Create a QLabel widget.
 *
 * @param string $text Initial text
 * @return int Widget handle
 */
function qt_label_create(string $text = ''): int {}

/**
 * Set QLabel text.
 *
 * @param int $label Widget handle
 * @param string $text New text
 */
function qt_label_set_text(int $label, string $text): void {}

/**
 * Create a QPushButton widget.
 *
 * @param string $text Button label
 * @return int Widget handle
 */
function qt_button_create(string $text = ''): int {}

/**
 * Create a QLineEdit (text input) widget.
 *
 * @param string $placeholder Placeholder text
 * @return int Widget handle
 */
function qt_line_edit_create(string $placeholder = ''): int {}

/**
 * Get QLineEdit text value.
 *
 * @param int $edit Widget handle
 */
function qt_line_edit_get_text(int $edit): string {}

/**
 * Create a QComboBox (dropdown) widget.
 *
 * @param array $items Options list
 * @return int Widget handle
 */
function qt_combo_box_create(array $items = []): int {}

/**
 * Create a QProgressBar widget.
 *
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @return int Widget handle
 */
function qt_progress_bar_create(int $min = 0, int $max = 100): int {}

/**
 * Set QProgressBar value.
 *
 * @param int $progress Widget handle
 * @param int $value Current value
 */
function qt_progress_bar_set_value(int $progress, int $value): void {}

/**
 * Create a QTextEdit (multi-line text) widget.
 *
 * @param string $text Initial text
 * @return int Widget handle
 */
function qt_text_edit_create(string $text = ''): int {}

/**
 * Get QTextEdit content.
 *
 * @param int $edit Widget handle
 */
function qt_text_edit_get_text(int $edit): string {}

/**
 * Create a QSplitter (resizable panels).
 *
 * @param string $orientation 'horizontal' or 'vertical'
 * @return int Widget handle
 */
function qt_splitter_create(string $orientation = 'horizontal'): int {}

/**
 * Show a message box dialog.
 *
 * @param int $window Parent window handle (0 for none)
 * @param string $title Dialog title
 * @param string $message Message text
 * @param string $icon 'info', 'warning', 'error', 'question'
 */
function qt_message_box_show(int $window, string $title, string $message, string $icon = 'info'): void {}

/**
 * Show a file open dialog.
 *
 * @param int $window Parent window handle (0 for none)
 * @param string $title Dialog title
 * @param string $filter File filter (e.g., 'Images (*.png *.jpg)')
 * @return string Selected file path or empty string if cancelled
 */
function qt_file_dialog_open(int $window, string $title, string $filter = ''): string {}

/**
 * Show a folder selection dialog.
 *
 * @param int $window Parent window handle (0 for none)
 * @param string $title Dialog title
 * @return string Selected folder path or empty string if cancelled
 */
function qt_folder_dialog_open(int $window, string $title): string {}

/**
 * Destroy a widget/window and free resources.
 *
 * @param int $handle Widget or window handle
 */
function qt_destroy(int $handle): void {}

// ============================================================================
// Layout Management
// ============================================================================

/**
 * Create a vertical box layout (QVBoxLayout).
 * Returns layout handle.
 */
function qt_layout_vbox_create(): int {}

/**
 * Create a horizontal box layout (QHBoxLayout).
 * Returns layout handle.
 */
function qt_layout_hbox_create(): int {}

/**
 * Add a widget to a layout.
 *
 * @param int $layout Layout handle
 * @param int $widget Widget handle
 */
function qt_layout_add_widget(int $layout, int $widget): void {}

/**
 * Set a layout on a widget.
 *
 * @param int $widget Widget handle
 * @param int $layout Layout handle
 */
function qt_widget_set_layout(int $widget, int $layout): void {}

/**
 * Set layout spacing.
 */
function qt_layout_set_spacing(int $layout, int $spacing): void {}

/**
 * Set layout margins.
 */
function qt_layout_set_margins(int $layout, int $left, int $top, int $right, int $bottom): void {}

/**
 * Add stretchable space to a layout.
 */
function qt_layout_add_stretch(int $layout): void {}

/**
 * Add a child layout to a parent layout.
 */
function qt_layout_add_layout(int $parent, int $child): void {}

// ============================================================================
// Widget Helpers
// ============================================================================

/**
 * Create an empty QWidget (for use with layouts).
 */
function qt_widget_create(): int {}

/**
 * Set a widget's enabled state.
 */
function qt_widget_set_enabled(int $widget, bool $enabled): void {}

/**
 * Set button click callback.
 * Posts 'button_click' event with 'callback_id' data when clicked.
 */
function qt_button_set_on_click(int $button, string $callback_id): void {}

/**
 * Set line edit text-changed callback.
 * Posts 'text_changed' event with 'callback_id' and 'text' data.
 */
function qt_line_edit_set_on_text_changed(int $edit, string $callback_id): void {}

/**
 * Set combo box selection-changed callback.
 * Posts 'combo_changed' event with 'callback_id', 'index', and 'text' data.
 */
function qt_combo_box_set_on_current_index_changed(int $combo, string $callback_id): void {}

/**
 * Get combo box current text.
 */
function qt_combo_box_get_current_text(int $combo): string {}

/**
 * Get combo box current index.
 */
function qt_combo_box_get_current_index(int $combo): int {}

/**
 * Set combo box current index.
 */
function qt_combo_box_set_current_index(int $combo, int $index): void {}

/**
 * Set progress bar text visibility.
 */
function qt_progress_bar_set_text_visible(int $progress, bool $visible): void {}

/**
 * Set progress bar format string (e.g., "%v/%m (%p%)").
 */
function qt_progress_bar_set_format(int $progress, string $format): void {}

/**
 * Set QLabel word wrap.
 */
function qt_label_set_word_wrap(int $label, bool $wrap): void {}

/**
 * Set QTextEdit read-only state.
 */
function qt_text_edit_set_read_only(int $edit, bool $read_only): void {}

/**
 * Append text to QTextEdit.
 */
function qt_text_edit_append(int $edit, string $text): void {}

/**
 * Set central widget using a widget handle.
 */
function qt_window_set_central_widget_handle(int $window, int $widget): void {}

// ============================================================================
// Input Dialogs
// ============================================================================

/**
 * Show a text input dialog.
 * Returns the entered text, or empty string if cancelled.
 */
function qt_input_dialog_get_text(int $window, string $title, string $label, string $default_text = ''): string {}

/**
 * Show an item selection dialog.
 * Returns the selected item, or empty string if cancelled.
 */
function qt_input_dialog_get_item(int $window, string $title, string $label, array $items, int $current = 0, bool $editable = false): string {}

/**
 * Show an integer input dialog.
 * Returns the entered value, or -1 if cancelled.
 */
function qt_input_dialog_get_int(int $window, string $title, string $label, int $value = 0, int $min = -2147483647, int $max = 2147483647, int $step = 1): int {}

/**
 * Show a double input dialog.
 * Returns the entered value, or -1.0 if cancelled.
 */
function qt_input_dialog_get_double(int $window, string $title, string $label, double $value = 0.0, double $min = -2147483647, double $max = 2147483647, int $decimals = 1): double {}
