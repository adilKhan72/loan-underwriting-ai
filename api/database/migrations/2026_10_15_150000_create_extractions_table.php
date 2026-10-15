<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per processing run, so re-processing a document keeps the earlier result for audit.
        Schema::create('extractions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->json('data')->nullable();
            $table->json('errors')->nullable();
            // What was redacted (type => count), never the values themselves (ADR-0004).
            $table->json('redactions')->nullable();
            $table->string('provider', 30)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extractions');
    }
};
