<?php

namespace App\Services\RAG;

use App\Models\Document;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocsCrawler
{
    public function crawl(string $path): array
    {
        if (!File::isDirectory($path)) {
            throw new \Exception("Directory not found: {$path}");
        }

        $files = File::allFiles($path);
        $stats = ['added' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $content = File::get($file->getRealPath());
            $filename = $file->getFilenameWithoutExtension();
            $hash = hash('sha256', $content);

            $existing = Document::where('url', $filename)->first();

            if ($existing && $existing->content_hash === $hash) {
                $stats['skipped']++;
                continue;
            }

            $title = $this->extractTitle($content) ?: Str::headline($filename);

            Document::updateOrCreate(
                ['url' => $filename],
                [
                    'source' => 'laravel_docs',
                    'title' => $title,
                    'section' => 'documentation',
                    'content' => $content,
                    'content_hash' => $hash,
                    'metadata' => [
                        'file_path' => $file->getRelativePathname(),
                        'size' => $file->getSize(),
                    ],
                ]
            );

            if ($existing) {
                $stats['updated']++;
            } else {
                $stats['added']++;
            }
        }

        return $stats;
    }

    private function extractTitle(string $content): ?string
    {
        if (preg_match('/^#\s+(.*)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
