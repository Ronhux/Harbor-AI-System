<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_price_references', function (Blueprint $table) {
            $table->string('source_commodity_name')->nullable();
            $table->string('source_geographic_scope')->nullable();
            $table->string('observation_period', 50)->nullable();
            $table->enum('period_frequency', ['daily', 'monthly', 'quarterly', 'annual', 'other'])->nullable();
            $table->enum('observation_status', ['not_stated', 'final', 'preliminary', 'revised'])->default('not_stated');
            $table->timestamp('source_retrieved_at')->nullable();
            $table->char('deduplication_key', 64)->nullable();
            $table->unique('deduplication_key', 'mpr_deduplication_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('market_price_references', function (Blueprint $table) {
            $table->dropUnique('mpr_deduplication_key_unique');
            $table->dropColumn([
                'source_commodity_name',
                'source_geographic_scope',
                'observation_period',
                'period_frequency',
                'observation_status',
                'source_retrieved_at',
                'deduplication_key',
            ]);
        });
    }
};
