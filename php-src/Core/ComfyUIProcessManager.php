<?php

/**
 * H3PHP — ComfyUI Process Manager.
 *
 * Manages the lifecycle of a ComfyUI server instance:
 *   1. Start ComfyUI as a background process
 *   2. Monitor health via HTTP /system_stats
 *   3. Communicate via python-src/comfyui_bridge.py (JSON stdin/stdout)
 *   4. Graceful shutdown
 *
 * Architecture:
 *   PHP → ComfyUIProcessManager → python-src/comfyui_bridge.py → ComfyUI server (HTTP :8188)
 *                                      ↑ JSON stdin/stdout ↑        ↑ HTTP REST API ↑
 */

namespace H3Php\Core;

class ComfyUIProcessManager
{
    /** Path to ComfyUI installation */
    private string $comfyuiPath;

    /** Python executable path */
    private string $pythonPath;

    /** ComfyUI server host */
    private string $host;

    /** ComfyUI server port */
    private int $port;

    /** Running bridge process */
    private $process = null;

    /** Process pipes [stdin, stdout, stderr] */
    private array $pipes = [];

    /** Whether the bridge is connected */
    private bool $connected = false;

    /** Last error message */
    private string $lastError = '';

    /**
     * @param string $comfyuiPath Path to ComfyUI installation
     * @param string $pythonPath Python executable path
     * @param string $host Server host
     * @param int $port Server port
     */
    public function __construct(
        string $comfyuiPath,
        string $pythonPath = 'python3',
        string $host = '127.0.0.1',
        int $port = 8188,
    ) {
        $this->comfyuiPath = rtrim($comfyuiPath, '/\\');
        $this->pythonPath = $pythonPath;
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Start the ComfyUI bridge process.
     *
     * Launches python-src/comfyui_bridge.py as a persistent subprocess.
     * Commands are sent via stdin, responses received via stdout.
     */
    public function start(): bool
    {
        if ($this->connected) {
            return true;
        }

        $bridgePath = $this->getBridgePath();
        if (!file_exists($bridgePath)) {
            $this->lastError = "Bridge script not found: {$bridgePath}";
            return false;
        }

        $cmd = [
            $this->pythonPath,
            $bridgePath,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin — write commands
            1 => ['pipe', 'w'],  // stdout — read responses
            2 => ['pipe', 'w'],  // stderr — read errors
        ];

        $cmdString = implode(' ', array_map('escapeshellarg', $cmd));
        $this->process = proc_open($cmdString, $descriptors, $this->pipes, null, null, ['bypass_shell' => true]);

        if (!is_resource($this->process)) {
            $this->lastError = 'Failed to start bridge process';
            return false;
        }

        // Set non-blocking mode for stdout
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        // Initialize ComfyUI modules
        $response = $this->sendCommand('init', ['comfyui_path' => $this->comfyuiPath]);

        if ($response === null || !($response['success'] ?? false)) {
            $this->lastError = $response['message'] ?? 'Init failed';
            $this->stop();
            return false;
        }

        $this->connected = true;
        return true;
    }

    /**
     * Stop the bridge process.
     */
    public function stop(): void
    {
        if ($this->connected) {
            $this->sendCommand('shutdown');
        }

        if (is_resource($this->process)) {
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate($this->process, 9);
            proc_close($this->process);
        }

        $this->process = null;
        $this->pipes = [];
        $this->connected = false;
    }

    /**
     * Check if ComfyUI server is running and responsive.
     */
    public function ping(): bool
    {
        $response = $this->sendCommand('ping', ['host' => $this->host, 'port' => $this->port]);
        return $response !== null && ($response['success'] ?? false);
    }

    /**
     * Load a model into ComfyUI.
     *
     * @param string $modelType Model type folder (e.g., 'checkpoints', 'vae')
     * @param string $modelName Model filename
     * @return array{success: bool, message: string, model_key: string}
     */
    public function loadModel(string $modelType, string $modelName): array
    {
        $response = $this->sendCommand('load', [
            'model_type' => $modelType,
            'model_name' => $modelName,
        ]);

        return $response ?? ['success' => false, 'message' => 'No response from bridge'];
    }

    /**
     * Execute a ComfyUI workflow.
     *
     * @param array<string, mixed> $workflow ComfyUI API-format workflow
     * @return array{success: bool, message: string, prompt_id: string}
     */
    public function execute(array $workflow): array
    {
        $response = $this->sendCommand('execute', ['workflow' => $workflow]);

        return $response ?? ['success' => false, 'message' => 'No response from bridge'];
    }

    /**
     * Interrupt current generation.
     */
    public function interrupt(): bool
    {
        $response = $this->sendCommand('interrupt');
        return $response !== null && ($response['success'] ?? false);
    }

    /**
     * Get system statistics (VRAM, RAM).
     *
     * @return array{vram_total: int, vram_free: int, vram_used: int, ram_total: int, ram_used: int}|null
     */
    public function getStats(): ?array
    {
        $response = $this->sendCommand('stats');

        if ($response === null || !($response['success'] ?? false)) {
            return null;
        }

        return [
            'vram_total' => $response['vram_total'] ?? 0,
            'vram_free' => $response['vram_free'] ?? 0,
            'vram_used' => $response['vram_used'] ?? 0,
            'ram_total' => $response['ram_total'] ?? 0,
            'ram_used' => $response['ram_used'] ?? 0,
        ];
    }

    /**
     * Check if bridge is connected.
     */
    public function isConnected(): bool
    {
        return $this->connected && is_resource($this->process);
    }

    /**
     * Get the last error message.
     */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Get the path to the bridge script.
     */
    public function getBridgePath(): string
    {
        return dirname(__DIR__, 2) . '/python-src/comfyui_bridge.py';
    }

    /**
     * Send a command to the bridge and read the response.
     *
     * @param string $cmd Command name
     * @param array<string, mixed> $params Command parameters
     * @return array<string, mixed>|null Decoded response or null on failure
     */
    private function sendCommand(string $cmd, array $params = []): ?array
    {
        if (!$this->connected && 'shutdown' !== $cmd) {
            return null;
        }

        $payload = json_encode(['cmd' => $cmd, 'params' => $params]);
        $written = fwrite($this->pipes[0], $payload . "\n");

        if (false === $written) {
            return null;
        }

        // Read response with timeout
        $response = $this->readResponse(30);

        if (null === $response) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Read a line from stdout with timeout.
     *
     * @param int $timeoutSeconds Max wait time
     * @return string|null Response line or null on timeout
     */
    private function readResponse(int $timeoutSeconds = 30): ?string
    {
        $startTime = time();
        $buffer = '';

        while (time() - $startTime < $timeoutSeconds) {
            $char = fgetc($this->pipes[1]);

            if (false === $char) {
                usleep(10000); // 10ms poll interval
                continue;
            }

            if ("\n" === $char) {
                return trim($buffer);
            }

            $buffer .= $char;
        }

        return null; // Timeout
    }

    /**
     * Destructor: ensure process is cleaned up.
     */
    public function __destruct()
    {
        $this->stop();
    }
}
