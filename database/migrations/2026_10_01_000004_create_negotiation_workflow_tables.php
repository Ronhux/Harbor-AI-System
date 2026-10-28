<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('negotiations')) Schema::create('negotiations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('reference', 32)->unique();
            $table->integer('buyer_id')->index();
            $table->integer('producer_id')->index();
            $table->integer('listing_id')->index();
            $table->integer('demand_request_id')->nullable();
            $table->string('status', 48)->index();
            $table->unsignedInteger('current_version')->default(1);
            $table->json('agreement_snapshot')->nullable();
            $table->timestamps();
        });
        if (!Schema::hasTable('negotiation_versions')) Schema::create('negotiation_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('negotiation_id')->index();
            $table->unsignedInteger('version');
            $table->integer('proposed_by_user_id')->index();
            $table->decimal('listed_price', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 50);
            $table->date('delivery_date')->nullable();
            $table->text('delivery_arrangement')->nullable();
            $table->decimal('additional_charges', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('remarks')->nullable();
            $table->string('proposed_by_role', 20);
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->timestamp('producer_confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['negotiation_id', 'version']);
        });
        if (!Schema::hasTable('negotiation_events')) Schema::create('negotiation_events', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('negotiation_id')->index();
            $table->integer('version_id')->nullable();
            $table->integer('actor_user_id')->index();
            $table->string('event', 48);
            $table->string('status', 48);
            $table->text('remarks')->nullable();
            $table->json('terms')->nullable();
            $table->timestamps();
        });
        if (!Schema::hasTable('transaction_notifications')) Schema::create('transaction_notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('recipient_user_id')->index();
            $table->integer('negotiation_id')->nullable()->index();
            $table->integer('order_id')->nullable()->index();
            $table->string('title', 160);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        if (Schema::hasTable('orders')) {
            try {
                DB::select('select * from orders limit 0');
                if (!Schema::hasColumn('orders', 'negotiation_id')) Schema::table('orders', function (Blueprint $table) { $table->integer('negotiation_id')->nullable()->unique(); });
                if (!Schema::hasColumn('orders', 'delivery_date')) Schema::table('orders', function (Blueprint $table) { $table->date('delivery_date')->nullable(); });
            } catch (\Throwable $error) {
                // Preserve an inaccessible legacy table: never drop or rebuild it here.
            }
        }
    }
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'negotiation_id')) Schema::table('orders', fn (Blueprint $table) => $table->dropUnique(['negotiation_id']));
        if (Schema::hasColumn('orders', 'negotiation_id')) Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('negotiation_id'));
        if (Schema::hasColumn('orders', 'delivery_date')) Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivery_date'));
        Schema::dropIfExists('transaction_notifications');
        Schema::dropIfExists('negotiation_events');
        Schema::dropIfExists('negotiation_versions');
        Schema::dropIfExists('negotiations');
    }
};
