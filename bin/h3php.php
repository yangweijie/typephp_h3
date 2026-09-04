<?php

/**
 * H3PHP — MiniMax-H3 Video Generation Engine (PHP CLI).
 *
 * Main entry point. Registered as composer bin.
 * Usage:
 *   h3php -d MODEL_DIR -p "prompt" [options]     # one-shot generation
 *   h3php -d MODEL_DIR [options]                  # interactive session
 *   h3php -d MODEL_DIR --info                     # device + model info
 *   h3php --help                                  # show usage
 */

require __DIR__ . '/bootstrap.php';

require H3PHP_ROOT_PATH . '/php-src/main.php';

const H3PHP_PHP_SCRIPT_ENTRY = true;

try {
    /* @var array<int, string> $argv */
    /* @var int<1, max> $argc */
    main($argc, $argv);
} catch (H3Php\Cli\Exception $e) {
    // Application::error() throws for testability;
    // here we actually exit with the error code.
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit($e->getExitCode());
} catch (Exception $e) {
    fwrite(STDERR, "Unexpected error: " . $e->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Fatal error: " . $e->getMessage() . PHP_EOL);
    exit(255);
}
