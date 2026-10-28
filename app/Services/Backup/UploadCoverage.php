<?php

namespace App\Services\Backup;

use App\Models\FcaEvidence;
use App\Models\ProductListing;
use App\Models\ProductListingImage;
use RuntimeException;

class UploadCoverage
{
    /** Compare database references with staged archive metadata, never disclose file identifiers. */
    public function verify(array $entries): array
    {
        $counts = ['product_images' => 0, 'legacy_images' => 0, 'fca_evidence' => 0, 'external_images_not_archived' => 0];
        foreach (ProductListingImage::query()->select('path')->cursor() as $image) {
            $this->requireEntry($entries, 'uploads', $image->path);
            $counts['product_images']++;
        }
        foreach (ProductListing::query()->select('image_path', 'image_url')->cursor() as $listing) {
            if ($listing->image_path) {
                $this->requireEntry($entries, 'uploads', preg_replace('~^public/~', '', $listing->image_path));
                $counts['legacy_images']++;
            } elseif ($listing->image_url) {
                $counts['external_images_not_archived']++;
            }
        }
        foreach (FcaEvidence::query()->select('storage_key', 'sha256', 'bytes')->cursor() as $evidence) {
            $metadata = $this->requireEntry($entries, 'fca-evidence', $evidence->storage_key);
            if (($metadata['bytes'] ?? null) !== $evidence->bytes || ! hash_equals((string) $evidence->sha256, $metadata['sha256'] ?? '')) {
                throw new RuntimeException('Archived FCA evidence does not match its database integrity record.');
            }
            $counts['fca_evidence']++;
        }

        return $counts;
    }

    private function requireEntry(array $entries, string $root, ?string $path): array
    {
        if (! $path || str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/') || str_contains($path, ':')) {
            throw new RuntimeException('Database upload reference is unsafe or unavailable.');
        }
        $metadata = $entries['files/'.$root.'/'.$path] ?? null;
        if (! is_array($metadata)) {
            throw new RuntimeException('A database-referenced upload is missing from the staged backup.');
        }

        return $metadata;
    }
}
