#!/usr/bin/env bash
# H3PHP — Linux Build Script
#
# Builds H3PHP as a standalone Linux executable using TypePHP + gcc + Qt 6.
# Prerequisites:
#   - gcc 11+ (C++17)
#   - Qt 6 development packages (qt6-base-dev)
#   - PHP 8.4+ (for TypePHP compiler)
#   - tpc (TypePHP AOT compiler) on PATH
#
# Usage:
#   ./build_linux.sh [H3_C_DIR]
#
# Environment variables:
#   QT_DIR  — Qt installation path (default: /usr)
#   H3_C_DIR — Path to H3 C library (optional)

set -euo pipefail

echo "========================================"
echo "H3PHP — Linux Build"
echo "========================================"

# === Configuration ===
QT_DIR="${QT_DIR:-/usr}"
H3_C_DIR="${1:-}"

# === Verify Qt ===
if [ ! -d "$QT_DIR/include/x86_64-linux-gnu/qt6" ] && [ ! -d "$QT_DIR/include/QtCore" ]; then
    echo "ERROR: Qt 6 development packages not found."
    echo "Install with: sudo apt install qt6-base-dev"
    echo "Or set QT_DIR to your Qt installation path."
    exit 1
fi

echo "Qt found: $QT_DIR"

# === Verify gcc ===
if ! command -v g++ &> /dev/null; then
    echo "ERROR: g++ not found. Install with: sudo apt install g++"
    exit 1
fi

GCC_VERSION=$(g++ -dumpversion | cut -d. -f1)
echo "GCC version: $GCC_VERSION"

# === Generate platform-specific project.yml ===
echo "Generating project_linux.yml..."

cat > project_linux.yml << EOF
name: h3php
build-mode: bin
version: 0.1.0
cxx-std: c++17

sources:
  - php-src
  - cpp-src

cxx-flags: |
  -DQT_WIDGETS_LIB
  -DQT_GUI_LIB
  -DQT_CORE_LIB
  -I$QT_DIR/include/x86_64-linux-gnu/qt6
  -I$QT_DIR/include/x86_64-linux-gnu/qt6/QtWidgets
  -I$QT_DIR/include/x86_64-linux-gnu/qt6/QtGui
  -I$QT_DIR/include/x86_64-linux-gnu/qt6/QtCore
  -Imetal-cpp-main
  -fPIC

ld-flags: |
  -lQt6Widgets
  -lQt6Gui
  -lQt6Core
  -lpthread
  -ldl

ignore:
  - ./php-src/Testing
EOF

# === Build with TypePHP ===
echo ""
echo "Building with TypePHP..."

if [ -n "$H3_C_DIR" ]; then
    H3_FLAGS="-I$H3_C_DIR/include -L$H3_C_DIR/lib -l h3"
    tpc.php project_linux.yml $H3_FLAGS
else
    tpc.php project_linux.yml
fi

if [ $? -ne 0 ]; then
    echo ""
    echo "ERROR: Build failed!"
    exit 1
fi

# === Strip binary ===
if command -v strip &> /dev/null; then
    echo "Stripping binary..."
    strip h3php
fi

echo ""
echo "========================================"
echo "Build complete: h3php"
echo "========================================"
