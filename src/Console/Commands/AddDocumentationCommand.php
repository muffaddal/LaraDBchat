<?php

namespace LaraDBChat\Console\Commands;

use Illuminate\Console\Command;
use LaraDBChat\Services\LaraDBChatService;

class AddDocumentationCommand extends Command
{
    protected $signature = 'laradbchat:add-docs
                            {--file= : Path to a documentation file}
                            {--sample : Add sample queries interactively}';

    protected $description = 'Add business documentation or sample queries to improve accuracy';

    public function handle(LaraDBChatService $service): int
    {
        if ($this->option('file')) {
            return $this->addFromFile($service);
        }

        if ($this->option('sample')) {
            return $this->addSampleInteractive($service);
        }

        // Interactive documentation
        return $this->addDocumentationInteractive($service);
    }

    protected function addDocumentationInteractive(LaraDBChatService $service): int
    {
        $this->info('Add Business Documentation');
        $this->info('This helps the AI understand your database structure better.');
        $this->newLine();

        $title = $this->ask('Documentation title (e.g., "Invoice Types")');

        if (empty($title)) {
            $this->error('Title is required.');
            return self::FAILURE;
        }

        $this->line('Enter documentation content (press Enter twice to finish):');

        $content = '';
        $emptyLines = 0;

        while ($emptyLines < 2) {
            $line = $this->ask('');
            if (empty($line)) {
                $emptyLines++;
            } else {
                $emptyLines = 0;
                $content .= $line . "\n";
            }
        }

        if (empty(trim($content))) {
            $this->error('Content is required.');
            return self::FAILURE;
        }

        $service->addDocumentation($title, trim($content));

        $this->info("Documentation '{$title}' added successfully!");

        return self::SUCCESS;
    }

    protected function addSampleInteractive(LaraDBChatService $service): int
    {
        $this->info('Add Sample Query');
        $this->info('Sample queries help the AI learn your query patterns.');
        $this->newLine();

        while (true) {
            $question = $this->ask('Natural language question (or "done" to finish)');

            if (strtolower($question) === 'done' || empty($question)) {
                break;
            }

            $sql = $this->ask('Corresponding SQL query');

            if (empty($sql)) {
                $this->warn('SQL is required. Skipping.');
                continue;
            }

            $service->addSampleQuery($question, $sql);
            $this->info("Sample added: {$question}");
            $this->newLine();
        }

        $this->info('Done adding samples.');
        return self::SUCCESS;
    }

    protected function addFromFile(LaraDBChatService $service): int
    {
        $file = $this->option('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $content = file_get_contents($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        if ($extension === 'json') {
            return $this->processJsonFile($service, $content);
        }

        // Treat as plain text documentation
        $title = pathinfo($file, PATHINFO_FILENAME);
        $service->addDocumentation($title, $content);
        $this->info("Documentation '{$title}' added from file.");

        return self::SUCCESS;
    }

    protected function processJsonFile(LaraDBChatService $service, string $content): int
    {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON file.');
            return self::FAILURE;
        }

        // Process documentation
        if (isset($data['documentation'])) {
            foreach ($data['documentation'] as $doc) {
                $service->addDocumentation($doc['title'], $doc['content']);
                $this->info("Added documentation: {$doc['title']}");
            }
        }

        // Process samples
        if (isset($data['samples'])) {
            foreach ($data['samples'] as $sample) {
                $service->addSampleQuery($sample['question'], $sample['sql']);
                $this->info("Added sample: {$sample['question']}");
            }
        }

        return self::SUCCESS;
    }
}
