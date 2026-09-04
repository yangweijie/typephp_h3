<?php
/**
 * H3PHP — Cross-Attention KV-Cache
 *
 * Caches K/V projections of text embeddings for reuse across denoising steps.
 * Text embeddings don't change during the denoising loop, so their K/V
 * can be computed once and cached for all subsequent steps.
 *
 * Performance impact: eliminates redundant K/V computation for text conditioning.
 * For 20 denoising steps with 50 layers each, this saves up to 1000 K/V projections.
 */

namespace H3Php\Inference;

use H3Php\Metal\Buffer;

class KVCache
{
    /** @var Buffer|null Cached K projection */
    private ?Buffer $cachedK = null;

    /** @var Buffer|null Cached V projection */
    private ?Buffer $cachedV = null;

    /** @var int|null Hash of the text embedding used to generate cache */
    private ?int $cacheHash = null;

    /** @var int Number of times cache was hit */
    private int $hitCount = 0;

    /** @var int Number of times cache was missed */
    private int $missCount = 0;

    /**
     * Get cached K/V for text embeddings, computing if necessary.
     *
     * @param Buffer $textEmbedding Text embedding buffer
     * @param callable $computeK Function to compute K: fn(Buffer $emb) => Buffer
     * @param callable $computeV Function to compute V: fn(Buffer $emb) => Buffer
     * @return array{0: Buffer, 1: Buffer} K and V buffers
     */
    public function getKV(Buffer $textEmbedding, callable $computeK, callable $computeV): array
    {
        $hash = $this->computeHash($textEmbedding);

        if ($this->cacheHash === $hash && $this->cachedK !== null && $this->cachedV !== null) {
            $this->hitCount++;
            return [$this->cachedK, $this->cachedV];
        }

        // Cache miss — compute and store
        $this->missCount++;
        $this->invalidate();

        $this->cachedK = $computeK($textEmbedding);
        $this->cachedV = $computeV($textEmbedding);
        $this->cacheHash = $hash;

        return [$this->cachedK, $this->cachedV];
    }

    /**
     * Check if cache is valid for given embedding.
     */
    public function isValid(Buffer $textEmbedding): bool
    {
        return $this->cacheHash === $this->computeHash($textEmbedding)
            && $this->cachedK !== null
            && $this->cachedV !== null;
    }

    /**
     * Invalidate the cache.
     */
    public function invalidate(): void
    {
        if ($this->cachedK !== null) {
            $this->cachedK->free();
            $this->cachedK = null;
        }
        if ($this->cachedV !== null) {
            $this->cachedV->free();
            $this->cachedV = null;
        }
        $this->cacheHash = null;
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        return [
            'hits' => $this->hitCount,
            'misses' => $this->missCount,
            'hit_rate' => ($this->hitCount + $this->missCount) > 0
                ? $this->hitCount / ($this->hitCount + $this->missCount)
                : 0.0,
            'cached' => $this->cachedK !== null,
        ];
    }

    /**
     * Compute a simple hash for the embedding buffer.
     */
    private function computeHash(Buffer $buffer): int
    {
        // Use buffer length and first/last bytes as a quick hash
        $length = $buffer->getLength();
        $sample = $buffer->getContents(0, min(64, $length));
        return crc32($sample) ^ $length;
    }

    /**
     * Free all cached resources.
     */
    public function free(): void
    {
        $this->invalidate();
    }

    public function __destruct()
    {
        $this->free();
    }
}
