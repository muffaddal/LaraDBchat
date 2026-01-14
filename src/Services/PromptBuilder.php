<?php

namespace LaraDBChat\Services;

class PromptBuilder
{
    protected array $sampleQueries;

    public function __construct(array $sampleQueries = [])
    {
        $this->sampleQueries = $sampleQueries;
    }

    /**
     * Build a complete prompt for text-to-SQL conversion.
     */
    public function build(string $question, array $schemaContext, string $dialect = 'mysql'): string
    {
        $prompt = $this->buildSystemContext($dialect);
        $prompt .= $this->buildSchemaSection($schemaContext);
        $prompt .= $this->buildExamplesSection();
        $prompt .= $this->buildQuestionSection($question);

        return $prompt;
    }

    /**
     * Build a complete prompt with documentation and sample context.
     */
    public function buildWithContext(
        string $question,
        array $schemaContext,
        array $documentation,
        array $samples,
        string $dialect = 'mysql'
    ): string {
        $prompt = $this->buildSystemContext($dialect);

        // Add business documentation (CRITICAL for understanding domain)
        if (!empty($documentation)) {
            $prompt .= "\n### Business Context & Documentation\n\n";
            $prompt .= "IMPORTANT: Use this information to understand the database structure:\n\n";
            foreach ($documentation as $doc) {
                $prompt .= $doc['content'] . "\n\n";
            }
        }

        $prompt .= $this->buildSchemaSection($schemaContext);

        // Add retrieved sample queries (most relevant to this question)
        if (!empty($samples)) {
            $prompt .= "\n### Similar Query Examples\n\n";
            foreach ($samples as $sample) {
                if (isset($sample['metadata']['question']) && isset($sample['metadata']['sql'])) {
                    $prompt .= "Question: {$sample['metadata']['question']}\n";
                    $prompt .= "SQL: {$sample['metadata']['sql']}\n\n";
                }
            }
        }

        // Add default examples
        $prompt .= $this->buildExamplesSection();
        $prompt .= $this->buildQuestionSection($question);

        return $prompt;
    }

    /**
     * Build the system context section.
     */
    protected function buildSystemContext(string $dialect): string
    {
        $dialectInstructions = $this->getDialectInstructions($dialect);

        return <<<PROMPT
You are an expert SQL query generator. Your task is to convert natural language questions into valid SQL queries.

Rules:
1. Generate ONLY the SQL query, no explanations or markdown
2. Use proper SQL syntax for {$dialect}
3. Always use table aliases for clarity
4. Use appropriate JOIN types based on relationships
5. Include WHERE clauses for filtering
6. Use aggregate functions (COUNT, SUM, AVG, etc.) when asked about totals or averages
7. Add ORDER BY for sorted results
8. Add LIMIT for "top N" queries
9. Handle date/time functions appropriately

{$dialectInstructions}

PROMPT;
    }

    /**
     * Get dialect-specific instructions.
     */
    protected function getDialectInstructions(string $dialect): string
    {
        return match ($dialect) {
            'mysql' => "MySQL-specific notes:\n- Use backticks for identifiers with special characters\n- Use DATE_SUB/DATE_ADD for date arithmetic\n- Use IFNULL() for null handling\n- Use LIMIT for row limiting",
            'pgsql' => "PostgreSQL-specific notes:\n- Use double quotes for identifiers with special characters\n- Use INTERVAL for date arithmetic\n- Use COALESCE() for null handling\n- Use LIMIT/OFFSET for pagination",
            'sqlite' => "SQLite-specific notes:\n- Use date() and datetime() functions for date handling\n- Use IFNULL() or COALESCE() for null handling\n- Use LIMIT for row limiting",
            'sqlsrv' => "SQL Server-specific notes:\n- Use square brackets for identifiers with special characters\n- Use DATEADD/DATEDIFF for date arithmetic\n- Use ISNULL() or COALESCE() for null handling\n- Use TOP or OFFSET-FETCH for row limiting",
            default => '',
        };
    }

    /**
     * Build the schema context section.
     */
    protected function buildSchemaSection(array $schemaContext): string
    {
        if (empty($schemaContext)) {
            return '';
        }

        $section = "\n### Database Schema\n\n";

        foreach ($schemaContext as $item) {
            $section .= $item['content'] . "\n\n";
        }

        return $section;
    }

    /**
     * Build the examples section with few-shot learning.
     */
    protected function buildExamplesSection(): string
    {
        $examples = array_merge($this->getDefaultExamples(), $this->sampleQueries);

        if (empty($examples)) {
            return '';
        }

        $section = "\n### Examples\n\n";

        foreach ($examples as $example) {
            $section .= "Question: {$example['question']}\n";
            $section .= "SQL: {$example['sql']}\n\n";
        }

        return $section;
    }

    /**
     * Get default examples for common query patterns.
     */
    protected function getDefaultExamples(): array
    {
        return [
            [
                'question' => 'Show all users',
                'sql' => 'SELECT * FROM users',
            ],
            [
                'question' => 'How many orders were placed this month?',
                'sql' => "SELECT COUNT(*) as order_count FROM orders WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            ],
            [
                'question' => 'Get the top 10 products by sales',
                'sql' => 'SELECT p.name, SUM(oi.quantity) as total_sold FROM products p JOIN order_items oi ON p.id = oi.product_id GROUP BY p.id, p.name ORDER BY total_sold DESC LIMIT 10',
            ],
            [
                'question' => 'List users who signed up in the last 7 days',
                'sql' => 'SELECT * FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            ],
        ];
    }

    /**
     * Build the question section.
     */
    protected function buildQuestionSection(string $question): string
    {
        return <<<PROMPT

### Question

{$question}

### SQL Query

PROMPT;
    }

    /**
     * Add custom sample queries.
     */
    public function addSampleQuery(string $question, string $sql): self
    {
        $this->sampleQueries[] = [
            'question' => $question,
            'sql' => $sql,
        ];

        return $this;
    }

    /**
     * Set sample queries.
     */
    public function setSampleQueries(array $queries): self
    {
        $this->sampleQueries = $queries;

        return $this;
    }
}
