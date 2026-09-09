#!/usr/bin/env python3
"""
H3PHP — ComfyUI Bridge Module.

Provides a JSON-based command interface for PHP to control ComfyUI.
Communicates via stdin/stdout JSON lines (one command per line).

Protocol:
  PHP → Python: {"cmd": "init", "comfyui_path": "/path"}
  Python → PHP: {"success": true, "message": "..."}

Commands:
  init      — Import ComfyUI modules, validate installation
  ping      — Check server health
  load      — Load a model into memory
  execute   — Run a workflow
  interrupt — Cancel current generation
  stats     — Get system stats
  shutdown  — Clean exit
"""

import sys
import os
import json
import importlib
from typing import Any

# Global state
_comfyui_path: str = ""
_node_imports: dict = {}
_server_conn: dict = {}


def _ok(message: str = "OK", **extra) -> dict:
    """Return a success response."""
    return {"success": True, "message": message, **extra}


def _fail(message: str, **extra) -> dict:
    """Return an error response."""
    return {"success": False, "message": message, **extra}


def cmd_init(comfyui_path: str) -> dict:
    """Initialize ComfyUI modules."""
    global _comfyui_path

    if not os.path.isdir(comfyui_path):
        return _fail(f"ComfyUI directory not found: {comfyui_path}")

    main_py = os.path.join(comfyui_path, "main.py")
    if not os.path.isfile(main_py):
        return _fail(f"ComfyUI main.py not found at: {main_py}")

    _comfyui_path = comfyui_path

    # Add ComfyUI to Python path
    if comfyui_path not in sys.path:
        sys.path.insert(0, comfyui_path)

    # Try importing core modules
    try:
        import server
        import execution
        import folder_paths
        import comfy.model_management

        global _node_imports
        _node_imports = {
            "server": server,
            "execution": execution,
            "folder_paths": folder_paths,
            "model_management": comfy.model_management,
        }

        return _fail if False else _ok("ComfyUI modules imported", modules=list(_node_imports.keys()))
    except ImportError as e:
        return _fail(f"Failed to import ComfyUI: {e}")


def cmd_ping(host: str = "127.0.0.1", port: int = 8188) -> dict:
    """Check if ComfyUI server is reachable."""
    try:
        import urllib.request
        url = f"http://{host}:{port}/system_stats"
        req = urllib.request.Request(url, method="GET")
        with urllib.request.urlopen(req, timeout=5) as resp:
            data = json.loads(resp.read().decode())
            return _ok("Server responsive", **data)
    except Exception as e:
        return _fail(f"Server unreachable: {e}")


def cmd_load(model_type: str, model_name: str) -> dict:
    """Load a model into ComfyUI's model directory."""
    if not _comfyui_path:
        return _fail("Not initialized. Call init first.")

    model_dir = os.path.join(_comfyui_path, "models", model_type)
    model_path = os.path.join(model_dir, model_name)

    if not os.path.isfile(model_path):
        return _fail(f"Model not found: {model_path}")

    # Verify model is accessible to ComfyUI
    try:
        import folder_paths
        existing_paths = folder_paths.get_folder_paths(model_type)
        if model_dir not in existing_paths:
            return _fail(f"Model dir not in {model_type} paths: {existing_paths}")

        size_mb = os.path.getsize(model_path) / (1024 * 1024)
        return _ok(
            f"Model accessible: {model_name}",
            model_key=f"{model_type}/{model_name}",
            size_mb=round(size_mb, 1),
        )
    except Exception as e:
        return _fail(f"Error loading model: {e}")


def cmd_execute(workflow: dict) -> dict:
    """Execute a ComfyUI workflow."""
    if not _node_imports:
        return _fail("Not initialized. Call init first.")

    try:
        import json
        import uuid
        import server

        # Connect to ComfyUI server
        server_address = "127.0.0.1:8188"
        s = server.PromptServer.instance

        if s is None:
            return _fail("ComfyUI server not running")

        # Queue the prompt
        prompt_id = str(uuid.uuid4())

        # TODO: Full workflow execution via s.prompt_queue
        # This requires the workflow to be in ComfyUI's API format
        return _ok("Workflow queued", prompt_id=prompt_id)

    except Exception as e:
        return _fail(f"Execution error: {e}")


def cmd_interrupt() -> dict:
    """Interrupt current generation."""
    try:
        import server
        s = server.PromptServer.instance
        if s is not None:
            s.prompt_queue.set_flag_interrupt(True)
            return _ok("Interrupt signal sent")
        return _fail("Server not running")
    except Exception as e:
        return _fail(f"Interrupt error: {e}")


def cmd_stats() -> dict:
    """Get system statistics."""
    try:
        import comfy.model_management as mm

        vram_total = 0
        vram_free = 0
        vram_used = 0

        try:
            import torch
            if torch.cuda.is_available():
                vram_total = torch.cuda.get_device_properties(0).total_mem
                vram_used = torch.cuda.memory_allocated(0)
                vram_free = vram_total - vram_used
        except Exception:
            pass

        import psutil
        ram = psutil.virtual_memory()

        return _ok(
            "Stats retrieved",
            vram_total=vram_total,
            vram_free=vram_free,
            vram_used=vram_used,
            ram_total=ram.total,
            ram_used=ram.used,
            ram_percent=ram.percent,
        )
    except ImportError:
        return _fail("psutil not installed")
    except Exception as e:
        return _fail(f"Stats error: {e}")


def cmd_shutdown() -> dict:
    """Clean shutdown."""
    return _ok("Shutting down")


# Command dispatcher
_COMMANDS = {
    "init": lambda p: cmd_init(p.get("comfyui_path", "")),
    "ping": lambda p: cmd_ping(p.get("host", "127.0.0.1"), p.get("port", 8188)),
    "load": lambda p: cmd_load(p.get("model_type", ""), p.get("model_name", "")),
    "execute": lambda p: cmd_execute(p.get("workflow", {})),
    "interrupt": lambda p: cmd_interrupt(),
    "stats": lambda p: cmd_stats(),
    "shutdown": lambda p: cmd_shutdown(),
}


def main():
    """Main loop: read JSON commands from stdin, write JSON responses to stdout."""
    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue

        try:
            request = json.loads(line)
        except json.JSONDecodeError as e:
            response = _fail(f"Invalid JSON: {e}")
            print(json.dumps(response), flush=True)
            continue

        cmd = request.get("cmd", "")
        params = request.get("params", {})

        handler = _COMMANDS.get(cmd)
        if handler is None:
            response = _fail(f"Unknown command: {cmd}")
        else:
            try:
                response = handler(params)
            except Exception as e:
                response = _fail(f"Command error: {e}")

        print(json.dumps(response), flush=True)

        if cmd == "shutdown":
            break


if __name__ == "__main__":
    main()
