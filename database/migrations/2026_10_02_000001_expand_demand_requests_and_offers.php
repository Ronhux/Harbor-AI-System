<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('demand_requests')) {
            Schema::table('demand_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('demand_requests', 'preferred_delivery_date')) $table->date('preferred_delivery_date')->nullable();
                if (!Schema::hasColumn('demand_requests', 'quality_requirements')) $table->text('quality_requirements')->nullable();
                if (!Schema::hasColumn('demand_requests', 'special_requirements')) $table->text('special_requirements')->nullable();
                if (!Schema::hasColumn('demand_requests', 'partial_fulfillment')) $table->boolean('partial_fulfillment')->default(false);
            });
        }

        if (Schema::hasTable('demand_details')) {
            Schema::table('demand_details', function (Blueprint $table) {
                if (!Schema::hasColumn('demand_details', 'listing_id')) $table->unsignedBigInteger('listing_id')->nullable()->index();
                if (!Schema::hasColumn('demand_details', 'quantity_offered')) $table->decimal('quantity_offered', 12, 3)->nullable();
                if (!Schema::hasColumn('demand_details', 'availability_date')) $table->date('availability_date')->nullable();
                if (!Schema::hasColumn('demand_details', 'delivery_terms')) $table->text('delivery_terms')->nullable();
                if (!Schema::hasColumn('demand_details', 'quality_grade')) $table->string('quality_grade', 255)->nullable();
                if (!Schema::hasColumn('demand_details', 'revisions')) $table->json('revisions')->nullable();
            });
        }

        if (Schema::hasTable('negotiations') && !Schema::hasColumn('negotiations', 'demand_detail_id')) {
            Schema::table('negotiations', fn (Blueprint $table) => $table->unsignedBigInteger('demand_detail_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        // Keep submitted offers and negotiation links intact on rollback.
    }
};
