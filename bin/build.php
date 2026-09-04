#!/usr/bin/env php
<?php
/**
 * H3PHP — Build Helper
 *
 * Helper script for building the standalone binary via TypePHP.
 * Usage:
 *   php bin/build.php              # Build with defaults
 *   php bin/build.php --optimize   # Build with optimization
 *   php bin/build.php --run        # Build and run
 */

require __DIR__ . '/bootstrap.php';

$optimize = in_array('--optimize', $argv, true);
$run = in_array('--run', $argv, true);
$jobs = 8;

// Find TypePHP compiler
$tpc = null;
$possiblePaths = [
    H3PHP_ROOT_PATH . '/vendor/bin/tpc.php',
    H3PHP_ROOT_PATH . '/vendor/swoole/typephp/bin/tpc.php',
    dirname(PHP_BINARY) . '/tpc.php',
    '/usr/local/bin/tpc.php',
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $tpc = $path;
        break;
    }
}

if ($tpc === null) {
    echo "Error: TypePHP compiler (tpc.php) not found.\n";
    echo "Install via: composer require swoole/typephp\n";
    exit(1);
}

// Build command
$cmd = [
    PHP_BINARY,
    $tpc,
    '-j' . $jobs,
    '-m', 'bin',
    '-o', H3PHP_ROOT_PATH . '/h3php',
];

if ($optimize) {
    $cmd[] = '-O2';
}

$cmd[] = H3PHP_ROOT_PATH . '/project.yml';

echo "Building H3PHP standalone binary...\n";
echo "Command: " . implode(' ', $cmd) . "\n\n";

// Execute build
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($process)) {
    echo "Error: Failed to start build process\n";
    exit(1);
}

fclose($pipes[0]);

while (!feof($pipes[1])) {
    echo fgets($pipes[1]);
}

$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exitCode = proc_close($process);

if ($exitCode !== 0) {
    echo "\nBuild failed (exit code: {$exitCode})\n";
    if ($stderr) {
        echo "Error output:\n{$stderr}\n";
    }
    exit($exitCode);
}

echo "\nBuild successful!\n";
echo "Binary: " . H3PHP_ROOT_PATH . "/h3php\n";

// Run if requested
if ($run) {
    echo "\nRunning: ./h3php --help\n\n";
    passthru(H3PHP_ROOT_PATH . '/h3php --help');
}
