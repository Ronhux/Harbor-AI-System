<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `product_listings.quantity_available` was created as INT while the sibling
 * `quantity` column is DECIMAL(12,2) and the listing/offer API accepts decimals.
 * MySQL silently rounded fractional stock, so a listing of 40.5 kg was stored
 * and reported back as 41.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_listings') || !Schema::hasColumn('product_listings', 'quantity_available')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `product_listings` MODIFY `quantity_available` DECIMAL(14,3) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        // Listings may already rely on decimal quantities; keep them readable.
    }
};
