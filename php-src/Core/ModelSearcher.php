<?php

/**
 * H3PHP — Model Searcher.
 *
 * Keyword-based model search across multiple registries:
 * - HuggingFace (text-to-video, image-to-video models)
 * - ModelScope (Chinese mirror, popular for video models)
 * - CivitAI (community models, LoRAs, checkpoints)
 *
 * Each registry exposes a public JSON API — no authentication required
 * for search. Results are normalized into a unified SearchResult format.
 */

namespace H3Php\Core;

class ModelSearcher
{
    /** API endpoints */
    private const array ENDPOINTS = [
        'huggingface' => 'https://huggingface.co/api/models',
        'modelscope' => 'https://modelscope.cn/api/v1/models',
        'civitai' => 'https://civitai.com/api/v1/models',
    ];

    /** @var array<string, string> Base URLs for each source */
    private array $mirrors = [];

    /** Request timeout in seconds */
    private int $timeout;

    /** Proxy URL (optional) */
    private ?string $proxy;

    public function __construct(int $timeout = 15, ?string $proxy = null)
    {
        $this->timeout = $timeout;
        $this->proxy = $proxy;

        $this->mirrors = [
            'huggingface' => 'https://huggingface.co',
            'modelscope' => 'https://modelscope.cn',
            'civitai' => 'https://civitai.com',
        ];
    }

    /**
     * Search across all configured sources.
     *
     * @param string $query Search keyword
     * @param array $sources Sources to search: ['huggingface', 'modelscope', 'civitai']
     * @param int $limit Max results per source
     * @param string|null $modelType Filter by model type (e.g., 'text-to-video', 'image-to-video')
     * @return SearchResult[] Unified search results
     */
    public function searchAll(
        string $query,
        array $sources = ['huggingface', 'modelscope', 'civitai'],
        int $limit = 10,
        ?string $modelType = null,
    ): array {
        $allResults = [];

        foreach ($sources as $source) {
            $results = $this->search($query, $source, $limit, $modelType);
            $allResults = array_merge($allResults, $results);
        }

        // Sort by relevance score descending
        usort($allResults, fn (SearchResult $a, SearchResult $b) => $b->score <=> $a->score);

        return $allResults;
    }

    /**
     * Search a single source.
     *
     * @param string $query Search keyword
     * @param string $source Source name: 'huggingface', 'modelscope', 'civitai'
     * @param int $limit Max results
     * @param string|null $modelType Filter by model type
     * @return SearchResult[]
     */
    public function search(
        string $query,
        string $source = 'huggingface',
        int $limit = 10,
        ?string $modelType = null,
    ): array {
        $results = match ($source) {
            'huggingface' => $this->searchHuggingFace($query, $limit, $modelType),
            'modelscope' => $this->searchModelScope($query, $limit, $modelType),
            'civitai' => $this->searchCivitAI($query, $limit, $modelType),
            default => [],
        };

        return $results;
    }

    /**
     * Search HuggingFace models.
     *
     * API: GET https://huggingface.co/api/models?search=QUERY&sort=downloads&direction=-1&limit=N
     */
    private function searchHuggingFace(string $query, int $limit, ?string $modelType): array
    {
        $params = [
            'search' => $query,
            'sort' => 'downloads',
            'direction' => '-1',
            'limit' => (string) $limit,
        ];

        // Add pipeline tag filter if specified
        if (null !== $modelType) {
            $params['pipeline_tag'] = $modelType;
        }

        // Filter to video-related libraries
        $params['library'] = 'diffusers';

        $url = self::ENDPOINTS['huggingface'] . '?' . http_build_query($params);
        $data = $this->httpGetJson($url);

        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $item) {
            $results[] = new SearchResult(
                id: $item['id'] ?? '',
                name: $item['id'] ?? '',
                source: 'huggingface',
                description: $this->extractDescription($item),
                downloads: $item['downloads'] ?? 0,
                likes: $item['likes'] ?? 0,
                lastModified: $item['lastModified'] ?? null,
                pipelineTag: $item['pipeline_tag'] ?? null,
                libraryName: $item['library_name'] ?? null,
                url: $this->mirrors['huggingface'] . '/' . ($item['id'] ?? ''),
                tags: $item['tags'] ?? [],
                score: $this->calculateScore($item['downloads'] ?? 0, $item['likes'] ?? 0),
            );
        }

        return $results;
    }

    /**
     * Search ModelScope models.
     *
     * API: GET https://modelscope.cn/api/v1/models?search=QUERY&pageSize=N
     */
    private function searchModelScope(string $query, int $limit, ?string $modelType): array
    {
        // ModelScope expects non-standard query format — search param is at root
        $url = self::ENDPOINTS['modelscope']
            . '?PageSize=' . $limit
            . '&PageNumber=1'
            . '&search=' . urlencode($query);

        if (null !== $modelType) {
            $taskMap = [
                'text-to-video' => 'text-to-video',
                'image-to-video' => 'image-to-video',
                'text-to-image' => 'text-to-image',
            ];
            $url .= '&Task=' . urlencode($taskMap[$modelType] ?? $modelType);
        }

        $data = $this->httpGetJson($url);

        if (!is_array($data) || !isset($data['Data']['Models'])) {
            return [];
        }

        $results = [];
        foreach ($data['Data']['Models'] as $item) {
            $modelId = $item['Name'] ?? ($item['ModelId'] ?? '');
            $results[] = new SearchResult(
                id: $modelId,
                name: $modelId,
                source: 'modelscope',
                description: $item['Description'] ?? $item['Intro'] ?? '',
                downloads: $item['DownloadCount'] ?? 0,
                likes: $item['LikeCount'] ?? 0,
                lastModified: $item['GmtModified'] ?? null,
                pipelineTag: $item['Task'] ?? null,
                libraryName: $item['Framework'] ?? null,
                url: $this->mirrors['modelscope'] . '/models/' . $modelId,
                tags: $item['Tags'] ?? [],
                score: $this->calculateScore($item['DownloadCount'] ?? 0, $item['LikeCount'] ?? 0),
            );
        }

        return $results;
    }

    /**
     * Search CivitAI models.
     *
     * API: GET https://civitai.com/api/v1/models?query=QUERY&limit=N&sort=Most Downloaded
     */
    private function searchCivitAI(string $query, int $limit, ?string $modelType): array
    {
        $params = [
            'query' => $query,
            'limit' => (string) $limit,
            'sort' => 'Most Downloaded',
        ];

        // CivitAI uses numeric model types
        if (null !== $modelType) {
            $typeMap = [
                'checkpoint' => '1',
                'textualinversion' => '2',
                'hypernetwork' => '3',
                'lora' => '4',
                'controlnet' => '5',
                'poses' => '6',
                'esrgan' => '7',
                'motion module' => '8',
            ];
            $params['type'] = $typeMap[strtolower($modelType)] ?? null;
        }

        $url = self::ENDPOINTS['civitai'] . '?' . http_build_query(array_filter($params));
        $data = $this->httpGetJson($url, ['User-Agent: H3PHP/1.0']);

        if (!is_array($data) || !isset($data['items'])) {
            return [];
        }

        $results = [];
        foreach ($data['items'] as $item) {
            $modelId = (string) ($item['id'] ?? '');
            $stats = $item['stats'] ?? [];
            $results[] = new SearchResult(
                id: $modelId,
                name: $item['name'] ?? '',
                source: 'civitai',
                description: $item['description'] ?? '',
                downloads: $stats['downloadCount'] ?? 0,
                likes: $stats['favoriteCount'] ?? 0,
                lastModified: $item['updatedAt'] ?? $item['createdAt'] ?? null,
                pipelineTag: $item['type'] ?? null,
                libraryName: null,
                url: $this->mirrors['civitai'] . '/models/' . $modelId,
                tags: $item['tags'] ?? [],
                score: $this->calculateScore($stats['downloadCount'] ?? 0, $stats['favoriteCount'] ?? 0),
            );
        }

        return $results;
    }

    /**
     * Set a custom mirror for a source.
     */
    public function setMirror(string $source, string $url): self
    {
        $this->mirrors[$source] = rtrim($url, '/');

        return $this;
    }

    /**
     * Check if a source is reachable (useful for auto-selecting mirror).
     */
    public function isSourceReachable(string $source): bool
    {
        if (!isset(self::ENDPOINTS[$source])) {
            return false;
        }

        return $this->httpHead(self::ENDPOINTS[$source]);
    }

    /**
     * Get all sources sorted by reachability + latency.
     *
     * @return array<int, array{source: string, reachable: bool, latency_ms: int}>
     */
    public function getSourcesByLatency(): array
    {
        $results = [];

        foreach (array_keys(self::ENDPOINTS) as $source) {
            $start = microtime(true);
            $reachable = $this->isSourceReachable($source);
            $latency = (int) ((microtime(true) - $start) * 1000);

            $results[] = [
                'source' => $source,
                'reachable' => $reachable,
                'latency_ms' => $reachable ? $latency : PHP_INT_MAX,
            ];
        }

        usort($results, fn ($a, $b) => $a['latency_ms'] <=> $b['latency_ms']);

        return $results;
    }

    // ========================================================================
    // HTTP Helpers
    // ========================================================================

    /**
     * Perform an HTTP GET and decode JSON response.
     *
     * @return array|null Decoded JSON or null on failure
     */
    private function httpGetJson(string $url, array $headers = []): ?array
    {
        $contextOptions = [
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => array_merge([
                    'Accept: application/json',
                    'User-Agent: H3PHP-ModelSearcher/1.0',
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

    /**
     * Perform an HTTP HEAD request to check reachability.
     */
    private function httpHead(string $url): bool
    {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? ('https' === $scheme ? 443 : 80);

        if (empty($host)) {
            return false;
        }

        $timeout = min($this->timeout, 5);
        $transport = 'ssl';
        if ('https' === $scheme) {
            $transport = 'ssl';
        } elseif ('http' === $scheme) {
            $transport = 'tcp';
        }

        $connection = @stream_socket_client(
            "{$transport}://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true],
            ])
        );

        if (false === $connection) {
            return false;
        }

        fclose($connection);

        return true;
    }

    /**
     * Calculate a relevance score from downloads and likes.
     * Downloads are weighted more heavily (direct usage indicator).
     */
    private function calculateScore(int $downloads, int $likes): float
    {
        return ($downloads * 1.0) + ($likes * 5.0);
    }

    /**
     * Extract a short description from a HuggingFace model item.
     */
    private function extractDescription(array $item): string
    {
        // Try cardData first (from README frontmatter)
        if (!empty($item['cardData']['description'])) {
            return substr($item['cardData']['description'], 0, 200);
        }

        // Try the first line of the README if available
        if (!empty($item['readme'])) {
            $lines = explode("\n", $item['readme']);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ('' !== $trimmed && !str_starts_with($trimmed, '#')) {
                    return substr($trimmed, 0, 200);
                }
            }
        }

        return $item['id'] ?? '';
    }
}
