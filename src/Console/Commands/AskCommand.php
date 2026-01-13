<?php

namespace LaraDBChat\Console\Commands;

use Illuminate\Console\Command;
use LaraDBChat\Services\LaraDBChatService;

class AskCommand extends Command
{
    protected $signature = 'laradbchat:ask
                            {question? : The natural language question to ask}
                            {--sql-only : Only show the generated SQL, do not execute}
                            {--json : Output results as JSON}
                            {--interactive : Start interactive mode}';

    protected $description = 'Ask a natural language question about your database';

    public function handle(LaraDBChatService $service): int
    {
        // Check if interactive mode
        if ($this->option('interactive')) {
            return $this->interactiveMode($service);
        }

        $question = $this->argument('question');

        if (empty($question)) {
            $question = $this->ask('What would you like to know about your database?');
        }

        if (empty($question)) {
            $this->error('Please provide a question.');
            return self::FAILURE;
        }

        return $this->processQuestion($service, $question);
    }

    protected function processQuestion(LaraDBChatService $service, string $question): int
    {
        $this->info("Question: {$question}");
        $this->newLine();

        try {
            if ($this->option('sql-only')) {
                $this->info('Generating SQL...');
                $sql = $service->generateSql($question);

                $this->newLine();
                $this->line('Generated SQL:');
                $this->newLine();
                $this->line($sql);

                return self::SUCCESS;
            }

            $this->info('Processing...');
            $result = $service->ask($question);

            if ($this->option('json')) {
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
                return $result['success'] ? self::SUCCESS : self::FAILURE;
            }

            $this->newLine();
            $this->displayResult($result);

            return $result['success'] ? self::SUCCESS : self::FAILURE;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function displayResult(array $result): void
    {
        // Show SQL
        $this->info('Generated SQL:');
        $this->line($result['sql']);
        $this->newLine();

        // Show execution info
        $this->line("Provider: {$result['provider']}");
        $this->line("Total Time: {$result['total_time']}s");

        if ($result['execution_time']) {
            $this->line("Query Time: {$result['execution_time']}s");
        }

        $this->newLine();

        if (!$result['success']) {
            $this->error("Error: {$result['error']}");
            return;
        }

        if ($result['data'] === null) {
            $this->warn('Query execution is disabled. SQL generated only.');
            return;
        }

        // Show results
        $this->info("Results: {$result['count']} row(s)");
        $this->newLine();

        if (empty($result['data'])) {
            $this->line('No results found.');
            return;
        }

        // Convert to array for table display
        $data = array_map(fn($row) => (array) $row, $result['data']);

        // Get headers from first row
        $headers = array_keys($data[0]);

        // Truncate long values for display
        $displayData = array_map(function ($row) {
            return array_map(function ($value) {
                if (is_string($value) && strlen($value) > 50) {
                    return substr($value, 0, 47) . '...';
                }
                return $value;
            }, $row);
        }, array_slice($data, 0, 20));

        $this->table($headers, $displayData);

        if (count($data) > 20) {
            $this->line('... and ' . (count($data) - 20) . ' more rows');
        }
    }

    protected function interactiveMode(LaraDBChatService $service): int
    {
        $this->info('LaraDBChat Interactive Mode');
        $this->info('Type "exit" or "quit" to exit, "history" to see query history');
        $this->newLine();

        while (true) {
            $question = $this->ask('Ask');

            if (empty($question)) {
                continue;
            }

            $question = trim($question);

            if (in_array(strtolower($question), ['exit', 'quit', 'q'])) {
                $this->info('Goodbye!');
                break;
            }

            if (strtolower($question) === 'history') {
                $this->showHistory($service);
                continue;
            }

            if (strtolower($question) === 'status') {
                $this->showStatus($service);
                continue;
            }

            if (strtolower($question) === 'help') {
                $this->showHelp();
                continue;
            }

            $this->processQuestion($service, $question);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function showHistory(LaraDBChatService $service): void
    {
        $history = $service->getHistory(10);

        if (empty($history)) {
            $this->line('No query history found.');
            return;
        }

        $this->info('Recent Queries:');
        $this->newLine();

        foreach ($history as $item) {
            $status = $item['status'] === 'success' ? '<fg=green>OK</>' : '<fg=red>ERR</>';
            $this->line("[{$status}] {$item['question']}");
            $this->line("    SQL: " . substr($item['sql'], 0, 60) . (strlen($item['sql']) > 60 ? '...' : ''));
            $this->newLine();
        }
    }

    protected function showStatus(LaraDBChatService $service): void
    {
        $status = $service->getTrainingStatus();

        $this->info('Training Status:');
        $this->table(
            ['Type', 'Count'],
            [
                ['Tables', $status['tables']],
                ['Descriptions', $status['descriptions']],
                ['Samples', $status['samples']],
                ['Total', $status['total']],
            ]
        );

        $this->line("Provider: {$service->getProvider()}");
    }

    protected function showHelp(): void
    {
        $this->info('Available Commands:');
        $this->line('  exit, quit, q  - Exit interactive mode');
        $this->line('  history        - Show recent query history');
        $this->line('  status         - Show training status');
        $this->line('  help           - Show this help message');
        $this->newLine();
        $this->line('Or type any natural language question about your database.');
    }
}
