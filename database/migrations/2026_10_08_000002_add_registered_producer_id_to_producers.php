<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            if (!Schema::hasColumn('producers', 'registered_producer_id')) {
                $table->unsignedBigInteger('registered_producer_id')
                    ->nullable()
                    ->after('user_id')
                    ->unique('producers_registered_producer_id_unique');
            } else {
                $table->unsignedBigInteger('registered_producer_id')->nullable()->change();
            }
        });

        Schema::table('producers', function (Blueprint $table) {
            if (!Schema::hasColumn('producers', 'registered_producer_id')) {
                return;
            }

            $foreignKeys = Schema::getForeignKeys('producers');
            $exists = collect($foreignKeys)->contains(fn (array $foreignKey) => ($foreignKey['columns'] ?? []) === ['registered_producer_id']);

            if (!$exists) {
                $table->foreign('registered_producer_id', 'producers_registered_producer_id_foreign')
                    ->references('registry_id')
                    ->on('registered_producers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            if (Schema::hasColumn('producers', 'registered_producer_id')) {
                $foreignKeys = Schema::getForeignKeys('producers');
                $hasForeignKey = collect($foreignKeys)->contains(
                    fn (array $foreignKey) => ($foreignKey['columns'] ?? []) === ['registered_producer_id']
                );

                if ($hasForeignKey) {
                    $table->dropForeign('producers_registered_producer_id_foreign');
                }

                $table->dropUnique('producers_registered_producer_id_unique');
                $table->dropColumn('registered_producer_id');
            }
        });
    }
};
