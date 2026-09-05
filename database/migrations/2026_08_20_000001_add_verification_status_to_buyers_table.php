<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('buyers', 'verification_status')) {
            Schema::table('buyers', function (Blueprint $table) {
                $table->string('verification_status')->default('Pending')->after('buyer_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('buyers', 'verification_status')) {
            Schema::table('buyers', function (Blueprint $table) {
                $table->dropColumn('verification_status');
            });
        }
    }
};