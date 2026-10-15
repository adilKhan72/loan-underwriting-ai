<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email');
            $table->string('phone', 30)->nullable();
            // Encrypted at rest (ADR-0004): ciphertext is far longer than the plain value.
            $table->text('date_of_birth');
            $table->text('ssn');
            // Plain last four digits, kept separately so display and search never need decryption.
            $table->char('ssn_last4', 4);
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
