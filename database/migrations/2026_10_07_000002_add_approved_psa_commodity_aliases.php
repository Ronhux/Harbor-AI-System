<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $aliasesByCommodity = [
            'Cabbage' => ['Repolyo'],
            'Camote [Sweet potato]' => ['Camote', 'Kamote'],
            'Cassava for food, fresh tubers' => ['Cassava Kamoteng Kahoy', 'Kamoteng Kahoy'],
        ];

        foreach ($aliasesByCommodity as $canonicalName => $aliasesToAdd) {
            $commodity = DB::table('commodity_references')
                ->where('canonical_name', $canonicalName)
                ->first(['id', 'aliases']);

            if (!$commodity) {
                continue;
            }

            $aliases = json_decode($commodity->aliases ?: '[]', true);
            if (!is_array($aliases)) {
                $aliases = [];
            }

            $updatedAliases = array_values(array_unique([
                ...$aliases,
                ...$aliasesToAdd,
            ]));

            DB::table('commodity_references')
                ->where('id', $commodity->id)
                ->update(['aliases' => json_encode($updatedAliases)]);
        }
    }

    public function down(): void
    {
        // Preserve aliases that may have been edited since this migration ran.
    }
};
