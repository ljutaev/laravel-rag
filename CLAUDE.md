# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Laravel 13 / PHP 8.3 RAG (Retrieval-Augmented Generation) application. Documents are crawled from Markdown files, stored in PostgreSQL (with the **pgvector** extension), and will be retrievable via semantic/vector search. Ollama runs locally as the LLM backend.

## Commands

```bash
# First-time setup
composer run setup

# Start all Docker services (PHP-FPM, Nginx, Postgres/pgvector, Redis, Adminer, Ollama)
docker compose up -d

# Run the full dev stack (server + queue + logs + Vite)
composer run dev

# Run all tests
composer run test
# or directly:
php artisan test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Run tests matching a filter
php artisan test --filter ExampleTest

# Code style fix (Laravel Pint / PSR-12)
vendor/bin/pint

# Code style check only
vendor/bin/pint --test

# Crawl docs into the database
php artisan rag:crawl
# With a custom path:
php artisan rag:crawl --path=storage/app/docs/my-docs

# Run migrations
php artisan migrate
```

## Infrastructure

| Service   | Port  | Notes                                      |
|-----------|-------|--------------------------------------------|
| Nginx     | 8000  | Serves the app; proxies to PHP-FPM         |
| PHP-FPM   | —     | `docker/php/Dockerfile` (PHP 8.3)          |
| Postgres  | 5432  | `ankane/pgvector` image — pgvector enabled |
| Redis     | 6379  | Cache / queues                             |
| Adminer   | 8080  | DB UI                                      |
| Ollama    | 11434 | Local LLM inference                        |

`.env` defaults to SQLite; switch `DB_CONNECTION` and uncomment the `DB_*` vars to use the Dockerised Postgres instance.

## Architecture

### RAG pipeline (current)

```
Markdown files  →  DocsCrawler  →  documents table  →  (vector search — upcoming)
```

- `app/Services/RAG/DocsCrawler.php` — reads `.md` files from a directory, extracts the title from the first `#` heading, hashes content to skip unchanged files, and upserts into `documents`.
- `app/Console/Commands/RAG/CrawlDocsCommand.php` — `rag:crawl` Artisan command; wires the path option to `DocsCrawler`.
- `app/Models/Document.php` — Eloquent model; `metadata` cast to `array` (stored as `jsonb`); `indexed_at` timestamps when a document was last embedded/indexed.

### `documents` table key columns

| Column         | Type         | Purpose                                    |
|----------------|--------------|--------------------------------------------|
| `source`       | string       | Origin identifier (default `laravel_docs`) |
| `url`          | string(500)  | Unique slug/path used as the natural key   |
| `content_hash` | string(64)   | SHA-256; skips re-processing unchanged docs|
| `metadata`     | jsonb        | Arbitrary per-document metadata            |
| `indexed_at`   | timestamp    | Set when embeddings are generated          |

### Namespace layout

```
app/
  Console/Commands/RAG/   Artisan commands for the RAG pipeline
  Services/RAG/           Business logic: crawling, (future) embedding, retrieval
  Models/                 Eloquent models
```

## Testing

Tests use SQLite in-memory (`DB_DATABASE=:memory:`) — no Docker required to run the test suite. Postgres-specific features (e.g. pgvector queries) will need a separate integration test suite against the real DB.

```bash
# Unit suite only
php artisan test --testsuite=Unit

# Feature suite only
php artisan test --testsuite=Feature
```