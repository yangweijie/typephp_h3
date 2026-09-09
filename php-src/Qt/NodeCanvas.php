<?php

/**
 * H3PHP — Node Canvas Controller.
 *
 * High-level controller for QGraphicsView-based node editor.
 * Manages nodes, connections, and event dispatch.
 * PHP owns all business logic; Qt only renders + captures input.
 */

namespace H3Php\Qt;

class NodeCanvas
{
    /** Opaque canvas handle */
    private int $handle;

    /** Node definitions (PHP-managed state) */
    private array $nodeDefs = [];

    /** Connection definitions (PHP-managed state) */
    private array $connectionDefs = [];

    /** Event handlers */
    private array $handlers = [];

    /** Whether the canvas has been created */
    private bool $created = false;

    /**
     * Create a new node canvas.
     */
    public function __construct()
    {
        $this->handle = qt_node_canvas_create();
        $this->created = ($this->handle > 0);
    }

    /**
     * Set canvas size.
     */
    public function setSize(int $width, int $height): self
    {
        qt_node_canvas_set_size($this->handle, $width, $height);

        return $this;
    }

    /**
     * Add a node to the canvas.
     *
     * @param string $nodeId Unique identifier
     * @param string $title Display title
     * @param string $nodeType Node type (e.g., 'LoadModel', 'KSampler')
     * @param int $x X position
     * @param int $y Y position
     * @param array $inputs Input ports [['name' => 'model', 'type' => 'model']]
     * @param array $outputs Output ports [['name' => 'image', 'type' => 'image']]
     */
    public function addNode(
        string $nodeId,
        string $title,
        string $nodeType,
        int $x = 0,
        int $y = 0,
        array $inputs = [],
        array $outputs = [],
    ): self {
        qt_node_canvas_add_node($this->handle, $nodeId, $title, $nodeType, $x, $y, $inputs, $outputs);
        $this->nodeDefs[$nodeId] = [
            'title' => $title,
            'type' => $nodeType,
            'x' => $x,
            'y' => $y,
            'inputs' => $inputs,
            'outputs' => $outputs,
            'params' => [],
        ];

        return $this;
    }

    /**
     * Remove a node and its connections.
     */
    public function removeNode(string $nodeId): self
    {
        // Remove connected connections first
        foreach ($this->connectionDefs as $cid => $conn) {
            if ($conn['source_node'] === $nodeId || $conn['target_node'] === $nodeId) {
                $this->removeConnection($cid);
            }
        }

        qt_node_canvas_remove_node($this->handle, $nodeId);
        unset($this->nodeDefs[$nodeId]);

        return $this;
    }

    /**
     * Add a connection between two node ports.
     */
    public function addConnection(
        string $connectionId,
        string $sourceNodeId,
        string $sourcePort,
        string $targetNodeId,
        string $targetPort,
    ): self {
        qt_node_canvas_add_connection(
            $this->handle,
            $connectionId,
            $sourceNodeId,
            $sourcePort,
            $targetNodeId,
            $targetPort,
        );
        $this->connectionDefs[$connectionId] = [
            'source_node' => $sourceNodeId,
            'source_port' => $sourcePort,
            'target_node' => $targetNodeId,
            'target_port' => $targetPort,
        ];

        return $this;
    }

    /**
     * Remove a connection.
     */
    public function removeConnection(string $connectionId): self
    {
        qt_node_canvas_remove_connection($this->handle, $connectionId);
        unset($this->connectionDefs[$connectionId]);

        return $this;
    }

    /**
     * Move a node to a new position.
     */
    public function moveNode(string $nodeId, int $x, int $y): self
    {
        qt_node_canvas_move_node($this->handle, $nodeId, $x, $y);

        if (isset($this->nodeDefs[$nodeId])) {
            $this->nodeDefs[$nodeId]['x'] = $x;
            $this->nodeDefs[$nodeId]['y'] = $y;
        }

        return $this;
    }

    /**
     * Get node position.
     *
     * @return array{x: int, y: int}|null
     */
    public function getNodePosition(string $nodeId): ?array
    {
        $pos = qt_node_canvas_get_node_position($this->handle, $nodeId);

        return $pos ?: null;
    }

    /**
     * Get all nodes.
     *
     * @return array List of node info arrays
     */
    public function getNodes(): array
    {
        return qt_node_canvas_get_nodes($this->handle);
    }

    /**
     * Get all connections.
     *
     * @return array List of connection info arrays
     */
    public function getConnections(): array
    {
        return qt_node_canvas_get_connections($this->handle);
    }

    /**
     * Clear all nodes and connections.
     */
    public function clear(): self
    {
        qt_node_canvas_clear($this->handle);
        $this->nodeDefs = [];
        $this->connectionDefs = [];

        return $this;
    }

    /**
     * Set grid visibility.
     */
    public function setGridVisible(bool $visible): self
    {
        qt_node_canvas_set_grid_visible($this->handle, $visible);

        return $this;
    }

    /**
     * Set snap-to-grid.
     */
    public function setSnapToGrid(bool $enabled, int $gridSize = 20): self
    {
        qt_node_canvas_set_snap_to_grid($this->handle, $enabled, $gridSize);

        return $this;
    }

    /**
     * Set zoom factor.
     */
    public function setZoom(float $factor): self
    {
        qt_node_canvas_set_zoom($this->handle, $factor);

        return $this;
    }

    /**
     * Fit all nodes in view.
     */
    public function fitInView(): self
    {
        qt_node_canvas_fit_in_view($this->handle);

        return $this;
    }

    /**
     * Show the canvas in a dedicated window.
     * Creates the window on first call; subsequent calls re-show it.
     */
    public function show(): self
    {
        qt_node_canvas_show($this->handle);

        return $this;
    }

    /**
     * Set a node's parameter value.
     */
    public function setNodeParam(string $nodeId, string $paramName, mixed $value): self
    {
        qt_node_canvas_set_node_param($this->handle, $nodeId, $paramName, $value);

        if (isset($this->nodeDefs[$nodeId])) {
            $this->nodeDefs[$nodeId]['params'][$paramName] = $value;
        }

        return $this;
    }

    /**
     * Get a node's parameter value.
     */
    public function getNodeParam(string $nodeId, string $paramName): mixed
    {
        return $this->nodeDefs[$nodeId]['params'][$paramName] ?? null;
    }

    /**
     * Register an event handler.
     *
     * @param string $eventType Event type ('node_moved', 'node_selected', etc.)
     * @param callable $handler Function(array $event): void
     */
    public function on(string $eventType, callable $handler): void
    {
        if (!isset($this->handlers[$eventType])) {
            $this->handlers[$eventType] = [];
        }
        $this->handlers[$eventType][] = $handler;
    }

    /**
     * Poll and dispatch events.
     * Call this regularly from the application event loop.
     */
    public function pollEvents(): void
    {
        while (true) {
            $event = qt_node_canvas_poll_event();
            if (null === $event) {
                break;
            }

            $type = $event['type'] ?? 'unknown';
            $handlers = $this->handlers[$type] ?? [];

            foreach ($handlers as $handler) {
                try {
                    $handler($event);
                } catch (\Throwable $e) {
                    fprintf(STDERR, "Node event handler error [{$type}]: {$e->getMessage()}\n");
                }
            }
        }
    }

    /**
     * Get the native canvas handle.
     */
    public function getHandle(): int
    {
        return $this->handle;
    }

    /**
     * Check if the canvas was created successfully.
     */
    public function isCreated(): bool
    {
        return $this->created;
    }

    /**
     * Get node definitions (PHP-managed state).
     */
    public function getNodeDefs(): array
    {
        return $this->nodeDefs;
    }

    /**
     * Destroy the canvas.
     */
    public function destroy(): void
    {
        qt_node_canvas_destroy($this->handle);
        $this->created = false;
    }
}
