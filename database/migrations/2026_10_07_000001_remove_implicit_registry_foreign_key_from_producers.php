<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('producers') || !Schema::hasTable('registered_producers')) {
            return;
        }

        foreach (Schema::getForeignKeys('producers') as $foreignKey) {
            if (
                $foreignKey['columns'] === ['rsbsa_number']
                && $foreignKey['foreign_table'] === 'registered_producers'
                && $foreignKey['foreign_columns'] === ['rsbsa_number']
            ) {
                Schema::table('producers', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey['name']);
                });
            }
        }
    }

    public function down(): void
    {
        // Do not restore the implicit constraint: unmatched applications must remain recordable.
    }
};
