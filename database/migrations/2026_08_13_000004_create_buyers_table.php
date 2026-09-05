<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('buyers')) {
            Schema::create('buyers', function (Blueprint $table) {
            $table->id('buyer_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('organization_name')->nullable();
            $table->string('buyer_type')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('shipping_address')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
