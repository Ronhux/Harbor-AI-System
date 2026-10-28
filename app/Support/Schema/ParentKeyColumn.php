<?php
namespace App\Support\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LogicException;
final class ParentKeyColumn {
    public static function add(Blueprint $table, string $name, string $parent, string $key) {
        $definition = collect(Schema::getColumns($parent))->firstWhere('name', $key);
        $type = strtolower($definition['type'] ?? '');
        if (!preg_match('/^(bigint|int|integer)(\b|\()/',$type)) throw new LogicException('Unsupported parent key type for '.$parent.'.'.$key);
        $method = str_starts_with($type, 'bigint') ? 'bigInteger' : 'integer';
        return $table->$method($name)->unsigned(str_contains($type, 'unsigned'));
    }
}
