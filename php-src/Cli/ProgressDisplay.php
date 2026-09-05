<?php

/**
 * H3PHP — Progress Display.
 *
 * Renders progress updates to stderr using \r carriage return for in-place updates.
 * Same phase overwrites its line; new phases start on a new line.
 * Matches h3.c's format: \r%-25s %4d/%-4d
 */

namespace H3Php\Cli;

class ProgressDisplay
{
    /** @var resource STDERR handle */
    private $stderr;

    /** Current phase being displayed */
    private string $currentPhase = '';

    /** Whether we've written to the current line */
    private bool $lineActive = false;

    public function __construct()
    {
        $this->stderr = STDERR;
    }

    /**
     * Update progress for a phase.
     *
     * Same phase overwrites its line; new phases start on a new line.
     *
     * @param string $phase     Phase name (e.g., "denoise", "decode")
     * @param int    $completed Current step
     * @param int    $total     Total steps
     */
    public function update(string $phase, int $completed, int $total): void
    {
        // If phase changed, terminate previous line
        if ($phase !== $this->currentPhase && $this->lineActive) {
            fwrite($this->stderr, "\n");
            $this->lineActive = false;
        }

        $this->currentPhase = $phase;

        // Format: \r%-25s %4d/%-4d
        $line = sprintf("\r%-25s %4d/%-4d", $phase, $completed, $total);
        fwrite($this->stderr, $line);
        $this->lineActive = true;
    }

    /**
     * Mark the current phase as complete.
     */
    public function complete(string $phase): void
    {
        if ($phase === $this->currentPhase && $this->lineActive) {
            fwrite($this->stderr, "\n");
            $this->lineActive = false;
        }
    }

    /**
     * Terminate any active progress line (call when generation ends).
     */
    public function finish(): void
    {
        if ($this->lineActive) {
            fwrite($this->stderr, "\n");
            $this->lineActive = false;
        }
        $this->currentPhase = '';
    }

    /**
     * Check if progress display is supported (interactive terminal).
     */
    public function isSupported(): bool
    {
        return stream_isatty($this->stderr);
    }
}
