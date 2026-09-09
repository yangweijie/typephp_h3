<?php

/**
 * H3PHP — Node Item.
 *
 * Represents a single node in the node graph.
 * This is a PHP-side data model; the visual representation
 * is managed by the C++ NodeGraphicsItem.
 */

namespace H3Php\Qt;

class NodeItem
{
    /** Unique node identifier */
    public string $id;

    /** Display title */
    public string $title;

    /** Node type (e.g., 'LoadModel', 'KSampler', 'VAEDecode') */
    public string $type;

    /** Position X */
    public int $x;

    /** Position Y */
    public int $y;

    /** Input ports */
    public array $inputs;

    /** Output ports */
    public array $outputs;

    /** Parameter values */
    public array $params;

    /** Whether the node is selected */
    public bool $selected;

    public function __construct(
        string $id,
        string $title,
        string $type,
        int $x = 0,
        int $y = 0,
        array $inputs = [],
        array $outputs = [],
        array $params = [],
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->type = $type;
        $this->x = $x;
        $this->y = $y;
        $this->inputs = $inputs;
        $this->outputs = $outputs;
        $this->params = $params;
        $this->selected = false;
    }

    /**
     * Create a node from a definition array.
     */
    public static function fromArray(array $def): self
    {
        return new self(
            $def['id'],
            $def['title'],
            $def['type'],
            $def['x'] ?? 0,
            $def['y'] ?? 0,
            $def['inputs'] ?? [],
            $def['outputs'] ?? [],
            $def['params'] ?? [],
        );
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'x' => $this->x,
            'y' => $this->y,
            'inputs' => $this->inputs,
            'outputs' => $this->outputs,
            'params' => $this->params,
            'selected' => $this->selected,
        ];
    }

    /**
     * Set a parameter value.
     */
    public function setParam(string $name, mixed $value): self
    {
        $this->params[$name] = $value;

        return $this;
    }

    /**
     * Get a parameter value.
     */
    public function getParam(string $name): mixed
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Move to a new position.
     */
    public function moveTo(int $x, int $y): self
    {
        $this->x = $x;
        $this->y = $y;

        return $this;
    }
}
