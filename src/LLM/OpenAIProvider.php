<?php

namespace LaraDBChat\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use LaraDBChat\Exceptions\LLMConnectionException;

class OpenAIProvider implements LLMProviderInterface
{
    protected Client $client;

    public function __construct(
        protected ?string $apiKey,
        protected string $model,
        protected string $embeddingModel,
        protected int $timeout = 60
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function generateSQL(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new LLMConnectionException(
                'OpenAI API key is not configured',
                401,
                null,
                'openai'
            );
        }

        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a SQL expert. Generate only valid SQL queries based on the given schema and question. Return only the SQL query without any explanation.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 2048,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $sql = $data['choices'][0]['message']['content'] ?? '';

            return $this->extractSQL($sql);
        } catch (ConnectException $e) {
            throw LLMConnectionException::connectionFailed('OpenAI', 'api.openai.com', $e);
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();

            if ($statusCode === 401) {
                throw LLMConnectionException::authenticationFailed('OpenAI', $e);
            }

            if ($statusCode === 429) {
                throw LLMConnectionException::rateLimited('OpenAI', $e);
            }

            throw new LLMConnectionException(
                'OpenAI request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                'openai'
            );
        }
    }

    public function generateEmbedding(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new LLMConnectionException(
                'OpenAI API key is not configured',
                401,
                null,
                'openai'
            );
        }

        try {
            $response = $this->client->post('embeddings', [
                'json' => [
                    'model' => $this->embeddingModel,
                    'input' => $text,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['data'][0]['embedding'] ?? [];
        } catch (ConnectException $e) {
            throw LLMConnectionException::connectionFailed('OpenAI', 'api.openai.com', $e);
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();

            if ($statusCode === 401) {
                throw LLMConnectionException::authenticationFailed('OpenAI', $e);
            }

            if ($statusCode === 429) {
                throw LLMConnectionException::rateLimited('OpenAI', $e);
            }

            throw new LLMConnectionException(
                'OpenAI embedding request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                'openai'
            );
        }
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function supportsEmbeddings(): bool
    {
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
