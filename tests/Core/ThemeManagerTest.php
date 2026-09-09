<?php

/**
 * H3PHP — ThemeManager Tests.
 */

namespace H3Php\Tests\Core;

use PHPUnit\Framework\TestCase;
use H3Php\Core\ThemeManager;

class ThemeManagerTest extends TestCase
{
    public function testDefaultTheme(): void
    {
        $theme = new ThemeManager();

        $this->assertSame('dark', $theme->getTheme());
    }

    public function testSetTheme(): void
    {
        $theme = new ThemeManager();

        $this->assertTrue($theme->setTheme('light'));
        $this->assertSame('light', $theme->getTheme());
    }

    public function testSetInvalidTheme(): void
    {
        $theme = new ThemeManager();

        $this->assertFalse($theme->setTheme('nonexistent'));
    }

    public function testGetAvailableThemes(): void
    {
        $theme = new ThemeManager();
        $themes = $theme->getAvailableThemes();

        $this->assertContains('dark', $themes);
        $this->assertContains('light', $themes);
        $this->assertContains('system', $themes);
    }

    public function testGetColor(): void
    {
        $theme = new ThemeManager();
        $color = $theme->getColor('window');

        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $color);
    }

    public function testGetPalette(): void
    {
        $theme = new ThemeManager();
        $palette = $theme->getPalette();

        $this->assertArrayHasKey('window', $palette);
        $this->assertArrayHasKey('text', $palette);
        $this->assertArrayHasKey('node_bg', $palette);
    }

    public function testGenerateQSS(): void
    {
        $theme = new ThemeManager();
        $qss = $theme->generateQSS();

        $this->assertStringContainsString('QMainWindow', $qss);
        $this->assertStringContainsString('QPushButton', $qss);
        $this->assertStringContainsString('QGraphicsView', $qss);
    }

    public function testSetOverride(): void
    {
        $theme = new ThemeManager();
        $theme->setOverride('window', '#ff0000');

        $this->assertSame('#ff0000', $theme->getColor('window'));
    }

    public function testLightThemeColors(): void
    {
        $theme = new ThemeManager();
        $theme->setTheme('light');

        $windowColor = $theme->getColor('window');

        // Light theme should have a light window color
        $this->assertNotSame($windowColor, (new ThemeManager())->getColor('window'));
    }
}
