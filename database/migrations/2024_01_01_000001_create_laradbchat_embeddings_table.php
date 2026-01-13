<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laradbchat_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // 'table', 'column', 'relationship', 'documentation'
            $table->string('identifier')->index(); // table name, column path, etc.
            $table->text('content'); // The actual text content (DDL, description, etc.)
            $table->json('embedding'); // Vector embedding as JSON array
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();

            $table->unique(['type', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laradbchat_embeddings');
    }
};
