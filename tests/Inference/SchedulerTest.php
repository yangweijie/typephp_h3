<?php
/**
 * H3PHP — Scheduler Test
 */

namespace H3Php\Tests\Inference;

use H3Php\Inference\Scheduler;
use PHPUnit\Framework\TestCase;

class SchedulerTest extends TestCase
{
    private Scheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new Scheduler();
    }

    public function testSigmaSchedule(): void
    {
        $sigmas = $this->scheduler->computeSigmas(20);
        $this->assertCount(21, $sigmas);
    }

    public function testTimestepEmbeddingSize(): void
    {
        $embedding = $this->scheduler->computeTimestepEmbedding(1.0);
        $this->assertCount(5376, $embedding); // hiddenDim
    }

    public function testTimestepEmbeddingDifferentValues(): void
    {
        $emb1 = $this->scheduler->computeTimestepEmbedding(1.0);
        $emb2 = $this->scheduler->computeTimestepEmbedding(2.0);

        // Different sigmas should produce different embeddings
        $this->assertNotEquals($emb1, $emb2);
    }

    public function testAdaLNModulationStructure(): void
    {
        $modulation = $this->scheduler->computeAdaLNModulation(1.0);

        $this->assertArrayHasKey('scale', $modulation);
        $this->assertArrayHasKey('shift', $modulation);
        $this->assertArrayHasKey('gate', $modulation);

        $this->assertCount(5376, $modulation['scale']);
        $this->assertCount(5376, $modulation['shift']);
        $this->assertCount(5376, $modulation['gate']);
    }

    public function testGateScoresStructure(): void
    {
        $scores = $this->scheduler->computeGateScores(1.0);

        $this->assertCount(50, $scores); // 50 blocks

        foreach ($scores as $score) {
            $this->assertArrayHasKey('block_index', $score);
            $this->assertArrayHasKey('gate_score', $score);
        }
    }

    public function testGateScoresSortedDescending(): void
    {
        $scores = $this->scheduler->computeGateScores(1.0);

        // Should be sorted by gate_score descending
        for ($i = 0; $i < count($scores) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $scores[$i + 1]['gate_score'],
                $scores[$i]['gate_score']
            );
        }
    }

    public function testTopBlocks(): void
    {
        $blocks = $this->scheduler->getTopBlocks(1.0, 45);

        $this->assertCount(45, $blocks);

        // First and last blocks should always be included
        $this->assertContains(0, $blocks);
        $this->assertContains(49, $blocks);
    }

    public function testTopBlocksOrdering(): void
    {
        $blocks = $this->scheduler->getTopBlocks(1.0, 40);

        // Should be sorted in ascending order
        $sorted = $blocks;
        sort($sorted);
        $this->assertEquals($sorted, $blocks);
    }

    public function testTopBlocksMinimumLayers(): void
    {
        $blocks = $this->scheduler->getTopBlocks(1.0, 35);

        $this->assertCount(35, $blocks);
        $this->assertContains(0, $blocks);
        $this->assertContains(49, $blocks);
    }
}
