<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fca_evidence') || !Schema::hasTable('fca_review_events')
            || Schema::hasTable('fca_inspection_attempts') || Schema::hasColumn('fca_evidence', 'evidence_version')) {
            throw new RuntimeException('FCA inspection dependencies/partial schema require review before DDL.');
        }
        foreach (['evidence_version', 'inspection_state', 'latest_attempt_id'] as $column) {
            if (Schema::hasColumn('fca_evidence', $column)) throw new RuntimeException('Partial evidence inspection schema: manual review required.');
        }
        foreach (['evidence_id', 'evidence_version', 'authorization_decision', 'reason_category', 'inspection_state', 'correlation_id'] as $column) {
            if (Schema::hasColumn('fca_review_events', $column)) throw new RuntimeException('Partial inspection audit schema: manual review required.');
        }
        $userKey = collect(Schema::getColumns('users'))->firstWhere('name', 'user_id');
        $type = strtolower($userKey['type'] ?? '');
        if (!preg_match('/\A(?:bigint|int|integer)(?:\(\d+\))?(?: unsigned)?\z/', $type)) throw new RuntimeException('Unsupported actor key.');
        Schema::table('fca_evidence', function (Blueprint $table) {
            $table->unsignedInteger('evidence_version')->default(1);
            $table->string('inspection_state', 32)->default('quarantined');
            $table->uuid('latest_attempt_id')->nullable();
        });
        Schema::create('fca_inspection_attempts', function (Blueprint $table) use ($type) {
            $table->uuid('id')->primary();
            $table->uuid('evidence_id');
            $table->foreign('evidence_id')->references('id')->on('fca_evidence')->restrictOnDelete();
            $table->unsignedInteger('evidence_version');
            $table->char('input_sha256', 64);
            $table->uuid('operation_key')->unique();
            $table->uuid('retry_of_id')->nullable();
            $table->foreign('retry_of_id')->references('id')->on('fca_inspection_attempts')->restrictOnDelete();
            $actor = str_contains($type, 'bigint') ? $table->bigInteger('initiated_by') : $table->integer('initiated_by');
            if (str_contains($type, 'unsigned')) $actor->unsigned();
            $table->foreign('initiated_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->string('inspection_type', 32)->default('document');
            $table->string('adapter_id', 64);
            $table->string('status', 32)->default('pending');
            $table->string('error_category', 64)->nullable();
            $table->json('check_results')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['evidence_id', 'evidence_version', 'status'], 'fca_attempt_evidence_status');
        });
        Schema::table('fca_evidence', fn (Blueprint $table) => $table->foreign('latest_attempt_id')->references('id')->on('fca_inspection_attempts')->restrictOnDelete());
        Schema::table('fca_review_events', function (Blueprint $table) {
            // Extend the existing audit ledger, do not duplicate application history.
            $table->uuid('evidence_id')->nullable();
            $table->foreign('evidence_id')->references('id')->on('fca_evidence')->restrictOnDelete();
            $table->unsignedInteger('evidence_version')->nullable();
            $table->string('authorization_decision', 32)->nullable();
            $table->string('reason_category', 64)->nullable();
            $table->string('inspection_state', 32)->nullable();
            $table->uuid('correlation_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (DB::table('fca_inspection_attempts')->exists() || DB::table('fca_review_events')->whereNotNull('evidence_id')->exists()
            || DB::table('fca_review_events')->whereNotNull('correlation_id')->exists()
            || DB::table('fca_review_events')->whereNotNull('authorization_decision')->exists()
            || DB::table('fca_evidence')->whereNotNull('latest_attempt_id')->exists()
            || DB::table('fca_evidence')->where('evidence_version', '>', 1)->exists()) {
            throw new RuntimeException('Rollback refused: preserve inspection and release history. Disable workflow.');
        }
        Schema::table('fca_review_events', function (Blueprint $table) {
            $table->dropForeign(['evidence_id']); $table->dropIndex(['correlation_id']);
            $table->dropColumn(['evidence_id', 'evidence_version', 'authorization_decision', 'reason_category', 'inspection_state', 'correlation_id']);
        });
        Schema::table('fca_evidence', function (Blueprint $table) {
            $table->dropForeign(['latest_attempt_id']);
            $table->dropColumn(['evidence_version', 'inspection_state', 'latest_attempt_id']);
        });
        Schema::drop('fca_inspection_attempts');
    }
};
