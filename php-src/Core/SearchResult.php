<?php

/**
 * H3PHP — Search Result.
 *
 * Unified search result format across all model registries
 * (HuggingFace, ModelScope, CivitAI, ComfyUI).
 */

namespace H3Php\Core;

class SearchResult
{
    public string $id;

    public string $name;

    public string $source;

    public string $description;

    public int $downloads;

    public int $likes;

    public ?string $lastModified;

    public ?string $pipelineTag;

    public ?string $libraryName;

    public string $url;

    public array $tags;

    public float $score;

    // ComfyUI-specific fields
    public ?string $author = null;

    public ?string $license = null;

    public ?int $stars = null;

    public function __construct(
        string $id,
        string $name,
        string $source,
        string $description = '',
        int $downloads = 0,
        int $likes = 0,
        ?string $lastModified = null,
        ?string $pipelineTag = null,
        ?string $libraryName = null,
        string $url = '',
        array $tags = [],
        float $score = 0,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->source = $source;
        $this->description = strip_tags($description);
        $this->downloads = $downloads;
        $this->likes = $likes;
        $this->lastModified = $lastModified;
        $this->pipelineTag = $pipelineTag;
        $this->libraryName = $libraryName;
        $this->url = $url;
        $this->tags = $tags;
        $this->score = $score;
    }

    /**
     * Format downloads count for display.
     */
    public function getDownloadsFormatted(): string
    {
        return match (true) {
            $this->downloads >= 1000000 => sprintf('%.1fM', $this->downloads / 1000000),
            $this->downloads >= 1000 => sprintf('%.1fK', $this->downloads / 1000),
            default => (string) $this->downloads,
        };
    }

    /**
     * Get source badge with emoji/icon prefix.
     */
    public function getSourceBadge(): string
    {
        return match ($this->source) {
            'huggingface' => '🤗 HF',
            'modelscope' => '🔬 MS',
            'civitai' => '🎨 CivitAI',
            'comfyui' => '🧩 ComfyUI',
            default => '❓ ' . ucfirst($this->source),
        };
    }

    /**
     * Convert to a display array (for CLI tables).
     */
    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source' => $this->source,
            'downloads' => $this->getDownloadsFormatted(),
            'likes' => $this->likes,
            'type' => $this->pipelineTag ?? 'unknown',
            'description' => substr($this->description, 0, 80),
            'url' => $this->url,
        ];
    }
}
