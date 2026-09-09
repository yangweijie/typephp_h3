<?php

namespace H3Php\Tests\Core;

use H3Php\Core\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 应用级 UI 翻译器：向导文案在 Apply 语言后必须能切换（修复"点了没反应"）。
 */
final class TranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        Translator::setLanguage('en');
    }

    public function testDefaultsToEnglishSourceStrings(): void
    {
        self::assertSame('en', Translator::language());
        self::assertSame('Welcome to H3PHP', Translator::t('wizard.welcome_heading'));
        self::assertSame('Back', Translator::t('wizard.back'));
    }

    public function testSwitchingToChineseTranslatesWizardStrings(): void
    {
        Translator::setLanguage('zh_CN');

        self::assertSame('zh_CN', Translator::language());
        self::assertSame('欢迎使用 H3PHP', Translator::t('wizard.welcome_heading'));
        self::assertSame('上一步', Translator::t('wizard.back'));
        self::assertSame('下一步', Translator::t('wizard.next'));
        self::assertSame('设置完成！', Translator::t('wizard.ready_heading'));
    }

    public function testUnknownKeysReturnTheKeyItself(): void
    {
        self::assertSame('wizard.not_a_key', Translator::t('wizard.not_a_key'));
    }

    public function testUnknownLanguageFallsBackToEnglish(): void
    {
        Translator::setLanguage('xx_XX');

        self::assertSame('en', Translator::language());
        self::assertSame('Welcome to H3PHP', Translator::t('wizard.welcome_heading'));
    }

    public function testOnlyTranslatedLanguagesAreReported(): void
    {
        self::assertContains('zh_CN', Translator::translatedLanguages());
        self::assertNotContains('en', Translator::translatedLanguages());
    }
}
