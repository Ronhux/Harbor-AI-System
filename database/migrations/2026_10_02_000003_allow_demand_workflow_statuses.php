<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demand_requests')
            && Schema::hasColumn('demand_requests', 'status')
            && DB::connection()->getDriverName() === 'mysql') {
            // Legacy installations used ENUM('Open', 'Fulfilled', 'Cancelled'),
            // which rejects the current workflow states such as Active and Order Created.
            DB::statement('ALTER TABLE `demand_requests` MODIFY `status` VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        // Keep current workflow statuses intact during rollback.
    }
};
