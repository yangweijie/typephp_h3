<?php

/**
 * H3PHP — Model Component.
 *
 * Represents a single model component (weights, tokenizer, encoder, etc.)
 * with metadata for download and validation.
 */

namespace H3Php\Core;

class ModelComponent
{
    /** Component identifier (e.g., "transformer", "video_vae") */
    public string $id;

    /** Human-readable name */
    public string $name;

    /** Size in GB */
    public float $sizeGb;

    /** ModelScope model ID for download (e.g., "modelscope/MiniMaxAI/H3-DiT") */
    public string $modelscopeId;

    /** HuggingFace model ID (alternative source) */
    public ?string $huggingfaceId;

    /** Required files within the repo (empty = all files) */
    public array $requiredFiles;

    /** SHA256 checksums for key files [filename => sha256] */
    public array $checksums;

    public function __construct(
        string $id,
        string $name,
        float $sizeGb,
        string $modelscopeId,
        ?string $huggingfaceId = null,
        array $requiredFiles = [],
        array $checksums = [],
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->sizeGb = $sizeGb;
        $this->modelscopeId = $modelscopeId;
        $this->huggingfaceId = $huggingfaceId;
        $this->requiredFiles = $requiredFiles;
        $this->checksums = $checksums;
    }

    /**
     * Get formatted size string.
     */
    public function getFormattedSize(): string
    {
        if ($this->sizeGb >= 1) {
            return sprintf('%.1f GB', $this->sizeGb);
        }

        return sprintf('%.0f MB', $this->sizeGb * 1024);
    }

    /**
     * Convert to array for UI display.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'size_gb' => $this->sizeGb,
            'size_formatted' => $this->getFormattedSize(),
            'modelscope_id' => $this->modelscopeId,
            'huggingface_id' => $this->huggingfaceId,
            'required_files' => $this->requiredFiles,
        ];
    }
}
