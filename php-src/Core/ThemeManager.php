<?php

/**
 * H3PHP — Theme Manager.
 *
 * Manages application theming: dark, light, and system themes.
 * Provides QSS (Qt Style Sheet) generation and color palette management.
 */

namespace H3Php\Core;

class ThemeManager
{
    /** Current theme name */
    private string $currentTheme = 'dark';

    /** Available themes */
    private array $themes = [];

    /** Custom overrides */
    private array $overrides = [];

    public function __construct()
    {
        $this->registerDefaultThemes();
    }

    /**
     * Register built-in themes.
     */
    private function registerDefaultThemes(): void
    {
        $this->themes = [
            'dark' => [
                'name' => 'Dark',
                'window' => '#2b2b2b',
                'window_text' => '#ffffff',
                'base' => '#1e1e1e',
                'alternate_base' => '#2d2d2d',
                'tooltip_base' => '#3d3d3d',
                'tooltip_text' => '#ffffff',
                'text' => '#ffffff',
                'button' => '#3d3d3d',
                'button_text' => '#ffffff',
                'bright_text' => '#ff0000',
                'link' => '#569cd6',
                'highlight' => '#264f78',
                'highlighted_text' => '#ffffff',
                'light' => '#4d4d4d',
                'midlight' => '#3d3d3d',
                'dark' => '#1e1e1e',
                'mid' => '#333333',
                'shadow' => '#000000',
                // Custom H3PHP colors
                'node_bg' => '#3c3c3c',
                'node_header' => '#4a7ab5',
                'node_border' => '#555555',
                'connection' => '#c8c864',
                'grid' => '#333333',
                'canvas' => '#282828',
                'success' => '#4caf50',
                'warning' => '#ff9800',
                'error' => '#f44336',
                'info' => '#2196f3',
            ],
            'light' => [
                'name' => 'Light',
                'window' => '#f0f0f0',
                'window_text' => '#000000',
                'base' => '#ffffff',
                'alternate_base' => '#f5f5f5',
                'tooltip_base' => '#ffffdc',
                'tooltip_text' => '#000000',
                'text' => '#000000',
                'button' => '#e0e0e0',
                'button_text' => '#000000',
                'bright_text' => '#ff0000',
                'link' => '#0000ff',
                'highlight' => '#308cc6',
                'highlighted_text' => '#ffffff',
                'light' => '#ffffff',
                'midlight' => '#f0f0f0',
                'dark' => '#c0c0c0',
                'mid' => '#d0d0d0',
                'shadow' => '#a0a0a0',
                // Custom H3PHP colors
                'node_bg' => '#ffffff',
                'node_header' => '#6a9fd4',
                'node_border' => '#cccccc',
                'connection' => '#b8860b',
                'grid' => '#e0e0e0',
                'canvas' => '#fafafa',
                'success' => '#4caf50',
                'warning' => '#ff9800',
                'error' => '#f44336',
                'info' => '#2196f3',
            ],
            'system' => [
                'name' => 'System',
                // Will be resolved at runtime based on OS theme
                'window' => 'auto',
                'window_text' => 'auto',
                'base' => 'auto',
                'alternate_base' => 'auto',
                'tooltip_base' => 'auto',
                'tooltip_text' => 'auto',
                'text' => 'auto',
                'button' => 'auto',
                'button_text' => 'auto',
                'bright_text' => 'auto',
                'link' => 'auto',
                'highlight' => 'auto',
                'highlighted_text' => 'auto',
                'light' => 'auto',
                'midlight' => 'auto',
                'dark' => 'auto',
                'mid' => 'auto',
                'shadow' => 'auto',
                'node_bg' => 'auto',
                'node_header' => 'auto',
                'node_border' => 'auto',
                'connection' => 'auto',
                'grid' => 'auto',
                'canvas' => 'auto',
                'success' => '#4caf50',
                'warning' => '#ff9800',
                'error' => '#f44336',
                'info' => '#2196f3',
            ],
        ];
    }

    /**
     * Set the current theme.
     */
    public function setTheme(string $name): bool
    {
        if (!isset($this->themes[$name])) {
            return false;
        }

        $this->currentTheme = $name;

        return true;
    }

    /**
     * Get the current theme name.
     */
    public function getTheme(): string
    {
        return $this->currentTheme;
    }

    /**
     * Get all available theme names.
     */
    public function getAvailableThemes(): array
    {
        return array_keys($this->themes);
    }

    /**
     * Get a color value from the current theme.
     */
    public function getColor(string $key): string
    {
        $theme = $this->resolveTheme();

        return $theme[$key] ?? '#000000';
    }

    /**
     * Get the full color palette for the current theme.
     */
    public function getPalette(): array
    {
        return $this->resolveTheme();
    }

    /**
     * Set a custom color override.
     */
    public function setOverride(string $key, string $color): self
    {
        $this->overrides[$key] = $color;

        return $this;
    }

    /**
     * Generate QSS (Qt Style Sheet) for the current theme.
     */
    public function generateQSS(): string
    {
        $c = $this->resolveTheme();

        return <<<QSS
/* H3PHP — Generated Theme: {$c['name']} */

QMainWindow, QDialog {
    background-color: {$c['window']};
    color: {$c['window_text']};
}

QWidget {
    background-color: {$c['base']};
    color: {$c['text']};
    font-family: "Segoe UI", "SF Pro", sans-serif;
}

QPushButton {
    background-color: {$c['button']};
    color: {$c['button_text']};
    border: 1px solid {$c['mid']};
    border-radius: 4px;
    padding: 6px 16px;
    min-width: 80px;
}

QPushButton:hover {
    background-color: {$c['midlight']};
}

QPushButton:pressed {
    background-color: {$c['highlight']};
    color: {$c['highlighted_text']};
}

QLineEdit, QTextEdit, QPlainTextEdit {
    background-color: {$c['base']};
    color: {$c['text']};
    border: 1px solid {$c['mid']};
    border-radius: 3px;
    padding: 4px;
}

QComboBox {
    background-color: {$c['button']};
    color: {$c['button_text']};
    border: 1px solid {$c['mid']};
    border-radius: 3px;
    padding: 4px;
}

QProgressBar {
    border: 1px solid {$c['mid']};
    border-radius: 3px;
    text-align: center;
    background-color: {$c['base']};
}

QProgressBar::chunk {
    background-color: {$c['info']};
    border-radius: 2px;
}

QMenuBar {
    background-color: {$c['window']};
    color: {$c['window_text']};
}

QMenuBar::item:selected {
    background-color: {$c['highlight']};
    color: {$c['highlighted_text']};
}

QMenu {
    background-color: {$c['window']};
    color: {$c['window_text']};
    border: 1px solid {$c['mid']};
}

QMenu::item:selected {
    background-color: {$c['highlight']};
    color: {$c['highlighted_text']};
}

QScrollBar:vertical {
    background-color: {$c['base']};
    width: 12px;
}

QScrollBar::handle:vertical {
    background-color: {$c['mid']};
    border-radius: 6px;
    min-height: 20px;
}

QScrollBar::handle:vertical:hover {
    background-color: {$c['light']};
}

QGraphicsView {
    background-color: {$c['canvas']};
    border: none;
}

QStatusBar {
    background-color: {$c['window']};
    color: {$c['window_text']};
}

QToolBar {
    background-color: {$c['window']};
    border: none;
    spacing: 4px;
}

QSplitter::handle {
    background-color: {$c['mid']};
}
QSS;
    }

    /**
     * Resolve the current theme (handles 'system' theme).
     */
    private function resolveTheme(): array
    {
        $theme = $this->themes[$this->currentTheme] ?? $this->themes['dark'];

        // Resolve 'system' theme
        if ('system' === $this->currentTheme) {
            $theme = $this->resolveSystemTheme($theme);
        }

        // Apply overrides
        foreach ($this->overrides as $key => $value) {
            $theme[$key] = $value;
        }

        return $theme;
    }

    /**
     * Resolve system theme (detect OS dark/light mode).
     */
    private function resolveSystemTheme(array $theme): array
    {
        // On Windows, check registry
        if (PHP_OS_FAMILY === 'Windows') {
            $isDark = $this->isWindowsDarkMode();
        }
        // On macOS, check defaults
        elseif (PHP_OS_FAMILY === 'Darwin') {
            $isDark = $this->isMacOSDarkMode();
        }
        // Default to dark
        else {
            $isDark = true;
        }

        $resolved = $this->themes[$isDark ? 'dark' : 'light'];
        $resolved['name'] = 'System';

        return $resolved;
    }

    /**
     * Check if Windows is in dark mode.
     */
    private function isWindowsDarkMode(): bool
    {
        // Check registry key for dark mode
        $output = shell_exec(
            'reg query "HKCU\Software\Microsoft\Windows\CurrentVersion\Themes\Personalize" /v AppsUseLightTheme 2>nul'
        );

        if (preg_match('/0x0/', $output ?? '')) {
            return true; // 0 means dark mode
        }

        return false;
    }

    /**
     * Check if macOS is in dark mode.
     */
    private function isMacOSDarkMode(): bool
    {
        $output = shell_exec('defaults read -g AppleInterfaceStyle 2>/dev/null');

        return false !== strpos($output ?? '', 'Dark');
    }

    /**
     * Register a custom theme.
     */
    public function registerTheme(string $name, array $colors): self
    {
        $this->themes[$name] = $colors;

        return $this;
    }
}
