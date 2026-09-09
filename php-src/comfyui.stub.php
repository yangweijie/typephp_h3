<?php

/**
 * H3PHP — ComfyUI FFI Stubs.
 *
 * PHP declarations for Python-bridged ComfyUI functions.
 * These stubs enable static analysis (PHPStan) and serve as the
 * contract for the Python FFI bridge.
 *
 * Implementation: python-src/comfyui_bridge.py (via process pipe)
 * FFI mode: Direct Python module import (interpreted PHP only)
 *
 * phpstan.neon ignores "function not found" for h3_comfyui_* functions.
 */

/**
 * Initialize the Python runtime and import ComfyUI modules.
 *
 * @param string $comfyuiPath Path to ComfyUI installation
 * @return bool True if Python + ComfyUI imported successfully
 */
function h3_comfyui_init(string $comfyuiPath): bool {}

/**
 * Check if ComfyUI server is running and responsive.
 *
 * @param string $host Server host (e.g., '127.0.0.1')
 * @param int $port Server port (e.g., 8188)
 * @return bool True if server responds to /system_stats
 */
function h3_comfyui_ping(string $host, int $port): bool {}

/**
 * Load a model into ComfyUI's memory.
 *
 * @param string $modelType Model type (e.g., 'checkpoints', 'vae', 'clip')
 * @param string $modelName Model filename (e.g., 'MiniMax-H3-4B.safetensors')
 * @return array{success: bool, message: string, model_key: string}
 */
function h3_comfyui_load_model(string $modelType, string $modelName): array {}

/**
 * Execute a ComfyUI workflow and return output.
 *
 * @param array<string, mixed> $workflow ComfyUI workflow JSON as PHP array
 * @return array{success: bool, output_path: string, error: string}
 */
function h3_comfyui_execute(array $workflow): array {}

/**
 * Interrupt the current generation.
 *
 * @return bool True if interrupt signal sent
 */
function h3_comfyui_interrupt(): bool {}

/**
 * Get ComfyUI system stats (VRAM, memory, etc.).
 *
 * @return array{vram_total: int, vram_free: int, vram_used: int, ram_total: int, ram_used: int}
 */
function h3_comfyui_get_stats(): array {}

/**
 * Shutdown the Python runtime and release resources.
 */
function h3_comfyui_shutdown(): void {}
