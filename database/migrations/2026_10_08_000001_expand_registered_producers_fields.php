<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registered_producers', function (Blueprint $table) {
            if (!Schema::hasColumn('registered_producers', 'first_name')) {
                $table->string('first_name')->nullable()->after('rsbsa_number');
            }
            if (!Schema::hasColumn('registered_producers', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('registered_producers', 'last_name')) {
                $table->string('last_name')->nullable()->after('middle_name');
            }
            if (!Schema::hasColumn('registered_producers', 'extension_name')) {
                $table->string('extension_name')->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('registered_producers', 'sex')) {
                $table->string('sex')->nullable()->after('extension_name');
            }
            if (!Schema::hasColumn('registered_producers', 'house_number')) {
                $table->string('house_number')->nullable()->after('sex');
            }
            if (!Schema::hasColumn('registered_producers', 'zone')) {
                $table->string('zone')->nullable()->after('house_number');
            }
            if (!Schema::hasColumn('registered_producers', 'street')) {
                $table->string('street')->nullable()->after('zone');
            }
            if (!Schema::hasColumn('registered_producers', 'province')) {
                $table->string('province')->nullable()->after('municipality');
            }
            if (!Schema::hasColumn('registered_producers', 'region')) {
                $table->string('region')->nullable()->after('province');
            }
            if (!Schema::hasColumn('registered_producers', 'mobile_number')) {
                $table->string('mobile_number')->nullable()->after('region');
            }
            if (!Schema::hasColumn('registered_producers', 'farm_location')) {
                $table->string('farm_location')->nullable()->after('mobile_number');
            }
            if (!Schema::hasColumn('registered_producers', 'area_ha')) {
                $table->decimal('area_ha', 8, 2)->nullable()->after('farm_location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registered_producers', function (Blueprint $table) {
            $columns = ['first_name', 'middle_name', 'last_name', 'extension_name', 'sex', 'house_number', 'zone', 'street', 'province', 'region', 'mobile_number', 'farm_location', 'area_ha'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('registered_producers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
