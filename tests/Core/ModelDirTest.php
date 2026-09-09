<?php

/**
 * H3PHP — Real model tree tests.
 *
 * These exercise code against an actual MiniMax-H3 model directory. They are
 * skipped unless H3_MODEL_DIR is provided (weights are not vendored):
 *
 *   H3_MODEL_DIR=/path/to/MiniMax-H3 vendor/bin/pest
 */

use H3Php\Core\ModelConfig;

test('parses transformer config from the real model tree', function () {
    skip_without_model_dir();

    $configFile = model_dir() . '/FL2VA/transformer/config.json';
    if (!is_file($configFile)) {
        test()->skip('Model tree has no FL2VA/transformer/config.json');
    }

    $raw = json_decode((string) file_get_contents($configFile), true);
    $cfg = ModelConfig::fromModelDir((string) model_dir());

    expect($cfg->hiddenSize)->toBe($raw['hidden_size']);
    expect($cfg->numHeads)->toBe($raw['num_attention_heads']);
})->skip(fn () => null === model_dir(), 'Set H3_MODEL_DIR to run (needs a real model tree).');
