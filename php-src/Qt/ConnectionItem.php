<?php

/**
 * H3PHP — Connection Item.
 *
 * Represents a connection (edge) between two node ports.
 * Data model for the visual bezier curve drawn by C++ QGraphicsPathItem.
 */

namespace H3Php\Qt;

class ConnectionItem
{
    /** Unique connection identifier */
    public string $id;

    /** Source node ID */
    public string $sourceNodeId;

    /** Source port name */
    public string $sourcePort;

    /** Target node ID */
    public string $targetNodeId;

    /** Target port name */
    public string $targetPort;

    /** Whether the connection is selected */
    public bool $selected;

    public function __construct(
        string $id,
        string $sourceNodeId,
        string $sourcePort,
        string $targetNodeId,
        string $targetPort,
    ) {
        $this->id = $id;
        $this->sourceNodeId = $sourceNodeId;
        $this->sourcePort = $sourcePort;
        $this->targetNodeId = $targetNodeId;
        $this->targetPort = $targetPort;
        $this->selected = false;
    }

    /**
     * Create from definition array.
     */
    public static function fromArray(array $def): self
    {
        return new self(
            $def['id'],
            $def['source_node'],
            $def['source_port'],
            $def['target_node'],
            $def['target_port'],
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_node' => $this->sourceNodeId,
            'source_port' => $this->sourcePort,
            'target_node' => $this->targetNodeId,
            'target_port' => $this->targetPort,
            'selected' => $this->selected,
        ];
    }

    /**
     * Check if this connection involves a specific node.
     */
    public function involvesNode(string $nodeId): bool
    {
        return $this->sourceNodeId === $nodeId || $this->targetNodeId === $nodeId;
    }

    /**
     * Check if this connection connects two specific ports.
     */
    public function connects(string $sourceNode, string $sourcePort, string $targetNode, string $targetPort): bool
    {
        return $this->sourceNodeId === $sourceNode
            && $this->sourcePort === $sourcePort
            && $this->targetNodeId === $targetNode
            && $this->targetPort === $targetPort;
    }
}
