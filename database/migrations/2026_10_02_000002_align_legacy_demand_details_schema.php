<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('demand_details')) {
            return;
        }

        // Older installations called each demand line a spec and used request_id.
        // Preserve those rows while aligning their key names with DemandDetail.
        if (Schema::hasColumn('demand_details', 'spec_id') && !Schema::hasColumn('demand_details', 'id')) {
            Schema::table('demand_details', fn (Blueprint $table) => $table->renameColumn('spec_id', 'id'));
        }

        Schema::table('demand_details', function (Blueprint $table) {
            if (!Schema::hasColumn('demand_details', 'demand_request_id')) $table->unsignedBigInteger('demand_request_id')->nullable()->index();
            if (!Schema::hasColumn('demand_details', 'producer_id')) $table->unsignedBigInteger('producer_id')->nullable()->index();
            if (!Schema::hasColumn('demand_details', 'proposed_quantity')) $table->integer('proposed_quantity')->nullable();
            if (!Schema::hasColumn('demand_details', 'proposed_price')) $table->decimal('proposed_price', 10, 2)->nullable();
            if (!Schema::hasColumn('demand_details', 'notes')) $table->text('notes')->nullable();
            if (!Schema::hasColumn('demand_details', 'status')) $table->string('status')->default('Legacy');
            if (!Schema::hasColumn('demand_details', 'created_at')) $table->timestamp('created_at')->nullable();
            if (!Schema::hasColumn('demand_details', 'updated_at')) $table->timestamp('updated_at')->nullable();
        });

        if (Schema::hasColumn('demand_details', 'request_id')) {
            DB::table('demand_details')->whereNull('demand_request_id')->update([
                'demand_request_id' => DB::raw('request_id'),
            ]);
        }

        if (Schema::hasColumn('demand_details', 'quantity_needed')) {
            DB::table('demand_details')->whereNull('proposed_quantity')->update([
                'proposed_quantity' => DB::raw('quantity_needed'),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the compatibility columns and preserved demand detail rows intact.
    }
};
