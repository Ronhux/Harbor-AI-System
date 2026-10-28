<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_listing_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['listing_id', 'is_primary']);
            $table->foreign('listing_id')->references('listing_id')->on('product_listings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listing_images');
    }
};
