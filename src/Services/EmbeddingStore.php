<?php

namespace LaraDBChat\Services;

use Illuminate\Support\Facades\DB;
use LaraDBChat\LLM\LLMProviderInterface;

class EmbeddingStore
{
    protected string $table = 'laradbchat_embeddings';
    protected ?string $storageConnection;

    /**
     * Maximum characters per chunk for embedding.
     * Most embedding models have ~8192 token limit, ~4 chars/token = ~6000 chars safe limit.
     */
    protected int $maxChunkSize = 6000;

    /**
     * Overlap between chunks to maintain context.
     */
    protected int $chunkOverlap = 200;

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
            'max_chunk_size' => 6000,
            'chunk_overlap' => 200,
        ], $config);

        $this->maxChunkSize = $this->config['max_chunk_size'];
        $this->chunkOverlap = $this->config['chunk_overlap'];
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
     * Store content with automatic chunking for large texts.
     * Splits content into overlapping chunks if it exceeds the max chunk size.
     *
     * @param string $type The type of embedding (e.g., 'documentation', 'model')
     * @param string $identifier Base identifier for the content
     * @param string $content The content to store
     * @param array $metadata Additional metadata
     * @return int Number of chunks stored
     */
    public function storeChunked(string $type, string $identifier, string $content, array $metadata = []): int
    {
        $chunks = $this->chunkContent($content);

        if (count($chunks) === 1) {
            // Content fits in a single chunk, use regular store
            $this->store($type, $identifier, $content, $metadata);
            return 1;
        }

        // Store each chunk with a unique identifier
        foreach ($chunks as $index => $chunk) {
            $chunkIdentifier = "{$identifier}_chunk_{$index}";
            $chunkMetadata = array_merge($metadata, [
                'chunk_index' => $index,
                'total_chunks' => count($chunks),
                'parent_identifier' => $identifier,
            ]);

            $this->store($type, $chunkIdentifier, $chunk, $chunkMetadata);
        }

        return count($chunks);
    }

    /**
     * Split content into overlapping chunks.
     *
     * @param string $content The content to chunk
     * @return array Array of content chunks
     */
    protected function chunkContent(string $content): array
    {
        $contentLength = strlen($content);

        // If content fits in one chunk, return as-is
        if ($contentLength <= $this->maxChunkSize) {
            return [$content];
        }

        $chunks = [];
        $position = 0;

        while ($position < $contentLength) {
            // Calculate chunk end position
            $chunkEnd = min($position + $this->maxChunkSize, $contentLength);

            // If not at the end, try to break at a natural boundary
            if ($chunkEnd < $contentLength) {
                $chunkEnd = $this->findNaturalBreak($content, $position, $chunkEnd);
            }

            // Extract chunk
            $chunk = substr($content, $position, $chunkEnd - $position);
            $chunks[] = trim($chunk);

            // Move position forward, accounting for overlap
            $position = $chunkEnd - $this->chunkOverlap;

            // Ensure we make progress
            if ($position <= 0 || $position >= $contentLength - $this->chunkOverlap) {
                break;
            }
        }

        // Handle any remaining content
        if ($position < $contentLength) {
            $remaining = substr($content, $position);
            if (strlen(trim($remaining)) > 50) { // Only add if substantial
                $chunks[] = trim($remaining);
            }
        }

        return $chunks;
    }

    /**
     * Find a natural break point (newline, period, space) near the target position.
     *
     * @param string $content The full content
     * @param int $start Start position of current chunk
     * @param int $targetEnd Target end position
     * @return int Adjusted end position
     */
    protected function findNaturalBreak(string $content, int $start, int $targetEnd): int
    {
        // Look back up to 200 characters for a good break point
        $searchStart = max($targetEnd - 200, $start + ($this->maxChunkSize / 2));

        // Priority 1: Double newline (paragraph break)
        $doubleNewline = strrpos(substr($content, $searchStart, $targetEnd - $searchStart), "\n\n");
        if ($doubleNewline !== false) {
            return $searchStart + $doubleNewline + 2;
        }

        // Priority 2: Single newline
        $newline = strrpos(substr($content, $searchStart, $targetEnd - $searchStart), "\n");
        if ($newline !== false) {
            return $searchStart + $newline + 1;
        }

        // Priority 3: Period followed by space
        $period = strrpos(substr($content, $searchStart, $targetEnd - $searchStart), ". ");
        if ($period !== false) {
            return $searchStart + $period + 2;
        }

        // Priority 4: Space
        $space = strrpos(substr($content, $searchStart, $targetEnd - $searchStart), " ");
        if ($space !== false) {
            return $searchStart + $space + 1;
        }

        // No good break found, use target position
        return $targetEnd;
    }

    /**
     * Check if content needs chunking.
     *
     * @param string $content The content to check
     * @return bool True if content exceeds max chunk size
     */
    public function needsChunking(string $content): bool
    {
        return strlen($content) > $this->maxChunkSize;
    }

    /**
     * Get the maximum chunk size.
     *
     * @return int Maximum characters per chunk
     */
    public function getMaxChunkSize(): int
    {
        return $this->maxChunkSize;
    }

    /**
     * Set the maximum chunk size.
     *
     * @param int $size Maximum characters per chunk
     * @return self
     */
    public function setMaxChunkSize(int $size): self
    {
        $this->maxChunkSize = $size;
        return $this;
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
