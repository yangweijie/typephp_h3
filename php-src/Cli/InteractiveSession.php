<?php
/**
 * H3PHP — Interactive Session
 *
 * REPL mode with !commands, matching h3.c's h3_cli.c functionality.
 *
 * Commands:
 *   !help                    Show all commands
 *   !status                  Show current settings
 *   !quit / !exit            Exit session
 *   !seed [N|random]         Set/show seed
 *   !steps [N]               Set/show denoising steps
 *   !reuse [N]               Set/show denoiser reuse
 *   !layers [N]              Set/show active DiT blocks
 *   !core-reuse [N]          Set/show core refresh interval
 *   !size [WxH]              Set/show output size
 *   !render-size [WxH|native] Set/show internal render size
 *   !frames [N]              Set/show requested frames
 *   !seconds [N]             Set/show duration
 *   !token-reduction [on/off] Toggle token reduction
 *   !ssd-streaming [on/off]  Toggle SSD streaming
 *   !int8-row-fc2 [on/off]   Toggle int8 FC2
 *   !reference-rope [on/off] Toggle reference RoPE
 *   !first [PATH|clear]      Set/show/clear first frame
 *   !last [PATH|clear]       Set/show/clear last frame
 *   !ref-image PATH          Append ordered image reference
 *   !refs [clear]            List or clear references
 *   !ref-remove N            Remove ordered reference N
 *   !show [on/off]           Toggle denoising previews
 *   !zoom N                  Set terminal zoom
 *   !open [on/off]           Toggle opening completed videos
 *   !output [DIR]            Set/show output directory
 *   !save [PATH]             Copy last generated video
 *   !sr [on/off|...]         Super-resolution settings
 *   !again                   Repeat last prompt
 *   !cache                   Show cache state
 *   !cache clear             Clear caches
 *   !memory-plan [auto|off]  Show/control memory plan
 */

namespace H3Php\Cli;

use H3Php\Core\H3Context;

class InteractiveSession
{
    private Application $app;
    private H3Context $context;
    private ProgressDisplay $progress;

    /** Current settings (mirrors h3_params) */
    private array $settings = [];

    /** Ordered references */
    private array $references = [];

    /** Last prompt for !again */
    private ?string $lastPrompt = null;

    /** Output directory */
    private string $outputDir = '';

    /** Generation counter for auto-naming */
    private int $generationCount = 0;

    /** Show previews flag */
    private bool $showPreviews = false;

    /** Terminal zoom */
    private int $zoom = 2;

    /** Auto-open completed videos */
    private bool $autoOpen = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->progress = new ProgressDisplay();
        $this->context = new H3Context($app->get('model-dir'), $app);
        $this->resetSettings();
    }

    /**
     * Reset settings to defaults from CLI options.
     */
    private function resetSettings(): void
    {
        $this->settings = [
            'width' => $this->app->get('width'),
            'height' => $this->app->get('height'),
            'render_width' => $this->app->get('render-width'),
            'render_height' => $this->app->get('render-height'),
            'frames' => $this->app->get('frames'),
            'seconds' => $this->app->get('seconds'),
            'steps' => $this->app->get('steps'),
            'reuse' => $this->app->get('reuse'),
            'layers' => $this->app->get('layers'),
            'core_reuse' => $this->app->get('core-reuse'),
            'seed' => null, // random by default in interactive mode
            'token_reduction' => $this->app->get('token-reduction'),
            'ssd_streaming' => $this->app->get('ssd-streaming'),
            'int8_row_fc2' => $this->app->get('use-int8-row-fc2'),
            'reference_rope' => $this->app->get('use-reference-rope'),
            'first_frame' => null,
            'last_frame' => null,
            'lora' => $this->app->get('lora'),
        ];
    }

    /**
     * Run the interactive REPL.
     */
    public function run(): void
    {
        $this->printWelcome();

        $stdin = fopen('php://stdin', 'r');
        if ($stdin === false) {
            $this->app->error('Cannot open stdin for interactive session', 1);
        }

        $this->printStatus();

        while (true) {
            fwrite(STDOUT, 'h3> ');
            $line = fgets($stdin);

            if ($line === false) {
                // EOF (Ctrl+D)
                $this->app->out('');
                break;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Check for !command
            if (str_starts_with($line, '!')) {
                $shouldExit = $this->handleCommand($line);
                if ($shouldExit) {
                    break;
                }
            } else {
                // Treat as prompt for generation
                $this->handleGenerate($line);
            }
        }

        fclose($stdin);
        $this->app->info('Goodbye.');
    }

    /**
     * Print welcome message.
     */
    private function printWelcome(): void
    {
        $this->app->out('');
        $this->app->header('H3 Interactive Mode');
        $this->app->info("Model: {$this->context->getModelDir()}");
        $this->app->info('Outputs: ' . ($this->outputDir ?: 'auto'));
        $this->app->out('Type a prompt to generate video; !help lists commands.');
        $this->app->out('');
    }

    /**
     * Handle a !command. Returns true if session should exit.
     */
    private function handleCommand(string $line): bool
    {
        $parts = preg_split('/\s+/', $line, 2);
        $cmd = $parts[0];
        $args = $parts[1] ?? '';

        switch ($cmd) {
            case '!help':
                $this->printHelp();
                break;
            case '!status':
                $this->printStatus();
                break;
            case '!quit':
            case '!exit':
                return true;

            case '!seed':
                $this->cmdSeed($args);
                break;
            case '!steps':
                $this->cmdSteps($args);
                break;
            case '!reuse':
                $this->cmdReuse($args);
                break;
            case '!layers':
                $this->cmdLayers($args);
                break;
            case '!core-reuse':
                $this->cmdCoreReuse($args);
                break;
            case '!size':
                $this->cmdSize($args);
                break;
            case '!render-size':
                $this->cmdRenderSize($args);
                break;
            case '!frames':
                $this->cmdFrames($args);
                break;
            case '!seconds':
                $this->cmdSeconds($args);
                break;

            case '!token-reduction':
                $this->cmdToggle('token_reduction', $args, 'Token reduction');
                break;
            case '!ssd-streaming':
                $this->cmdToggle('ssd_streaming', $args, 'SSD streaming');
                break;
            case '!int8-row-fc2':
                $this->cmdToggle('int8_row_fc2', $args, 'int8 row FC2');
                break;
            case '!reference-rope':
                $this->cmdToggle('reference_rope', $args, 'Reference RoPE');
                break;

            case '!first':
                $this->cmdFirstFrame($args);
                break;
            case '!last':
                $this->cmdLastFrame($args);
                break;
            case '!ref-image':
                $this->cmdRefImage($args);
                break;
            case '!refs':
                $this->cmdRefs($args);
                break;
            case '!ref-remove':
                $this->cmdRefRemove($args);
                break;

            case '!show':
                $this->cmdToggle('show', $args, 'Show previews');
                break;
            case '!zoom':
                $this->cmdZoom($args);
                break;
            case '!open':
                $this->cmdToggle('auto_open', $args, 'Auto-open');
                break;
            case '!output':
                $this->cmdOutput($args);
                break;
            case '!save':
                $this->cmdSave($args);
                break;

            case '!sr':
                $this->cmdSr($args);
                break;
            case '!again':
                $this->cmdAgain();
                break;
            case '!cache':
                $this->cmdCache($args);
                break;
            case '!memory-plan':
                $this->cmdMemoryPlan($args);
                break;

            default:
                $this->app->warning("Unknown command: {$cmd}. Type !help for available commands.");
        }

        return false;
    }

    /**
     * Handle a generation prompt.
     */
    private function handleGenerate(string $prompt): void
    {
        $this->lastPrompt = $prompt;
        $this->generationCount++;

        $seed = $this->settings['seed'] ?? random_int(0, PHP_INT_MAX);
        $this->app->info("Seed: {$seed}");
        $this->app->out('');

        // TODO: Actual generation pipeline
        $this->progress->update('load', 0, 1);
        $this->progress->warning('Generation pipeline not yet implemented (Phase 1 skeleton)');
        $this->progress->finish();

        $outputPath = $this->getOutputPath();
        $this->app->out('');
        $this->app->success("Done -> {$outputPath} [pending]");
    }

    /**
     * Get the output path for the next generation.
     */
    private function getOutputPath(): string
    {
        if ($this->outputDir) {
            return $this->outputDir . DIRECTORY_SEPARATOR . sprintf('video-%04d.mp4', $this->generationCount);
        }
        return sprintf('outputs/video-%04d.mp4', $this->generationCount);
    }

    // ========================================================================
    // Command Implementations
    // ========================================================================

    private function printHelp(): void
    {
        $commands = [
            ['!help', 'Show this help'],
            ['!status', 'Show current settings'],
            ['!quit / !exit', 'Exit session'],
            ['', ''],
            ['!seed [N|random]', 'Set/show seed'],
            ['!steps [N]', 'Denoising steps (1-1000)'],
            ['!reuse [N]', 'Denoiser reuse (1-32)'],
            ['!layers [N]', 'Active DiT blocks (35-50)'],
            ['!core-reuse [N]', 'Core refresh (1-6)'],
            ['', ''],
            ['!size [WxH]', 'Output size'],
            ['!render-size [WxH|native]', 'Internal render size'],
            ['!frames [N]', 'Requested frames'],
            ['!seconds [N]', 'Duration at 24fps'],
            ['', ''],
            ['!token-reduction [on/off]', 'Toggle token reduction'],
            ['!ssd-streaming [on/off]', 'Toggle SSD streaming'],
            ['!int8-row-fc2 [on/off]', 'Toggle int8 FC2'],
            ['!reference-rope [on/off]', 'Toggle reference RoPE'],
            ['', ''],
            ['!first [PATH|clear]', 'Set/show/clear first frame'],
            ['!last [PATH|clear]', 'Set/show/clear last frame'],
            ['!ref-image PATH', 'Append image reference'],
            ['!refs [clear]', 'List/clear references'],
            ['!ref-remove N', 'Remove reference N'],
            ['', ''],
            ['!show [on/off]', 'Toggle previews'],
            ['!zoom N', 'Terminal zoom'],
            ['!open [on/off]', 'Toggle auto-open'],
            ['!output [DIR]', 'Set/show output directory'],
            ['!save [PATH]', 'Copy last video'],
            ['', ''],
            ['!sr [on/off|...]', 'Super-resolution settings'],
            ['!again', 'Repeat last prompt'],
            ['!cache [clear]', 'Show/clear cache'],
            ['!memory-plan [auto|off]', 'Memory plan'],
        ];

        $this->app->header('Interactive Commands:');
        foreach ($commands as [$cmd, $desc]) {
            if ($cmd === '') {
                $this->app->out('');
            } else {
                $padding = str_pad("  {$cmd}", 35);
                $this->app->out("{$padding}{$desc}");
            }
        }
    }

    private function printStatus(): void
    {
        $s = $this->settings;
        $renderStr = ($s['render_width'] > 0)
            ? " (render {$s['render_width']}x{$s['render_height']})"
            : '';

        $this->app->header('Current Settings:');
        $this->app->out("  Size: {$s['width']}x{$s['height']}{$renderStr}");
        $this->app->out("  Frames: {$s['frames']} requested");
        $this->app->out("  Steps: {$s['steps']} | reuse: {$s['reuse']} | layers: {$s['layers']} | core reuse: {$s['core_reuse']}");
        $this->app->out("  Tokens: " . ($s['token_reduction'] ? 'reduced' : 'full'));
        $this->app->out("  Weights: " . ($s['ssd_streaming'] ? 'SSD BF16' : 'resident'));
        $this->app->out("  FC2: " . ($s['int8_row_fc2'] ? 'int8 row' : 'BF16'));
        $this->app->out("  Seed: " . ($s['seed'] ?? 'random'));
        $this->app->out("  First: " . ($s['first_frame'] ?? 'none'));
        $this->app->out("  Last: " . ($s['last_frame'] ?? 'none'));
        $this->app->out("  References: " . count($this->references));
        $this->app->out("  Show: " . ($this->showPreviews ? 'on' : 'off') . " | zoom: {$this->zoom}");
        $this->app->out("  Output: " . ($this->outputDir ?: 'auto'));
        $this->app->out('');
    }

    private function cmdSeed(string $args): void
    {
        if ($args === '') {
            $this->app->info('Seed: ' . ($this->settings['seed'] ?? 'random'));
        } elseif (strtolower($args) === 'random') {
            $this->settings['seed'] = null;
            $this->app->info('Seed: random');
        } elseif (is_numeric($args) && (int) $args >= 0) {
            $this->settings['seed'] = (int) $args;
            $this->app->info("Seed: {$this->settings['seed']}");
        } else {
            $this->app->warning('Usage: !seed [N|random]');
        }
    }

    private function cmdSteps(string $args): void
    {
        if ($args === '') {
            $this->app->info("Steps: {$this->settings['steps']}");
        } elseif ($this->validateRange($args, 1, 1000)) {
            $this->settings['steps'] = (int) $args;
            $this->app->info("Steps: {$this->settings['steps']}");
        } else {
            $this->app->warning('Steps must be between 1 and 1000');
        }
    }

    private function cmdReuse(string $args): void
    {
        if ($args === '') {
            $this->app->info("Reuse: {$this->settings['reuse']}");
        } elseif ($this->validateRange($args, 1, 32)) {
            $this->settings['reuse'] = (int) $args;
            $this->app->info("Reuse: {$this->settings['reuse']}");
        } else {
            $this->app->warning('Reuse must be between 1 and 32');
        }
    }

    private function cmdLayers(string $args): void
    {
        if ($args === '') {
            $this->app->info("Layers: {$this->settings['layers']}");
        } elseif ($this->validateRange($args, 35, 50)) {
            $this->settings['layers'] = (int) $args;
            $this->app->info("Layers: {$this->settings['layers']}");
        } else {
            $this->app->warning('Layers must be between 35 and 50');
        }
    }

    private function cmdCoreReuse(string $args): void
    {
        if ($args === '') {
            $this->app->info("Core reuse: {$this->settings['core_reuse']}");
        } elseif ($this->validateRange($args, 1, 6)) {
            $this->settings['core_reuse'] = (int) $args;
            $this->app->info("Core reuse: {$this->settings['core_reuse']}");
        } else {
            $this->app->warning('Core reuse must be between 1 and 6');
        }
    }

    private function cmdSize(string $args): void
    {
        if ($args === '') {
            $this->app->info("Size: {$this->settings['width']}x{$this->settings['height']}");
        } elseif (preg_match('/^(\d+)x(\d+)$/', $args, $m)) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            if ($w % 32 === 0 && $h % 32 === 0 && $w * $h <= 768 * 1344) {
                $this->settings['width'] = $w;
                $this->settings['height'] = $h;
                $this->app->info("Size: {$w}x{$h}");
            } else {
                $this->app->warning('Width/height must be multiples of 32, product <= 768*1344');
            }
        } else {
            $this->app->warning('Usage: !size WxH (e.g., !size 864x480)');
        }
    }

    private function cmdRenderSize(string $args): void
    {
        if ($args === '') {
            $rw = $this->settings['render_width'];
            $rh = $this->settings['render_height'];
            $this->app->info("Render size: " . ($rw > 0 ? "{$rw}x{$rh}" : 'native'));
        } elseif (strtolower($args) === 'native') {
            $this->settings['render_width'] = 0;
            $this->settings['render_height'] = 0;
            $this->app->info('Render size: native');
        } elseif (preg_match('/^(\d+)x(\d+)$/', $args, $m)) {
            $this->settings['render_width'] = (int) $m[1];
            $this->settings['render_height'] = (int) $m[2];
            $this->app->info("Render size: {$m[1]}x{$m[2]}");
        } else {
            $this->app->warning('Usage: !render-size WxH|native');
        }
    }

    private function cmdFrames(string $args): void
    {
        if ($args === '') {
            $this->app->info("Frames: {$this->settings['frames']}");
        } elseif ($this->validateRange($args, 22, 362)) {
            $this->settings['frames'] = (int) $args;
            $this->app->info("Frames: {$this->settings['frames']}");
        } else {
            $this->app->warning('Frames must be between 22 and 362');
        }
    }

    private function cmdSeconds(string $args): void
    {
        if ($args === '') {
            $this->app->info("Seconds: {$this->settings['seconds']}");
        } elseif (is_numeric($args) && (float) $args > 0) {
            $seconds = (float) $args;
            // Convert to frames: seconds * 24, then align to 5 + 17*n
            $frames = (int) round($seconds * 24);
            $frames = $this->alignFrames($frames);
            $this->settings['seconds'] = $seconds;
            $this->settings['frames'] = $frames;
            $this->app->info("Seconds: {$seconds} ({$frames} frames)");
        } else {
            $this->app->warning('Usage: !seconds N (positive number)');
        }
    }

    private function cmdToggle(string $key, string $args, string $label): void
    {
        if ($args === '') {
            // Toggle
            $this->settings[$key] = !($this->settings[$key] ?? false);
        } elseif (strtolower($args) === 'on') {
            $this->settings[$key] = true;
        } elseif (strtolower($args) === 'off') {
            $this->settings[$key] = false;
        } else {
            $this->app->warning("Usage: !{$key} [on|off]");
            return;
        }
        $state = $this->settings[$key] ? 'on' : 'off';
        $this->app->info("{$label}: {$state}");
    }

    private function cmdFirstFrame(string $args): void
    {
        if ($args === '') {
            $this->app->info('First frame: ' . ($this->settings['first_frame'] ?? 'none'));
        } elseif (strtolower($args) === 'clear') {
            $this->settings['first_frame'] = null;
            $this->app->info('First frame: cleared');
        } elseif (file_exists($args)) {
            $this->settings['first_frame'] = $args;
            $this->app->info("First frame: {$args}");
        } else {
            $this->app->warning("File not found: {$args}");
        }
    }

    private function cmdLastFrame(string $args): void
    {
        if ($args === '') {
            $this->app->info('Last frame: ' . ($this->settings['last_frame'] ?? 'none'));
        } elseif (strtolower($args) === 'clear') {
            $this->settings['last_frame'] = null;
            $this->app->info('Last frame: cleared');
        } elseif (file_exists($args)) {
            $this->settings['last_frame'] = $args;
            $this->app->info("Last frame: {$args}");
        } else {
            $this->app->warning("File not found: {$args}");
        }
    }

    private function cmdRefImage(string $args): void
    {
        if ($args === '') {
            $this->app->warning('Usage: !ref-image PATH');
            return;
        }
        if (!file_exists($args)) {
            $this->app->warning("File not found: {$args}");
            return;
        }
        if (count($this->references) >= 12) {
            $this->app->warning('Maximum 12 references allowed');
            return;
        }
        $imageCount = count(array_filter($this->references, fn($r) => $r['type'] === 'image'));
        if ($imageCount >= 9) {
            $this->app->warning('Maximum 9 image references allowed');
            return;
        }
        $this->references[] = ['type' => 'image', 'path' => $args];
        $idx = count($this->references);
        $this->app->info("Added image reference {$idx}: {$args} <Picture {$idx}>");
    }

    private function cmdRefs(string $args): void
    {
        if (strtolower($args) === 'clear') {
            $this->references = [];
            $this->app->info('References cleared');
            return;
        }
        if (empty($this->references)) {
            $this->app->info('No references set');
            return;
        }
        $this->app->header('Ordered references:');
        foreach ($this->references as $i => $ref) {
            $n = $i + 1;
            $placeholder = "<{$ref['type']} {$n}>";
            $this->app->out("  {$n}. {$ref['type']} {$placeholder} {$ref['path']}");
        }
    }

    private function cmdRefRemove(string $args): void
    {
        if (!is_numeric($args) || (int) $args < 1 || (int) $args > count($this->references)) {
            $this->app->warning('Usage: !ref-remove N (1-based index)');
            return;
        }
        $idx = (int) $args - 1;
        $removed = array_splice($this->references, $idx, 1);
        $this->app->info("Removed reference: {$removed[0]['path']}");
    }

    private function cmdZoom(string $args): void
    {
        if ($args === '') {
            $this->app->info("Zoom: {$this->zoom}");
        } elseif ($this->validateRange($args, 1, 10)) {
            $this->zoom = (int) $args;
            $this->app->info("Zoom: {$this->zoom}");
        } else {
            $this->app->warning('Zoom must be between 1 and 10');
        }
    }

    private function cmdOutput(string $args): void
    {
        if ($args === '') {
            $this->app->info('Output: ' . ($this->outputDir ?: 'auto'));
        } elseif (is_dir($args) || mkdir($args, 0755, true)) {
            $this->outputDir = $args;
            $this->app->info("Output: {$this->outputDir}");
        } else {
            $this->app->warning("Cannot create directory: {$args}");
        }
    }

    private function cmdSave(string $args): void
    {
        $this->app->warning('Save not yet implemented (requires completed generation)');
    }

    private function cmdSr(string $args): void
    {
        $this->app->warning('Super-resolution settings not yet implemented');
    }

    private function cmdAgain(): void
    {
        if ($this->lastPrompt === null) {
            $this->app->warning('No previous prompt to repeat');
            return;
        }
        $this->app->info("Repeating: {$this->lastPrompt}");
        $this->handleGenerate($this->lastPrompt);
    }

    private function cmdCache(string $args): void
    {
        if (strtolower($args) === 'clear') {
            $this->app->info('Cache cleared (pending implementation)');
        } else {
            $this->app->info('Cache: embeddings 0 (0.0 MiB), DiT empty, video VAE empty');
        }
    }

    private function cmdMemoryPlan(string $args): void
    {
        if (strtolower($args) === 'off') {
            $this->app->info('Auto memory plan: off (manual knobs)');
        } else {
            $plan = $this->context->autoMemoryPlan();
            $this->app->header('Memory Plan:');
            $this->app->out("  Reason: {$plan['reason']}");
            $this->app->out("  SSD streaming: " . ($plan['ssd_streaming'] ? 'yes' : 'no'));
            $this->app->out("  int8 row FC2: " . ($plan['int8_row_fc2'] ? 'yes' : 'no'));
            $this->app->out("  Layers: {$plan['layers']}");
        }
    }

    // ========================================================================
    // Utility Methods
    // ========================================================================

    /**
     * Validate a numeric value is within range.
     */
    private function validateRange(string $value, int $min, int $max): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $n = (int) $value;
        return $n >= $min && $n <= $max;
    }

    /**
     * Align frame count to 5 + 17*n (h3.c frame alignment).
     */
    private function alignFrames(int $frames): int
    {
        if ($frames < 22) {
            return 22;
        }
        if ($frames > 362) {
            return 362;
        }
        // Align to 5 + 17*n
        $aligned = 5 + (int) round(($frames - 5) / 17) * 17;
        return max(22, min(362, $aligned));
    }
}
