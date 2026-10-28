<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        $column=function(Blueprint $table,string $name,string $parent,string $key){
            $definition=collect(Schema::getColumns($parent))->firstWhere('name',$key);
            $type=strtolower($definition['type']??'');
            $method=str_contains($type,'bigint')?'bigInteger':'integer';
            $table->$method($name)->unsigned(str_contains($type,'unsigned'));
            $table->foreign($name)->references($key)->on($parent)->restrictOnDelete();
        };
        Schema::create('buyer_demand_submissions',function(Blueprint $table)use($column){
            $table->id();$column($table,'buyer_id','buyers','buyer_id');
            $table->uuid('submission_key');$table->char('payload_hash',64);
            $column($table,'demand_request_id','demand_requests','request_id');
            $table->timestamp('created_at');$table->unique(['buyer_id','submission_key']);
        });
    }
    public function down():void {Schema::dropIfExists('buyer_demand_submissions');}
};
