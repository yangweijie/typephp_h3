#!/bin/bash
# Build H3PHP standalone binary via TypePHP
# Usage: ./build_native.sh [H3_C_DIR]
#   H3_C_DIR: Path to libh3.a directory (default: /Volumes/data/git/c/h3.c)

set -e

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

# Path to libh3.a (override via argument or H3_C_DIR env)
H3_C_DIR="${1:-${H3_C_DIR:-/Volumes/data/git/c/h3.c}}"

if [ ! -f "${H3_C_DIR}/libh3.a" ]; then
    echo "ERROR: libh3.a not found at ${H3_C_DIR}"
    echo "Build it first: cd ${H3_C_DIR} && make libh3.a"
    exit 1
fi

echo "=== Building H3PHP ==="
echo "H3_C_DIR: ${H3_C_DIR}"

php vendor/bin/tpc.php project.yml \
    -I "${H3_C_DIR}" \
    -L "${H3_C_DIR}" \
    -l h3 \
    --no-progress

echo "=== Done ==="
echo "Binary: ./h3php"
echo "Test: ./h3php -d /path/to/model --info"
