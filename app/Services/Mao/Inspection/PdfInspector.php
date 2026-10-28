<?php

namespace App\Services\Mao\Inspection;

interface PdfInspector
{
    /** A trusted parser must inspect structure and encoded/compressed active content. */
    public function inspect(string $bytes): string;
}
