<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function legacyKey(Blueprint $table, string $column, string $parent, string $key, bool $nullable = false): void
    {
        $metadata = collect(Schema::getColumns($parent))->firstWhere('name', $key);
        $type = strtolower($metadata['type'] ?? '');
        if (!preg_match('/\A(?:bigint|int|integer)(?:\(\d+\))?(?: unsigned)?\z/', $type)) throw new RuntimeException('Unsupported FCA parent key.');
        $field = str_contains($type, 'bigint') ? $table->bigInteger($column) : $table->integer($column);
        if (str_contains($type, 'unsigned')) $field->unsigned();
        if ($nullable) $field->nullable();
        $table->foreign($column)->references($key)->on($parent)->restrictOnDelete();
    }

    public function up(): void
    {
        if (!Schema::hasTable('fca_registry') || !Schema::hasTable('fca_representatives')) {
            throw new RuntimeException('Prepared FCA base schema must be reviewed/applied first.');
        }
        if (Schema::hasTable('fca_evidence') || Schema::hasTable('fca_review_events')
            || Schema::hasColumn('fca_representatives', 'submitted_details')
            || Schema::hasColumn('fca_representatives', 'submission_key')
            || Schema::hasColumn('fca_representatives', 'row_version')) {
            throw new RuntimeException('Partial or existing schema: stop for manual review, never retry automatically.');
        }
        // Check all key types before the first nontransactional MySQL DDL step.
        foreach (['users' => 'user_id', 'producers' => 'producer_id', 'fca_representatives' => 'id'] as $parent => $key) {
            $column = collect(Schema::getColumns($parent))->firstWhere('name', $key);
            if (!preg_match('/\A(?:bigint|int|integer)(?:\(\d+\))?(?: unsigned)?\z/', strtolower($column['type'] ?? ''))) {
                throw new RuntimeException('FCA parent key must be reviewed before schema changes.');
            }
        }
        Schema::table('fca_representatives', function (Blueprint $table) {
            $table->longText('submitted_details')->nullable();
            $table->uuid('submission_key')->nullable()->unique();
            $table->unsignedInteger('row_version')->default(0);
        });
        Schema::create('fca_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $this->legacyKey($table, 'uploaded_by', 'users', 'user_id');
            $this->legacyKey($table, 'producer_id', 'producers', 'producer_id');
            $this->legacyKey($table, 'application_id', 'fca_representatives', 'id', true);
            $table->string('storage_key', 100)->unique();
            $table->string('mime', 64);
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->string('status', 32)->default('quarantined');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['uploaded_by', 'status']);
        });
        Schema::create('fca_review_events', function (Blueprint $table) {
            $table->id();
            $this->legacyKey($table, 'application_id', 'fca_representatives', 'id', true);
            $this->legacyKey($table, 'actor_user_id', 'users', 'user_id');
            $table->string('event_type', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->longText('review_reason')->nullable();
            $table->uuid('decision_key')->nullable()->unique();
            $table->char('decision_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('fca_evidence')->exists() || DB::table('fca_review_events')->exists()
            || DB::table('fca_representatives')->whereNotNull('submitted_details')->exists()
            || DB::table('fca_representatives')->whereNotNull('submission_key')->exists()
            || DB::table('fca_representatives')->where('row_version', '>', 0)->exists()) {
            throw new RuntimeException('Rollback refused: retain/export application, evidence and review history. Disable workflow instead.');
        }
        Schema::drop('fca_review_events');
        Schema::drop('fca_evidence');
        Schema::table('fca_representatives', function (Blueprint $table) {
            $table->dropUnique(['submission_key']);
            $table->dropColumn(['submitted_details', 'submission_key', 'row_version']);
        });
    }
};
