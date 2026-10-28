<?php
namespace App\Services\Mao\President;
use App\Support\PhilippineMobile;
final class PresidentIdentity {
    public static function name(string $raw): ?string {
        if(mb_strlen($raw)>255 || preg_match('/[\x00-\x1f]/u',$raw))return null;
        // Missing Unicode normalizer: refuse non-ASCII rather than guess equivalence.
        if(preg_match('/[^\x20-\x7e]/',$raw)) {
            if(!class_exists(\Normalizer::class))return null;
            $raw=\Normalizer::normalize($raw,\Normalizer::FORM_C);if($raw===false)return null;
        }
        $name=mb_strtolower(trim(preg_replace('/[\p{Z}\s]+/u',' ',$raw)));
        return $name!==''?$name:null;
    }
    public static function phone(mixed $raw): ?string {
        if(!is_string($raw)||strlen($raw)>40||preg_match('/[\x00-\x1f]/',$raw))return null;
        return PhilippineMobile::normalize($raw);
    }
    public static function digest(string $value,string $purpose='phone'): string {
        $key=config('fca_president.lookup_key');
        if(!is_string($key)||strlen($key)<32)throw new \RuntimeException('Protected lookup configuration unavailable.');
        return hash_hmac('sha256',$purpose.'|'.$value,$key);
    }
}
