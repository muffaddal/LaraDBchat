<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laradbchat_query_logs', function (Blueprint $table) {
            $table->id();
            $table->text('question'); // Natural language question
            $table->text('generated_sql'); // Generated SQL query
            $table->json('results')->nullable(); // Query results (if stored)
            $table->integer('result_count')->nullable(); // Number of results
            $table->float('execution_time')->nullable(); // Query execution time in seconds
            $table->text('error')->nullable(); // Error message if query failed
            $table->string('status')->default('success'); // success, error, pending
            $table->string('llm_provider')->nullable(); // Which LLM was used
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();

            $table->index('created_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laradbchat_query_logs');
    }
};
