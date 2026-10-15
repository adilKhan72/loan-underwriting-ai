<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('policy_version', 20);
            $table->string('outcome', 20);
            $table->json('rule_results');
            // The exact inputs the rules saw: replaying facts + policy_version reproduces the outcome.
            $table->json('facts');
            $table->string('explanation_status', 20)->default('pending');
            $table->text('underwriter_summary')->nullable();
            $table->text('applicant_message')->nullable();
            $table->json('principal_reasons')->nullable();
            $table->string('explanation_provider', 30)->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['loan_application_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
