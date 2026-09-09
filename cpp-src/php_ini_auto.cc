/**
 * php_ini_auto.cc — Auto-locate php.ini from exe directory
 *
 * On Windows, the embed SAPI defaults extension_dir to "C:\php\ext" (hardcoded
 * at compile time).  When no php.ini is found, PHP emits:
 *   "PHP Request Startup: 系统找不到指定的路径。"
 *
 * This file sets php_embed_module.php_ini_path_override BEFORE main() runs,
 * so the embed SAPI will load php.ini from the exe's directory if present.
 *
 * Mechanism: a __declspec(thread) initializer runs before main() on MSVC.
 * We also use a static constructor as fallback.
 */

#include <phpx.h>
#include <windows.h>
#include <string>
#include <cstring>

BEGIN_EXTERN_C()
#include "sapi/embed/php_embed.h"
END_EXTERN_C()

// C++ static constructor — guaranteed to run before main() on MSVC
struct PhpIniAutoInit {
    PhpIniAutoInit() {
        // Get the full path of the running executable
        char exePath[MAX_PATH];
        DWORD len = GetModuleFileNameA(nullptr, exePath, MAX_PATH);
        if (len == 0 || len >= MAX_PATH) {
            return;  // Cannot determine exe path, skip
        }

        // Find the last backslash to get the directory
        char* lastSlash = strrchr(exePath, '\\');
        if (!lastSlash) {
            return;
        }

        // Construct php.ini path: <exe_dir>\php.ini
        // We store in static buffer so it persists
        static char iniPath[MAX_PATH];
        size_t dirLen = (size_t)(lastSlash - exePath + 1);  // include trailing backslash
        memcpy(iniPath, exePath, dirLen);
        memcpy(iniPath + dirLen, "php.ini", 8);  // "php.ini" + null terminator

        // Check if the file exists
        DWORD attrib = GetFileAttributesA(iniPath);
        if (attrib == INVALID_FILE_ATTRIBUTES || (attrib & FILE_ATTRIBUTE_DIRECTORY)) {
            return;  // php.ini not found in exe directory, skip
        }

        // Set the override path — embed SAPI will use this instead of defaults
        php_embed_module.php_ini_path_override = iniPath;
    }
};

// Static instance — constructor runs before main()
static PhpIniAutoInit s_phpIniAutoInit;
