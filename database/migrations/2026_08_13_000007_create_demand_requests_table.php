<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('demand_requests')) {
            Schema::create('demand_requests', function (Blueprint $table) {
            $table->id('request_id');
            $table->unsignedBigInteger('buyer_id')->nullable()->index();
            $table->string('product_name');
            $table->string('category')->nullable();
            $table->integer('quantity_needed')->default(0);
            $table->string('unit')->nullable();
            $table->decimal('max_price_per_unit', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_requests');
    }
};
