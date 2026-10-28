<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KADIWA event, application, inquiry and audit tables.
 *
 * KADIWA is a market facilitation service: it publishes selling venues where approved
 * producers sell directly to customers. It is NOT an institutional buyer, procurement
 * process, purchase order, award or payment service, and nothing in this schema records one.
 *
 * Properties that must not be relaxed:
 * - All foreign keys use `restrict` to preserve audit history
 * - Foreign keys onto a `$table->id()` parent (kadiwa_events, kadiwa_applications) MUST be
 *   unsignedBigInteger; producers.producer_id and users.user_id are signed int(11) on this
 *   installation and are resolved through ParentKeyColumn
 * - Row version columns for optimistic concurrency
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kadiwa_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('venue');
            $table->string('location');
            // Nullable rather than NOT NULL without a default: this server runs with
            // explicit_defaults_for_timestamp=OFF, where a second, third and fourth
            // timestamp column is implicitly given DEFAULT '0000-00-00 00:00:00' and the
            // CREATE TABLE is rejected. Validation still requires all four on every write.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->json('categories')->nullable();
            $table->json('requirements')->nullable();
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('row_version')->default(0);
            $table->boolean('evidence_required')->default(false);
            $table->boolean('rsbsa_required')->default(false);
            $table->timestamps();

            $table->index('status', 'kadiwa_events_status_index');
            $table->index('starts_at', 'kadiwa_events_starts_at_index');
        });

        Schema::create('kadiwa_applications', function (Blueprint $table) {
            $table->id();
            // Bigint unsigned: matches kadiwa_events.id from $table->id().
            $table->unsignedBigInteger('event_id');
            // Signed int: matches producers.producer_id (int(11)) on this installation.
            \App\Support\Schema\ParentKeyColumn::add($table, 'producer_id', 'producers', 'producer_id');
            $table->string('submission_key', 36);
            $table->char('submission_hash', 64);
            $table->json('details')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('row_version')->default(0);
            $table->timestamps();

            $table->unique('submission_key', 'kadiwa_applications_submission_key_unique');
            $table->index(['event_id', 'status'], 'kadiwa_applications_event_id_status_index');
            $table->index('producer_id', 'kadiwa_applications_producer_id_index');
            // One live participation record per producer per event; superseded rows stay for audit.
            $table->index(['event_id', 'producer_id', 'status'], 'kadiwa_applications_event_producer_status_index');

            $table->foreign('event_id', 'kadiwa_applications_event_id_foreign')
                ->references('id')->on('kadiwa_events')->onDelete('restrict');
            $table->foreign('producer_id', 'kadiwa_applications_producer_id_foreign')
                ->references('producer_id')->on('producers')->onDelete('restrict');
        });

        Schema::create('kadiwa_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('locality', 160);
            $table->text('message');
            $table->text('response')->nullable();
            $table->string('status', 32)->default('pending');
            \App\Support\Schema\ParentKeyColumn::add($table, 'owner_id', 'users', 'user_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'reviewer_id', 'users', 'user_id')->nullable();
            $table->char('payload_hash', 64);
            $table->unsignedInteger('row_version')->default(0);
            $table->timestamps();

            $table->index('owner_id', 'kadiwa_inquiries_owner_id_index');
            $table->index('status', 'kadiwa_inquiries_status_index');
            $table->index('payload_hash', 'kadiwa_inquiries_payload_hash_index');

            $table->foreign('owner_id', 'kadiwa_inquiries_owner_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
            $table->foreign('reviewer_id', 'kadiwa_inquiries_reviewer_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
        });

        // Coordinator assignments - separate table for event-to-coordinator mapping.
        Schema::create('kadiwa_event_coordinators', function (Blueprint $table) {
            $table->id();
            // Bigint unsigned: matches kadiwa_events.id from $table->id().
            $table->unsignedBigInteger('event_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'user_id', 'users', 'user_id');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'user_id'], 'kadiwa_event_coordinators_event_user_unique');
            $table->index('user_id', 'kadiwa_event_coordinators_user_id_index');

            $table->foreign('event_id', 'kadiwa_event_coordinators_event_id_foreign')
                ->references('id')->on('kadiwa_events')->onDelete('restrict');
            $table->foreign('user_id', 'kadiwa_event_coordinators_user_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
        });

        Schema::create('kadiwa_participation_reviews', function (Blueprint $table) {
            $table->id();
            // Bigint unsigned: matches kadiwa_applications.id from $table->id().
            $table->unsignedBigInteger('application_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'reviewer_id', 'users', 'user_id');
            $table->string('decision', 32); // approve, reject, request_information, withdraw
            $table->text('reason');
            $table->unsignedInteger('version');
            $table->char('operation_key', 36);
            $table->timestamps();

            $table->unique('operation_key', 'kadiwa_participation_reviews_operation_key_unique');
            $table->index('application_id', 'kadiwa_participation_reviews_application_id_index');

            $table->foreign('application_id', 'kadiwa_participation_reviews_application_id_foreign')
                ->references('id')->on('kadiwa_applications')->onDelete('restrict');
            $table->foreign('reviewer_id', 'kadiwa_participation_reviews_reviewer_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
        });

        // Participation status timeline. Written for every submission, resubmission, withdrawal,
        // review decision and event cancellation so a producer can always see how a decision was reached.
        Schema::create('kadiwa_application_status_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'actor_user_id', 'users', 'user_id')->nullable();
            $table->string('action', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->timestamps();

            $table->index('application_id', 'kadiwa_application_status_events_application_id_index');

            $table->foreign('application_id', 'kadiwa_application_status_events_application_id_foreign')
                ->references('id')->on('kadiwa_applications')->onDelete('restrict');
            $table->foreign('actor_user_id', 'kadiwa_application_status_events_actor_user_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
        });

        // Management audit trail for significant event actions (create, edit, publish, close, reschedule).
        Schema::create('kadiwa_event_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            \App\Support\Schema\ParentKeyColumn::add($table, 'actor_user_id', 'users', 'user_id');
            $table->string('action', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedInteger('version');
            $table->char('operation_key', 36);
            $table->timestamps();

            $table->unique('operation_key', 'kadiwa_event_audits_operation_key_unique');
            $table->index('event_id', 'kadiwa_event_audits_event_id_index');
            $table->index('actor_user_id', 'kadiwa_event_audits_actor_user_id_index');

            $table->foreign('event_id', 'kadiwa_event_audits_event_id_foreign')
                ->references('id')->on('kadiwa_events')->onDelete('restrict');
            $table->foreign('actor_user_id', 'kadiwa_event_audits_actor_user_id_foreign')
                ->references('user_id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kadiwa_event_audits');
        Schema::dropIfExists('kadiwa_application_status_events');
        Schema::dropIfExists('kadiwa_participation_reviews');
        Schema::dropIfExists('kadiwa_event_coordinators');
        Schema::dropIfExists('kadiwa_inquiries');
        Schema::dropIfExists('kadiwa_applications');
        Schema::dropIfExists('kadiwa_events');
    }
};