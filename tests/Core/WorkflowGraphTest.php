<?php

/**
 * H3PHP — WorkflowGraph Tests.
 */

namespace H3Php\Tests\Core;

use PHPUnit\Framework\TestCase;
use H3Php\Core\WorkflowGraph;
use H3Php\Core\H3NodeLibrary;
use H3Php\Qt\NodeItem;
use H3Php\Qt\ConnectionItem;

class WorkflowGraphTest extends TestCase
{
    public function testCreateEmptyGraph(): void
    {
        $graph = new WorkflowGraph('Test');

        $this->assertSame('Test', $graph->getMetadata('name'));
        $this->assertSame(0, $graph->getNodeCount());
        $this->assertSame(0, $graph->getConnectionCount());
    }

    public function testAddNode(): void
    {
        $graph = new WorkflowGraph();
        $node = new NodeItem('n1', 'Test Node', 'LoadH3Model', 10, 20);

        $graph->addNode($node);

        $this->assertSame(1, $graph->getNodeCount());
        $this->assertSame($node, $graph->getNode('n1'));
    }

    public function testRemoveNode(): void
    {
        $graph = new WorkflowGraph();
        $node = new NodeItem('n1', 'Test Node', 'LoadH3Model');

        $graph->addNode($node);
        $graph->removeNode('n1');

        $this->assertSame(0, $graph->getNodeCount());
        $this->assertNull($graph->getNode('n1'));
    }

    public function testAddConnection(): void
    {
        $graph = new WorkflowGraph();

        $node1 = new NodeItem('n1', 'Loader', 'LoadH3Model', 0, 0, [], [['name' => 'model', 'type' => 'model']]);
        $node2 = new NodeItem('n2', 'Sampler', 'H3KSampler', 200, 0, [['name' => 'model', 'type' => 'model']], []);

        $graph->addNode($node1);
        $graph->addNode($node2);

        $conn = new ConnectionItem('c1', 'n1', 'model', 'n2', 'model');
        $graph->addConnection($conn);

        $this->assertSame(1, $graph->getConnectionCount());
    }

    public function testAddConnectionInvalidNode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $graph = new WorkflowGraph();
        $node1 = new NodeItem('n1', 'Loader', 'LoadH3Model', 0, 0, [], [['name' => 'model', 'type' => 'model']]);
        $graph->addNode($node1);

        $conn = new ConnectionItem('c1', 'n1', 'model', 'nonexistent', 'model');
        $graph->addConnection($conn);
    }

    public function testTopologicalSort(): void
    {
        $graph = H3NodeLibrary::getDefaultWorkflow();

        $sorted = $graph->topologicalSort();

        $this->assertGreaterThan(0, count($sorted));

        // Loader should come before sampler
        $ids = array_map(fn (NodeItem $n) => $n->id, $sorted);
        $loaderIdx = array_search('loader_1', $ids);
        $samplerIdx = array_search('sampler_1', $ids);

        $this->assertLessThan($samplerIdx, $loaderIdx);
    }

    public function testCycleDetection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cycle detected');

        $graph = new WorkflowGraph();

        $node1 = new NodeItem('n1', 'A', 'Test', 0, 0, [['name' => 'in', 'type' => 'any']], [['name' => 'out', 'type' => 'any']]);
        $node2 = new NodeItem('n2', 'B', 'Test', 200, 0, [['name' => 'in', 'type' => 'any']], [['name' => 'out', 'type' => 'any']]);

        $graph->addNode($node1);
        $graph->addNode($node2);

        // Create cycle: n1 -> n2 -> n1
        $graph->addConnection(new ConnectionItem('c1', 'n1', 'out', 'n2', 'in'));
        $graph->addConnection(new ConnectionItem('c2', 'n2', 'out', 'n1', 'in'));

        $graph->topologicalSort();
    }

    public function testValidate(): void
    {
        $graph = H3NodeLibrary::getDefaultWorkflow();

        $errors = $graph->validate();

        // Default workflow should be valid
        $this->assertIsArray($errors);
    }

    public function testToArrayFromArray(): void
    {
        $graph = H3NodeLibrary::getDefaultWorkflow();

        $array = $graph->toArray();
        $restored = WorkflowGraph::fromArray($array);

        $this->assertSame($graph->getNodeCount(), $restored->getNodeCount());
        $this->assertSame($graph->getConnectionCount(), $restored->getConnectionCount());
    }

    public function testGetInputConnections(): void
    {
        $graph = H3NodeLibrary::getDefaultWorkflow();

        $inputs = $graph->getInputConnections('sampler_1');

        $this->assertGreaterThan(0, count($inputs));
    }

    public function testGetOutputConnections(): void
    {
        $graph = H3NodeLibrary::getDefaultWorkflow();

        $outputs = $graph->getOutputConnections('loader_1');

        $this->assertGreaterThan(0, count($outputs));
    }
}
