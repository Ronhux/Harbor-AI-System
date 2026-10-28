<?php

namespace App\Http\Controllers;

use App\Models\ProductListing;
use App\Services\Analysis\DeterministicMarketAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuyerPriceReferenceController extends Controller
{
    private const MAX_LISTINGS_PER_REQUEST = 25;

    public function comparisons(Request $request, DeterministicMarketAnalysisService $analysis)
    {
        $data = $request->validate([
            'listing_ids' => ['required', 'array', 'min:1', 'max:' . self::MAX_LISTINGS_PER_REQUEST],
            'listing_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $listings = ProductListing::query()
            ->with('producer')
            ->whereIn('listing_id', $data['listing_ids'])
            ->whereHas('producer', fn ($query) => $query->whereRaw('LOWER(verification_status) = ?', ['verified']))
            ->whereIn(DB::raw('LOWER(status)'), ['active', 'available'])
            ->where(function ($query) {
                $query->where('quantity_available', '>', 0)
                    ->orWhere('quantity', '>', 0);
            })
            ->get();

        $comparisons = $listings->map(function (ProductListing $listing) use ($analysis): array {
            $unit = $listing->unit_of_measure ?: $listing->unit;
            $price = $listing->current_price_per_unit ?? $listing->price_per_unit;
            $result = $analysis->analyze([
                'product_name' => $listing->product_name,
                'category' => $listing->product_category ?: $listing->category,
                'unit' => $unit,
                'producer_price' => $price,
            ]);

            $referenceSummary = fn (array $reference): array => [
                'status' => $reference['status'] ?? 'NO_REFERENCE_AVAILABLE',
                'source' => $reference['source'] ?? null,
                'source_name' => $reference['source_name'] ?? null,
                'geography' => $reference['geography'] ?? null,
                'price_type' => $reference['price_type'] ?? null,
                'currency' => $reference['currency'] ?? null,
                'unit' => $reference['unit'] ?? null,
                'observation_count' => $reference['observation_count'] ?? 0,
                'minimum_price' => $reference['minimum_price'] ?? null,
                'maximum_price' => $reference['maximum_price'] ?? null,
                'average_price' => $reference['average_price'] ?? null,
                'first_observation_date' => $reference['first_observation_date'] ?? null,
                'latest_available_observation' => $reference['latest_available_observation'] ?? null,
            ];

            $resolution = $result['commodity_resolution'];
            $comparison = $result['producer_price_comparison'];
            $status = match (true) {
                $resolution['status'] === 'MULTIPLE_MATCHES' => 'MULTIPLE_MATCHES',
                $resolution['status'] !== 'RESOLVED' => 'UNRESOLVED',
                ($result['psa_reference']['observation_count'] ?? 0) === 0 => 'NO_REFERENCE_AVAILABLE',
                default => $comparison['status'],
            };

            return [
                'listing_id' => (int) $listing->listing_id,
                'status' => $status,
                'commodity_resolution' => [
                    'status' => $resolution['status'],
                    'canonical_name' => $resolution['canonical_name'],
                    'candidates' => $resolution['candidates'],
                ],
                'listing_price' => [
                    'amount' => $price === null ? null : (float) $price,
                    'currency' => 'PHP',
                    'unit' => $unit,
                ],
                'comparison' => [
                    'status' => $comparison['status'],
                    'normalized_listing_price' => $comparison['normalized_price_per_kg'],
                    'unit' => 'PHP/kg',
                    'difference_from_average' => $comparison['difference_from_average'],
                    'position_relative_to_range' => $comparison['position_relative_to_range'],
                ],
                'seasonality' => $result['seasonality'],
                'reference' => $referenceSummary($result['psa_reference']),
                'reference_candidates' => array_map(
                    fn (array $candidate): array => [
                        'commodity_label' => $candidate['commodity_label'],
                        'match_method' => $candidate['match_method'],
                        'reference' => $referenceSummary($candidate['psa_reference']),
                    ],
                    $result['psa_reference_candidates']
                ),
            ];
        })->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'data' => $comparisons,
        ]);
    }
}
