<?php

/**
 * H3PHP — Backend Status Value Object.
 *
 * Immutable snapshot of a backend's current state.
 * Returned by BackendInterface::getStatus().
 */

namespace H3Php\Core;

class BackendStatus
{
    /**
     * @param bool $ready Whether the backend is ready for inference
     * @param string $message Human-readable status message
     * @param array<string, mixed> $details Backend-specific details (GPU info, model loaded, etc.)
     */
    public function __construct(
        public readonly bool $ready,
        public readonly string $message,
        public readonly array $details = [],
    ) {}

    /**
     * Create a "ready" status.
     */
    public static function ready(string $message = 'Ready', array $details = []): self
    {
        return new self(true, $message, $details);
    }

    /**
     * Create a "not ready" status.
     */
    public static function notReady(string $message, array $details = []): self
    {
        return new self(false, $message, $details);
    }

    /**
     * Create an "error" status.
     */
    public static function error(string $message, array $details = []): self
    {
        return new self(false, "Error: {$message}", $details);
    }
}
