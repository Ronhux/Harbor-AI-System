<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep the role column capable of storing Super Admin if rolling back.
    }
};
