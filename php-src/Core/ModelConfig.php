<?php
/**
 * H3PHP — Model Configuration
 *
 * Central location for model dimensions and precision constants.
 *
 * IMPORTANT: These values may vary between H3 model variants.
 * The VDN-H3 project (D:/git/python/vdn-minimax-h3) uses:
 *   hidden_size=5120, num_heads=40, head_dim=128, num_layers=40
 *
 * The h3.c project (D:/git/c/h3) uses:
 *   hidden_size=5376, num_heads=56, head_dim=96, num_layers=50
 *
 * VERIFY against your specific checkpoint's config.json before use.
 */

namespace H3Php\Core;

class ModelConfig
{
    // ========================================================================
    // Architecture Dimensions (verify for your checkpoint)
    // ========================================================================

    /** Hidden dimension (embedding size) */
    public int $hiddenSize = 5120;  // VDN-H3: 5120, h3.c: 5376

    /** Number of attention heads */
    public int $numHeads = 40;  // VDN-H3: 40, h3.c: 56

    /** Attention head dimension */
    public int $attentionHeadDim = 128;  // VDN-H3: 128, h3.c: 96

    /** Number of transformer blocks */
    public int $numLayers = 40;  // VDN-H3: 40, h3.c: 50

    /** MLP intermediate dimension */
    public int $mlpDim = 14336;  // h3.c documented

    // ========================================================================
    // Video Latent Parameters
    // ========================================================================

    /** Video latent height */
    public int $latentHeight = 48;

    /** Video latent width */
    public int $latentWidth = 84;

    /** Video latent channels */
    public int $videoChannels = 24;

    /** Spatial compression ratio */
    public int $spatialRatio = 16;

    /** Patch size for video (temporal, height, width) */
    public array $patchSize = [1, 2, 2];

    /** Tokens per frame = (latentHeight/2) * (latentWidth/2) */
    public int $tokensPerFrame = 1008;  // (48/2)*(84/2)

    // ========================================================================
    // Audio Parameters
    // ========================================================================

    /** Audio sample rate */
    public int $audioSampleRate = 32000;

    /** Audio latent channels (per channel) */
    public int $audioLatentChannels = 32;

    /** Audio latent frame rate */
    public int $audioLatentFps = 40;

    /** Number of audio channels (stereo) */
    public int $audioChannels = 2;

    // ========================================================================
    // Precision Constants
    // ========================================================================

    /** Video sigma schedule shift */
    public float $videoSigmaShift = 12.0;

    /** Audio sigma schedule shift */
    public float $audioSigmaShift = 3.0;

    /** RoPE frequency base */
    public float $ropeTheta = 10000.0;

    /** AdaLN epsilon for numerical stability */
    public float $adalnEpsilon = 1e-6;

    // ========================================================================
    // Precision Rules (from VDN-H3 analysis)
    // ========================================================================

    /**
     * Operations that MUST run in FP32 (not BF16):
     * 1. SiLU activation in AdaLN modulation linear layer
     * 2. RMSNorm second-moment accumulation
     * 3. Linear branch A statistics (if implementing hybrid attention)
     * 4. FrameKDAAlpha decay gate
     * 5. Bidirectional scan state recurrence
     *
     * Operations safe in BF16:
     * - Attention QKV projections
     * - Attention output
     * - MLP layers (fc1, fc2)
     * - Residual additions
     */

    // ========================================================================
    // Factory Methods
    // ========================================================================

    /**
     * Create config from VDN-H3 defaults.
     */
    public static function vdnH3(): self
    {
        $cfg = new self();
        $cfg->hiddenSize = 5120;
        $cfg->numHeads = 40;
        $cfg->attentionHeadDim = 128;
        $cfg->numLayers = 40;
        $cfg->tokensPerFrame = 1008;
        return $cfg;
    }

    /**
     * Create config from h3.c defaults.
     */
    public static function h3c(): self
    {
        $cfg = new self();
        $cfg->hiddenSize = 5376;
        $cfg->numHeads = 56;
        $cfg->attentionHeadDim = 96;
        $cfg->numLayers = 50;
        $cfg->tokensPerFrame = 768;
        return $cfg;
    }

    /**
     * Load config from a model directory's transformer/config.json.
     */
    public static function fromModelDir(string $modelDir): self
    {
        $cfg = new self();
        $configPath = $modelDir . DIRECTORY_SEPARATOR . 'FL2VA'
                     . DIRECTORY_SEPARATOR . 'transformer'
                     . DIRECTORY_SEPARATOR . 'config.json';

        if (file_exists($configPath)) {
            $data = json_decode(file_get_contents($configPath), true);
            if ($data !== null) {
                $cfg->hiddenSize = $data['hidden_size'] ?? $data['dim'] ?? $cfg->hiddenSize;
                $cfg->numHeads = $data['num_attention_heads'] ?? $data['n_heads'] ?? $cfg->numHeads;
                $cfg->attentionHeadDim = $data['attention_head_dim'] ?? $data['head_dim']
                                       ?? (int)($cfg->hiddenSize / $cfg->numHeads);
                $cfg->numLayers = $data['num_layers'] ?? $data['n_layers'] ?? $cfg->numLayers;
                $cfg->mlpDim = $data['intermediate_size'] ?? $data['mlp_dim'] ?? $cfg->mlpDim;
            }
        }

        return $cfg;
    }
}
