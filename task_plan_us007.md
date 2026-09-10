# US-007 Fix: Node Editor Crash (Dangling Pointer)

## Goal
Fix the crash when opening Tools → Node Editor in the H3PHP GUI.

## Root Cause
`NodeGraphicsItem` stores `NodeInfo* info` pointing to a **stack-allocated** `NodeInfo info` in `php_qt_node_canvas_add_node()`. When the function returns, the stack variable is destroyed → **dangling pointer** → crash on first `paint()` / `boundingRect()` access.

## Fix Plan

### Phase 1: Fix NodeGraphicsItem (DONE in previous session)
- [x] Change `NodeInfo* info` → `NodeInfo info` (store by value)
- [x] Change constructor from `NodeGraphicsItem(NodeInfo* info)` → `NodeGraphicsItem(const NodeInfo& info_)`
- [x] Change `new NodeGraphicsItem(&info)` → `new NodeGraphicsItem(info)`
- [x] Change all `info->` → `info.` in member access
- [x] Add `static_cast<int>()` for `size_t` → `int` conversions
- [x] Fix `QPainter::drawText()` argument order

### Phase 2: Verify build compiles
- [ ] Build with `build_windows.bat` or `powershell -Command "cmd.exe /c build_direct.bat"`
- [ ] Confirm no compilation errors

### Phase 3: Runtime test
- [ ] Launch GUI: `h3php.exe`
- [ ] Click Tools → Node Editor
- [ ] Verify Node Editor window appears with 5 nodes and 6 connections
- [ ] Verify nodes render with correct titles and ports
- [ ] No crash

### Phase 4: Edge case testing
- [ ] Open Node Editor multiple times
- [ ] Close and reopen Node Editor
- [ ] Verify events work (node_moved, etc.)

## Errors Encountered
| Error | Attempt | Resolution |
|-------|---------|------------|
| C2819: no `operator->` for NodeInfo | 1 | Changed `info->` to `info.` everywhere |
| C2672: `std::max` no overload | 1 | Added `static_cast<int>()` for `.size()` |
| C2665: `QPainter::drawText` args | 1 | Used `QTextOption` overload: `drawText(rect, text, QTextOption(align))` |

## Files Modified
- `cpp-src/qt_node_editor.cc` - NodeGraphicsItem class
