<?php

/**
 * H3PHP — Model Layout Resolver.
 *
 * Resolves on-disk locations of MiniMax-H3 model components
 * (transformer, tokenizer, text_encoder, video_vae, audio_vae) for the
 * FL2VA and Ref2VA streams.
 *
 * Default layout: MODEL_DIR/<STREAM>/<component>.
 * A YAML manifest may override any component with an absolute or
 * model-dir-relative path — e.g. to keep small files on the system disk
 * while streaming heavy weights (transformer/video_vae) from an SSD.
 *
 * `text_encoder` and `clipproj` additionally honor the H3_CLIPPROJ_DIR /
 * H3_CLIPPROJ_PROJ environment variables (mirroring the C reference
 * h3_text_encode_clipproj_bf16). Manifest entries take precedence over env.
 *
 * Manifest format (paths absolute or relative to -d/--model-dir):
 *   fl2va:
 *     transformer: /Volumes/SSD/H3/FL2VA/transformer
 *     tokenizer:   /Volumes/System/H3/FL2VA/tokenizer
 *   ref2va:
 *     transformer: /Volumes/SSD/H3/Ref2VA/transformer
 */

namespace H3Php\Core;

use Symfony\Component\Yaml\Yaml;

class ModelLayout
{
    private const array STREAMS = ['FL2VA', 'Ref2VA'];
    // text_encoder / clipproj 同时支持 H3_CLIPPROJ_DIR / H3_CLIPPROJ_PROJ 环境变量
    private const array COMPONENTS = ['transformer', 'tokenizer', 'text_encoder', 'video_vae', 'audio_vae', 'clipproj'];

    /** Model base directory (from -d/--model-dir) */
    private string $modelDir;

    /** Override map: [STREAM][COMPONENT] => absolute path */
    private array $overrides = [];

    public function __construct(string $modelDir, ?string $manifestPath = null)
    {
        $this->modelDir = rtrim($modelDir, '/\\');

        if (null !== $manifestPath && is_file($manifestPath)) {
            $data = Yaml::parseFile($manifestPath) ?? [];
            foreach (self::STREAMS as $stream) {
                $entry = $data[strtolower($stream)] ?? null;
                if (!is_array($entry)) {
                    continue;
                }
                foreach (self::COMPONENTS as $component) {
                    if (isset($entry[$component]) && is_string($entry[$component])) {
                        $this->overrides[$stream][$component] = $this->toAbsolute($entry[$component]);
                    }
                }
            }
        }
    }

    public function getModelDir(): string
    {
        return $this->modelDir;
    }

    /**
     * Absolute path of a component directory.
     */
    public function resolve(string $stream, string $component): string
    {
        if (isset($this->overrides[$stream][$component])) {
            return $this->overrides[$stream][$component];
        }

        // Env-var overrides mirror the C reference (h3_text_encoder):
        //   H3_CLIPPROJ_DIR  -> text_encoder (Qwen3-VL-4B base)
        //   H3_CLIPPROJ_PROJ -> clipproj     (ClipProj MLP projection)
        // Manifest overrides (above) take precedence over env.
        $envVar = match ($component) {
            'text_encoder' => 'H3_CLIPPROJ_DIR',
            'clipproj' => 'H3_CLIPPROJ_PROJ',
            default => null,
        };
        if (null !== $envVar) {
            $env = getenv($envVar);
            if (is_string($env) && '' !== $env) {
                return $env;
            }
        }

        return $this->modelDir . DIRECTORY_SEPARATOR . $stream . DIRECTORY_SEPARATOR . $component;
    }

    /**
     * Path to transformer/config.json for a stream.
     */
    public function configPath(string $stream = 'FL2VA'): string
    {
        return $this->resolve($stream, 'transformer') . DIRECTORY_SEPARATOR . 'config.json';
    }

    /**
     * Path to tokenizer/tokenizer.json for a stream.
     */
    public function tokenizerPath(string $stream = 'FL2VA'): string
    {
        return $this->resolve($stream, 'tokenizer') . DIRECTORY_SEPARATOR . 'tokenizer.json';
    }

    private function toAbsolute(string $path): string
    {
        if ('' === $path || '/' === $path[0] || preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            return $path;
        }

        return $this->modelDir . DIRECTORY_SEPARATOR . $path;
    }
}
