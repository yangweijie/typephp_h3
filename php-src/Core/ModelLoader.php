<?php

/**
 * H3PHP — Model Loader.
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

class ModelLoader
{
    private ModelLayout $layout;

    public function __construct(ModelLayout $layout)
    {
        $this->layout = $layout;
    }

    /**
     * Scan model directory and return inventory of components.
     *
     * @return array<string, array{present: bool, files: int, tensors: int, size_gib: float}>
     */
    public function scanDirectory(): array
    {
        $inventory = [];

        foreach (['FL2VA', 'Ref2VA'] as $stream) {
            foreach (['transformer', 'tokenizer', 'text_encoder', 'video_vae', 'audio_vae'] as $component) {
                $inventory["{$stream}/{$component}"] = $this->scanComponent(
                    $this->layout->resolve($stream, $component)
                );
            }
        }

        return $inventory;
    }

    /**
     * Scan a single model component directory.
     */
    private function scanComponent(string $fullPath): array
    {
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
                ++$files;
                $totalBytes += $file->getSize();
                if (str_ends_with($file->getFilename(), '.safetensors')) {
                    ++$safetensorsFiles;
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
    public function validate(): array
    {
        $errors = [];

        if (!is_dir($this->layout->getModelDir())) {
            return ['valid' => false, 'errors' => ["Model directory not found: {$this->layout->getModelDir()}"]];
        }

        // Check FL2VA required files
        foreach ([$this->layout->configPath('FL2VA'), $this->layout->tokenizerPath('FL2VA')] as $required) {
            if (!file_exists($required)) {
                $errors[] = "Missing required file: {$required}";
            }
        }

        // Check that at least one of the model directories exists
        $fl2vaExists = is_dir($this->layout->resolve('FL2VA', 'transformer'));
        $ref2vaExists = is_dir($this->layout->resolve('Ref2VA', 'transformer'));

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
    public function getModelConfig(string $stream = 'FL2VA'): ?array
    {
        $configPath = $this->layout->configPath($stream);

        if (!file_exists($configPath)) {
            return null;
        }

        $content = file_get_contents($configPath);

        return json_decode($content, true);
    }
}
