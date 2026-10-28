<?php

namespace App\Services\Mao;

/** A trusted scanner integration is a separate activation dependency. Never assume clean. */
class FcaEvidenceScanner
{
    public function inspect(string $bytes, string $mime): string
    {
        $result = app(\App\Services\Mao\Inspection\DocumentInspector::class)->inspect($bytes, $mime);
        return $result->usable() ? 'clean' : ($result->status === 'rejected' ? 'rejected' : 'quarantined');
    }
}
