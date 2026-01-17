<?php

namespace LaraDBChat\Services;

use LaraDBChat\LLM\LLMProviderInterface;
use LaraDBChat\Logging\QueryLoggerInterface;

class LaraDBChatService
{
    public function __construct(
        protected LLMProviderInterface $llm,
        protected SchemaExtractor $schemaExtractor,
        protected EmbeddingStore $embeddingStore,
        protected PromptBuilder $promptBuilder,
        protected QueryExecutor $queryExecutor,
        protected QueryLoggerInterface $logger,
        protected array $config = []
    ) {
        $this->config = array_merge([
            'enabled' => true,
            'read_only' => true,
        ], $config);
    }

    /**
     * Ask a natural language question and get SQL results.
     */
    public function ask(string $question): array
    {
        $startTime = microtime(true);
        $error = null;
        $sql = '';
        $results = null;

        try {
            // Generate SQL from the question
            $sql = $this->generateSql($question);

            // Execute the query if enabled
            if ($this->config['enabled']) {
                $results = $this->queryExecutor->execute($sql);
            } else {
                $results = [
                    'success' => true,
                    'sql' => $sql,
                    'data' => null,
                    'count' => null,
                    'execution_time' => 0,
                    'message' => 'Query execution is disabled. SQL generated only.',
                ];
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $results = [
                'success' => false,
                'sql' => $sql,
                'error' => $error,
            ];
        }

        $totalTime = microtime(true) - $startTime;

        // Log the query
        $this->logger->log(
            $question,
            $sql,
            $results['data'] ?? null,
            $totalTime,
            $error
        );

        return [
            'question' => $question,
            'sql' => $sql,
            'success' => $results['success'] ?? false,
            'data' => $results['data'] ?? null,
            'count' => $results['count'] ?? null,
            'execution_time' => $results['execution_time'] ?? null,
            'total_time' => round($totalTime, 4),
            'error' => $error,
            'provider' => $this->llm->getName(),
        ];
    }

    /**
     * Generate SQL from a natural language question.
     */
    public function generateSql(string $question): string
    {
        // Find relevant schema context
        $schemaContext = $this->embeddingStore->findSimilar($question, 'table');

        // If no context found, get all tables (limited)
        if (empty($schemaContext)) {
            $schemaContext = $this->embeddingStore->getByType('table');
            $schemaContext = array_slice($schemaContext, 0, 10);
        }

        // Find relevant documentation
        $documentation = $this->embeddingStore->findSimilar($question, 'documentation', 3);

        // Find similar sample queries
        $samples = $this->embeddingStore->findSimilar($question, 'sample', 5);

        // Build the prompt with all context
        $prompt = $this->promptBuilder->buildWithContext(
            $question,
            $schemaContext,
            $documentation,
            $samples,
            $this->schemaExtractor->getDriver()
        );

        // Generate SQL
        return $this->llm->generateSQL($prompt);
    }

    /**
     * Train the system on the database schema.
     */
    public function train(): array
    {
        $schema = $this->schemaExtractor->extract();
        $trained = 0;
        $errors = [];

        foreach ($schema as $tableName => $tableInfo) {
            try {
                // Store DDL as embedding
                $this->embeddingStore->store(
                    'table',
                    $tableName,
                    $tableInfo['ddl'],
                    [
                        'columns' => array_column($tableInfo['columns'], 'name'),
                        'has_foreign_keys' => !empty($tableInfo['foreign_keys']),
                    ]
                );

                // Also store a description
                $description = $this->schemaExtractor->generateTableDescription($tableName);
                $this->embeddingStore->store(
                    'description',
                    $tableName,
                    $description,
                    ['table' => $tableName]
                );

                $trained++;
            } catch (\Exception $e) {
                $errors[$tableName] = $e->getMessage();
            }
        }

        return [
            'success' => empty($errors),
            'tables_trained' => $trained,
            'total_tables' => count($schema),
            'errors' => $errors,
        ];
    }

    /**
     * Add custom training data.
     */
    public function addTrainingData(string $type, string $identifier, string $content, array $metadata = []): void
    {
        $this->embeddingStore->store($type, $identifier, $content, $metadata);
    }

    /**
     * Add business documentation to help the LLM understand your domain.
     * This is crucial for accurate query generation.
     * Automatically chunks large content to handle embedding model limits.
     */
    public function addDocumentation(string $title, string $content): self
    {
        $fullContent = "## {$title}\n\n{$content}";

        // Use chunked storage to handle large documentation
        $this->embeddingStore->storeChunked(
            'documentation',
            md5($title),
            $fullContent,
            ['title' => $title]
        );

        return $this;
    }

    /**
     * Add a sample query for few-shot learning.
     */
    public function addSampleQuery(string $question, string $sql): self
    {
        $this->promptBuilder->addSampleQuery($question, $sql);

        // Also store as embedding for retrieval
        $this->embeddingStore->store(
            'sample',
            md5($question),
            "Question: {$question}\nSQL: {$sql}",
            ['question' => $question, 'sql' => $sql]
        );

        return $this;
    }

    /**
     * Get query history.
     */
    public function getHistory(int $limit = 50, int $offset = 0): array
    {
        return $this->logger->getHistory($limit, $offset);
    }

    /**
     * Get database schema information.
     */
    public function getSchema(): array
    {
        return $this->schemaExtractor->extract();
    }

    /**
     * Get training status.
     */
    public function getTrainingStatus(): array
    {
        return [
            'tables' => $this->embeddingStore->count('table'),
            'descriptions' => $this->embeddingStore->count('description'),
            'samples' => $this->embeddingStore->count('sample'),
            'documentation' => $this->embeddingStore->count('documentation'),
            'total' => $this->embeddingStore->count(),
        ];
    }

    /**
     * Clear all training data.
     */
    public function clearTraining(): int
    {
        return $this->embeddingStore->clear();
    }

    /**
     * Validate a SQL query without executing it.
     */
    public function validateSql(string $sql): array
    {
        try {
            $this->queryExecutor->validate($sql);
            return ['valid' => true, 'sql' => $sql];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage(), 'sql' => $sql];
        }
    }

    /**
     * Get the current LLM provider name.
     */
    public function getProvider(): string
    {
        return $this->llm->getName();
    }
}
