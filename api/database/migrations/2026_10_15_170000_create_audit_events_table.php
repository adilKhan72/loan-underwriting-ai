<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('loan_application_id');
            // 1, 2, 3… per application: explicit order, and a deleted event leaves a detectable gap.
            $table->unsignedInteger('position');
            $table->string('event', 60);
            $table->string('actor_type', 20);
            $table->string('actor_id', 40)->nullable();
            $table->json('data');
            // Hash chain per application: each hash covers the event and the previous hash.
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64)->unique();
            $table->timestamp('occurred_at', 6);

            $table->unique(['loan_application_id', 'position']);
        });

        // Defence in depth: on PostgreSQL even raw SQL can't rewrite the trail.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION audit_events_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_events is append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER audit_events_no_update_delete
                    BEFORE UPDATE OR DELETE ON audit_events
                    FOR EACH ROW EXECUTE FUNCTION audit_events_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_update_delete ON audit_events; DROP FUNCTION IF EXISTS audit_events_append_only();');
        }

        Schema::dropIfExists('audit_events');
    }
};
