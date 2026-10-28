<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_name');
            $table->enum('source_type', ['harborai_actual', 'official_reference', 'synthetic_simulated']);
            $table->string('source_url')->nullable();
            $table->string('organization')->nullable();
            $table->string('geographic_scope')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_official')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['source_type', 'source_name'], 'ai_sources_type_name_unique');
        });

        Schema::create('commodity_references', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name');
            $table->string('normalized_name')->unique();
            $table->enum('category', ['Grains', 'Fish', 'Vegetables', 'Fruits', 'Livestock']);
            $table->json('aliases')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('market_price_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_reference_id')->constrained('commodity_references')->restrictOnDelete();
            $table->foreignId('ai_data_source_id')->constrained('ai_data_sources')->restrictOnDelete();
            $table->decimal('price', 14, 4);
            $table->char('currency', 3)->default('PHP');
            $table->string('unit', 50);
            $table->enum('price_type', ['farmgate', 'wholesale', 'retail', 'harborai_listing', 'negotiated_transaction']);
            $table->string('geographic_scope');
            $table->string('source_url')->nullable();
            $table->string('source_reference')->nullable();
            $table->date('observation_date');
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['commodity_reference_id', 'geographic_scope', 'observation_date'], 'mpr_lookup_idx');
        });

        Schema::create('seasonality_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_reference_id')->constrained('commodity_references')->restrictOnDelete();
            $table->foreignId('ai_data_source_id')->constrained('ai_data_sources')->restrictOnDelete();
            $table->unsignedTinyInteger('month')->nullable();
            $table->string('period_label')->nullable();
            $table->enum('season_status', ['planting', 'growing', 'harvest', 'peak', 'off_season', 'year_round', 'other']);
            $table->unsignedTinyInteger('start_month')->nullable();
            $table->unsignedTinyInteger('end_month')->nullable();
            $table->string('peak_period')->nullable();
            $table->unsignedSmallInteger('production_duration_days')->nullable();
            $table->string('geographic_scope');
            $table->string('source_url')->nullable();
            $table->string('source_reference')->nullable();
            $table->date('reference_date')->nullable();
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['commodity_reference_id', 'geographic_scope', 'month'], 'sr_lookup_idx');
        });

        Schema::create('unit_conversion_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_reference_id')->nullable()->constrained('commodity_references')->restrictOnDelete();
            $table->string('from_unit', 50);
            $table->string('to_unit', 50);
            $table->decimal('conversion_factor', 20, 9);
            $table->foreignId('ai_data_source_id')->nullable()->constrained('ai_data_sources')->restrictOnDelete();
            $table->string('source_url')->nullable();
            $table->string('source_reference')->nullable();
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'rejected'])->default('unverified');
            $table->date('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['commodity_reference_id', 'from_unit', 'to_unit'], 'ucr_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_conversion_references');
        Schema::dropIfExists('seasonality_references');
        Schema::dropIfExists('market_price_references');
        Schema::dropIfExists('commodity_references');
        Schema::dropIfExists('ai_data_sources');
    }
};
