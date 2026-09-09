<?php

/**
 * H3PHP — ComfyUI Serializer.
 *
 * Converts between WorkflowGraph and ComfyUI workflow JSON format.
 *
 * ComfyUI workflow JSON structure:
 * {
 *   "last_node_id": 10,
 *   "last_link_id": 15,
 *   "nodes": [
 *     {"id": 1, "type": "CheckpointLoaderSimple", "outputs": [...], "pos": [0, 0], "widgets_values": [...]}
 *   ],
 *   "links": [
 *     {"id": 1, "source": 1, "source_slot": 0, "target": 3, "target_slot": 0}
 *   ]
 * }
 */

namespace H3Php\Core;

class ComfyUISerializer
{
    /** Node ID counter */
    private int $nextNodeId = 1;

    /** Link ID counter */
    private int $nextLinkId = 1;

    /** Mapping from our node ID to ComfyUI node ID */
    private array $nodeIdMap = [];

    /** Node references indexed by our node ID (for slot lookup during serialization) */
    private array $nodeRefs = [];

    /**
     * Serialize a WorkflowGraph to ComfyUI workflow JSON.
     *
     * @return array ComfyUI workflow as PHP array
     */
    public function serialize(WorkflowGraph $graph): array
    {
        $this->nextNodeId = 1;
        $this->nextLinkId = 1;
        $this->nodeIdMap = [];
        $this->nodeRefs = [];

        $workflow = [
            'last_node_id' => 0,
            'last_link_id' => 0,
            'nodes' => [],
            'links' => [],
            'groups' => [],
            'config' => [],
            'extra' => [],
            'version' => 0.4,
        ];

        // Serialize nodes
        foreach ($graph->getNodes() as $node) {
            $comfyNodeId = $this->nextNodeId++;
            $this->nodeIdMap[$node->id] = $comfyNodeId;
            $this->nodeRefs[$node->id] = $node;

            $workflow['nodes'][] = $this->serializeNode($node, $comfyNodeId);
        }

        // Serialize connections
        foreach ($graph->getConnections() as $conn) {
            $link = $this->serializeConnection($conn);
            if (null !== $link) {
                $workflow['links'][] = $link;
            }
        }

        $workflow['last_node_id'] = $this->nextNodeId - 1;
        $workflow['last_link_id'] = $this->nextLinkId - 1;

        return $workflow;
    }

    /**
     * Deserialize ComfyUI workflow JSON to WorkflowGraph.
     */
    public function deserialize(array $workflow): WorkflowGraph
    {
        $graph = new WorkflowGraph('Imported Workflow');

        // Map ComfyUI node IDs to our node IDs
        $comfyToOurId = [];

        // Deserialize nodes
        foreach ($workflow['nodes'] ?? [] as $comfyNode) {
            $comfyId = $comfyNode['id'];
            $ourId = $comfyNode['type'] . '_' . $comfyId;
            $comfyToOurId[$comfyId] = $ourId;

            $node = $this->deserializeNode($comfyNode, $ourId);
            $graph->addNode($node);
        }

        // Deserialize connections
        foreach ($workflow['links'] ?? [] as $link) {
            $sourceComfyId = $link['source'];
            $targetComfyId = $link['target'];

            $sourceId = $comfyToOurId[$sourceComfyId] ?? null;
            $targetId = $comfyToOurId[$targetComfyId] ?? null;

            if (null === $sourceId || null === $targetId) {
                continue;
            }

            $conn = $this->deserializeConnection($link, $sourceId, $targetId);
            if (null !== $conn) {
                try {
                    $graph->addConnection($conn);
                } catch (\InvalidArgumentException $e) {
                    // Skip invalid connections
                }
            }
        }

        return $graph;
    }

    /**
     * Serialize a single node to ComfyUI format.
     */
    private function serializeNode(\H3Php\Qt\NodeItem $node, int $comfyNodeId): array
    {
        // Get node definition for ComfyUI type mapping
        $nodeDef = H3NodeLibrary::get($node->type);
        $comfyType = $nodeDef['comfy_type'] ?? $node->type;

        // Build outputs
        $outputs = [];
        $slotIndex = 0;
        foreach ($node->outputs as $port) {
            $outputs[] = [
                'name' => $port['name'],
                'type' => $this->mapPortType($port['type']),
                'links' => null,
                'slot_index' => $slotIndex++,
            ];
        }

        // Build inputs
        $inputs = [];
        $slotIndex = 0;
        foreach ($node->inputs as $port) {
            $inputs[] = [
                'name' => $port['name'],
                'type' => $this->mapPortType($port['type']),
                'link' => null,
                'slot_index' => $slotIndex++,
            ];
        }

        // Build widgets_values from params
        $widgetsValues = [];
        foreach ($node->params as $name => $value) {
            $widgetsValues[] = $value;
        }

        return [
            'id' => $comfyNodeId,
            'type' => $comfyType,
            'pos' => [$node->x, $node->y],
            'size' => [180, 50],
            'flags' => [],
            'order' => 0,
            'mode' => 0,
            'inputs' => $inputs,
            'outputs' => $outputs,
            'properties' => ['Node name for S&R' => $node->title],
            'widgets_values' => $widgetsValues,
            'color' => $nodeDef['color'] ?? '#232323',
            'bgcolor' => $nodeDef['bgcolor'] ?? '#353535',
        ];
    }

    /**
     * Serialize a connection to ComfyUI link format.
     */
    private function serializeConnection(\H3Php\Qt\ConnectionItem $conn): ?array
    {
        $sourceComfyId = $this->nodeIdMap[$conn->sourceNodeId] ?? null;
        $targetComfyId = $this->nodeIdMap[$conn->targetNodeId] ?? null;

        if (null === $sourceComfyId || null === $targetComfyId) {
            return null;
        }

        // Find slot indices
        $sourceNode = $this->getNodeById($conn->sourceNodeId);
        $targetNode = $this->getNodeById($conn->targetNodeId);

        $sourceSlot = $this->findOutputSlot($sourceNode, $conn->sourcePort);
        $targetSlot = $this->findInputSlot($targetNode, $conn->targetPort);

        if (null === $sourceSlot || null === $targetSlot) {
            return null;
        }

        return [
            'id' => $this->nextLinkId++,
            'source' => $sourceComfyId,
            'source_slot' => $sourceSlot,
            'target' => $targetComfyId,
            'target_slot' => $targetSlot,
        ];
    }

    /**
     * Deserialize a ComfyUI node to NodeItem.
     */
    private function deserializeNode(array $comfyNode, string $ourId): \H3Php\Qt\NodeItem
    {
        $comfyType = $comfyNode['type'];
        $nodeType = H3NodeLibrary::mapFromComfyType($comfyType);

        $pos = $comfyNode['pos'] ?? [0, 0];

        // Extract inputs from ComfyUI format
        $inputs = [];
        foreach ($comfyNode['inputs'] ?? [] as $input) {
            $inputs[] = [
                'name' => $input['name'],
                'type' => $this->reverseMapPortType($input['type'] ?? ''),
            ];
        }

        // Extract outputs
        $outputs = [];
        foreach ($comfyNode['outputs'] ?? [] as $output) {
            $outputs[] = [
                'name' => $output['name'],
                'type' => $this->reverseMapPortType($output['type'] ?? ''),
            ];
        }

        // Extract params from widgets_values
        $params = [];
        $paramNames = H3NodeLibrary::getParamNames($nodeType);
        $widgetIdx = 0;
        foreach ($paramNames as $name) {
            if (isset($comfyNode['widgets_values'][$widgetIdx])) {
                $params[$name] = $comfyNode['widgets_values'][$widgetIdx];
            }
            $widgetIdx++;
        }

        return new \H3Php\Qt\NodeItem(
            $ourId,
            $comfyNode['properties']['Node name for S&R'] ?? $comfyType,
            $nodeType,
            $pos[0] ?? 0,
            $pos[1] ?? 0,
            $inputs,
            $outputs,
            $params,
        );
    }

    /**
     * Deserialize a ComfyUI link to ConnectionItem.
     */
    private function deserializeConnection(array $link, string $sourceId, string $targetId): ?ConnectionItem
    {
        return new \H3Php\Qt\ConnectionItem(
            'link_' . $link['id'],
            $sourceId,
            'output_' . ($link['source_slot'] ?? 0),
            $targetId,
            'input_' . ($link['target_slot'] ?? 0),
        );
    }

    /**
     * Map our port type to ComfyUI type.
     */
    private function mapPortType(string $type): string
    {
        $map = [
            'model' => 'MODEL',
            'vae' => 'VAE',
            'clip' => 'CLIP',
            'conditioning' => 'CONDITIONING',
            'latent' => 'LATENT',
            'image' => 'IMAGE',
            'mask' => 'MASK',
            'string' => 'STRING',
            'int' => 'INT',
            'float' => 'FLOAT',
            'boolean' => 'BOOLEAN',
        ];

        return $map[$type] ?? $type;
    }

    /**
     * Reverse map ComfyUI type to our port type.
     */
    private function reverseMapPortType(string $comfyType): string
    {
        $map = [
            'MODEL' => 'model',
            'VAE' => 'vae',
            'CLIP' => 'clip',
            'CONDITIONING' => 'conditioning',
            'LATENT' => 'latent',
            'IMAGE' => 'image',
            'MASK' => 'mask',
            'STRING' => 'string',
            'INT' => 'int',
            'FLOAT' => 'float',
            'BOOLEAN' => 'boolean',
        ];

        return $map[$comfyType] ?? strtolower($comfyType);
    }

    private function getNodeById(string $nodeId): ?\H3Php\Qt\NodeItem
    {
        return $this->nodeRefs[$nodeId] ?? null;
    }

    private function findOutputSlot(?\H3Php\Qt\NodeItem $node, string $portName): ?int
    {
        if (null === $node) {
            return null;
        }
        foreach ($node->outputs as $i => $port) {
            if ($port['name'] === $portName) {
                return $i;
            }
        }

        return null;
    }

    private function findInputSlot(?\H3Php\Qt\NodeItem $node, string $portName): ?int
    {
        if (null === $node) {
            return null;
        }
        foreach ($node->inputs as $i => $port) {
            if ($port['name'] === $portName) {
                return $i;
            }
        }

        return null;
    }
}
