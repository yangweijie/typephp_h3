<?php

/**
 * H3PHP — CLI Exception.
 *
 * Exception thrown by Application::error() for testability.
 * The CLI entry point catches this and exits with the error code.
 */

namespace H3Php\Cli;

class Exception extends \RuntimeException
{
    /** @var int Exit code to use when caught by CLI entry point */
    private int $exitCode;

    public function __construct(string $message, int $exitCode = 1, ?\Throwable $previous = null)
    {
        parent::__construct($message, $exitCode, $previous);
        $this->exitCode = $exitCode;
    }

    /**
     * Get the exit code.
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
