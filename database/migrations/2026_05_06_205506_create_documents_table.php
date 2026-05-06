<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('laravel_docs');
            $table->string('url', 500);
            $table->string('title', 500)->nullable();
            $table->string('section')->nullable();
            $table->text('content');
            $table->string('content_hash', 64)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('url');
            $table->index('content_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
