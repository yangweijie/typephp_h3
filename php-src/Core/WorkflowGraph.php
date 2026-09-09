<?php

/**
 * H3PHP — Workflow Graph.
 *
 * Data model for ComfyUI workflow graphs.
 * Represents a directed acyclic graph (DAG) of nodes and connections.
 * Can be serialized to/from ComfyUI workflow JSON format.
 */

namespace H3Php\Core;

use H3Php\Qt\NodeItem;
use H3Php\Qt\ConnectionItem;

class WorkflowGraph
{
    /** Nodes indexed by ID */
    private array $nodes = [];

    /** Connections indexed by ID */
    private array $connections = [];

    /** Graph metadata */
    private array $metadata = [
        'name' => 'Untitled Workflow',
        'version' => '1.0',
        'created' => '',
    ];

    public function __construct(string $name = 'Untitled Workflow')
    {
        $this->metadata['name'] = $name;
        $this->metadata['created'] = date('c');
    }

    /**
     * Add a node to the graph.
     */
    public function addNode(NodeItem $node): self
    {
        $this->nodes[$node->id] = $node;

        return $this;
    }

    /**
     * Remove a node and all its connections.
     */
    public function removeNode(string $nodeId): self
    {
        // Remove connected connections
        foreach ($this->connections as $cid => $conn) {
            if ($conn->involvesNode($nodeId)) {
                unset($this->connections[$cid]);
            }
        }

        unset($this->nodes[$nodeId]);

        return $this;
    }

    /**
     * Get a node by ID.
     */
    public function getNode(string $nodeId): ?NodeItem
    {
        return $this->nodes[$nodeId] ?? null;
    }

    /**
     * Get all nodes.
     *
     * @return NodeItem[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Add a connection between two node ports.
     *
     * @throws \InvalidArgumentException If nodes/ports don't exist
     */
    public function addConnection(ConnectionItem $conn): self
    {
        // Validate nodes exist
        if (!isset($this->nodes[$conn->sourceNodeId])) {
            throw new \InvalidArgumentException("Source node not found: {$conn->sourceNodeId}");
        }
        if (!isset($this->nodes[$conn->targetNodeId])) {
            throw new \InvalidArgumentException("Target node not found: {$conn->targetNodeId}");
        }

        // Validate ports exist
        $sourceNode = $this->nodes[$conn->sourceNodeId];
        $sourcePortNames = array_column($sourceNode->outputs, 'name');
        if (!in_array($conn->sourcePort, $sourcePortNames, true)) {
            throw new \InvalidArgumentException(
                "Source port '{$conn->sourcePort}' not found on node '{$conn->sourceNodeId}'"
            );
        }

        $targetNode = $this->nodes[$conn->targetNodeId];
        $targetPortNames = array_column($targetNode->inputs, 'name');
        if (!in_array($conn->targetPort, $targetPortNames, true)) {
            throw new \InvalidArgumentException(
                "Target port '{$conn->targetPort}' not found on node '{$conn->targetNodeId}'"
            );
        }

        $this->connections[$conn->id] = $conn;

        return $this;
    }

    /**
     * Remove a connection.
     */
    public function removeConnection(string $connectionId): self
    {
        unset($this->connections[$connectionId]);

        return $this;
    }

    /**
     * Get a connection by ID.
     */
    public function getConnection(string $connectionId): ?ConnectionItem
    {
        return $this->connections[$connectionId] ?? null;
    }

    /**
     * Get all connections.
     *
     * @return ConnectionItem[]
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Get connections for a specific node.
     *
     * @return ConnectionItem[]
     */
    public function getNodeConnections(string $nodeId): array
    {
        return array_filter(
            $this->connections,
            fn (ConnectionItem $c) => $c->involvesNode($nodeId)
        );
    }

    /**
     * Get input connections for a node.
     *
     * @return ConnectionItem[]
     */
    public function getInputConnections(string $nodeId): array
    {
        return array_filter(
            $this->connections,
            fn (ConnectionItem $c) => $c->targetNodeId === $nodeId
        );
    }

    /**
     * Get output connections for a node.
     *
     * @return ConnectionItem[]
     */
    public function getOutputConnections(string $nodeId): array
    {
        return array_filter(
            $this->connections,
            fn (ConnectionItem $c) => $c->sourceNodeId === $nodeId
        );
    }

    /**
     * Topological sort of nodes (execution order).
     *
     * @return NodeItem[] Nodes in execution order
     * @throws \RuntimeException If cycle detected
     */
    public function topologicalSort(): array
    {
        $inDegree = [];
        $adj = [];

        // Initialize
        foreach ($this->nodes as $id => $node) {
            $inDegree[$id] = 0;
            $adj[$id] = [];
        }

        // Build adjacency list
        foreach ($this->connections as $conn) {
            $adj[$conn->sourceNodeId][] = $conn->targetNodeId;
            $inDegree[$conn->targetNodeId]++;
        }

        // Kahn's algorithm
        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if (0 === $degree) {
                $queue[] = $id;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $id = array_shift($queue);
            $sorted[] = $this->nodes[$id];

            foreach ($adj[$id] as $neighbor) {
                $inDegree[$neighbor]--;
                if (0 === $inDegree[$neighbor]) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($this->nodes)) {
            throw new \RuntimeException('Cycle detected in workflow graph');
        }

        return $sorted;
    }

    /**
     * Validate the graph.
     *
     * @return array List of validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        // Check all connections reference valid nodes/ports
        foreach ($this->connections as $conn) {
            if (!isset($this->nodes[$conn->sourceNodeId])) {
                $errors[] = "Connection {$conn->id}: source node '{$conn->sourceNodeId}' not found";
            }
            if (!isset($this->nodes[$conn->targetNodeId])) {
                $errors[] = "Connection {$conn->id}: target node '{$conn->targetNodeId}' not found";
            }
        }

        // Check for cycles
        try {
            $this->topologicalSort();
        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        // Check required parameters
        foreach ($this->nodes as $node) {
            $requiredParams = H3NodeLibrary::getRequiredParams($node->type);
            foreach ($requiredParams as $param) {
                if (null === $node->getParam($param)) {
                    $errors[] = "Node {$node->id} ({$node->type}): missing required param '{$param}'";
                }
            }
        }

        return $errors;
    }

    /**
     * Clear all nodes and connections.
     */
    public function clear(): self
    {
        $this->nodes = [];
        $this->connections = [];

        return $this;
    }

    /**
     * Get node count.
     */
    public function getNodeCount(): int
    {
        return count($this->nodes);
    }

    /**
     * Get connection count.
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Set metadata.
     */
    public function setMetadata(string $key, string $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Get metadata.
     */
    public function getMetadata(string $key): ?string
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        $nodes = [];
        foreach ($this->nodes as $node) {
            $nodes[] = $node->toArray();
        }

        $connections = [];
        foreach ($this->connections as $conn) {
            $connections[] = $conn->toArray();
        }

        return [
            'metadata' => $this->metadata,
            'nodes' => $nodes,
            'connections' => $connections,
        ];
    }

    /**
     * Create from array representation.
     */
    public static function fromArray(array $data): self
    {
        $graph = new self($data['metadata']['name'] ?? 'Untitled');

        // Restore metadata (name already set via constructor)
        foreach ($data['metadata'] ?? [] as $key => $value) {
            if ('name' !== $key) {
                $graph->setMetadata($key, (string) $value);
            }
        }

        foreach ($data['nodes'] ?? [] as $nodeDef) {
            $graph->addNode(NodeItem::fromArray($nodeDef));
        }

        foreach ($data['connections'] ?? [] as $connDef) {
            $graph->addConnection(ConnectionItem::fromArray($connDef));
        }

        return $graph;
    }
}
