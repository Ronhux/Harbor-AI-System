<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('product_listings', 'category')) {
                $table->string('category')->nullable()->after('product_category');
            }

            if (!Schema::hasColumn('product_listings', 'price_per_unit')) {
                $table->decimal('price_per_unit', 10, 2)->nullable()->after('current_price_per_unit');
            }

            if (!Schema::hasColumn('product_listings', 'unit')) {
                $table->string('unit')->nullable()->after('unit_of_measure');
            }

            if (!Schema::hasColumn('product_listings', 'quantity')) {
                $table->decimal('quantity', 12, 2)->default(0)->after('quantity_available');
            }

            if (!Schema::hasColumn('product_listings', 'description')) {
                $table->text('description')->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('product_listings', 'location')) {
                $table->string('location')->nullable()->after('description');
            }

            if (!Schema::hasColumn('product_listings', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('harvest_date');
            }

            if (!Schema::hasColumn('product_listings', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_listings', function (Blueprint $table) {
            $columns = ['category', 'price_per_unit', 'unit', 'quantity', 'description', 'location', 'expiry_date'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('product_listings', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('product_listings', 'created_at') && Schema::hasColumn('product_listings', 'updated_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
