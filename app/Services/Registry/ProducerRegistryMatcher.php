<?php

namespace App\Services\Registry;

use App\Models\RegisteredProducer;

class ProducerRegistryMatcher
{
    /**
     * @return array{
     *     status: string,
     *     rsbsa_match: bool,
     *     valid_match: bool,
     *     name_match: bool|null,
     *     registry_id: int|null,
     *     registry_rsbsa_number: string|null,
     *     registry_name: string|null,
     *     registry_location: string|null,
     *     municipality: string|null,
     *     primary_livelihood: string|null,
     *     producer_type: string|null
     * }
     */
    public function match(?string $rsbsaNumber, string $applicantName, bool $lock = false): array
    {
        $number = trim((string) $rsbsaNumber);
        if ($number === '') {
            return $this->result('NO_RSBSA_NUMBER');
        }

        $registry = $this->findRegistryForAccountReview($number, $lock);

        if (!$registry) {
            return $this->result('NO_REGISTRY_MATCH');
        }

        // Exact registration uses structured registry names. Imported rows need not
        // also populate either legacy display-name column.
        $registryName = trim((string) ($registry->full_name ?: $registry->name));
        if (trim((string) $registry->first_name) !== '' && trim((string) $registry->last_name) !== '') {
            $registryName = trim($registry->first_name . ' ' . $registry->last_name);
        }
        if ($registryName === '') {
            return $this->result('REGISTRY_MATCH_NAME_UNAVAILABLE', $registry, null);
        }

        $registryParts = $this->nameParts($registryName);
        $applicantParts = $this->nameParts($applicantName);
        $nameMatches = count($registryParts) >= 2
            && count($applicantParts) >= 2
            && $registryParts[0] === $applicantParts[0]
            && $registryParts[array_key_last($registryParts)] === $applicantParts[array_key_last($applicantParts)];

        return $this->result(
            $nameMatches ? 'REGISTRY_MATCH_NAME_MATCH' : 'REGISTRY_MATCH_NAME_MISMATCH',
            $registry,
            $nameMatches
        );
    }

    public function verifyExactMatch(array $data): array
    {
        $rsbsaNumber = trim((string) ($data['rsbsa_number'] ?? ''));
        if ($rsbsaNumber === '') {
            return $this->result('NO_RSBSA_NUMBER');
        }

        $registry = $this->findRegistryForVerification($rsbsaNumber);
        if (!$registry) {
            return $this->result('NO_REGISTRY_MATCH');
        }

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $middleName = trim((string) ($data['middle_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $extensionName = trim((string) ($data['extension_name'] ?? $data['suffix'] ?? ''));
        $sex = trim((string) ($data['sex'] ?? ''));
        $barangay = trim((string) ($data['barangay'] ?? ''));

        $registryFirstName = trim((string) ($registry->first_name ?? ''));
        $registryMiddleName = trim((string) ($registry->middle_name ?? ''));
        $registryLastName = trim((string) ($registry->last_name ?? ''));
        $registryExtensionName = trim((string) ($registry->extension_name ?? ''));
        $registrySex = trim((string) ($registry->sex ?? ''));
        $registryBarangay = trim((string) ($registry->barangay ?? ''));

        $firstMatches = $this->normalizeText($registryFirstName) === $this->normalizeText($firstName);
        $lastMatches = $this->normalizeText($registryLastName) === $this->normalizeText($lastName);
        $sexMatches = $this->normalizeText($registrySex) === $this->normalizeText($sex);
        $barangayMatches = $this->normalizeText($registryBarangay) === $this->normalizeText($barangay);
        $extensionMatches = $this->optionalRegistryFieldMatches($registryExtensionName, $extensionName, true);
        $middleMatches = $this->optionalRegistryFieldMatches($registryMiddleName, $middleName);

        $matches = $firstMatches && $lastMatches && $sexMatches && $barangayMatches && $extensionMatches && $middleMatches;

        if (!$matches) {
            return $this->result('REGISTRY_INFORMATION_DOES_NOT_MATCH', $registry, false);
        }

        return $this->result('REGISTRY_MATCH_NAME_MATCH', $registry, true);
    }

    private function findRegistryForAccountReview(string $rsbsaNumber, bool $lock = false): ?RegisteredProducer
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', trim($rsbsaNumber)));
        if ($normalized === '') {
            return null;
        }

        return RegisteredProducer::query()
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->whereRaw("UPPER(REPLACE(REPLACE(COALESCE(rsbsa_number, ''), ' ', ''), '-', '')) = ?", [$normalized])
            ->first();
    }

    private function findRegistryForVerification(string $rsbsaNumber): ?RegisteredProducer
    {
        $normalized = $this->normalizeRsbsa($rsbsaNumber);

        return $normalized === ''
            ? null
            : RegisteredProducer::query()->where('rsbsa_number', $normalized)->first();
    }

    private function normalizeRsbsa(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{15}$/', $value) === 1) {
            $digits = $value;
        } elseif (preg_match('/^\d{2}-\d{2}-\d{2}-\d{3}-\d{6}$/', $value) === 1) {
            $digits = str_replace('-', '', $value);
        } else {
            return '';
        }

        return substr($digits, 0, 2) . '-' . substr($digits, 2, 2) . '-' . substr($digits, 4, 2) . '-'
            . substr($digits, 6, 3) . '-' . substr($digits, 9, 6);
    }

    private function normalizeText(?string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) $value)));
    }

    private function optionalRegistryFieldMatches(?string $registryValue, ?string $applicantValue, bool $allowPeriodVariation = false): bool
    {
        $registryValue = $this->normalizeText($registryValue);
        $applicantValue = $this->normalizeText($applicantValue);

        if ($allowPeriodVariation) {
            $registryValue = rtrim($registryValue, '.');
            $applicantValue = rtrim($applicantValue, '.');
        }

        return $registryValue === $applicantValue;
    }

    private function nameParts(string $name): array
    {
        $parts = preg_split('/[\s,]+/u', mb_strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $part): string => (string) preg_replace('/[^\pL\pN]+/u', '', $part),
            $parts
        )));
    }

    private function result(string $status, ?RegisteredProducer $registry = null, ?bool $nameMatches = null): array
    {
        return [
            'status' => $status,
            'rsbsa_match' => $registry !== null,
            'valid_match' => $status === 'REGISTRY_MATCH_NAME_MATCH',
            'name_match' => $nameMatches,
            'registry_id' => $registry ? (int) $registry->registry_id : null,
            'registry_rsbsa_number' => $registry?->rsbsa_number,
            'registry_name' => $registry ? trim((string) ($registry->full_name ?: $registry->name ?: trim($registry->first_name . ' ' . $registry->last_name))) ?: null : null,
            'registry_location' => $registry ? trim(implode(', ', array_filter([
                $registry->barangay,
                $registry->municipality,
                $registry->province,
            ]))) ?: null : null,
            'municipality' => $registry?->municipality,
            'primary_livelihood' => $registry?->primary_livelihood,
            'producer_type' => $registry?->producer_type ?: $registry?->farm_type,
        ];
    }
}
