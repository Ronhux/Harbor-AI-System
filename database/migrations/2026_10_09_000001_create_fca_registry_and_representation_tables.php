<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FCA registry and producer-representation tables.
 *
 * The column, index and foreign-key contract is the reviewed schema contract that was already
 * built, migrated and verified in the isolated MAO scratch database (32 InnoDB tables, ledger
 * orders 1-11, all green). It is reproduced here rather than redesigned.
 *
 * Two properties are deliberate and must not be relaxed:
 *
 *  - Referential actions are `restrict` everywhere. FCA records are audit-bearing; a cascade
 *    could silently erase evidence, representative authority or a decision history.
 *  - `fca_representatives.president_claim_id` is created here but its foreign key is added in
 *    the president-verification migration, because the two tables reference each other in
 *    different directions. The column is nullable, so creating it early is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fca_registry', function (Blueprint $table) {
            $table->id();
            $table->string('source_record_key')->nullable();
            $table->string('official_name');
            $table->text('address');
            $table->string('president_name');
            $table->string('contact_number')->nullable();
            $table->string('registry_source')->nullable();
            $table->string('directory_verification_status', 32)->default('pending');
            $table->string('procurement_eligibility_status', 32)->default('not_assessed');
            $table->text('procurement_evidence_reference')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique('source_record_key', 'fca_registry_source_record_key_unique');
        });

        Schema::create('fca_representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fca_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'producer_id', 'producers', 'producer_id')->nullable();
            \App\Support\Schema\ParentKeyColumn::add($table, 'reviewed_by', 'users', 'user_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('authorization_evidence_reference')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->longText('submitted_details')->nullable();
            $table->char('submission_key', 36)->nullable();
            // Optimistic-concurrency counter. A plain default would defeat its purpose on a row
            // written by an UPDATE rather than an INSERT, so it is explicit.
            $table->unsignedInteger('row_version')->default(0);
            $table->string('verification_kind', 24)->nullable();
            $table->unsignedBigInteger('president_claim_id')->nullable();

            $table->unique(['fca_id', 'producer_id'],
                'fca_representatives_fca_id_producer_id_unique');
            $table->unique('submission_key', 'fca_representatives_submission_key_unique');

            $table->foreign('fca_id', 'fca_representatives_fca_id_foreign')
                ->references('id')->on('fca_registry')->onDelete('restrict');
            $table->foreign('producer_id', 'fca_representatives_producer_id_foreign')
                ->references('producer_id')->on('producers')->onDelete('restrict');
            $table->foreign('reviewed_by', 'fca_representatives_reviewed_by_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fca_representatives');
        Schema::dropIfExists('fca_registry');
    }
};