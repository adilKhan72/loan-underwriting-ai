<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('status', 20)->default('uploaded');
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamps();

            // The same file can't be attached to one application twice.
            $table->unique(['loan_application_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
