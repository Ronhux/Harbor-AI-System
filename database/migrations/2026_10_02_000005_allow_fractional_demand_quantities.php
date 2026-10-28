<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy `demand_requests.quantity_needed` was created as INT even though the
 * Post Demand form, the API contract and every downstream workflow (offer
 * quantities, negotiation quantities) allow decimals such as 12.5 kg.
 * MySQL silently rounded those values, so the API answered with the submitted
 * decimal while the stored record kept a different integer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('demand_requests') || !Schema::hasColumn('demand_requests', 'quantity_needed')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `demand_requests` MODIFY `quantity_needed` DECIMAL(14,3) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        // Demands may already rely on decimal quantities; keep them readable.
    }
};