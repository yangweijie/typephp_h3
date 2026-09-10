<?php

/**
 * H3PHP — Main Orchestration.
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
use H3Php\Generator\Params;
use H3Php\Generator\ReferenceToVideo;
use H3Php\Generator\TextToVideo;
use H3Php\Gui\GuiApp;

/**
 * Main entry point.
 *
 * @param int   $argc Argument count
 * @param array $args Argument vector
 */
function main(int $argc = 0, array $args = []): void
{
    // Bootstrap constants. In dev mode these are defined by bin/bootstrap.php
    // (which is not compiled into the standalone binary), so define them here
    // for bin builds. Guarded so dev mode (bootstrap defines first) is unaffected.
    if (!defined('H3PHP_VERSION')) {
        define('H3PHP_VERSION', '0.1.0');
    }
    if (!defined('H3PHP_OS_FAMILY')) {
        define('H3PHP_OS_FAMILY', PHP_OS_FAMILY);
    }
    if (!defined('H3PHP_IS_MACOS')) {
        define('H3PHP_IS_MACOS', PHP_OS_FAMILY === 'Darwin');
    }

    // tpc's bin-mode entry calls main() with no arguments; fall back to $argv global.
    // (Parameter is named $args, not $argv: TypePHP hoists `global $argv` into
    // function scope, which would redeclare a `$argv` parameter in the C++ output.)
    if ($args === []) {
        global $argv;
        $args = $argv;
        $argc = count($args);
    }

    try {
        $app = new Application();
        $app->parse($argc, $args);

        $mode = $app->getMode();

        // Determine whether the user explicitly asked for a mode.
        // If no mode-specific flags are given, default to GUI.
        // Explicit mode flags: --gui, --help, --info, --prompt (oneshot),
        // --search, --download-preset.
        $explicitMode = $app->flag('gui')
            || $app->flag('help')
            || $app->flag('info')
            || $app->has('prompt')
            || $app->has('search')
            || $app->has('download-preset')
            || $app->flag('test-node-editor');

        if (!$explicitMode) {
            // Default to GUI: no args, or only -d/--model-dir given.
            $mode = 'gui';
        }

        switch ($mode) {
            case 'help':
                $app->showHelp();

                return; // showHelp exits, but for type safety

            case 'info':
                executeInfoMode($app);

                return;

            case 'oneshot':
                executeOneShotMode($app);

                return;

            case 'search':
                $app->runSearch();

                return;

            case 'download-preset':
                executeDownloadPresetMode($app);

                return;

            case 'interactive':
                executeInteractiveMode($app);

                return;

            case 'test-node-editor':
                executeTestNodeEditor($app);

                return;

            case 'gui':
                executeGuiMode($app);

                return;

            default:
                $app->error("Unknown execution mode: {$mode}", 2);

                return;
        }
    } catch (H3Php\Cli\Exception $e) {
        // Error message already written to stderr by Application::error();
        // just exit cleanly with the code. In dev mode bin/h3php.php also
        // catches this, but the AOT binary has no outer wrapper.
        exit($e->getExitCode());
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

    // Load model via C library for device + inventory info
    $handle = h3_model_load($modelDir);
    if ($handle < 0) {
        $app->error("Failed to load model: " . h3_get_last_error(), 2);
    }

    // Device info from C library
    $app->header('Device Information:');
    $deviceName = h3_model_get_device_name($handle);
    $app->info("  {$deviceName}");
    $app->out('');

    // Model inventory from C library
    $app->header('Model Directory Inventory:');
    $modelInfo = h3_model_get_info($handle);
    $app->out($modelInfo);
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

    h3_model_free($handle);
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

    // tpc requires a variable to keep a single concrete type, so the two
    // generator classes are bound to distinct variables (one per branch).
    if ($totalRefs > 0) {
        // Reference-to-Video mode
        $refGenerator = new ReferenceToVideo($app);
        $success = $refGenerator->generate($prompt, $params);
        $refGenerator->free();
    } else {
        // Text-to-Video mode
        $textGenerator = new TextToVideo($app);
        $success = $textGenerator->generate($prompt, $params);
        $textGenerator->free();
    }

    if (!$success) {
        $app->error('Generation failed', 1);
    }
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

/**
 * Test mode: directly open Node Editor to verify it doesn't crash.
 * Usage: h3php --test-node-editor
 */
function executeTestNodeEditor(Application $app): void
{
    fprintf(STDOUT, "=== Node Editor Test Mode ===\n");

    $gui = new GuiApp();
    $gui->runNodeEditorTest();
    exit(0);
}

/**
 * GUI mode: Qt widgets interface.
 *
 * Usage:
 *   h3php                     → launch GUI (default)
 *   h3php --gui               → launch GUI (explicit)
 *   h3php --gui -d MODEL_DIR  → launch GUI with pre-filled model dir
 */
function executeGuiMode(Application $app): void
{
    // Store model-dir if provided via CLI
    $modelDir = $app->get('model-dir');

    $gui = new GuiApp();

    // If model dir was provided via CLI, pre-populate it
    if ($modelDir && is_dir($modelDir)) {
        $gui->setModelDir($modelDir);
    }

    $exitCode = $gui->run();
    exit($exitCode);
}

/**
 * Download preset mode: detect hardware and download recommended models.
 *
 * Usage:
 *   h3php --download-preset                  → auto-recommend + download
 *   h3php --download-preset recommended      → auto-recommend + download
 *   h3php --download-preset minimal          → download minimal preset
 *   h3php --download-preset standard         → download standard preset
 *   h3php --download-preset full             → download full preset
 */
function executeDownloadPresetMode(Application $app): void
{
    $presetId = $app->get('download-preset') ?: 'recommended';
    $downloadDir = getenv('HOME') . '/h3php/models';

    $app->header('H3PHP Model Download — Preset: ' . $presetId);
    $app->out('');

    // Step 1: detect environment
    $app->info('Detecting hardware...');
    $env = new \H3Php\Core\EnvironmentDetector();
    $gpu = $env->detectGpu();
    $mem = $env->detectMemory();
    $disk = $env->detectDiskSpace();

    $app->out(sprintf(
        '  GPU: %s (%d MB VRAM)',
        $gpu['name'] ?? 'Unknown',
        $gpu['vram_mb'] ?? 0
    ));
    $app->out(sprintf(
        '  RAM: %.1f GB',
        ($mem['total_mb'] ?? 0) / 1024
    ));
    $app->out(sprintf(
        '  Disk free: %.1f GB',
        ($disk['free_bytes'] ?? 0) / (1024 ** 3)
    ));
    $app->out('');

    // Step 2: resolve preset
    $downloadManager = new \H3Php\Core\DownloadManager($downloadDir);

    if ('recommended' === $presetId || '' === $presetId) {
        $app->info('Recommending preset based on hardware...');
        $rec = $downloadManager->getRecommendation($env);
        $preset = $rec['preset'];
        $app->out('  ' . $rec['reason']);
        if (!empty($rec['warnings'])) {
            foreach ($rec['warnings'] as $w) {
                $app->warning('  ⚠ ' . $w);
            }
        }
        $app->out('');
    } else {
        $presets = \H3Php\Core\DownloadPreset::allPresets();
        if (!isset($presets[$presetId])) {
            $app->error("Unknown preset: {$presetId}. Valid: minimal, standard, full, recommended", 2);
        }
        $preset = $presets[$presetId];
    }

    // Step 3: show preset details
    $app->header('Selected preset: ' . $preset->label);
    $app->out('  ' . $preset->description);
    $app->out(sprintf('  Components (%d):', count($preset->components)));
    foreach ($preset->components as $c) {
        $app->out(sprintf('    • %s (%s)', $c->name, $c->getFormattedSize()));
    }
    $app->out(sprintf('  Total size: %.1f GB', $preset->totalSizeGb));
    $app->out(sprintf('  Requires: %.1f GB VRAM, %.1f GB RAM', $preset->requiredVramGb, $preset->requiredRamGb));
    $app->out('');

    // Step 4: recommend source
    $sourceRec = $downloadManager->recommendSource($env);
    $source = $sourceRec['source'];
    $app->info('Download source: ' . $sourceRec['reason']);
    $app->out('');

    // Step 5: start download
    $app->info("Downloading to: {$downloadDir}");
    $app->out('');

    $results = $downloadManager->downloadPreset(
        $preset,
        $downloadDir,
        $source,
        function (string $componentId, float $percent, string $msg) use ($app) {
            $bar = str_repeat('█', (int) ($percent / 5)) . str_repeat('░', 20 - (int) ($percent / 5));
            $app->out(sprintf("  [%s] %s %s — %.0f%%", $componentId, $bar, $msg, $percent));
        }
    );

    // Step 6: summary
    $app->out('');
    $app->header('Download complete:');
    $allOk = true;
    foreach ($results as $id => $ok) {
        if ($ok) {
            $app->success("  ✓ {$id}");
        } else {
            $app->error("  ✗ {$id} (failed)");
            $allOk = false;
        }
    }

    if (!$allOk) {
        exit(1);
    }
}
