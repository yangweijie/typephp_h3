<?php
/**
 * H3PHP — CLI Application
 *
 * Wraps league/climate for argument parsing, colored output, and styling.
 * Follows the pattern from aot-compiler's Translator::output().
 */

namespace H3Php\Cli;

use League\CLImate\CLImate;
use H3Php\Cli\Exception;

class Application
{
    private CLImate $climate;

    /** Parsed arguments */
    private array $parsed = [];

    public function __construct()
    {
        $this->climate = new CLImate();
        $this->registerArguments();
    }

    /**
     * Register all CLI arguments from the centralized Options schema.
     */
    private function registerArguments(): void
    {
        $arguments = $this->climate->arguments;

        foreach (Options::ALL as $name => $config) {
            $args = [];

            if (isset($config['prefix'])) {
                $args['prefix'] = $config['prefix'];
            }
            if (isset($config['longPrefix'])) {
                $args['longPrefix'] = $config['longPrefix'];
            }
            if (isset($config['description'])) {
                $args['description'] = $config['description'];
            }
            if (isset($config['castTo'])) {
                $args['castTo'] = $config['castTo'];
            }
            if (isset($config['defaultValue'])) {
                $args['defaultValue'] = $config['defaultValue'];
            }
            if (isset($config['noValue']) && $config['noValue']) {
                $args['noValue'] = true;
            }
            if (isset($config['multiple']) && $config['multiple']) {
                $args['multiple'] = true;
            }

            $arguments->add([$name => $args]);
        }
    }

    /**
     * Parse command-line arguments.
     */
    public function parse(int $argc, array $argv): self
    {
        // Remove script name
        array_shift($argv);

        // Build arguments string for CLImate parser
        $this->climate->arguments->parse($argv);

        // Extract parsed values
        foreach (Options::ALL as $name => $config) {
            $value = $this->climate->arguments->get($name);
            if ($value !== null) {
                $this->parsed[$name] = $value;
            }
        }

        return $this;
    }

    /**
     * Get a parsed argument value.
     */
    public function get(string $name): mixed
    {
        return $this->parsed[$name] ?? Options::getDefault($name);
    }

    /**
     * Check if an argument was explicitly provided.
     */
    public function has(string $name): bool
    {
        return isset($this->parsed[$name]);
    }

    /**
     * Check if a boolean flag is set.
     */
    public function flag(string $name): bool
    {
        return (bool) ($this->parsed[$name] ?? Options::getDefault($name) ?? false);
    }

    /**
     * Get the underlying CLImate instance for direct output.
     */
    public function climate(): CLImate
    {
        return $this->climate;
    }

    /**
     * Output a message with optional style.
     */
    public function output(string $message, string $style = 'out'): void
    {
        $this->climate->{$style}($message);
    }

    /**
     * Output an info message (light blue).
     */
    public function info(string $message): void
    {
        $this->climate->lightBlue($message);
    }

    /**
     * Output a success message (green).
     */
    public function success(string $message): void
    {
        $this->climate->green($message);
    }

    /**
     * Output a warning message (yellow).
     */
    public function warning(string $message): void
    {
        $this->climate->yellow($message);
    }

    /**
     * Output an error message (red) and throw.
     *
     * Note: Throws instead of exit() for testability.
     * The CLI entry point (bin/h3php.php) catches this and exits.
     *
     * @param string $message Error message
     * @param int $code Exit code (used when caught by entry point)
     * @throws \H3Php\Cli\Exception Always thrown
     */
    public function error(string $message, int $code = 1): void
    {
        $this->climate->red("Error: {$message}");
        throw new Exception($message, $code);
    }

    /**
     * Output a bold header.
     */
    public function header(string $message): void
    {
        $this->climate->bold()->out($message);
    }

    /**
     * Output a table.
     */
    public function table(array $data): void
    {
        $this->climate->table($data);
    }

    /**
     * Determine the execution mode based on parsed arguments.
     */
    public function getMode(): string
    {
        if ($this->flag('help')) {
            return 'help';
        }
        if (!$this->has('model-dir')) {
            return 'error-no-model';
        }
        if ($this->flag('info')) {
            return 'info';
        }
        if ($this->has('prompt')) {
            return 'oneshot';
        }
        return 'interactive';
    }

    /**
     * Show help message and exit.
     *
     * @throws \H3Php\Cli\Exception Always thrown (exits after display)
     */
    public function showHelp(): void
    {
        $this->climate->bold()->out("h3php — MiniMax-H3 Video Generation Engine v" . H3PHP_VERSION);
        $this->climate->out('');
        $this->climate->bold()->out('Usage:');
        $this->climate->out('  h3php -d MODEL_DIR -p "prompt" [options]     One-shot generation');
        $this->climate->out('  h3php -d MODEL_DIR [options]                  Interactive session');
        $this->climate->out('  h3php -d MODEL_DIR --info                     Device + model info');
        $this->climate->out('  h3php --help                                  Show this help');
        $this->climate->out('');

        foreach (Options::getCategories() as $category => $optionNames) {
            $this->climate->bold()->out("{$category}:");
            foreach ($optionNames as $name) {
                $config = Options::ALL[$name];
                $flag = isset($config['prefix'])
                    ? "-{$config['prefix']}, --{$config['longPrefix']}"
                    : "    --{$config['longPrefix']}";

                $suffix = '';
                if (Options::isFlag($name)) {
                    $suffix = '';
                } elseif (isset($config['castTo']) && $config['castTo'] === 'int') {
                    $suffix = ' <int>';
                } elseif (isset($config['castTo']) && $config['castTo'] === 'float') {
                    $suffix = ' <float>';
                } elseif (!Options::isFlag($name)) {
                    $suffix = ' <value>';
                }

                $default = '';
                if (isset($config['defaultValue']) && !Options::isFlag($name)) {
                    $default = " (default: {$config['defaultValue']})";
                }

                $padding = str_pad("  {$flag}{$suffix}", 40);
                $this->climate->out("{$padding}{$config['description']}{$default}");
            }
            $this->climate->out('');
        }

        exit(0);
    }
}
