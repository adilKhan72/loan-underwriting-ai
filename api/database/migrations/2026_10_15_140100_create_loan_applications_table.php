<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('applicant_id')->constrained()->cascadeOnDelete();
            // Money is always integer cents: no floating-point rounding in underwriting maths.
            $table->unsignedBigInteger('amount_requested_cents');
            $table->unsignedSmallInteger('term_months');
            $table->string('purpose')->nullable();
            $table->unsignedBigInteger('stated_monthly_income_cents');
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
