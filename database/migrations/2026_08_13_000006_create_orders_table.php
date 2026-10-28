<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->increments('order_id');
                $table->integer('buyer_id')->index();
                $table->integer('product_listing_id')->nullable()->index();
                $table->integer('quantity')->default(1);
                $table->decimal('total_price', 12, 2)->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->text('shipping_address')->nullable();
                $table->string('fulfillment_status')->default('Pending');
                $table->string('payment_status')->default('Pending');
                $table->string('status')->default('pending');
                $table->date('order_date')->nullable();
                $table->date('delivery_date')->nullable();
                $table->integer('negotiation_id')->nullable()->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
