<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('registered_producers')) {
            Schema::create('registered_producers', function (Blueprint $table) {
            $table->id('registry_id');
            $table->string('rsbsa_number')->nullable();
            $table->string('full_name')->nullable();
            $table->string('municipality')->nullable();
            $table->string('barangay')->nullable();
            $table->string('primary_livelihood')->nullable();
            $table->string('producer_type')->nullable();
            $table->string('status')->default('active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('registered_producers');
    }
};
