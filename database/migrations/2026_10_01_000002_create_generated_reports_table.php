<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('generated_by')->index();
            $table->string('report_type');
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->json('filters')->nullable();
            $table->longText('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
