<?php

namespace LaraDBChat\LLM;

interface LLMProviderInterface
{
    /**
     * Generate SQL from a natural language prompt.
     *
     * @param string $prompt The complete prompt including schema context
     * @return string The generated SQL query
     */
    public function generateSQL(string $prompt): string;

    /**
     * Generate an embedding vector for the given text.
     *
     * @param string $text The text to embed
     * @return array<float> The embedding vector
     */
    public function generateEmbedding(string $text): array;

    /**
     * Get the name of the provider.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if the provider supports embedding generation.
     *
     * @return bool
     */
    public function supportsEmbeddings(): bool;
}
