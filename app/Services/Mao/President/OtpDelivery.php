<?php
namespace App\Services\Mao\President;
interface OtpDelivery { public function send(string $trustedDirectoryPhone,string $code): ?string; }
