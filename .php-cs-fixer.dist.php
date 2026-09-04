<?php
/**
 * H3PHP — PHP-CS-Fixer Configuration
 *
 * Follows aot-compiler's code style: PSR-2 + Symfony rules.
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/php-src')
    ->in(__DIR__ . '/bin')
    ->in(__DIR__ . '/tests')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_quote' => false,
        'concat_space' => ['spacing' => 'one'],
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_align' => true,
        'phpdoc_order' => true,
    ])
    ->setFinder($finder);
