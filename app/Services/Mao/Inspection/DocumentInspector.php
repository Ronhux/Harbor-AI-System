<?php

namespace App\Services\Mao\Inspection;

use Throwable;

final class DocumentInspector
{
    public function __construct(private ?MalwareProvider $malware = null, private ?PdfInspector $pdf = null) {}

    public function inspect(string $bytes, string $mime, ?string $expectedHash = null): InspectionResult
    {
        $hash = hash('sha256', $bytes);
        $checks = ['integrity' => $expectedHash === null || hash_equals($expectedHash, $hash) ? 'clean' : 'rejected'];
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $checks['content'] = strlen($bytes) > 0 && strlen($bytes) <= 5 * 1024 * 1024
            && $detected === $mime && in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true) ? 'clean' : 'rejected';
        if (in_array('rejected', $checks, true)) return InspectionResult::aggregate($checks, $hash);
        if ($mime === 'application/pdf') {
            $basic = preg_match('/\A%PDF-1\.[0-7]/', $bytes) && str_ends_with(rtrim($bytes), '%%EOF')
                && !preg_match('/\/(?:JavaScript|JS|OpenAction|AA|Launch|EmbeddedFile|RichMedia|XFA|Encrypt)\b/i', $bytes);
            $checks['pdf_policy'] = $basic ? $this->call(fn () => $this->pdf?->inspect($bytes)) : 'rejected';
        } else {
            $dimensions = @getimagesizefromstring($bytes);
            if (!$dimensions || $dimensions[0] > 5000 || $dimensions[1] > 5000 || $dimensions[0] * $dimensions[1] > 8000000) {
                $checks['image_decode'] = 'rejected';
            } elseif (!function_exists('imagecreatefromstring')) {
                $checks['image_decode'] = 'quarantined';
            } else {
                $image = @imagecreatefromstring($bytes);
                $checks['image_decode'] = $image ? 'clean' : 'rejected';
                if ($image) imagedestroy($image);
            }
        }
        $checks['malware'] = $this->call(fn () => $this->malware?->inspect($bytes, $mime));
        return InspectionResult::aggregate($checks, $hash);
    }

    private function call(callable $check): string
    {
        try {
            $state = $check();
            return $state === null ? 'quarantined' : (in_array($state, InspectionResult::STATES, true) ? $state : 'error');
        } catch (Throwable) {
            return 'error';
        }
    }
}
