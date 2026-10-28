<?php
namespace App\Services\Mao\President;
use Illuminate\Support\Facades\Http;
/** Genuine Semaphore transport. Returns only opaque message ID; never retains raw response/errors. */
final class SemaphoreOtpDelivery implements OtpDelivery {
 public function send(string $trustedDirectoryPhone,string $code): ?string {
   if(!app()->environment('testing')||config('fca_president.verification_method')!=='otp_future'||config('fca_president.workflow_enabled')!==true||config('fca_president.sms_enabled')!==true||config('fca_president.provider')!=='semaphore')return null;
   $key=config('fca_president.semaphore_key');$sender=config('fca_president.sender');
   if(!is_string($key)||$key===''||!is_string($sender)||$sender===''||!preg_match('/\A\d{6}\z/',$code)||PresidentIdentity::phone($trustedDirectoryPhone)!==$trustedDirectoryPhone)return null;
   $timeout=config('fca_president.timeout_seconds');if(!is_int($timeout)||$timeout<1||$timeout>30)return null;
   try {
     $r=Http::asForm()->connectTimeout(3)->timeout($timeout)->withoutRedirecting()->post('https://api.semaphore.co/api/v4/otp',[
       'apikey'=>$key,'sendername'=>$sender,'number'=>$trustedDirectoryPhone,'code'=>$code,
       'message'=>'HarborAI phone verification code: {otp}. Never share it. This does not approve FCA authority.',
     ]);
     if(!$r->successful())return null;$rows=$r->json();
     if(!is_array($rows)||count($rows)!==1||!isset($rows[0]['status'],$rows[0]['message_id'],$rows[0]['recipient'],$rows[0]['code']))return null;
     $row=$rows[0];if(!in_array($row['status'],['Queued','Pending','Sent'],true)||PresidentIdentity::phone((string)$row['recipient'])!==$trustedDirectoryPhone)return null;
     if((string)$row['code']!==$code||(!is_int($row['message_id'])&&!ctype_digit((string)$row['message_id'])))return null;
     return (string)$row['message_id']; // Queued is acceptance, NOT proof of delivery or verification.
   } catch(\Throwable){return null;} // No raw vendor exception, body, phone, code or key logged.
 }
}
