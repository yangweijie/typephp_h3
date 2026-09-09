<?php

/**
 * H3PHP — Qt Node Editor Stubs.
 *
 * PHP declarations for QGraphicsView-based node editor.
 * Implemented in cpp-src/qt_node_editor.cc via php_ prefix ABI.
 *
 * Architecture:
 * - NodeCanvas = QGraphicsView (the viewport)
 * - NodeScene = QGraphicsScene (owns all items)
 * - NodeItem = QGraphicsItem (a single node)
 * - ConnectionItem = QGraphicsPathItem (bezier curve between nodes)
 */

/**
 * Create a new node canvas (QGraphicsView + QGraphicsScene).
 * Returns an opaque canvas handle.
 */
function qt_node_canvas_create(): int {}

/**
 * Set canvas size.
 *
 * @param int $canvas Canvas handle
 * @param int $width Width in pixels
 * @param int $height Height in pixels
 */
function qt_node_canvas_set_size(int $canvas, int $width, int $height): void {}

/**
 * Add a node to the canvas.
 *
 * @param int $canvas Canvas handle
 * @param string $nodeId Unique node identifier
 * @param string $title Node title text
 * @param string $nodeType Node type (e.g., 'LoadModel', 'KSampler')
 * @param int $x X position
 * @param int $y Y position
 * @param array $inputs Input ports [['name' => 'model', 'type' => 'model']]
 * @param array $outputs Output ports [['name' => 'image', 'type' => 'image']]
 * @return int Node item handle
 */
function qt_node_canvas_add_node(
    int $canvas,
    string $nodeId,
    string $title,
    string $nodeType,
    int $x,
    int $y,
    array $inputs = [],
    array $outputs = [],
): int {}

/**
 * Remove a node from the canvas.
 *
 * @param int $canvas Canvas handle
 * @param string $nodeId Node identifier
 */
function qt_node_canvas_remove_node(int $canvas, string $nodeId): void {}

/**
 * Add a connection between two node ports.
 *
 * @param int $canvas Canvas handle
 * @param string $connectionId Unique connection identifier
 * @param string $sourceNodeId Source node
 * @param string $sourcePort Source port name
 * @param string $targetNodeId Target node
 * @param string $targetPort Target port name
 * @return int Connection item handle
 */
function qt_node_canvas_add_connection(
    int $canvas,
    string $connectionId,
    string $sourceNodeId,
    string $sourcePort,
    string $targetNodeId,
    string $targetPort,
): int {}

/**
 * Remove a connection.
 *
 * @param int $canvas Canvas handle
 * @param string $connectionId Connection identifier
 */
function qt_node_canvas_remove_connection(int $canvas, string $connectionId): void {}

/**
 * Move a node to a new position.
 *
 * @param int $canvas Canvas handle
 * @param string $nodeId Node identifier
 * @param int $x New X position
 * @param int $y New Y position
 */
function qt_node_canvas_move_node(int $canvas, string $nodeId, int $x, int $y): void {}

/**
 * Get node position.
 *
 * @param int $canvas Canvas handle
 * @param string $nodeId Node identifier
 * @return array{x: int, y: int}
 */
function qt_node_canvas_get_node_position(int $canvas, string $nodeId): array {}

/**
 * Get all nodes on the canvas.
 *
 * @param int $canvas Canvas handle
 * @return array List of node info arrays
 */
function qt_node_canvas_get_nodes(int $canvas): array {}

/**
 * Get all connections on the canvas.
 *
 * @param int $canvas Canvas handle
 * @return array List of connection info arrays
 */
function qt_node_canvas_get_connections(int $canvas): array {}

/**
 * Clear all nodes and connections from the canvas.
 *
 * @param int $canvas Canvas handle
 */
function qt_node_canvas_clear(int $canvas): void {}

/**
 * Set grid visibility.
 *
 * @param int $canvas Canvas handle
 * @param bool $visible Show grid
 */
function qt_node_canvas_set_grid_visible(int $canvas, bool $visible): void {}

/**
 * Set snap-to-grid.
 *
 * @param int $canvas Canvas handle
 * @param bool $snap Snap enabled
 * @param int $gridSize Grid size in pixels
 */
function qt_node_canvas_set_snap_to_grid(int $canvas, bool $snap, int $gridSize = 20): void {}

/**
 * Zoom the canvas view.
 *
 * @param int $canvas Canvas handle
 * @param float $factor Zoom factor (1.0 = 100%)
 */
function qt_node_canvas_set_zoom(int $canvas, float $factor): void {}

/**
 * Fit all nodes in view.
 *
 * @param int $canvas Canvas handle
 */
function qt_node_canvas_fit_in_view(int $canvas): void {}

/**
 * Show the canvas in a dedicated window (creates one on first call).
 *
 * @param int $canvas Canvas handle
 */
function qt_node_canvas_show(int $canvas): void {}

/**
 * Poll for node editor events.
 * Events: 'node_moved', 'node_selected', 'connection_created', 'connection_deleted', 'node_deleted'
 *
 * @return array|null Event array or null
 */
function qt_node_canvas_poll_event(): ?array {}

/**
 * Set node selected state.
 *
 * @param int $canvas Canvas handle
 * @param string $nodeId Node identifier
 * @param bool $selected Selected state
 */
function qt_node_canvas_set_node_selected(int $canvas, string $nodeId, bool $selected): void {}

/**
 * Set node parameter value.
 *
 * @param int $canvas Canvas handle
 * @param string $nodeId Node identifier
 * @param string $paramName Parameter name
 * @param mixed $value Parameter value
 */
function qt_node_canvas_set_node_param(int $canvas, string $nodeId, string $paramName, mixed $value): void {}

/**
 * Get node parameter value.
 *
 * @param int $canvas Canvas handle
 * @param string $nodeId Node identifier
 * @param string $paramName Parameter name
 * @return mixed Parameter value
 */
function qt_node_canvas_get_node_param(int $canvas, string $nodeId, string $paramName): mixed {}

/**
 * Destroy the canvas and all its items.
 *
 * @param int $canvas Canvas handle
 */
function qt_node_canvas_destroy(int $canvas): void {}
