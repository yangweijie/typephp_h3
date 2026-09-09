<?php

/**
 * H3PHP — ComfyUI Node Result.
 *
 * Represents a ComfyUI custom node / plugin search result.
 * Contains metadata for installation and compatibility.
 */

namespace H3Php\Core;

class ComfyNodeResult
{
    public string $id;

    public string $name;

    public string $description;

    public string $author;

    public int $downloads;

    public int $stars;

    /** @var string[] Python pip dependencies */
    public array $pipPackages;

    /** Install method: 'git-clone', 'pip', 'npm', 'easy-install' */
    public string $installMethod;

    public ?string $nodeVersion;

    public ?string $githubUrl;

    public ?string $license;

    public array $tags;

    public ?string $updatedAt;

    public function __construct(
        string $id,
        string $name,
        string $description = '',
        string $author = 'unknown',
        int $downloads = 0,
        int $stars = 0,
        array $pipPackages = [],
        string $installMethod = 'git-clone',
        ?string $nodeVersion = null,
        ?string $githubUrl = null,
        ?string $license = null,
        array $tags = [],
        ?string $updatedAt = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = strip_tags($description);
        $this->author = $author;
        $this->downloads = $downloads;
        $this->stars = $stars;
        $this->pipPackages = $pipPackages;
        $this->installMethod = $installMethod;
        $this->nodeVersion = $nodeVersion;
        $this->githubUrl = $githubUrl;
        $this->license = $license;
        $this->tags = $tags;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Get formatted stars count.
     */
    public function getStarsFormatted(): string
    {
        return match (true) {
            $this->stars >= 1000 => sprintf('%.1fK', $this->stars / 1000),
            default => (string) $this->stars,
        };
    }

    /**
     * Get formatted downloads count.
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
     * Get pip install command if applicable.
     */
    public function getPipCommand(): ?string
    {
        if (empty($this->pipPackages)) {
            return null;
        }

        return 'pip install ' . implode(' ', $this->pipPackages);
    }

    /**
     * Get git clone command if applicable.
     */
    public function getGitCloneCommand(string $comfyuiDir = 'ComfyUI/custom_nodes'): string
    {
        if (null !== $this->githubUrl) {
            $repoName = basename($this->githubUrl);

            return "cd {$comfyuiDir} && git clone {$this->githubUrl}";
        }

        return "# No git URL available for {$this->name}";
    }

    /**
     * Get one-line install summary.
     */
    public function getInstallSummary(): string
    {
        return match ($this->installMethod) {
            'pip' => $this->getPipCommand() ?? 'pip install ' . $this->name,
            'git-clone' => $this->getGitCloneCommand(),
            'npm' => "npx comfyui-install-npm {$this->name}",
            default => "Install via {$this->installMethod}",
        };
    }
}
