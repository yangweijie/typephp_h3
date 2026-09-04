<?php
/**
 * H3PHP — Main Orchestration
 *
 * Top-level entry point. Dispatches to the appropriate execution mode:
 *   - help:       show usage and exit
 *   - info:       probe device + model inventory
 *   - oneshot:    single prompt → video generation
 *   - interactive: REPL session with !commands
 *
 * Follows the pattern from aot-compiler's compiler.php main() function.
 */

use H3Php\Cli\Application;
use H3Php\Cli\InteractiveSession;
use H3Php\Core\ModelLoader;
use H3Php\Generator\Params;
use H3Php\Generator\TextToVideo;
use H3Php\Generator\ReferenceToVideo;

/**
 * Main entry point.
 *
 * @param int $argc Argument count
 * @param array $argv Argument vector
 */
function main(int $argc, array $argv): void
{
    $app = new Application();
    $app->parse($argc, $argv);

    $mode = $app->getMode();

    switch ($mode) {
        case 'help':
            $app->showHelp();
            return; // showHelp exits, but for type safety

        case 'error-no-model':
            $app->error(
                'Missing required option: -d/--model-dir' . PHP_EOL .
                'Usage: h3php -d MODEL_DIR [options]' . PHP_EOL .
                '       h3php --help for full usage',
                2
            );
            return;

        case 'info':
            executeInfoMode($app);
            return;

        case 'oneshot':
            executeOneShotMode($app);
            return;

        case 'interactive':
            executeInteractiveMode($app);
            return;

        default:
            $app->error("Unknown execution mode: {$mode}", 2);
    }
}

/**
 * --info mode: Display device and model information without loading weights.
 */
function executeInfoMode(Application $app): void
{
    $modelDir = $app->get('model-dir');

    // Validate model directory exists
    if (!is_dir($modelDir)) {
        $app->error("Model directory not found: {$modelDir}", 2);
    }

    $app->header("h3php " . H3PHP_VERSION);
    $app->out('');

    // Platform check
    if (!H3PHP_IS_MACOS) {
        $app->warning('Warning: Metal GPU requires macOS Apple Silicon. Current platform: ' . H3PHP_OS_FAMILY);
        $app->out('');
    }

    // Device info (placeholder — will be populated by Metal native layer)
    $app->header('Device Information:');
    if (H3PHP_IS_MACOS) {
        $app->info('  Platform: macOS (Metal capable)');
        // TODO: Query actual Metal device info via native layer
        $app->out('  Device: <pending Metal integration>');
        $app->out('  Architecture: <pending>');
        $app->out('  Physical memory: <pending>');
        $app->out('  Recommended GPU working set: <pending>');
        $app->out('  Max Metal buffer: <pending>');
        $app->out('  Apple GPU family: <pending>');
        $app->out('  Metal 4: <pending>');
        $app->out('  Unified memory: <pending>');
    } else {
        $app->out('  Metal GPU not available on this platform');
    }
    $app->out('');

    // Model inventory
    $app->header('Model Directory Inventory:');
    $loader = new ModelLoader($app);
    $inventory = $loader->scanDirectory($modelDir);

    if (empty($inventory)) {
        $app->warning('  No valid MiniMax-H3 model structure detected');
        $app->out('  Expected: FL2VA/transformer/config.json, FL2VA/tokenizer/tokenizer.json, etc.');
    } else {
        foreach ($inventory as $component => $info) {
            $status = $info['present'] ? '✓' : '✗';
            $detail = $info['present']
                ? "{$info['files']} files, {$info['tensors']} tensors, {$info['size_gib']} GiB"
                : 'not found';
            $app->out("  [{$status}] {$component}: {$detail}");
        }
    }
    $app->out('');

    // Configuration summary
    $app->header('Configuration:');
    $app->out("  Canvas: {$app->get('width')}x{$app->get('height')}");
    $app->out("  Frames: {$app->get('frames')}");
    $app->out("  Steps: {$app->get('steps')}");
    $app->out("  Layers: {$app->get('layers')}");
    $app->out("  Reuse: {$app->get('reuse')}");
    $app->out("  Core reuse: {$app->get('core-reuse')}");
    $app->out("  Seed: {$app->get('seed')}");
    $app->out('');
}

/**
 * One-shot mode: Generate video from a single prompt.
 */
function executeOneShotMode(Application $app): void
{
    $modelDir = $app->get('model-dir');
    $prompt = $app->get('prompt');

    // Validate model directory
    if (!is_dir($modelDir)) {
        $app->error("Model directory not found: {$modelDir}", 2);
    }

    // Platform check
    if (!H3PHP_IS_MACOS) {
        $app->error('Video generation requires macOS Apple Silicon with Metal support', 1);
    }

    // Build params from CLI
    $params = Params::fromApplication($app);

    // Determine generation mode based on references
    $totalRefs = count($params->refImages) + count($params->refVideos) + count($params->refAudios);

    if ($totalRefs > 0) {
        // Reference-to-Video mode
        $generator = new ReferenceToVideo($app);
    } else {
        // Text-to-Video mode
        $generator = new TextToVideo($app);
    }

    // Execute generation
    $success = $generator->generate($prompt, $params);

    if (!$success) {
        $app->error('Generation failed', 1);
    }

    $generator->free();
}

/**
 * Interactive mode: REPL session with !commands.
 */
function executeInteractiveMode(Application $app): void
{
    $modelDir = $app->get('model-dir');

    // Validate model directory
    if (!is_dir($modelDir)) {
        $app->error("Model directory not found: {$modelDir}", 2);
    }

    // Platform check
    if (!H3PHP_IS_MACOS) {
        $app->error('Interactive mode requires macOS Apple Silicon with Metal support', 1);
    }

    $session = new InteractiveSession($app);
    $session->run();
}
