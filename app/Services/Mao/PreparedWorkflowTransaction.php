<?php
namespace App\Services\Mao;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
final class PreparedWorkflowTransaction {
    private static array $owned=[];
    public static function run(Closure $operation): mixed {
        $connection=DB::connection(); $mysql=$connection->getDriverName()==='mysql'; $key=spl_object_id($connection);
        if($mysql && $connection->transactionLevel()>0 && empty(self::$owned[$key]))abort(503,'Prepared workflow requires an owned READ COMMITTED transaction');
        if($mysql && $connection->transactionLevel()===0)abort_unless($connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'),503,'Required transaction isolation unavailable');
        self::$owned[$key]=(self::$owned[$key]??0)+1;
        try { return $connection->transaction($operation,1); }
        catch(UniqueConstraintViolationException) { abort(409,'Duplicate workflow operation or conflicting unique reference. Reload current state.'); }
        finally { if(--self::$owned[$key]===0)unset(self::$owned[$key]); }
    }
}
