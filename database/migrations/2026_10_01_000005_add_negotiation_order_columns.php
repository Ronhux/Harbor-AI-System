<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;
        try { DB::select('select * from orders limit 0'); } catch (\Throwable $error) { return; }
        // Existing installations use order_id as the key. Leave incompatible
        // legacy schemas untouched for an explicit repair instead of guessing.
        if (!Schema::hasColumn('orders', 'order_id')) return;
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'negotiation_id')) $table->integer('negotiation_id')->nullable()->unique();
            if (!Schema::hasColumn('orders', 'total_amount')) $table->decimal('total_amount', 12, 2)->default(0);
            if (!Schema::hasColumn('orders', 'shipping_address')) $table->text('shipping_address')->nullable();
            if (!Schema::hasColumn('orders', 'fulfillment_status')) $table->string('fulfillment_status')->default('Pending');
            if (!Schema::hasColumn('orders', 'payment_status')) $table->string('payment_status')->default('Pending');
            if (!Schema::hasColumn('orders', 'delivery_date')) $table->date('delivery_date')->nullable();
        });
    }
    public function down(): void
    {
        // Columns may be populated by orders after installation; retain them.
    }
};
