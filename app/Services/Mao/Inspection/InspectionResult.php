<?php

namespace App\Services\Mao\Inspection;

final readonly class InspectionResult
{
    public const STATES = ['pending', 'clean', 'rejected', 'error', 'quarantined'];

    public function __construct(public string $status, public array $checks, public string $sha256) {}

    public static function aggregate(array $checks, string $sha256): self
    {
        foreach ($checks as $check) {
            if (!in_array($check, self::STATES, true)) return new self('error', ['provider_contract' => 'error'], $sha256);
        }
        foreach (['rejected', 'error', 'quarantined', 'pending'] as $state) {
            if (in_array($state, $checks, true)) return new self($state, $checks, $sha256);
        }
        return new self($checks ? 'clean' : 'quarantined', $checks, $sha256);
    }

    public function usable(): bool
    {
        return $this->status === 'clean' && isset($this->checks['integrity'], $this->checks['content'], $this->checks['malware'])
            && (isset($this->checks['pdf_policy']) || isset($this->checks['image_decode']))
            && count(array_filter($this->checks, fn ($state) => $state !== 'clean')) === 0;
    }

    public function safeSummary(): array
    {
        return ['status' => $this->status, 'checks' => $this->checks, 'usable' => $this->usable()];
    }
}
