<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
            $table->id('admin_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('role')->nullable();
            $table->string('department')->nullable();
            $table->integer('permission_level')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
