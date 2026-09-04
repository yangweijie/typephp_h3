<?php
/**
 * H3PHP — MiniMax-H3 Video Generation Engine (PHP CLI)
 *
 * Bootstrap file: autoloader + constants + runtime detection.
 * Follows the dual entry point pattern from aot-compiler.
 */

// Root path constant
define('H3PHP_ROOT_PATH', dirname(__DIR__, 1));

// Autoloader detection: supports both composer vendor and standalone
if (isset($GLOBALS['_composer_autoload_path'])) {
    require $GLOBALS['_composer_autoload_path'];
} elseif (file_exists(H3PHP_ROOT_PATH . '/vendor/autoload.php')) {
    require H3PHP_ROOT_PATH . '/vendor/autoload.php';
} else {
    // Fallback: minimal PSR-4 autoloader for H3PHP namespace
    spl_autoload_register(function (string $class): void {
        $prefix = 'H3Php\\';
        $baseDir = H3PHP_ROOT_PATH . '/php-src/';

        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
    });
}

// Version constant
define('H3PHP_VERSION', '0.1.0');

// Platform detection
define('H3PHP_OS_FAMILY', PHP_OS_FAMILY);
define('H3PHP_IS_MACOS', PHP_OS_FAMILY === 'Darwin');
