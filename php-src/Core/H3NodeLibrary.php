<?php

/**
 * H3PHP — H3 Node Library.
 *
 * Defines all MiniMax-H3 node types for the ComfyUI workflow editor.
 * Each node has: type, ComfyUI type mapping, ports, parameters, colors.
 *
 * Node types:
 * - LoadH3Model: Load transformer + VAE
 * - H3TextEncode: ClipProj text encoding
 * - H3KSampler: DiT denoising loop
 * - H3VAEDecode: Video VAE decode
 * - H3VideoCombine: Output MP4
 */

namespace H3Php\Core;

class H3NodeLibrary
{
    /**
     * All node definitions.
     * Key = our node type, Value = definition array.
     */
    private static array $nodes = [
        'LoadH3Model' => [
            'comfy_type' => 'H3ModelLoader',
            'title' => 'Load H3 Model',
            'category' => 'loaders',
            'color' => '#2d4a7a',
            'bgcolor' => '#1a3a5c',
            'inputs' => [],
            'outputs' => [
                ['name' => 'model', 'type' => 'model'],
                ['name' => 'vae', 'type' => 'vae'],
                ['name' => 'clip', 'type' => 'clip'],
            ],
            'params' => [
                ['name' => 'model_path', 'type' => 'string', 'label' => 'Model Path', 'default' => ''],
                ['name' => 'vae_path', 'type' => 'string', 'label' => 'VAE Path', 'default' => ''],
                ['name' => 'text_encoder_path', 'type' => 'string', 'label' => 'Text Encoder', 'default' => ''],
            ],
            'required_params' => ['model_path'],
        ],

        'H3TextEncode' => [
            'comfy_type' => 'CLIPTextEncode',
            'title' => 'H3 Text Encode',
            'category' => 'conditioning',
            'color' => '#3a5a3a',
            'bgcolor' => '#2a4a2a',
            'inputs' => [
                ['name' => 'clip', 'type' => 'clip'],
            ],
            'outputs' => [
                ['name' => 'conditioning', 'type' => 'conditioning'],
            ],
            'params' => [
                ['name' => 'text', 'type' => 'text', 'label' => 'Prompt', 'default' => ''],
                ['name' => 'negative_text', 'type' => 'text', 'label' => 'Negative', 'default' => ''],
            ],
            'required_params' => ['text'],
        ],

        'H3KSampler' => [
            'comfy_type' => 'KSampler',
            'title' => 'H3 KSampler',
            'category' => 'sampling',
            'color' => '#5a3a5a',
            'bgcolor' => '#4a2a4a',
            'inputs' => [
                ['name' => 'model', 'type' => 'model'],
                ['name' => 'positive', 'type' => 'conditioning'],
                ['name' => 'negative', 'type' => 'conditioning'],
                ['name' => 'latent', 'type' => 'latent'],
            ],
            'outputs' => [
                ['name' => 'latent', 'type' => 'latent'],
            ],
            'params' => [
                ['name' => 'seed', 'type' => 'int', 'label' => 'Seed', 'default' => 0],
                ['name' => 'steps', 'type' => 'int', 'label' => 'Steps', 'default' => 20],
                ['name' => 'cfg', 'type' => 'float', 'label' => 'CFG', 'default' => 7.5],
                ['name' => 'sampler', 'type' => 'string', 'label' => 'Sampler', 'default' => 'euler'],
                ['name' => 'scheduler', 'type' => 'string', 'label' => 'Scheduler', 'default' => 'normal'],
                ['name' => 'denoise', 'type' => 'float', 'label' => 'Denoise', 'default' => 1.0],
                ['name' => 'width', 'type' => 'int', 'label' => 'Width', 'default' => 512],
                ['name' => 'height', 'type' => 'int', 'label' => 'Height', 'default' => 512],
                ['name' => 'frames', 'type' => 'int', 'label' => 'Frames', 'default' => 22],
            ],
            'required_params' => ['steps', 'width', 'height', 'frames'],
        ],

        'H3VAEDecode' => [
            'comfy_type' => 'VAEDecode',
            'title' => 'H3 VAE Decode',
            'category' => 'latent',
            'color' => '#5a4a2a',
            'bgcolor' => '#4a3a1a',
            'inputs' => [
                ['name' => 'samples', 'type' => 'latent'],
                ['name' => 'vae', 'type' => 'vae'],
            ],
            'outputs' => [
                ['name' => 'image', 'type' => 'image'],
            ],
            'params' => [
                ['name' => 'tile_size', 'type' => 'int', 'label' => 'Tile Size', 'default' => 256],
            ],
            'required_params' => [],
        ],

        'H3VideoCombine' => [
            'comfy_type' => 'VHS_VideoCombine',
            'title' => 'H3 Video Combine',
            'category' => 'output',
            'color' => '#4a2a2a',
            'bgcolor' => '#3a1a1a',
            'inputs' => [
                ['name' => 'images', 'type' => 'image'],
            ],
            'outputs' => [],
            'params' => [
                ['name' => 'output_path', 'type' => 'string', 'label' => 'Output Path', 'default' => 'output/'],
                ['name' => 'fps', 'type' => 'int', 'label' => 'FPS', 'default' => 24],
                ['name' => 'format', 'type' => 'string', 'label' => 'Format', 'default' => 'video/h264-mp4'],
                ['name' => 'loop_count', 'type' => 'int', 'label' => 'Loops', 'default' => 0],
            ],
            'required_params' => ['output_path'],
        ],
    ];

    /**
     * Get a node definition by type.
     */
    public static function get(string $type): array
    {
        return self::$nodes[$type] ?? [
            'comfy_type' => $type,
            'title' => $type,
            'category' => 'unknown',
            'color' => '#333333',
            'bgcolor' => '#222222',
            'inputs' => [],
            'outputs' => [],
            'params' => [],
            'required_params' => [],
        ];
    }

    /**
     * Get all node definitions.
     */
    public static function getAll(): array
    {
        return self::$nodes;
    }

    /**
     * Get node types by category.
     *
     * @return array<string, string[]> Category => [node types]
     */
    public static function getByCategory(): array
    {
        $categories = [];
        foreach (self::$nodes as $type => $def) {
            $cat = $def['category'] ?? 'unknown';
            if (!isset($categories[$cat])) {
                $categories[$cat] = [];
            }
            $categories[$cat][] = $type;
        }

        return $categories;
    }

    /**
     * Map ComfyUI type to our node type.
     */
    public static function mapFromComfyType(string $comfyType): string
    {
        foreach (self::$nodes as $type => $def) {
            if (($def['comfy_type'] ?? '') === $comfyType) {
                return $type;
            }
        }

        return $comfyType;
    }

    /**
     * Get required parameter names for a node type.
     */
    public static function getRequiredParams(string $type): array
    {
        $def = self::get($type);

        return $def['required_params'] ?? [];
    }

    /**
     * Get parameter names for a node type.
     */
    public static function getParamNames(string $type): array
    {
        $def = self::get($type);
        $names = [];
        foreach ($def['params'] ?? [] as $param) {
            $names[] = $param['name'];
        }

        return $names;
    }

    /**
     * Create a NodeItem from a node type.
     */
    public static function createNode(string $type, string $id, int $x = 0, int $y = 0): \H3Php\Qt\NodeItem
    {
        $def = self::get($type);

        $inputs = [];
        foreach ($def['inputs'] ?? [] as $input) {
            $inputs[] = $input;
        }

        $outputs = [];
        foreach ($def['outputs'] ?? [] as $output) {
            $outputs[] = $output;
        }

        $params = [];
        foreach ($def['params'] ?? [] as $param) {
            $params[$param['name']] = $param['default'];
        }

        return new \H3Php\Qt\NodeItem(
            $id,
            $def['title'] ?? $type,
            $type,
            $x,
            $y,
            $inputs,
            $outputs,
            $params,
        );
    }

    /**
     * Get the default H3 video generation workflow.
     */
    public static function getDefaultWorkflow(): WorkflowGraph
    {
        $graph = new WorkflowGraph('H3 Video Generation');

        // Create nodes
        $loader = self::createNode('LoadH3Model', 'loader_1', 50, 200);
        $textEnc = self::createNode('H3TextEncode', 'textenc_1', 300, 100);
        $sampler = self::createNode('H3KSampler', 'sampler_1', 550, 200);
        $vaeDecode = self::createNode('H3VAEDecode', 'vae_1', 800, 200);
        $videoOut = self::createNode('H3VideoCombine', 'video_1', 1050, 200);

        // Set default params
        $textEnc->setParam('text', 'a red fox in snow');
        $sampler->setParam('steps', 20);
        $sampler->setParam('width', 512);
        $sampler->setParam('height', 512);
        $sampler->setParam('frames', 22);
        $videoOut->setParam('output_path', 'output/');
        $videoOut->setParam('fps', 24);

        // Add nodes
        $graph->addNode($loader);
        $graph->addNode($textEnc);
        $graph->addNode($sampler);
        $graph->addNode($vaeDecode);
        $graph->addNode($videoOut);

        // Add connections
        $graph->addConnection(new \H3Php\Qt\ConnectionItem(
            'c1', 'loader_1', 'model', 'sampler_1', 'model',
        ));
        $graph->addConnection(new \H3Php\Qt\ConnectionItem(
            'c2', 'loader_1', 'clip', 'textenc_1', 'clip',
        ));
        $graph->addConnection(new \H3Php\Qt\ConnectionItem(
            'c3', 'textenc_1', 'conditioning', 'sampler_1', 'positive',
        ));
        $graph->addConnection(new \H3Php\Qt\ConnectionItem(
            'c4', 'sampler_1', 'latent', 'vae_1', 'samples',
        ));
        $graph->addConnection(new \H3Php\Qt\ConnectionItem(
            'c5', 'loader_1', 'vae', 'vae_1', 'vae',
        ));
        $graph->addConnection(new \H3Php\Qt\ConnectionItem(
            'c6', 'vae_1', 'image', 'video_1', 'images',
        ));

        return $graph;
    }
}
