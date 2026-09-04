<?php
/**
 * H3PHP — Progress Display
 *
 * Renders progress updates to stderr using \r carriage return for in-place updates.
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
     * Update progress for the current phase.
     *
     * @param string $phase Phase name (e.g., "denoise", "video decode")
     * @param int $completed Current step
     * @param int $total Total steps
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
