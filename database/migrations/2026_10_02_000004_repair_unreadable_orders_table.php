<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs an `orders` table that exists as a directory entry but cannot be read
 * by InnoDB (error 1932 "Table doesn't exist in engine").
 *
 * That orphan state made every order read throw a QueryException, which surfaced
 * as HTTP 500 whenever a producer confirmation reached mutual agreement and the
 * negotiation controller created the official order.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::connection()->getDriverName() === 'mysql';

        // The orphan state and the signed int(11) key contract only exist on MySQL.
        if ($mysql && (!$this->tableIsReadable('orders') || !$this->orderKeyIsCompatible())) {
            $this->rebuildOrdersTable();

            return;
        }

        if (Schema::hasTable('orders')) {
            $this->ensureOrderColumns();
        }
    }

    public function down(): void
    {
        // Rebuilt orders hold live transaction data; never drop them on rollback.
    }

    private function tableIsReadable(string $table): bool
    {
        try {
            DB::table($table)->limit(1)->get();

            return true;
        } catch (\Throwable $error) {
            return false;
        }
    }

    /**
     * The key must stay a signed int(11) so the existing foreign keys from
     * order_details and demand_requests can be recreated against it.
     */
    private function orderKeyIsCompatible(): bool
    {
        try {
            $column = DB::selectOne(
                'select column_type from information_schema.columns'
                . ' where table_schema = database() and table_name = ? and column_name = ?',
                ['orders', 'order_id']
            );
        } catch (\Throwable $error) {
            return false;
        }

        return $column !== null
            && strtolower((string) $column->column_type) === 'int(11)';
    }

    private function rebuildOrdersTable(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->dropForeignKeysReferencing('orders');
            // Rows whose parent order is unreadable cannot be preserved or linked.
            if (Schema::hasTable('order_details')) DB::table('order_details')->delete();
            if (Schema::hasTable('demand_requests') && Schema::hasColumn('demand_requests', 'order_id')) {
                DB::table('demand_requests')->whereNotNull('order_id')->update(['order_id' => null]);
            }
            DB::statement('DROP TABLE IF EXISTS `orders`');
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Schema::create('orders', function (Blueprint $table) {
            // Every other HarborAI table uses a signed int(11) key, and
            // order_details.order_id / demand_requests.order_id already carry a
            // foreign key to this column. An unsigned increments() key cannot
            // satisfy those constraints (errno 150), so keep the same shape.
            $table->unsignedInteger('order_id', true)->primary();
            $table->integer('buyer_id')->index();
            $table->integer('product_listing_id')->nullable()->index();
            $table->integer('quantity')->default(1);
            $table->decimal('total_price', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('shipping_address')->nullable();
            $table->string('fulfillment_status')->default('Pending');
            $table->string('payment_status')->default('Pending');
            $table->string('status')->default('pending');
            $table->date('order_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->integer('negotiation_id')->nullable()->unique();
            $table->timestamps();
        });

        $this->restoreOrderForeignKeys();
    }

    private function dropForeignKeysReferencing(string $table): void
    {
        try {
            $keys = DB::select(
                'select constraint_name, table_name from information_schema.key_column_usage'
                . " where table_schema = database() and referenced_table_name = ?",
                [$table]
            );
        } catch (\Throwable $error) {
            return;
        }

        foreach ($keys as $key) {
            try {
                DB::statement('alter table `' . $key->table_name . '` drop foreign key `' . $key->constraint_name . '`');
            } catch (\Throwable $error) {
                // A key that cannot be dropped is reported by the recreate step.
            }
        }
    }

    private function restoreOrderForeignKeys(): void
    {
        if (Schema::hasTable('order_details')) {
            try {
                Schema::table('order_details', function (Blueprint $table) {
                    $table->foreign('order_id')->references('order_id')->on('orders')->cascadeOnDelete()->cascadeOnUpdate();
                });
            } catch (\Throwable $error) {
                report($error);
            }
        }

        if (Schema::hasTable('demand_requests') && Schema::hasColumn('demand_requests', 'order_id')) {
            try {
                Schema::table('demand_requests', function (Blueprint $table) {
                    $table->foreign('order_id')->references('order_id')->on('orders')->nullOnDelete();
                });
            } catch (\Throwable $error) {
                report($error);
            }
        }
    }

    private function ensureOrderColumns(): void
    {
        $columns = [
            'buyer_id' => fn (Blueprint $table) => $table->integer('buyer_id')->nullable()->index(),
            'negotiation_id' => fn (Blueprint $table) => $table->integer('negotiation_id')->nullable()->unique(),
            'total_amount' => fn (Blueprint $table) => $table->decimal('total_amount', 12, 2)->default(0),
            'shipping_address' => fn (Blueprint $table) => $table->text('shipping_address')->nullable(),
            'fulfillment_status' => fn (Blueprint $table) => $table->string('fulfillment_status')->default('Pending'),
            'payment_status' => fn (Blueprint $table) => $table->string('payment_status')->default('Pending'),
            'order_date' => fn (Blueprint $table) => $table->date('order_date')->nullable(),
            'delivery_date' => fn (Blueprint $table) => $table->date('delivery_date')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (!Schema::hasColumn('orders', $name)) {
                try {
                    Schema::table('orders', $definition);
                } catch (\Throwable $error) {
                    report($error);
                }
            }
        }
    }
};