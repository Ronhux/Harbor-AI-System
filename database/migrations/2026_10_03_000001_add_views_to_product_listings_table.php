<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the product view counter used by the producer My Listings page.
 *
 * The column is purely additive and starts at zero, so listings created
 * before this migration simply report no views. No backfill and no destructive
 * change is performed, because there is no historical view data to recover.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_listings')) {
            return;
        }

        if (Schema::hasColumn('product_listings', 'views')) {
            return;
        }

        Schema::table('product_listings', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0)->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('product_listings')) {
            return;
        }

        if (!Schema::hasColumn('product_listings', 'views')) {
            return;
        }

        Schema::table('product_listings', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};