<?php

/**
 * H3PHP — ComfyUI Custom Node / Plugin Searcher.
 *
 * Searches the ComfyUI custom node registry for plugins, extensions,
 * and workflow components. Sources:
 * - ComfyUI Manager API (https://api.comfyregistry.org)
 * - GitHub search (fallback for nodes not in registry)
 *
 * The ComfyUI Manager maintains a registry of community-contributed
 * custom nodes with metadata including pip dependencies, install methods,
 * and compatibility info.
 */

namespace H3Php\Core;

class ComfyUISearcher
{
    /** ComfyUI Manager Registry API (community-maintained) */
    private const string COMFY_REGISTRY_API = 'https://api.comfyregistry.org';

    /** GitHub API as fallback */
    private const string GITHUB_API = 'https://api.github.com';

    /** Base URL for ComfyUI Manager */
    private string $registryBase;

    /** GitHub API base */
    private string $githubBase;

    /** Request timeout */
    private int $timeout;

    /** Proxy URL */
    private ?string $proxy;

    public function __construct(int $timeout = 15, ?string $proxy = null)
    {
        $this->timeout = $timeout;
        $this->proxy = $proxy;
        $this->registryBase = self::COMFY_REGISTRY_API;
        $this->githubBase = self::GITHUB_API;
    }

    /**
     * Search ComfyUI custom nodes/plugins.
     *
     * @param string $query Search keyword (e.g., "controlnet", "ipadapter", "animate")
     * @param int $limit Max results (default: 15)
     * @param string $sort Sort order: 'downloads', 'stars', 'updated', 'relevance'
     * @return ComfyNodeResult[] Search results
     */
    public function search(string $query, int $limit = 15, string $sort = 'relevance'): array
    {
        // Primary: ComfyUI Registry API
        $results = $this->searchRegistry($query, $limit);

        // Fallback: GitHub if registry returns too few results
        if (count($results) < 3) {
            $githubResults = $this->searchGitHub($query, $limit - count($results));
            $results = array_merge($results, $githubResults);
        }

        // Sort results
        $results = $this->sortResults($results, $sort);

        return array_slice($results, 0, $limit);
    }

    /**
     * Search by specific functionality category.
     *
     * Common categories in ComfyUI ecosystem:
     * - loader: Model loaders (CheckpointLoader, UNetLoader)
     * - conditioning: CLIP, IPAdapter, ControlNet
     * - sampling: KSampler variants, schedulers
     * - video: AnimateDiff, SVD, video processing
     * - upscaling: ESRGAN, Real-ESRGAN, SwinIR
     * - utility: Image manipulation, masking, cropping
     * - api: External service integrations
     *
     * @param string $category Functional category
     * @param int $limit Max results
     * @return ComfyNodeResult[]
     */
    public function searchByCategory(string $category, int $limit = 10): array
    {
        $categoryTags = [
            'controlnet' => ['controlnet', 'control-adapter'],
            'ipadapter' => ['ipadapter', 'ip-adapter', 'ip_adapter'],
            'animatediff' => ['animatediff', 'animate-diff', 'motion'],
            'upscale' => ['upscale', 'super-resolution', 'sr', 'esrgan'],
            'lora' => ['lora', 'lycoris', 'loha', 'lokr'],
            'inpainting' => ['inpaint', 'inpainting'],
            'video' => ['video', 'frame-interpolation', 'rife'],
            'utility' => ['utility', 'helper', 'tools'],
            'api' => ['api', 'external', 'remote'],
            'loader' => ['loader', 'model-loader'],
            'sampling' => ['sampler', 'scheduler', 'ksampler'],
            'mask' => ['mask', 'segmentation', 'matting'],
        ];

        $tags = $categoryTags[strtolower($category)] ?? [strtolower($category)];

        // Build a combined search with all tags
        $allResults = [];
        foreach ($tags as $tag) {
            $results = $this->searchRegistry($tag, $limit);
            foreach ($results as $result) {
                $key = $result->id;
                if (!isset($allResults[$key])) {
                    $allResults[$key] = $result;
                }
            }
        }

        return array_slice(array_values($allResults), 0, $limit);
    }

    /**
     * Search the ComfyUI Registry API.
     *
     * Registry API endpoint: GET /nodes?query=SEARCH
     */
    private function searchRegistry(string $query, int $limit): array
    {
        $url = $this->registryBase . '/nodes?query=' . urlencode($query) . '&limit=' . $limit;
        $data = $this->httpGetJson($url);

        if (!is_array($data)) {
            return [];
        }

        $results = [];

        // Registry API returns either a list of nodes or a single node
        $nodes = $data['nodes'] ?? ($data['data'] ?? (isset($data['id']) ? [$data] : $data));

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $results[] = new ComfyNodeResult(
                id: $node['id'] ?? $node['name'] ?? '',
                name: $node['title'] ?? $node['name'] ?? '',
                description: $node['description'] ?? '',
                author: $node['author'] ?? $node['publisher'] ?? 'unknown',
                downloads: $node['downloads'] ?? 0,
                stars: $node['stars'] ?? $node['github_stars'] ?? 0,
                pipPackages: $node['pip'] ?? $node['pip_packages'] ?? [],
                installMethod: $node['install_type'] ?? 'git-clone',
                nodeVersion: $node['version'] ?? null,
                githubUrl: $node['github'] ?? $node['repository'] ?? null,
                license: $node['license'] ?? null,
                tags: $node['tags'] ?? [],
                updatedAt: $node['updated_at'] ?? $node['updatedAt'] ?? null,
            );
        }

        return $results;
    }

    /**
     * Fallback: Search GitHub for ComfyUI custom nodes.
     *
     * Search query: "ComfyUI {keyword}" in repositories
     */
    private function searchGitHub(string $query, int $limit): array
    {
        $searchQuery = urlencode("ComfyUI {$query}");
        $url = $this->githubBase . "/search/repositories?q={$searchQuery}&sort=stars&order=desc&per_page={$limit}";
        $data = $this->httpGetJson($url, ['User-Agent: H3PHP-ComfyUISearcher/1.0']);

        if (!is_array($data) || !isset($data['items'])) {
            return [];
        }

        $results = [];

        foreach ($data['items'] as $item) {
            $results[] = new ComfyNodeResult(
                id: (string) ($item['id'] ?? ''),
                name: $item['name'] ?? '',
                description: $item['description'] ?? '',
                author: $item['owner']['login'] ?? 'unknown',
                downloads: 0, // GitHub doesn't track downloads for repos
                stars: $item['stargazers_count'] ?? 0,
                pipPackages: [],
                installMethod: 'git-clone',
                nodeVersion: null,
                githubUrl: $item['html_url'] ?? null,
                license: $item['license']['spdx_id'] ?? null,
                tags: $item['topics'] ?? [],
                updatedAt: $item['updated_at'] ?? null,
            );
        }

        return $results;
    }

    /**
     * Sort results by the specified criteria.
     *
     * @param ComfyNodeResult[] $results
     * @return ComfyNodeResult[]
     */
    private function sortResults(array $results, string $sort): array
    {
        usort($results, function (ComfyNodeResult $a, ComfyNodeResult $b) use ($sort): int {
            return match ($sort) {
                'downloads' => $b->downloads <=> $a->downloads,
                'stars' => $b->stars <=> $a->stars,
                'updated' => ($b->updatedAt ?? '') <=> ($a->updatedAt ?? ''),
                default => ($b->stars + $b->downloads) <=> ($a->stars + $a->downloads),
            };
        });

        return $results;
    }

    /**
     * Get a single node's details by ID.
     */
    public function getNodeDetails(string $nodeId): ?ComfyNodeResult
    {
        $url = $this->registryBase . '/nodes/' . urlencode($nodeId);
        $data = $this->httpGetJson($url);

        if (!is_array($data) || empty($data['id'])) {
            return null;
        }

        return new ComfyNodeResult(
            id: $data['id'] ?? '',
            name: $data['title'] ?? $data['name'] ?? '',
            description: $data['description'] ?? '',
            author: $data['author'] ?? $data['publisher'] ?? 'unknown',
            downloads: $data['downloads'] ?? 0,
            stars: $data['stars'] ?? $data['github_stars'] ?? 0,
            pipPackages: $data['pip'] ?? $data['pip_packages'] ?? [],
            installMethod: $data['install_type'] ?? 'git-clone',
            nodeVersion: $data['version'] ?? null,
            githubUrl: $data['github'] ?? $data['repository'] ?? null,
            license: $data['license'] ?? null,
            tags: $data['tags'] ?? [],
            updatedAt: $data['updated_at'] ?? $data['updatedAt'] ?? null,
        );
    }

    /**
     * Get popular/trending nodes from the registry.
     *
     * @param int $limit Number of results
     * @param string $period 'daily', 'weekly', 'monthly'
     * @return ComfyNodeResult[]
     */
    public function getPopular(int $limit = 10, string $period = 'weekly'): array
    {
        $url = $this->registryBase . '/nodes?sort=popularity&period=' . $period . '&limit=' . $limit;
        $data = $this->httpGetJson($url);

        if (!is_array($data)) {
            return [];
        }

        $nodes = $data['nodes'] ?? ($data['data'] ?? $data);
        $results = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $results[] = new ComfyNodeResult(
                id: $node['id'] ?? $node['name'] ?? '',
                name: $node['title'] ?? $node['name'] ?? '',
                description: $node['description'] ?? '',
                author: $node['author'] ?? $node['publisher'] ?? 'unknown',
                downloads: $node['downloads'] ?? 0,
                stars: $node['stars'] ?? $node['github_stars'] ?? 0,
                pipPackages: $node['pip'] ?? $node['pip_packages'] ?? [],
                installMethod: $node['install_type'] ?? 'git-clone',
                nodeVersion: $node['version'] ?? null,
                githubUrl: $node['github'] ?? $node['repository'] ?? null,
                license: $node['license'] ?? null,
                tags: $node['tags'] ?? [],
                updatedAt: $node['updated_at'] ?? $node['updatedAt'] ?? null,
            );
        }

        return $results;
    }

    /**
     * HTTP GET with JSON response.
     */
    private function httpGetJson(string $url, array $headers = []): ?array
    {
        $contextOptions = [
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => array_merge([
                    'Accept: application/json',
                    'User-Agent: H3PHP-ComfyUISearcher/1.0',
                ], $headers),
                'follow_location' => true,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        if (null !== $this->proxy) {
            $contextOptions['http']['proxy'] = $this->proxy;
            $contextOptions['http']['request_fulluri'] = true;
        }

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        if (false === $response) {
            return null;
        }

        $data = json_decode($response, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return null;
        }

        return $data;
    }
}
