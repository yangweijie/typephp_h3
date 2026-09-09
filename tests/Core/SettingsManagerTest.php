<?php

/**
 * H3PHP — SettingsManager Tests.
 */

namespace H3Php\Tests\Core;

use PHPUnit\Framework\TestCase;
use H3Php\Core\SettingsManager;

class SettingsManagerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/h3php_test_settings_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testLoadDefaults(): void
    {
        $settings = new SettingsManager($this->tempFile);
        $settings->load();

        $this->assertSame('en', $settings->get('general.language'));
        $this->assertSame('dark', $settings->get('display.theme'));
        $this->assertSame('auto', $settings->get('backend.preferred_backend'));
    }

    public function testSetAndGet(): void
    {
        $settings = new SettingsManager($this->tempFile);
        $settings->load();

        $settings->set('general.language', 'zh');
        $this->assertSame('zh', $settings->get('general.language'));
    }

    public function testDotNotation(): void
    {
        $settings = new SettingsManager($this->tempFile);
        $settings->load();

        $settings->set('backend.device', 'cuda');
        $this->assertSame('cuda', $settings->get('backend.device'));
    }

    public function testSaveAndLoad(): void
    {
        $settings1 = new SettingsManager($this->tempFile);
        $settings1->load();
        $settings1->set('general.language', 'zh');
        $settings1->save();

        $settings2 = new SettingsManager($this->tempFile);
        $settings2->load();

        $this->assertSame('zh', $settings2->get('general.language'));
    }

    public function testReset(): void
    {
        $settings = new SettingsManager($this->tempFile);
        $settings->load();

        $settings->set('general.language', 'zh');
        $settings->reset();

        $this->assertSame('en', $settings->get('general.language'));
    }

    public function testGetSection(): void
    {
        $settings = new SettingsManager($this->tempFile);
        $settings->load();

        $section = $settings->getSection('display');

        $this->assertArrayHasKey('theme', $section);
        $this->assertArrayHasKey('font_size', $section);
    }

    public function testValidate(): void
    {
        $settings = new SettingsManager($this->tempFile);
        $settings->load();

        $errors = $settings->validate();

        $this->assertIsArray($errors);
    }

    public function testExportImport(): void
    {
        $settings = new SettingsManager($this->tempFile);
        $settings->load();
        $settings->set('general.language', 'zh');

        $exportFile = sys_get_temp_dir() . '/h3php_export_' . uniqid() . '.json';
        $settings->export($exportFile);

        $this->assertFileExists($exportFile);

        $settings2 = new SettingsManager($this->tempFile);
        $settings2->import($exportFile);

        $this->assertSame('zh', $settings2->get('general.language'));

        unlink($exportFile);
    }
}
