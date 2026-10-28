<?php

namespace App\Services\Mao\Inspection;

use DomainException;

/** Pure transition checks, not persistence or authority to release a document. */
final class InspectionLifecycle
{
    public function complete(string $current, InspectionResult $result): string
    {
        if ($current !== 'pending' || !in_array($result->status, InspectionResult::STATES, true)
            || $result->status === 'pending' || ($result->status === 'clean' && !$result->usable())) {
            throw new DomainException('Invalid or incomplete inspection transition.');
        }
        return $result->status;
    }

    public function retry(string $current, bool $explicitlyAuthorized): string
    {
        if (!$explicitlyAuthorized || !in_array($current, ['error', 'quarantined'], true)) {
            throw new DomainException('Inspection retry requires authorization and an eligible state.');
        }
        return 'pending';
    }
}
