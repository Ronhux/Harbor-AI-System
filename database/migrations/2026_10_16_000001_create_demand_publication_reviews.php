<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // Match each existing parent exactly, including legacy signed INT keys.
        $column = function (Blueprint $table, string $name, string $parent, string $key) {
            $definition = collect(Schema::getColumns($parent))->firstWhere('name', $key);
            $type = strtolower($definition['type'] ?? '');
            $method = str_contains($type, 'bigint') ? 'bigInteger' : 'integer';
            $table->$method($name)->unsigned(str_contains($type, 'unsigned'));
            $table->foreign($name)->references($key)->on($parent)->restrictOnDelete();
        };
        Schema::create('demand_publication_reviews', function (Blueprint $table) use ($column) {
            $table->id();
            $column($table, 'demand_request_id', 'demand_requests', 'request_id');
            $column($table, 'actor_user_id', 'users', 'user_id');
            $table->string('decision', 30);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['demand_request_id', 'id']);
        });
    }
    public function down(): void { Schema::dropIfExists('demand_publication_reviews'); }
};
