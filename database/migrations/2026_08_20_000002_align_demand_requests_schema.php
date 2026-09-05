<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('demand_requests')) {
            return;
        }

        $columns = [
            'buyer_id' => fn (Blueprint $table) => $table->unsignedBigInteger('buyer_id')->nullable()->index(),
            'category' => fn (Blueprint $table) => $table->string('category')->nullable(),
            'quantity_needed' => fn (Blueprint $table) => $table->integer('quantity_needed')->default(0),
            'unit' => fn (Blueprint $table) => $table->string('unit')->nullable(),
            'max_price_per_unit' => fn (Blueprint $table) => $table->decimal('max_price_per_unit', 10, 2)->nullable(),
            'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
            'location' => fn (Blueprint $table) => $table->string('location')->nullable(),
            'deadline' => fn (Blueprint $table) => $table->date('deadline')->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn('demand_requests', $name)) {
                Schema::table('demand_requests', $definition);
            }
        }
    }

    public function down(): void
    {
        // Keep the compatibility columns during rollback of later migrations.
    }
};