<?php
namespace App\Services\Mao\President;
use Illuminate\Support\Facades\DB;
/** Confidential HMAC-keyed mutex, held only inside an owned transaction after user/FCA locks. */
final class PhoneMutex {
 public static function lock(string $digest):void {
  if(!preg_match('/\A[a-f0-9]{64}\z/',$digest)||DB::transactionLevel()<1)throw new \LogicException('Protected recipient lock unavailable.');
  DB::table('fca_phone_locks')->insertOrIgnore(['key'=>$digest]);
  if(!DB::table('fca_phone_locks')->where('key',$digest)->lockForUpdate()->first())throw new \RuntimeException('Protected recipient lock unavailable.');
 }
}
