<?php

/**
 * H3PHP — CLI Application.
 *
 * Argument parsing and styled terminal output, implemented natively: the
 * AOT build compiles only php-src/ and cpp-src/, so classes from vendor/
 * (league/climate) are absent from the standalone binary.
 */

namespace H3Php\Cli;

class Application
{
    /**
     * ANSI SGR codes for the output styles used across the CLI.
     *
     * NOTE: constant names must not collide (case-insensitively) with method
     * names — TypePHP mangles both into one lowercase C++ symbol space.
     */
    private const array STYLES = [
        'info' => '94',    // light blue
        'success' => '32', // green
        'warning' => '33', // yellow
        'error' => '31',   // red
        'header' => '1',   // bold
    ];

    /** Parsed arguments */
    private array $parsed = [];

    /**
     * Parse command-line arguments.
     *
     * $argv is the full argument vector, including the script name at index 0
     * (the script name is skipped — do NOT pre-strip it, or the first real
     * option (e.g. -d) would be consumed as the script name).
     */
    public function parse(int $argc, array $argv): self
    {
        $definitions = Options::definitions();

        // Map every accepted token (-d, --model-dir) to its option name.
        $lookup = [];
        foreach ($definitions as $name => $config) {
            if (isset($config['longPrefix'])) {
                $lookup['--' . $config['longPrefix']] = $name;
            }
            if (isset($config['prefix'])) {
                $lookup['-' . $config['prefix']] = $name;
            }
        }

        $tokens = array_slice($argv, 1);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = (string) $tokens[$i];

            if (!str_starts_with($token, '-')) {
                continue;
            }

            // Accept both "--name value" and "--name=value".
            $inline = null;
            $eq = strpos($token, '=');
            if (false !== $eq) {
                $inline = substr($token, $eq + 1);
                $token = substr($token, 0, $eq);
            }

            $name = $lookup[$token] ?? null;
            if (null === $name) {
                continue;
            }

            $config = $definitions[$name];

            if (!empty($config['noValue'])) {
                $this->parsed[$name] = true;

                continue;
            }

            if (null !== $inline) {
                $value = $inline;
            } elseif (isset($tokens[$i + 1])) {
                $value = (string) $tokens[++$i];
            } else {
                continue; // value option with nothing following it
            }

            $value = $this->castValue($value, $config['castTo'] ?? null);

            if (!empty($config['multiple'])) {
                $this->parsed[$name][] = $value;
            } else {
                $this->parsed[$name] = $value;
            }
        }

        return $this;
    }

    /**
     * Cast a raw argument to the type declared in the option schema.
     */
    private function castValue(string $value, ?string $castTo): mixed
    {
        return match ($castTo) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            default => $value,
        };
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
     * Whether ANSI styling should be emitted.
     *
     * Suppressed when stdout is redirected to a pipe or file, so redirected
     * output stays free of escape sequences.
     */
    private function styled(): bool
    {
        return stream_isatty(STDOUT);
    }

    /**
     * Write a line to stdout, optionally wrapped in an ANSI style.
     */
    private function write(string $message, ?string $style = null): void
    {
        if (null !== $style && isset(self::STYLES[$style]) && $this->styled()) {
            $esc = chr(27);
            $message = "{$esc}[" . self::STYLES[$style] . "m{$message}{$esc}[0m";
        }

        echo $message . PHP_EOL;
    }

    /**
     * Output a plain line.
     */
    public function out(string $message): void
    {
        $this->write($message);
    }

    /**
     * Output an info message (light blue).
     */
    public function info(string $message): void
    {
        $this->write($message, 'info');
    }

    /**
     * Output a success message (green).
     */
    public function success(string $message): void
    {
        $this->write($message, 'success');
    }

    /**
     * Output a warning message (yellow).
     */
    public function warning(string $message): void
    {
        $this->write($message, 'warning');
    }

    /**
     * Output a bold header.
     */
    public function header(string $message): void
    {
        $this->write($message, 'header');
    }

    /**
     * Output an error message (red) and throw.
     *
     * Note: Throws instead of exit() for testability.
     * The CLI entry point (bin/h3php.php) catches this and exits.
     *
     * @param string $message Error message
     * @param int    $code    Exit code (used when caught by entry point)
     *
     * @throws Exception Always thrown
     */
    public function error(string $message, int $code = 1): void
    {
        $this->write("Error: {$message}", 'error');
        throw new Exception($message, $code);
    }

    /**
     * Determine the execution mode based on parsed arguments.
     */
    public function getMode(): string
    {
        if ($this->flag('help')) {
            return 'help';
        }
        if ($this->flag('gui')) {
            return 'gui';
        }
        if ($this->has('search')) {
            return 'search';
        }
        if ($this->has('download-preset')) {
            return 'download-preset';
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
     * Execute search mode.
     *
     * Searches model registries and/or ComfyUI plugins by keyword,
     * displays results in a formatted table, and exits.
     */
    public function runSearch(): void
    {
        $query = $this->get('search');
        $type = $this->get('search-type');
        $source = $this->get('search-source');
        $limit = $this->get('search-limit');

        $this->header("H3PHP Model Search — \"{$query}\"");
        $this->out('');

        // Search models
        if ('model' === $type || 'all' === $type) {
            $this->out('Searching model registries...');
            $this->out('');

            $searcher = new \H3Php\Core\ModelSearcher();

            $sources = 'all' === $source
                ? ['huggingface', 'modelscope', 'civitai']
                : explode(',', $source);

            $results = $searcher->searchAll($query, $sources, $limit);

            if (empty($results)) {
                $this->warning('No model results found.');
            } else {
                $this->header(sprintf('Models (%d results):', count($results)));
                $this->displayModelResults($results);
            }

            $this->out('');
        }

        // Search ComfyUI plugins
        if ('comfyui' === $type || 'all' === $type) {
            $this->out('Searching ComfyUI plugins...');
            $this->out('');

            $comfySearcher = new \H3Php\Core\ComfyUISearcher();
            $comfyResults = $comfySearcher->search($query, $limit);

            if (empty($comfyResults)) {
                $this->warning('No ComfyUI plugin results found.');
            } else {
                $this->header(sprintf('ComfyUI Plugins (%d results):', count($comfyResults)));
                $this->displayComfyResults($comfyResults);
            }
        }

        exit(0);
    }

    /**
     * Display model search results in a formatted table.
     *
     * @param \H3Php\Core\SearchResult[] $results
     */
    private function displayModelResults(array $results): void
    {
        $i = 1;
        foreach ($results as $r) {
            $badge = $r->getSourceBadge();
            $downloads = $r->getDownloadsFormatted();
            $desc = substr($r->description, 0, 60);

            $this->out(sprintf(
                '  %2d. [%s] %s (%s DL) %s',
                $i,
                $badge,
                $r->name,
                $downloads,
                $desc ? "— {$desc}" : ''
            ));
            $this->out(sprintf('      %s', $r->url));
            ++$i;
        }
    }

    /**
     * Display ComfyUI plugin search results.
     *
     * @param \H3Php\Core\ComfyNodeResult[] $results
     */
    private function displayComfyResults(array $results): void
    {
        $i = 1;
        foreach ($results as $r) {
            $stars = $r->getStarsFormatted();
            $desc = substr($r->description, 0, 55);

            $this->out(sprintf(
                '  %2d. [🧩 ComfyUI] %s ★%s by %s',
                $i,
                $r->name,
                $stars,
                $r->author
            ));
            if ($desc) {
                $this->out(sprintf('      %s', $desc));
            }
            $this->out(sprintf('      Install: %s', $r->getInstallSummary()));
            ++$i;
        }
    }

    /**
     * Show help message and exit.
     *
     * @throws Exception Always thrown (exits after display)
     */
    public function showHelp(): void
    {
        $this->header("h3php — MiniMax-H3 Video Generation Engine v" . H3PHP_VERSION);
        $this->out('');
        $this->header('Usage:');
        $this->out('  h3php -d MODEL_DIR -p "prompt" [options]     One-shot generation');
        $this->out('  h3php -d MODEL_DIR [options]                  Interactive session');
        $this->out('  h3php -d MODEL_DIR --gui                      Launch GUI mode');
        $this->out('  h3php -d MODEL_DIR --info                     Device + model info');
        $this->out('  h3php --search "keyword"                      Search models/plugins');
        $this->out('  h3php --help                                  Show this help');
        $this->out('');

        foreach (Options::getCategories() as $category => $optionNames) {
            $this->header("{$category}:");
            foreach ($optionNames as $name) {
                $config = Options::definitions()[$name];
                $flag = isset($config['prefix'])
                    ? "-{$config['prefix']}, --{$config['longPrefix']}"
                    : "    --{$config['longPrefix']}";

                $suffix = '';
                if (Options::isFlag($name)) {
                    $suffix = '';
                } elseif (isset($config['castTo']) && 'int' === $config['castTo']) {
                    $suffix = ' <int>';
                } elseif (isset($config['castTo']) && 'float' === $config['castTo']) {
                    $suffix = ' <float>';
                } elseif (!Options::isFlag($name)) {
                    $suffix = ' <value>';
                }

                $default = '';
                if (isset($config['defaultValue']) && !Options::isFlag($name)) {
                    $default = " (default: {$config['defaultValue']})";
                }

                $padding = str_pad("  {$flag}{$suffix}", 40);
                $this->out("{$padding}{$config['description']}{$default}");
            }
            $this->out('');
        }

        exit(0);
    }
}
