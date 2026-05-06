<?php

namespace Tests\Feature\Console;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CrawlDocsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempPath = storage_path('app/testing_docs');
        File::makeDirectory($this->tempPath, 0755, true, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempPath);
        parent::tearDown();
    }

    /** @test */
    public function test_it_runs_successfully_with_valid_path()
    {
        File::put($this->tempPath . '/test.md', "# Test Title\nContent");

        $this->artisan('rag:crawl', ['--path' => 'storage/app/testing_docs'])
            ->expectsOutput("Starting to crawl documentation from: " . base_path('storage/app/testing_docs'))
            ->expectsTable(['Status', 'Count'], [
                ['Added', 1],
                ['Updated', 0],
                ['Skipped (No changes)', 0],
            ])
            ->expectsOutput("Crawling completed successfully!")
            ->assertExitCode(0);

        $this->assertDatabaseHas('documents', ['url' => 'test']);
    }

    /** @test */
    public function test_it_fails_with_invalid_path()
    {
        $this->artisan('rag:crawl', ['--path' => 'nonexistent'])
            ->expectsOutput("Starting to crawl documentation from: " . base_path('nonexistent'))
            ->expectsOutputToContain("Error during crawling: Directory not found")
            ->assertExitCode(1);
    }

    /** @test */
    public function test_it_accepts_custom_path_option()
    {
        $customPath = 'storage/app/custom_docs';
        File::makeDirectory(base_path($customPath), 0755, true, true);
        File::put(base_path($customPath) . '/custom.md', "# Custom\nContent");

        $this->artisan('rag:crawl', ['--path' => $customPath])
            ->assertExitCode(0);

        $this->assertDatabaseHas('documents', ['url' => 'custom']);

        File::deleteDirectory(base_path($customPath));
    }
}
