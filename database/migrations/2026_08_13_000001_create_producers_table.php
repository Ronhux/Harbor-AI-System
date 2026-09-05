<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('producers')) {
            Schema::create('producers', function (Blueprint $table) {
            $table->id('producer_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('rsbsa_number')->nullable();
            $table->string('location')->nullable();
            $table->string('primary_product_type')->nullable();
            $table->string('verification_status')->default('pending');
            $table->string('producer_type')->nullable();
            $table->json('products')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producers');
    }
};
