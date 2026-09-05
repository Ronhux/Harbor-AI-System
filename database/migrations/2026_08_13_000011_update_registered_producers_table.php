<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registered_producers', function (Blueprint $table) {
            if (!Schema::hasColumn('registered_producers', 'name')) {
                $table->string('name')->nullable()->after('rsbsa_number');
            }
            if (!Schema::hasColumn('registered_producers', 'province')) {
                $table->string('province')->nullable()->after('municipality');
            }
            if (!Schema::hasColumn('registered_producers', 'farm_type')) {
                $table->string('farm_type')->nullable()->after('barangay');
            }
            if (!Schema::hasColumn('registered_producers', 'farm_size')) {
                $table->string('farm_size')->nullable()->after('farm_type');
            }
            if (!Schema::hasColumn('registered_producers', 'contact_number')) {
                $table->string('contact_number')->nullable()->after('farm_size');
            }
            if (!Schema::hasColumn('registered_producers', 'email')) {
                $table->string('email')->nullable()->after('contact_number');
            }
            if (!Schema::hasColumn('registered_producers', 'registration_date')) {
                $table->timestamp('registration_date')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('registered_producers', function (Blueprint $table) {
            if (Schema::hasColumn('registered_producers', 'registration_date')) {
                $table->dropColumn('registration_date');
            }
            if (Schema::hasColumn('registered_producers', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('registered_producers', 'contact_number')) {
                $table->dropColumn('contact_number');
            }
            if (Schema::hasColumn('registered_producers', 'farm_size')) {
                $table->dropColumn('farm_size');
            }
            if (Schema::hasColumn('registered_producers', 'farm_type')) {
                $table->dropColumn('farm_type');
            }
            if (Schema::hasColumn('registered_producers', 'province')) {
                $table->dropColumn('province');
            }
            if (Schema::hasColumn('registered_producers', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
