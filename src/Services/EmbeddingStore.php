<?php

namespace LaraDBChat\Services;

use Illuminate\Support\Facades\DB;
use LaraDBChat\LLM\LLMProviderInterface;

class EmbeddingStore
{
    protected string $table = 'laradbchat_embeddings';
    protected ?string $storageConnection;

    public function __construct(
        protected LLMProviderInterface $llm,
        protected ?string $connection = null,
        ?string $storageConnection = null,
        protected array $config = []
    ) {
        // Storage connection: explicit > storage config > main connection > default
        $this->storageConnection = $storageConnection
            ?? config('laradbchat.storage.connection')
            ?? $connection
            ?? config('database.default');

        $this->config = array_merge([
            'top_k' => 5,
            'similarity_threshold' => 0.3,
        ], $config);
    }

    /**
     * Store an embedding for a piece of content.
     */
    public function store(string $type, string $identifier, string $content, array $metadata = []): void
    {
        $embedding = $this->llm->generateEmbedding($content);

        $this->getConnection()->table($this->table)->updateOrInsert(
            [
                'type' => $type,
                'identifier' => $identifier,
            ],
            [
                'content' => $content,
                'embedding' => json_encode($embedding),
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Find similar content based on a query.
     */
    public function findSimilar(string $query, ?string $type = null, ?int $limit = null): array
    {
        $limit = $limit ?? $this->config['top_k'];
        $threshold = $this->config['similarity_threshold'];

        // Generate embedding for the query
        $queryEmbedding = $this->llm->generateEmbedding($query);

        // Get all embeddings from the database
        $builder = $this->getConnection()->table($this->table);

        if ($type !== null) {
            $builder->where('type', $type);
        }

        $rows = $builder->get();

        // Calculate similarities
        $results = [];
        foreach ($rows as $row) {
            $storedEmbedding = json_decode($row->embedding, true);
            $similarity = $this->cosineSimilarity($queryEmbedding, $storedEmbedding);

            if ($similarity >= $threshold) {
                $results[] = [
                    'type' => $row->type,
                    'identifier' => $row->identifier,
                    'content' => $row->content,
                    'metadata' => json_decode($row->metadata, true),
                    'similarity' => $similarity,
                ];
            }
        }

        // Sort by similarity descending
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Return top K results
        return array_slice($results, 0, $limit);
    }

    /**
     * Get all embeddings of a specific type.
     */
    public function getByType(string $type): array
    {
        return $this->getConnection()
            ->table($this->table)
            ->where('type', $type)
            ->get()
            ->map(function ($row) {
                return [
                    'type' => $row->type,
                    'identifier' => $row->identifier,
                    'content' => $row->content,
                    'metadata' => json_decode($row->metadata, true),
                ];
            })
            ->toArray();
    }

    /**
     * Delete an embedding by type and identifier.
     */
    public function delete(string $type, string $identifier): bool
    {
        return $this->getConnection()
            ->table($this->table)
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->delete() > 0;
    }

    /**
     * Delete all embeddings of a specific type.
     */
    public function deleteByType(string $type): int
    {
        return $this->getConnection()
            ->table($this->table)
            ->where('type', $type)
            ->delete();
    }

    /**
     * Clear all embeddings.
     */
    public function clear(): int
    {
        return $this->getConnection()
            ->table($this->table)
            ->delete();
    }

    /**
     * Get the count of stored embeddings.
     */
    public function count(?string $type = null): int
    {
        $builder = $this->getConnection()->table($this->table);

        if ($type !== null) {
            $builder->where('type', $type);
        }

        return $builder->count();
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Get the database connection for storage operations.
     */
    protected function getConnection()
    {
        return DB::connection($this->storageConnection);
    }

    /**
     * Get the storage connection name.
     */
    public function getStorageConnection(): ?string
    {
        return $this->storageConnection;
    }
}
