<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FCA president-verification tables, plus the deferred representative back-reference.
 *
 * This is the identity and representative-authority surface that the Phase 1 isolated test was
 * written for. The contract is the reviewed schema contract already verified in the isolated MAO
 * scratch database.
 *
 * Properties that must not be relaxed:
 *
 *  - `fca_president_claims` carries `row_version` for optimistic concurrency and a
 *    (applicant_user_id, fca_id) unique key, so one account cannot hold two live claims for the
 *    same association. Identity is separated from authority: claiming never grants authority.
 *  - `fca_manual_decisions` records `decision_key` uniquely and stores the reviewer's evidence.
 *    Trusted-contact and manual verification are separate approval paths recorded separately.
 *  - `fca_phone_locks` is a single-column `char(64)` key table used as an advisory lock. It has
 *    no surrogate key and is never truncated as part of ordinary cleanup.
 *  - Every foreign key is `restrict`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fca_directory_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fca_id');
            $table->text('phone');
            // Deterministic, keyed digest of the phone used for lookup. The plaintext is
            // encrypted at the model layer; this column exists so lookup never needs to decrypt.
            $table->char('phone_lookup', 64);
            $table->text('source_reference');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('disputed');
            $table->timestamp('valid_until');
            $table->timestamps();

            $table->unique('fca_id', 'fca_directory_contacts_fca_id_unique');
            $table->index('phone_lookup', 'fca_directory_contacts_phone_lookup_index');

            $table->foreign('fca_id', 'fca_directory_contacts_fca_id_foreign')
                ->references('id')->on('fca_registry')->onDelete('restrict');
        });

        Schema::create('fca_president_claims', function (Blueprint $table) {
            $table->id();
            \App\Support\Schema\ParentKeyColumn::add($table, 'applicant_user_id', 'users', 'user_id');
            $table->unsignedBigInteger('fca_id');
            $table->unsignedBigInteger('directory_contact_id')->nullable();
            $table->unsignedInteger('directory_version')->nullable();
            $table->timestamp('consented_at');
            $table->string('notice_version', 100);
            $table->text('claimed_name');
            $table->text('claimed_phone');
            $table->string('status', 32)->default('manual_review');
            $table->unsignedInteger('row_version')->default(1);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('proof_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['applicant_user_id', 'fca_id'],
                'fca_president_claims_applicant_user_id_fca_id_unique');
            $table->index(['fca_id', 'status'], 'fca_president_claims_fca_id_status_index');

            $table->foreign('applicant_user_id', 'fca_president_claims_applicant_user_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
            $table->foreign('fca_id', 'fca_president_claims_fca_id_foreign')
                ->references('id')->on('fca_registry')->onDelete('restrict');
            $table->foreign('directory_contact_id',
                'fca_president_claims_directory_contact_id_foreign')
                ->references('id')->on('fca_directory_contacts')->onDelete('restrict');
        });

        Schema::create('fca_otp_challenges', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->unsignedBigInteger('claim_id');
            // Keyed digest of the verification method actually used.
            $table->char('verifier', 64);
            $table->string('status', 24);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamps();

            $table->index(['claim_id', 'created_at'], 'fca_otp_challenges_claim_id_created_at_index');

            $table->foreign('claim_id', 'fca_otp_challenges_claim_id_foreign')
                ->references('id')->on('fca_president_claims')->onDelete('restrict');
        });

        Schema::create('fca_phone_events', function (Blueprint $table) {
            $table->id();
            // Signed int on this installation: users.user_id is int(11), not an unsigned bigint.
            \App\Support\Schema\ParentKeyColumn::add($table, 'actor_user_id', 'users', 'user_id');
            $table->unsignedBigInteger('claim_id');
            $table->string('event', 32);
            $table->timestamps();

            $table->foreign('actor_user_id', 'fca_phone_events_actor_user_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
            $table->foreign('claim_id', 'fca_phone_events_claim_id_foreign')
                ->references('id')->on('fca_president_claims')->onDelete('restrict');
        });

        // Advisory lock for the phone-consumption critical section. Keyed by the derived phone
        // digest, so it carries no personal data of its own.
        Schema::create('fca_phone_locks', function (Blueprint $table) {
            $table->char('key', 64)->primary();
        });

        Schema::create('fca_manual_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'reviewer_id', 'users', 'user_id');
            $table->char('decision_key', 36);
            $table->char('decision_hash', 64);
            $table->string('result', 32);
            $table->string('outcome', 32);
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedInteger('contact_version')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->text('evidence');
            $table->timestamps();

            $table->unique('decision_key', 'fca_manual_decisions_decision_key_unique');
            $table->index(['claim_id', 'id'], 'fca_manual_decisions_claim_id_id_index');

            $table->foreign('claim_id', 'fca_manual_decisions_claim_id_foreign')
                ->references('id')->on('fca_president_claims')->onDelete('restrict');
            $table->foreign('reviewer_id', 'fca_manual_decisions_reviewer_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
            $table->foreign('contact_id', 'fca_manual_decisions_contact_id_foreign')
                ->references('id')->on('fca_directory_contacts')->onDelete('restrict');
        });

        Schema::create('fca_claim_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            $table->unsignedBigInteger('target_claim_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'actor_id', 'users', 'user_id');
            $table->unsignedBigInteger('review_decision_id');
            $table->char('submission_key', 36);
            $table->char('payload_hash', 64);
            $table->unsignedInteger('previous_version');
            $table->text('previous_details');
            $table->text('submitted_details');
            $table->timestamps();

            $table->unique('submission_key', 'fca_claim_revisions_submission_key_unique');
            $table->unique('review_decision_id', 'fca_claim_revisions_review_decision_id_unique');
            $table->index(['claim_id', 'id'], 'fca_claim_revisions_claim_id_id_index');

            $table->foreign('claim_id', 'fca_claim_revisions_claim_id_foreign')
                ->references('id')->on('fca_president_claims')->onDelete('restrict');
            $table->foreign('target_claim_id', 'fca_claim_revisions_target_claim_id_foreign')
                ->references('id')->on('fca_president_claims')->onDelete('restrict');
            $table->foreign('actor_id', 'fca_claim_revisions_actor_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
            $table->foreign('review_decision_id', 'fca_claim_revisions_review_decision_id_foreign')
                ->references('id')->on('fca_manual_decisions')->onDelete('restrict');
        });

        // Deferred from fca_representatives: the target table only exists above.
        Schema::table('fca_representatives', function (Blueprint $table) {
            $table->foreign('president_claim_id', 'fca_representatives_president_claim_id_foreign')
                ->references('id')->on('fca_president_claims')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('fca_representatives', function (Blueprint $table) {
            $table->dropForeign('fca_representatives_president_claim_id_foreign');
        });

        Schema::dropIfExists('fca_claim_revisions');
        Schema::dropIfExists('fca_manual_decisions');
        Schema::dropIfExists('fca_phone_locks');
        Schema::dropIfExists('fca_phone_events');
        Schema::dropIfExists('fca_otp_challenges');
        Schema::dropIfExists('fca_president_claims');
        Schema::dropIfExists('fca_directory_contacts');
    }
};