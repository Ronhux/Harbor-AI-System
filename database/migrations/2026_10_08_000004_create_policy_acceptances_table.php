<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versioned policy acceptance evidence for the HarborAI Terms and
     * Conditions and Privacy Policy. One row per account, policy type, and
     * version; repeats are prevented by the unique constraint so original
     * acceptance timestamps are retained across future policy versions.
     */
    public function up(): void
    {
        Schema::create('policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('policy_type', 32);
            $table->string('policy_version', 32);
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->cascadeOnDelete();
            $table->unique(['user_id', 'policy_type', 'policy_version']);
            $table->index(['policy_type', 'policy_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_acceptances');
    }
};
