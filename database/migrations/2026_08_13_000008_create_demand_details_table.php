<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('demand_details')) {
            Schema::create('demand_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('demand_request_id')->index();
            $table->unsignedBigInteger('producer_id')->index();
            $table->integer('proposed_quantity')->default(0);
            $table->decimal('proposed_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('proposed');
            $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_details');
    }
};
