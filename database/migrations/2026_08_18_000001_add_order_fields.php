<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                // Add fulfillment_status if it doesn't exist
                if (!Schema::hasColumn('orders', 'fulfillment_status')) {
                    $table->string('fulfillment_status')->default('Processing')->after('status');
                }
                
                // Add payment_status if it doesn't exist
                if (!Schema::hasColumn('orders', 'payment_status')) {
                    $table->string('payment_status')->default('Pending')->after('fulfillment_status');
                }
                
                // Add shipping_address if it doesn't exist
                if (!Schema::hasColumn('orders', 'shipping_address')) {
                    $table->text('shipping_address')->nullable()->after('delivery_date');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'fulfillment_status')) {
                    $table->dropColumn('fulfillment_status');
                }
                if (Schema::hasColumn('orders', 'payment_status')) {
                    $table->dropColumn('payment_status');
                }
                if (Schema::hasColumn('orders', 'shipping_address')) {
                    $table->dropColumn('shipping_address');
                }
            });
        }
    }
};
