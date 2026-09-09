@echo off
REM H3PHP — Windows Build Script
REM
REM Builds H3PHP as a standalone Windows executable using TypePHP + MSVC + Qt 6.
REM Prerequisites:
REM   - Visual Studio 2022 (MSVC v143)
REM   - Qt 6.8.0 (msvc2022_64)
REM   - PHP 8.4+ (for TypePHP compiler)
REM   - tpc (TypePHP AOT compiler) on PATH
REM
REM Usage:
REM   build_windows.bat [H3_C_DIR]
REM
REM Environment variables:
REM   QT_DIR  — Qt installation path (default: C:\Qt\6.8.0\msvc2022_64)
REM   H3_C_DIR — Path to H3 C library (optional)

setlocal enabledelayedexpansion

echo ========================================
echo H3PHP — Windows Build
echo ========================================

REM === Configuration ===
if "%QT_DIR%"=="" set QT_DIR=D:\tools\Qt\6.9.3\msvc2022_64
if not "%~1"=="" set H3_C_DIR=%~1

REM === Verify Qt ===
if not exist "%QT_DIR%\bin\Qt6Widgets.dll" (
    echo ERROR: Qt 6 not found at %QT_DIR%
    echo Install Qt 6.8.0 or set QT_DIR environment variable.
    exit /b 1
)

echo Qt found: %QT_DIR%

REM === Setup MSVC environment ===
echo Setting up MSVC environment...
if exist "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" (
    call "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat"
) else if exist "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat" (
    call "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat"
) else (
    echo ERROR: vcvars64.bat not found. Install Visual Studio 2022.
    exit /b 1
)
if %errorlevel% neq 0 (
    echo ERROR: Failed to setup MSVC environment.
    exit /b 1
)

REM === Generate platform-specific project.yml ===
echo Generating project_windows.yml...

echo name: h3php > project_windows.yml
echo build-mode: bin >> project_windows.yml
echo version: 0.1.0 >> project_windows.yml
echo cxx-std: c++17 >> project_windows.yml
echo. >> project_windows.yml
echo sources: >> project_windows.yml
echo   - php-src >> project_windows.yml
echo   - cpp-src >> project_windows.yml
echo. >> project_windows.yml
echo cxx-flags: ^| >> project_windows.yml
echo   -DQT_WIDGETS_LIB >> project_windows.yml
echo   -DQT_GUI_LIB >> project_windows.yml
echo   -DQT_CORE_LIB >> project_windows.yml
echo   /Zc:__cplusplus >> project_windows.yml
echo   /permissive- >> project_windows.yml
echo   /DNDEBUG >> project_windows.yml
echo   -I%QT_DIR%\include >> project_windows.yml
echo   -I%QT_DIR%\include\QtWidgets >> project_windows.yml
echo   -I%QT_DIR%\include\QtGui >> project_windows.yml
echo   -I%QT_DIR%\include\QtCore >> project_windows.yml
echo   -Imetal-cpp-main >> project_windows.yml
echo. >> project_windows.yml
echo ld-flags: ^| >> project_windows.yml
echo   %QT_DIR%\lib\Qt6Widgets.lib >> project_windows.yml
echo   %QT_DIR%\lib\Qt6Gui.lib >> project_windows.yml
echo   %QT_DIR%\lib\Qt6Core.lib >> project_windows.yml
echo. >> project_windows.yml
echo ignore: >> project_windows.yml
echo   - ./php-src/Testing >> project_windows.yml
echo   - ./cpp-src/h3_native.mm >> project_windows.yml
echo   - ./cpp-src/metal.mm >> project_windows.yml
echo   - ./cpp-src/metal_native.mm >> project_windows.yml

REM === Build with TypePHP ===
echo.
echo Building with TypePHP...

if not "%H3_C_DIR%"=="" (
    set H3_FLAGS=-I%H3_C_DIR%\include -L%H3_C_DIR%\lib -l h3
    tpc.exe project_windows.yml %H3_FLAGS%
) else (
    tpc.exe project_windows.yml
)

if %errorlevel% neq 0 (
    echo.
    echo ERROR: Build failed!
    exit /b 1
)

REM === Deploy Qt dependencies ===
echo.
echo Deploying Qt dependencies...

if exist "%QT_DIR%\bin\windeployqt.exe" (
    "%QT_DIR%\bin\windeployqt.exe" --release h3php.exe
) else (
    echo WARNING: windeployqt.exe not found. Manual Qt deployment required.
    echo Copy these DLLs next to h3php.exe:
    echo   - Qt6Widgets.dll
    echo   - Qt6Gui.dll
    echo   - Qt6Core.dll
    echo   - platforms/qwindows.dll
)

echo.
echo ========================================
echo Build complete: h3php.exe
echo ========================================

endlocal
