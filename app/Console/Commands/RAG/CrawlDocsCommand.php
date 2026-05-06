<?php

namespace App\Console\Commands\RAG;

use App\Services\RAG\DocsCrawler;
use Illuminate\Console\Command;

class CrawlDocsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rag:crawl {--path=storage/app/docs/laravel-docs : The path to the documentation files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl documentation files and save them to the database';

    /**
     * Execute the console command.
     */
    public function handle(DocsCrawler $crawler)
    {
        $path = base_path($this->option('path'));

        $this->info("Starting to crawl documentation from: {$path}");

        try {
            $stats = $crawler->crawl($path);

            $this->table(
                ['Status', 'Count'],
                [
                    ['Added', $stats['added']],
                    ['Updated', $stats['updated']],
                    ['Skipped (No changes)', $stats['skipped']],
                ]
            );

            $this->info("Crawling completed successfully!");
        } catch (\Exception $e) {
            $this->error("Error during crawling: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
