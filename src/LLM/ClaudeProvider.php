<?php

namespace LaraDBChat\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use LaraDBChat\Exceptions\LLMConnectionException;

class ClaudeProvider implements LLMProviderInterface
{
    protected Client $client;
    protected ?Client $embeddingClient = null;

    public function __construct(
        protected ?string $apiKey,
        protected string $model,
        protected int $timeout = 60
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com/v1/',
            'timeout' => $this->timeout,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function generateSQL(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new LLMConnectionException(
                'Anthropic API key is not configured',
                401,
                null,
                'claude'
            );
        }

        try {
            $response = $this->client->post('messages', [
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => 2048,
                    'system' => 'You are a SQL expert. Generate only valid SQL queries based on the given schema and question. Return only the SQL query without any explanation.',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $sql = $data['content'][0]['text'] ?? '';

            return $this->extractSQL($sql);
        } catch (ConnectException $e) {
            throw LLMConnectionException::connectionFailed('Claude', 'api.anthropic.com', $e);
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();

            if ($statusCode === 401) {
                throw LLMConnectionException::authenticationFailed('Claude', $e);
            }

            if ($statusCode === 429) {
                throw LLMConnectionException::rateLimited('Claude', $e);
            }

            throw new LLMConnectionException(
                'Claude request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                'claude'
            );
        }
    }

    public function generateEmbedding(string $text): array
    {
        // Claude doesn't have a native embedding API
        // We'll use a simple hash-based approach for similarity
        // For better results, users should configure OpenAI or Ollama for embeddings
        return $this->generateSimpleEmbedding($text);
    }

    /**
     * Generate a simple embedding using text analysis.
     * This is a fallback when Claude is used without an embedding provider.
     */
    protected function generateSimpleEmbedding(string $text): array
    {
        // Normalize and tokenize text
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = array_filter(explode(' ', $text));

        // Create a simple bag-of-words style embedding
        $embedding = array_fill(0, 384, 0.0);

        foreach ($words as $word) {
            $hash = crc32($word);
            $index = abs($hash) % 384;
            $embedding[$index] += 1.0;
        }

        // Normalize the embedding
        $magnitude = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
        if ($magnitude > 0) {
            $embedding = array_map(fn($x) => $x / $magnitude, $embedding);
        }

        return $embedding;
    }

    public function getName(): string
    {
        return 'claude';
    }

    public function supportsEmbeddings(): bool
    {
        // Returns true but uses fallback embedding
        return true;
    }

    /**
     * Extract SQL from the LLM response.
     */
    protected function extractSQL(string $response): string
    {
        // Try to extract SQL from markdown code blocks
        if (preg_match('/```sql\s*(.*?)\s*```/si', $response, $matches)) {
            return trim($matches[1]);
        }

        // Try to extract from generic code blocks
        if (preg_match('/```\s*(.*?)\s*```/si', $response, $matches)) {
            return trim($matches[1]);
        }

        // Look for SELECT, INSERT, UPDATE, DELETE statements
        if (preg_match('/(SELECT|INSERT|UPDATE|DELETE|WITH)\s+.+?(?:;|$)/si', $response, $matches)) {
            return trim($matches[0]);
        }

        return trim($response);
    }
}
