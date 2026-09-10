/**
 * H3PHP — Windows Console Helper Implementation.
 *
 * Separated from windows_stubs.cc to avoid windows.h / Qt header conflicts.
 * This file is only compiled on Windows builds.
 */

#ifdef _WIN32

#include <windows.h>

// Hide the Windows console window (if any).
void php_qt_win_hide_console() {
    HWND consoleWnd = GetConsoleWindow();
    if (consoleWnd != NULL) {
        ShowWindow(consoleWnd, SW_HIDE);
    }
}

// Show the Windows console window (if one was previously hidden).
void php_qt_win_show_console() {
    HWND consoleWnd = GetConsoleWindow();
    if (consoleWnd != NULL) {
        ShowWindow(consoleWnd, SW_SHOW);
    }
}

#endif // _WIN32
