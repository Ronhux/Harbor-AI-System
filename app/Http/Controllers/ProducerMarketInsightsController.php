<?php

namespace App\Http\Controllers;

use App\Models\Producer;
use App\Models\ProductListing;
use App\Services\Analysis\DeterministicMarketAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProducerMarketInsightsController extends Controller
{
    public function index(Request $request, DeterministicMarketAnalysisService $analysis)
    {
        $producer = Producer::query()
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();

        $listings = ProductListing::query()
            ->with('producer')
            ->where('producer_id', $producer->producer_id)
            ->whereIn(DB::raw('LOWER(status)'), ['active', 'available'])
            ->where(function ($query) {
                $query->where('quantity_available', '>', 0)
                    ->orWhere('quantity', '>', 0);
            })
            ->orderBy('listing_id')
            ->get();

        $items = $listings->map(function (ProductListing $listing) use ($analysis) {
            $unit = $listing->unit_of_measure ?: $listing->unit;
            $price = $listing->current_price_per_unit ?? $listing->price_per_unit;
            $quantity = $listing->quantity_available ?? $listing->quantity;

            return [
                'listing' => [
                    'listing_id' => (int) $listing->listing_id,
                    'product_name' => $listing->product_name,
                    'category' => $listing->product_category ?: $listing->category,
                    'price_per_unit' => $price === null ? null : (float) $price,
                    'unit' => $unit,
                    'quantity_available' => $quantity === null ? null : (float) $quantity,
                    'location' => $listing->location ?: $listing->producer?->location,
                ],
                'analysis' => $analysis->analyze([
                    'product_name' => $listing->product_name,
                    'category' => $listing->product_category ?: $listing->category,
                    'origin' => $listing->location ?: $listing->producer?->location,
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'producer_price' => $price,
                ]),
            ];
        })->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'source' => 'Current producer listings, active HarborAI supply and demand records, and available verified market reference data.',
            'data' => $items,
        ]);
    }
}
