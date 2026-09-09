<?php

/**
 * H3PHP — H3NodeLibrary Tests.
 */

namespace H3Php\Tests\Core;

use PHPUnit\Framework\TestCase;
use H3Php\Core\H3NodeLibrary;

class H3NodeLibraryTest extends TestCase
{
    public function testGetAllNodes(): void
    {
        $nodes = H3NodeLibrary::getAll();

        $this->assertArrayHasKey('LoadH3Model', $nodes);
        $this->assertArrayHasKey('H3TextEncode', $nodes);
        $this->assertArrayHasKey('H3KSampler', $nodes);
        $this->assertArrayHasKey('H3VAEDecode', $nodes);
        $this->assertArrayHasKey('H3VideoCombine', $nodes);
    }

    public function testGetNodeDefinition(): void
    {
        $def = H3NodeLibrary::get('H3KSampler');

        $this->assertSame('KSampler', $def['comfy_type']);
        $this->assertNotEmpty($def['inputs']);
        $this->assertNotEmpty($def['outputs']);
        $this->assertNotEmpty($def['params']);
    }

    public function testGetByCategory(): void
    {
        $categories = H3NodeLibrary::getByCategory();

        $this->assertArrayHasKey('loaders', $categories);
        $this->assertArrayHasKey('sampling', $categories);
        $this->assertArrayHasKey('output', $categories);
    }

    public function testMapFromComfyType(): void
    {
        $this->assertSame('H3KSampler', H3NodeLibrary::mapFromComfyType('KSampler'));
        $this->assertSame('H3TextEncode', H3NodeLibrary::mapFromComfyType('CLIPTextEncode'));
    }

    public function testCreateNode(): void
    {
        $node = H3NodeLibrary::createNode('H3KSampler', 'test_sampler', 100, 200);

        $this->assertSame('test_sampler', $node->id);
        $this->assertSame('H3KSampler', $node->type);
        $this->assertSame(100, $node->x);
        $this->assertSame(200, $node->y);
        $this->assertNotEmpty($node->params);
    }

    public function testGetDefaultWorkflow(): void
    {
        $graph = H3NodeLibrary::getDefaultWorkflow();

        $this->assertGreaterThan(0, $graph->getNodeCount());
        $this->assertGreaterThan(0, $graph->getConnectionCount());

        // Should be valid
        $errors = $graph->validate();
        $this->assertIsArray($errors);
    }

    public function testGetRequiredParams(): void
    {
        $params = H3NodeLibrary::getRequiredParams('H3KSampler');

        $this->assertContains('steps', $params);
        $this->assertContains('width', $params);
        $this->assertContains('height', $params);
    }

    public function testGetParamNames(): void
    {
        $params = H3NodeLibrary::getParamNames('H3KSampler');

        $this->assertContains('seed', $params);
        $this->assertContains('steps', $params);
        $this->assertContains('cfg', $params);
    }
}
