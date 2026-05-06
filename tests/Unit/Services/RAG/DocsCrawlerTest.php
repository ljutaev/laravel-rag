<?php

namespace Tests\Unit\Services\RAG;

use App\Models\Document;
use App\Services\RAG\DocsCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DocsCrawlerTest extends TestCase
{
    use RefreshDatabase;

    private DocsCrawler $crawler;
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crawler = new DocsCrawler();
        $this->tempPath = storage_path('app/testing_docs');
        File::makeDirectory($this->tempPath, 0755, true, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempPath);
        parent::tearDown();
    }

    /** @test */
    public function test_it_throws_if_directory_not_found()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Directory not found");

        $this->crawler->crawl(storage_path('non_existent_path'));
    }

    /** @test */
    public function test_it_skips_non_markdown_files()
    {
        File::put($this->tempPath . '/test.txt', 'Some text');

        $stats = $this->crawler->crawl($this->tempPath);

        $this->assertEquals(0, $stats['added']);
        $this->assertEquals(0, $stats['skipped']);
        $this->assertDatabaseCount('documents', 0);
    }

    /** @test */
    public function test_it_adds_new_document()
    {
        File::put($this->tempPath . '/test.md', "# Test Title\nContent here");

        $stats = $this->crawler->crawl($this->tempPath);

        $this->assertEquals(1, $stats['added']);
        $this->assertDatabaseHas('documents', [
            'url' => 'test',
            'title' => 'Test Title',
            'content' => "# Test Title\nContent here",
        ]);
    }

    /** @test */
    public function test_it_skips_unchanged_document()
    {
        $content = "# Test\nContent";
        $hash = hash('sha256', $content);
        Document::create([
            'url' => 'test',
            'title' => 'Test',
            'content' => $content,
            'content_hash' => $hash,
        ]);

        File::put($this->tempPath . '/test.md', $content);

        $stats = $this->crawler->crawl($this->tempPath);

        $this->assertEquals(1, $stats['skipped']);
        $this->assertEquals(0, $stats['added']);
        $this->assertEquals(0, $stats['updated']);
    }

    /** @test */
    public function test_it_updates_changed_document()
    {
        Document::create([
            'url' => 'test',
            'title' => 'Old Title',
            'content' => 'Old content',
            'content_hash' => 'old_hash',
        ]);

        $newContent = "# New Title\nNew content";
        File::put($this->tempPath . '/test.md', $newContent);

        $stats = $this->crawler->crawl($this->tempPath);

        $this->assertEquals(1, $stats['updated']);
        $this->assertDatabaseHas('documents', [
            'url' => 'test',
            'title' => 'New Title',
            'content' => $newContent,
        ]);
    }

    /** @test */
    public function test_it_extracts_h1_title()
    {
        File::put($this->tempPath . '/test.md', "# My Custom Title\nMore text");

        $this->crawler->crawl($this->tempPath);

        $this->assertDatabaseHas('documents', [
            'title' => 'My Custom Title',
        ]);
    }

    /** @test */
    public function test_it_uses_headline_fallback_when_no_h1()
    {
        File::put($this->tempPath . '/user-profile.md', "No H1 title here");

        $this->crawler->crawl($this->tempPath);

        $this->assertDatabaseHas('documents', [
            'title' => 'User Profile',
        ]);
    }

    /** @test */
    public function test_it_returns_correct_stats_summary()
    {
        // 1 Unchanged
        Document::create([
            'url' => 'unchanged',
            'content' => 'content',
            'content_hash' => hash('sha256', 'content'),
        ]);
        File::put($this->tempPath . '/unchanged.md', 'content');

        // 1 To update
        Document::create([
            'url' => 'update',
            'content' => 'old content',
            'content_hash' => 'old',
        ]);
        File::put($this->tempPath . '/update.md', 'new content');

        // 2 New
        File::put($this->tempPath . '/new1.md', 'content 1');
        File::put($this->tempPath . '/new2.md', 'content 2');

        $stats = $this->crawler->crawl($this->tempPath);

        $this->assertEquals(2, $stats['added']);
        $this->assertEquals(1, $stats['updated']);
        $this->assertEquals(1, $stats['skipped']);
    }

    /** @test */
    public function test_it_stores_metadata_with_file_path_and_size()
    {
        File::put($this->tempPath . '/meta.md', 'content');

        $this->crawler->crawl($this->tempPath);

        $doc = Document::where('url', 'meta')->first();
        $this->assertNotNull($doc->metadata);
        $this->assertEquals('meta.md', $doc->metadata['file_path']);
        $this->assertGreaterThan(0, $doc->metadata['size']);
    }
}
