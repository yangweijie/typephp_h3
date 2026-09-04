<?php
/**
 * H3PHP — Model Loader
 *
 * Validates and scans MiniMax-H3 model directory structure.
 * Expected layout (from h3.c documentation):
 *
 *   MODEL_DIR/
 *   +-- FL2VA/
 *   |   +-- transformer/config.json
 *   |   +-- tokenizer/tokenizer.json
 *   |   +-- text_encoder/           (optional with ClipProj)
 *   |   +-- video_vae/source/
 *   |   +-- audio_vae/
 *   +-- Ref2VA/                     (optional, for reference mode)
 *       +-- transformer/
 *       +-- tokenizer/tokenizer.json
 *       +-- text_encoder/
 *       +-- video_vae/source/
 *       +-- audio_vae/
 */

namespace H3Php\Core;

use H3Php\Cli\Application;

class ModelLoader
{
    private Application $app;

    /** Required files for FL2VA model */
    private const array FL2VA_REQUIRED = [
        'FL2VA/transformer/config.json',
        'FL2VA/tokenizer/tokenizer.json',
    ];

    /** Optional files for FL2VA model */
    private const array FL2VA_OPTIONAL = [
        'FL2VA/text_encoder',
        'FL2VA/video_vae/source',
        'FL2VA/audio_vae',
    ];

    /** Required files for Ref2VA model */
    private const array REF2VA_REQUIRED = [
        'Ref2VA/transformer/config.json',
        'Ref2VA/tokenizer/tokenizer.json',
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Scan model directory and return inventory of components.
     *
     * @return array<string, array{present: bool, files: int, tensors: int, size_gib: float}>
     */
    public function scanDirectory(string $modelDir): array
    {
        $inventory = [];

        // FL2VA components
        $inventory['FL2VA/transformer'] = $this->scanComponent($modelDir, 'FL2VA/transformer');
        $inventory['FL2VA/tokenizer'] = $this->scanComponent($modelDir, 'FL2VA/tokenizer');
        $inventory['FL2VA/text_encoder'] = $this->scanComponent($modelDir, 'FL2VA/text_encoder');
        $inventory['FL2VA/video_vae'] = $this->scanComponent($modelDir, 'FL2VA/video_vae');
        $inventory['FL2VA/audio_vae'] = $this->scanComponent($modelDir, 'FL2VA/audio_vae');

        // Ref2VA components
        $inventory['Ref2VA/transformer'] = $this->scanComponent($modelDir, 'Ref2VA/transformer');
        $inventory['Ref2VA/tokenizer'] = $this->scanComponent($modelDir, 'Ref2VA/tokenizer');
        $inventory['Ref2VA/text_encoder'] = $this->scanComponent($modelDir, 'Ref2VA/text_encoder');
        $inventory['Ref2VA/video_vae'] = $this->scanComponent($modelDir, 'Ref2VA/video_vae');
        $inventory['Ref2VA/audio_vae'] = $this->scanComponent($modelDir, 'Ref2VA/audio_vae');

        return $inventory;
    }

    /**
     * Scan a single model component directory.
     */
    private function scanComponent(string $modelDir, string $relativePath): array
    {
        $fullPath = $modelDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_dir($fullPath)) {
            return [
                'present' => false,
                'files' => 0,
                'tensors' => 0,
                'size_gib' => 0.0,
            ];
        }

        $files = 0;
        $totalBytes = 0;
        $safetensorsFiles = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files++;
                $totalBytes += $file->getSize();
                if (str_ends_with($file->getFilename(), '.safetensors')) {
                    $safetensorsFiles++;
                }
            }
        }

        return [
            'present' => true,
            'files' => $files,
            'tensors' => $safetensorsFiles, // Approximation: each safetensors file contains multiple tensors
            'size_gib' => round($totalBytes / (1024 * 1024 * 1024), 3),
        ];
    }

    /**
     * Validate that the model directory has minimum required structure.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(string $modelDir): array
    {
        $errors = [];

        if (!is_dir($modelDir)) {
            return ['valid' => false, 'errors' => ["Model directory not found: {$modelDir}"]];
        }

        // Check FL2VA required files
        foreach (self::FL2VA_REQUIRED as $required) {
            $path = $modelDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $required);
            if (!file_exists($path)) {
                $errors[] = "Missing required file: {$required}";
            }
        }

        // Check that at least one of the model directories exists
        $fl2vaExists = is_dir($modelDir . DIRECTORY_SEPARATOR . 'FL2VA');
        $ref2vaExists = is_dir($modelDir . DIRECTORY_SEPARATOR . 'Ref2VA');

        if (!$fl2vaExists && !$ref2vaExists) {
            $errors[] = 'Neither FL2VA/ nor Ref2VA/ directory found';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get the model configuration from transformer/config.json.
     */
    public function getModelConfig(string $modelDir, string $stream = 'FL2VA'): ?array
    {
        $configPath = $modelDir . DIRECTORY_SEPARATOR . $stream . DIRECTORY_SEPARATOR . 'transformer' . DIRECTORY_SEPARATOR . 'config.json';

        if (!file_exists($configPath)) {
            return null;
        }

        $content = file_get_contents($configPath);
        return json_decode($content, true);
    }
}
