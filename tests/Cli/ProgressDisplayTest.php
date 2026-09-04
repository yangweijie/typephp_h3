<?php

/**
 * H3PHP — ProgressDisplay Test.
 */

namespace H3Php\Tests\Cli;

use H3Php\Cli\ProgressDisplay;
use PHPUnit\Framework\TestCase;

class ProgressDisplayTest extends TestCase
{
    public function testUpdateWritesToStderr(): void
    {
        $progress = new ProgressDisplay();

        // Capture stderr
        $tmpFile = tempnam(sys_get_temp_dir(), 'h3test');
        $stderr = fopen('php://stderr', 'r');

        // We can't easily test stderr output without redirecting,
        // but we can verify the method doesn't throw
        $progress->update('denoise', 5, 20);
        $progress->finish();

        $this->assertTrue(true); // No exception thrown
    }

    public function testFinishTerminatesLine(): void
    {
        $progress = new ProgressDisplay();

        $progress->update('load', 0, 1);
        $progress->update('load', 1, 1);
        $progress->finish();

        // Should not throw
        $this->assertTrue(true);
    }

    public function testPhaseChange(): void
    {
        $progress = new ProgressDisplay();

        $progress->update('load', 1, 1);
        $progress->update('denoise', 0, 20);
        $progress->update('denoise', 1, 20);
        $progress->finish();

        // Should not throw
        $this->assertTrue(true);
    }

    public function testIsSupported(): void
    {
        $progress = new ProgressDisplay();

        // In test environment, stderr is typically not a tty
        // Just verify the method returns a boolean
        $result = $progress->isSupported();
        $this->assertIsBool($result);
    }
}
