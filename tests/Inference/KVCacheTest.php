<?php

/**
 * H3PHP — KVCache Test.
 */

namespace H3Php\Tests\Inference;

use H3Php\Inference\KVCache;
use PHPUnit\Framework\TestCase;

class KVCacheTest extends TestCase
{
    public function testCacheStartsEmpty(): void
    {
        $cache = new KVCache();
        $stats = $cache->getStats();

        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
        $this->assertFalse($stats['cached']);
        $this->assertEquals(0.0, $stats['hit_rate']);
    }

    public function testFirstAccessIsMiss(): void
    {
        $cache = new KVCache();

        // Create a mock buffer (we can't use real Metal in unit tests)
        $mockBuffer = $this->createMock(\H3Php\Metal\Buffer::class);
        $mockBuffer->method('getLength')->willReturn(1024);
        $mockBuffer->method('getContents')->willReturn(str_repeat('x', 64));

        $computeK = fn ($emb) => $mockBuffer;
        $computeV = fn ($emb) => $mockBuffer;

        [$k, $v] = $cache->getKV($mockBuffer, $computeK, $computeV);

        $stats = $cache->getStats();
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertTrue($stats['cached']);
    }

    public function testSecondAccessIsHit(): void
    {
        $cache = new KVCache();

        $mockBuffer = $this->createMock(\H3Php\Metal\Buffer::class);
        $mockBuffer->method('getLength')->willReturn(1024);
        $mockBuffer->method('getContents')->willReturn(str_repeat('x', 64));

        $computeCount = 0;
        $computeK = function ($emb) use (&$computeCount, $mockBuffer) {
            ++$computeCount;

            return $mockBuffer;
        };
        $computeV = function ($emb) use (&$computeCount, $mockBuffer) {
            ++$computeCount;

            return $mockBuffer;
        };

        // First access — miss
        $cache->getKV($mockBuffer, $computeK, $computeV);
        $this->assertEquals(2, $computeCount);  // K + V computed

        // Second access — hit
        $cache->getKV($mockBuffer, $computeK, $computeV);
        $this->assertEquals(2, $computeCount);  // No additional computation

        $stats = $cache->getStats();
        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(0.5, $stats['hit_rate']);
    }

    public function testInvalidateClearsCache(): void
    {
        $cache = new KVCache();

        $mockBuffer = $this->createMock(\H3Php\Metal\Buffer::class);
        $mockBuffer->method('getLength')->willReturn(1024);
        $mockBuffer->method('getContents')->willReturn(str_repeat('x', 64));

        $computeK = fn ($emb) => $mockBuffer;
        $computeV = fn ($emb) => $mockBuffer;

        // Populate cache
        $cache->getKV($mockBuffer, $computeK, $computeV);
        $this->assertTrue($cache->getStats()['cached']);

        // Invalidate
        $cache->invalidate();
        $this->assertFalse($cache->getStats()['cached']);
    }

    public function testIsValidReturnsCorrectState(): void
    {
        $cache = new KVCache();

        $mockBuffer = $this->createMock(\H3Php\Metal\Buffer::class);
        $mockBuffer->method('getLength')->willReturn(1024);
        $mockBuffer->method('getContents')->willReturn(str_repeat('x', 64));

        $computeK = fn ($emb) => $mockBuffer;
        $computeV = fn ($emb) => $mockBuffer;

        $this->assertFalse($cache->isValid($mockBuffer));

        $cache->getKV($mockBuffer, $computeK, $computeV);
        $this->assertTrue($cache->isValid($mockBuffer));
    }

    public function testFreeClearsAll(): void
    {
        $cache = new KVCache();

        $mockBuffer = $this->createMock(\H3Php\Metal\Buffer::class);
        $mockBuffer->method('getLength')->willReturn(1024);
        $mockBuffer->method('getContents')->willReturn(str_repeat('x', 64));

        $computeK = fn ($emb) => $mockBuffer;
        $computeV = fn ($emb) => $mockBuffer;

        $cache->getKV($mockBuffer, $computeK, $computeV);
        $cache->free();

        $stats = $cache->getStats();
        $this->assertFalse($stats['cached']);
    }
}
