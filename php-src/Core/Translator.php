<?php

/**
 * H3PHP — Application-level UI translator.
 *
 * Qt's QTranslator only translates strings wrapped in tr() inside Qt
 * itself; every label in this app is a plain literal created from PHP,
 * so language switching needs an application-side dictionary.
 *
 * Usage:
 *   Translator::setLanguage('zh_CN');
 *   $label = qt_label_create(Translator::t('wizard.welcome_heading'));
 *
 * Unknown keys return the key itself; unknown languages fall back to
 * English (the source strings).
 */

namespace H3Php\Core;

class Translator
{
    /** Currently active UI language (BCP-47-ish code) */
    private static string $language = 'en';

    /** English source strings (also the fallback) */
    private const EN = [
        'wizard.title' => 'H3PHP Setup Wizard',
        'wizard.welcome_heading' => 'Welcome to H3PHP',
        'wizard.welcome_intro' => 'This wizard helps you set up H3PHP for MiniMax-H3 video generation.',
        'wizard.welcome_backends' => "H3PHP is a cross-platform GUI supporting Native H3, ComfyUI, and HTTP backends.\nBefore we begin, let's check your system.",
        'wizard.howto' => 'How to use:',
        'wizard.howto_steps' => "1. Select a model directory (Model Dir)\n2. Enter a prompt describing the video\n3. Set parameters (resolution, frames, steps)\n4. Click 'Generate Video' to start\n5. Watch progress in the log area",
        'wizard.language' => 'Language:',
        'wizard.apply' => 'Apply',
        'wizard.back' => 'Back',
        'wizard.next' => 'Next',
        'wizard.finish' => 'Finish',
        'wizard.cancel' => 'Cancel',
        'wizard.python_heading' => 'Python Detection',
        'wizard.python_found' => "Python %s found at:\n%s",
        'wizard.python_missing' => 'Python not found. Please install Python 3.10+.',
        'wizard.python_packages' => 'Packages:',
        'wizard.gpu_heading' => 'GPU Detection',
        'wizard.gpu_found' => 'GPU detected! Preferred: %s',
        'wizard.gpu_missing' => 'No GPU detected. Generation will use CPU (very slow).',
        'wizard.models_heading' => 'Model Storage',
        'wizard.models_disk' => 'Available disk space: %s',
        'wizard.models_required' => 'Required: ~60GB minimum',
        'wizard.models_sufficient' => '✓ Sufficient space',
        'wizard.models_insufficient' => '✗ Insufficient space',
        'wizard.models_dir' => 'Model directory:',
        'wizard.ready_heading' => 'Setup Complete!',
        'wizard.ready_score' => 'System readiness: %s (%d/100)',
        'wizard.ready_ok' => 'Your system is ready to use H3PHP!',
        'wizard.suggestions' => 'Suggestions:',
    ];

    /** Translations by language code (missing entries fall back to EN) */
    private const DICTIONARIES = [
        'zh_CN' => [
            'wizard.title' => 'H3PHP 设置向导',
            'wizard.welcome_heading' => '欢迎使用 H3PHP',
            'wizard.welcome_intro' => '本向导帮助您完成 H3PHP（MiniMax-H3 视频生成）的初始设置。',
            'wizard.welcome_backends' => "H3PHP 是一个支持 Native H3、ComfyUI 与 HTTP 后端的跨平台图形界面。\n开始之前，先检查您的系统环境。",
            'wizard.howto' => '使用说明：',
            'wizard.howto_steps' => "1. 选择模型目录（Model Dir）\n2. 输入描述视频内容的提示词\n3. 设置参数（分辨率、帧数、步数）\n4. 点击「Generate Video」开始生成\n5. 在日志区查看进度",
            'wizard.language' => '语言：',
            'wizard.apply' => '应用',
            'wizard.back' => '上一步',
            'wizard.next' => '下一步',
            'wizard.finish' => '完成',
            'wizard.cancel' => '取消',
            'wizard.python_heading' => 'Python 检测',
            'wizard.python_found' => "检测到 Python %s，路径：\n%s",
            'wizard.python_missing' => '未检测到 Python。请安装 Python 3.10+。',
            'wizard.python_packages' => '依赖包：',
            'wizard.gpu_heading' => 'GPU 检测',
            'wizard.gpu_found' => '检测到 GPU！首选后端：%s',
            'wizard.gpu_missing' => '未检测到 GPU。生成将使用 CPU（非常慢）。',
            'wizard.models_heading' => '模型存储',
            'wizard.models_disk' => '可用磁盘空间：%s',
            'wizard.models_required' => '最低需要约 60GB',
            'wizard.models_sufficient' => '✓ 空间充足',
            'wizard.models_insufficient' => '✗ 空间不足',
            'wizard.models_dir' => '模型目录：',
            'wizard.ready_heading' => '设置完成！',
            'wizard.ready_score' => '系统就绪度：%s（%d/100）',
            'wizard.ready_ok' => '您的系统已就绪，可以开始使用 H3PHP！',
            'wizard.suggestions' => '建议：',
        ],
    ];

    /**
     * Set the active UI language.
     * Unknown codes fall back to English.
     */
    public static function setLanguage(string $code): void
    {
        self::$language = isset(self::DICTIONARIES[$code]) ? $code : 'en';
    }

    /**
     * Get the active UI language code.
     */
    public static function language(): string
    {
        return self::$language;
    }

    /**
     * Languages that have an application dictionary (beyond English).
     *
     * @return string[]
     */
    public static function translatedLanguages(): array
    {
        return array_keys(self::DICTIONARIES);
    }

    /**
     * Translate a key using the active language.
     * Falls back to the English source string, then to the key itself.
     */
    public static function t(string $key): string
    {
        if ('en' !== self::$language) {
            $entry = self::DICTIONARIES[self::$language][$key] ?? null;
            if (null !== $entry) {
                return $entry;
            }
        }

        return self::EN[$key] ?? $key;
    }
}
