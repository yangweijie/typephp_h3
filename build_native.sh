#!/bin/bash
# Build Metal native layer separately, then link with TypePHP

set -e

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

# Use PHP 8.4 (has libphp for linking)
PHP_BIN="/opt/homebrew/opt/php@8.4/bin/php"
PHP_CONFIG="/opt/homebrew/opt/php@8.4/bin/php-config"

if [ ! -x "$PHP_BIN" ]; then
    echo "ERROR: PHP 8.4 not found at ${PHP_BIN}"
    echo "Install with: brew install php@8.4"
    exit 1
fi

PHP_INCLUDES=$($PHP_CONFIG --includes)
PHP_LIB_DIR=$($PHP_CONFIG --prefix)/lib

echo "=== PHP Version: $($PHP_BIN --version | head -n 1) ==="

echo "=== Compiling metal_native.mm ==="
clang++ -std=c++17 -c \
    -Ivendor/swoole/phpx/include \
    ${PHP_INCLUDES} \
    -framework Metal -framework MetalKit -framework Foundation -framework Accelerate \
    cpp-src/metal_native.mm -o cpp-src/metal_native.o

echo "=== Compiling h3_native.mm ==="
H3_C_DIR="/Volumes/data/git/c/h3.c"
if [ ! -f "${H3_C_DIR}/libh3.a" ]; then
    echo "ERROR: libh3.a not found. Build it first:"
    echo "  cd ${H3_C_DIR} && make libh3.a"
    exit 1
fi

clang++ -std=c++17 -c \
    -Ivendor/swoole/phpx/include \
    -I"${H3_C_DIR}" \
    ${PHP_INCLUDES} \
    -framework Metal -framework MetalKit -framework Foundation -framework Accelerate \
    -framework MetalPerformanceShaders -framework MetalPerformanceShadersGraph \
    cpp-src/h3_native.mm -o cpp-src/h3_native.o

echo "=== Building PHP project ==="
$PHP_BIN vendor/bin/tpc.php project.yml

echo "=== Done ==="
echo "Binary: ./h3php"
echo "Test: ./h3php -d /path/to/model --info"
