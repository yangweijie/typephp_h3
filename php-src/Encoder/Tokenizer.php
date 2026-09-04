<?php

/**
 * H3PHP — Tokenizer.
 *
 * Text tokenizer for the MiniMax-H3 model.
 * Wraps the tokenizer.json from the model directory.
 * The H3 model uses a Qwen3-VL based tokenizer.
 *
 * TODO: Integrate with native tokenizer implementation for performance.
 * For now, provides the interface and basic BPE tokenization structure.
 */

namespace H3Php\Encoder;

class Tokenizer
{
    /** Path to tokenizer.json */
    private string $tokenizerPath;

    /** Vocabulary: token string -> token id */
    private array $vocab = [];

    /** Special tokens */
    private array $specialTokens = [];

    /** Beginning of sequence token ID */
    private ?int $bosTokenId = null;

    /** End of sequence token ID */
    private ?int $eosTokenId = null;

    /** Padding token ID */
    private ?int $padTokenId = null;

    /** Maximum sequence length */
    private int $maxLength = 512;

    /**
     * @param string $tokenizerPath Path to tokenizer.json
     *
     * @throws \RuntimeException If tokenizer file not found or invalid
     */
    public function __construct(string $tokenizerPath)
    {
        $this->tokenizerPath = $tokenizerPath;
        $this->loadTokenizer();
    }

    /**
     * Load and parse the tokenizer.json file.
     */
    private function loadTokenizer(): void
    {
        if (!file_exists($this->tokenizerPath)) {
            throw new \RuntimeException("Tokenizer file not found: {$this->tokenizerPath}");
        }

        $content = file_get_contents($this->tokenizerPath);
        $data = json_decode($content, true);

        if (null === $data) {
            throw new \RuntimeException("Failed to parse tokenizer.json");
        }

        $this->vocab = $data['model']['vocab'] ?? [];
        $this->specialTokens = $data['added_tokens'] ?? [];

        // Extract special token IDs
        foreach ($this->specialTokens as $token) {
            switch ($token['content']) {
                case '<|im_start|>':
                case '<s>':
                    $this->bosTokenId = $token['id'];
                    break;
                case '<|im_end|>':
                case '</s>':
                    $this->eosTokenId = $token['id'];
                    break;
                case '<pad>':
                    $this->padTokenId = $token['id'];
                    break;
            }
        }

        // Set defaults if not found
        $this->bosTokenId ??= 1;
        $this->eosTokenId ??= 2;
        $this->padTokenId ??= 0;
    }

    /**
     * Encode a text string to token IDs.
     *
     * @param string $text   Input text
     * @param bool   $addBos Whether to prepend BOS token
     * @param bool   $addEos Whether to append EOS token
     *
     * @return int[] Token IDs
     */
    public function encode(string $text, bool $addBos = true, bool $addEos = true): array
    {
        $tokens = [];

        if ($addBos) {
            $tokens[] = $this->bosTokenId;
        }

        // TODO: Implement proper BPE tokenization
        // For now, use a simple character-level fallback
        $chars = mb_str_split($text);
        foreach ($chars as $char) {
            $tokens[] = $this->charToTokenId($char);
        }

        if ($addEos) {
            $tokens[] = $this->eosTokenId;
        }

        // Truncate to max length
        if (count($tokens) > $this->maxLength) {
            $tokens = array_slice($tokens, 0, $this->maxLength);
        }

        return $tokens;
    }

    /**
     * Decode token IDs back to text.
     *
     * @param int[] $tokenIds Token IDs
     */
    public function decode(array $tokenIds): string
    {
        $text = '';
        foreach ($tokenIds as $id) {
            $text .= $this->tokenIdToChar($id);
        }

        return $text;
    }

    /**
     * Get the vocabulary size.
     */
    public function getVocabSize(): int
    {
        return count($this->vocab);
    }

    /**
     * Get the BOS token ID.
     */
    public function getBosTokenId(): int
    {
        return $this->bosTokenId;
    }

    /**
     * Get the EOS token ID.
     */
    public function getEosTokenId(): int
    {
        return $this->eosTokenId;
    }

    /**
     * Get the maximum sequence length.
     */
    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    /**
     * Set the maximum sequence length.
     */
    public function setMaxLength(int $length): void
    {
        $this->maxLength = $length;
    }

    /**
     * Convert a character to a token ID (fallback for BPE).
     */
    private function charToTokenId(string $char): int
    {
        return $this->vocab[$char] ?? ord($char) % 32000;
    }

    /**
     * Convert a token ID to a character (fallback for BPE).
     */
    private function tokenIdToChar(int $id): string
    {
        $char = array_search($id, $this->vocab);

        return false !== $char ? $char : '';
    }
}
