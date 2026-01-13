<?php

namespace LaraDBChat\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use LaraDBChat\Exceptions\LLMConnectionException;

class OllamaProvider implements LLMProviderInterface
{
    protected Client $client;

    public function __construct(
        protected string $host,
        protected string $model,
        protected string $embeddingModel,
        protected int $timeout = 120
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($this->host, '/') . '/',
            'timeout' => $this->timeout,
        ]);
    }

    public function generateSQL(string $prompt): string
    {
        try {
            $response = $this->client->post('api/generate', [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.1,
                        'num_predict' => 2048,
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $sql = $data['response'] ?? '';

            return $this->extractSQL($sql);
        } catch (ConnectException $e) {
            throw LLMConnectionException::connectionFailed('Ollama', $this->host, $e);
        } catch (RequestException $e) {
            if ($e->getResponse()?->getStatusCode() === 408) {
                throw LLMConnectionException::timeout('Ollama', $this->timeout, $e);
            }
            throw new LLMConnectionException(
                'Ollama request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                'ollama'
            );
        }
    }

    public function generateEmbedding(string $text): array
    {
        try {
            $response = $this->client->post('api/embeddings', [
                'json' => [
                    'model' => $this->embeddingModel,
                    'prompt' => $text,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['embedding'] ?? [];
        } catch (ConnectException $e) {
            throw LLMConnectionException::connectionFailed('Ollama', $this->host, $e);
        } catch (RequestException $e) {
            throw new LLMConnectionException(
                'Ollama embedding request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                'ollama'
            );
        }
    }

    public function getName(): string
    {
        return 'ollama';
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

        // Return the response as-is if no pattern matched
        return trim($response);
    }
}
