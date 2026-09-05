<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_details', function (Blueprint $table) {

            $table->increments('order_detail_id');

            // Must match orders.order_id INT(11)
            $table->integer('order_id');

            // Must match product_listings.listing_id INT(11)
            $table->integer('listing_id');

            $table->decimal('quantity', 12, 2)
                ->default(0.00);

            $table->decimal('unit_price', 10, 2)
                ->default(0.00);

            $table->decimal('subtotal', 12, 2)
                ->default(0.00);

            $table->index('order_id');
            $table->index('listing_id');

            $table->foreign('order_id')
                ->references('order_id')
                ->on('orders')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('listing_id')
                ->references('listing_id')
                ->on('product_listings')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};