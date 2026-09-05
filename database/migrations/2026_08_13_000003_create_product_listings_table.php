<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_listings')) {
            Schema::create('product_listings', function (Blueprint $table) {
                $table->id('listing_id');
                $table->unsignedBigInteger('producer_id')->index();
                $table->string('product_name');
                $table->string('product_category')->nullable();
                $table->string('category')->nullable();
                $table->decimal('current_price_per_unit', 10, 2)->nullable();
                $table->decimal('price_per_unit', 10, 2)->nullable();
                $table->string('unit_of_measure')->nullable();
                $table->string('unit')->nullable();
                $table->decimal('quantity_available', 12, 2)->default(0);
                $table->decimal('quantity', 12, 2)->default(0);
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->date('harvest_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('status')->default('Active');
                $table->string('image_path')->nullable();
                $table->string('image_url')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listings');
    }
};
