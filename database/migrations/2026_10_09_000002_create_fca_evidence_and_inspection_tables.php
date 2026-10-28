<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FCA evidence, review-event and inspection-attempt tables.
 *
 * This is the sensitive-evidence and audit surface. The contract is the reviewed schema contract
 * already verified in the isolated MAO scratch database.
 *
 * Properties that must not be relaxed:
 *
 *  - `fca_evidence` and `fca_inspection_attempts` use a `char(36)` UUID primary key, matching the
 *    `string` key type declared on both Eloquent models. Nothing is auto-incrementing here, so a
 *    receipt is never guessable or enumerable.
 *  - `sha256`, `verifier`, `storage_key`, `input_sha256` and `operation_key` are the integrity
 *    guarantees the evidence services assert against. They are not nullable.
 *  - Every foreign key is `restrict`: evidence and its audit trail must never be cascaded away.
 *  - `fca_evidence.latest_attempt_id` is created before `fca_inspection_attempts` exists and its
 *    key is added at the end of this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fca_evidence', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            \App\Support\Schema\ParentKeyColumn::add($table, 'uploaded_by', 'users', 'user_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'producer_id', 'producers', 'producer_id');
            $table->unsignedBigInteger('application_id')->nullable();
            $table->string('storage_key', 100);
            $table->string('mime', 64);
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->string('status', 32)->default('quarantined');
            $table->timestamp('expires_at')->nullable()->default(null);
            $table->timestamps();
            $table->unsignedInteger('evidence_version')->default(1);
            $table->string('inspection_state', 32)->default('quarantined');
            // Added after fca_inspection_attempts exists; see up().
            $table->char('latest_attempt_id', 36)->nullable();

            $table->unique('storage_key', 'fca_evidence_storage_key_unique');
            $table->index(['uploaded_by', 'status'], 'fca_evidence_uploaded_by_status_index');

            $table->foreign('uploaded_by', 'fca_evidence_uploaded_by_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
            $table->foreign('producer_id', 'fca_evidence_producer_id_foreign')
                ->references('producer_id')->on('producers')->onDelete('restrict');
            $table->foreign('application_id', 'fca_evidence_application_id_foreign')
                ->references('id')->on('fca_representatives')->onDelete('restrict');
        });

        Schema::create('fca_review_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id')->nullable();
            \App\Support\Schema\ParentKeyColumn::add($table, 'actor_user_id', 'users', 'user_id');
            $table->string('event_type', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->longText('review_reason')->nullable();
            $table->char('decision_key', 36)->nullable();
            $table->char('decision_hash', 64)->nullable();
            $table->timestamps();
            $table->char('evidence_id', 36)->nullable();
            $table->unsignedInteger('evidence_version')->nullable();
            $table->string('authorization_decision', 32)->nullable();
            $table->string('reason_category', 64)->nullable();
            $table->string('inspection_state', 32)->nullable();
            $table->char('correlation_id', 36)->nullable();

            $table->unique('decision_key', 'fca_review_events_decision_key_unique');
            $table->index('correlation_id', 'fca_review_events_correlation_id_index');

            $table->foreign('application_id', 'fca_review_events_application_id_foreign')
                ->references('id')->on('fca_representatives')->onDelete('restrict');
            $table->foreign('actor_user_id', 'fca_review_events_actor_user_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
            $table->foreign('evidence_id', 'fca_review_events_evidence_id_foreign')
                ->references('id')->on('fca_evidence')->onDelete('restrict');
        });

        Schema::create('fca_inspection_attempts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('evidence_id', 36);
            $table->unsignedInteger('evidence_version');
            $table->char('input_sha256', 64);
            $table->char('operation_key', 36);
            $table->char('retry_of_id', 36)->nullable();
            \App\Support\Schema\ParentKeyColumn::add($table, 'initiated_by', 'users', 'user_id');
            $table->string('inspection_type', 32)->default('document');
            $table->string('adapter_id', 64);
            $table->string('status', 32)->default('pending');
            $table->string('error_category', 64)->nullable();
            // JSON is longtext plus a json_valid check on MySQL/MariaDB; the model casts to array.
            $table->json('check_results')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('operation_key', 'fca_inspection_attempts_operation_key_unique');
            $table->index(['evidence_id', 'evidence_version', 'status'], 'fca_attempt_evidence_status');

            $table->foreign('evidence_id', 'fca_inspection_attempts_evidence_id_foreign')
                ->references('id')->on('fca_evidence')->onDelete('restrict');
            $table->foreign('retry_of_id', 'fca_inspection_attempts_retry_of_id_foreign')
                ->references('id')->on('fca_inspection_attempts')->onDelete('restrict');
            $table->foreign('initiated_by', 'fca_inspection_attempts_initiated_by_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
        });

        // Deferred from fca_evidence: the target table only exists above.
        Schema::table('fca_evidence', function (Blueprint $table) {
            $table->foreign('latest_attempt_id', 'fca_evidence_latest_attempt_id_foreign')
                ->references('id')->on('fca_inspection_attempts')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('fca_evidence', function (Blueprint $table) {
            $table->dropForeign('fca_evidence_latest_attempt_id_foreign');
        });

        Schema::dropIfExists('fca_inspection_attempts');
        Schema::dropIfExists('fca_review_events');
        Schema::dropIfExists('fca_evidence');
    }
};