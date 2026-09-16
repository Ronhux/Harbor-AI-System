<?php

namespace App\Http\Controllers;

use App\Models\Producer;
use App\Models\ProductListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProducerController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $producer = Producer::where('user_id', $user->user_id)->first();

        if (!$producer) {
            $producer = Producer::firstOrCreate(
                ['user_id' => $user->user_id],
                [
                    'rsbsa_number' => null,
                    'location' => null,
                    'primary_product_type' => null,
                    'verification_status' => 'Pending',
                    'producer_type' => $user->user_type === 'Farmer' ? 'Farmer' : null,
                ]
            );
        }

        return response()->json([
            'producer' => [
                'producer_id' => $producer->producer_id,
                'user_id' => $producer->user_id,
                'rsbsa_number' => $producer->rsbsa_number,
                'location' => $producer->location,
                'primary_product_type' => $producer->primary_product_type,
                'verification_status' => $producer->verification_status,
                'producer_type' => $producer->producer_type,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
            ],
            'product_listings' => $producer->productListings()->orderByDesc('created_at')->get()->map(function ($listing) {
                return $this->serializeListing($listing);
            })->values(),
        ]);
    }

    public function browseProducers()
    {
        $producers = Producer::with(['user', 'productListings' => function ($query) {
            $query->orderByDesc('created_at');
        }])
            ->where('verification_status', 'Verified')
            ->orderByDesc('producer_id')
            ->get();

        $data = $producers->map(function (Producer $producer) {
            $listings = $producer->productListings->map(function ($listing) {
                return [
                    'listing_id' => $listing->listing_id,
                    'product_name' => $listing->product_name,
                    'product_category' => $listing->product_category ?? $listing->category,
                    'current_price_per_unit' => (float) ($listing->current_price_per_unit ?? $listing->price_per_unit ?? 0),
                    'unit' => $listing->unit_of_measure ?? $listing->unit ?? 'kg',
                    'quantity_available' => (float) ($listing->quantity_available ?? $listing->quantity ?? 0),
                    'status' => $listing->status ?? 'Active',
                ];
            })->values();

            $name = trim(($producer->user->first_name ?? '') . ' ' . ($producer->user->last_name ?? '')) ?: 'Verified Producer';

            return [
                'id' => $producer->producer_id,
                'producer_id' => $producer->producer_id,
                'name' => $name,
                'type' => $producer->producer_type === 'Fisherfolk' ? 'Fisher' : ($producer->producer_type ?? 'Farmer'),
                'location' => $producer->location ?? 'N/A',
                'verified' => ($producer->verification_status ?? 'Pending') === 'Verified',
                'products' => $listings->pluck('product_name')->filter()->values()->all(),
                'product_listings' => $listings,
                'rating' => 4.8,
                'orders' => (int) max(1, $listings->count() * 3),
                'price' => $listings->isNotEmpty() ? '₱' . number_format((float) $listings->first()['current_price_per_unit'], 0) . '/kg' : '₱0/kg',
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function createProductListing(Request $request)
    {
        $user = Auth::user();
        $producer = Producer::where('user_id', $user->user_id)->first();

        if (!$producer) {
            return response()->json(['message' => 'Producer profile not found'], 404);
        }

        if ($producer->verification_status !== 'Verified') {
            return response()->json(['message' => 'Only verified producers can create listings'], 403);
        }

        $request->validate([
            'product_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'price_per_unit' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'harvest_date' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $category = $request->input('category');
        $unit = $request->input('unit');
        $quantity = (float) $request->input('quantity');
        $price = (float) $request->input('price_per_unit');

        $data = [
            'producer_id' => $producer->producer_id,
            'product_name' => $request->input('product_name'),
            'product_category' => $category,
            'category' => $category,
            'quantity_available' => $quantity,
            'quantity' => $quantity,
            'unit_of_measure' => $unit,
            'unit' => $unit,
            'current_price_per_unit' => $price,
            'price_per_unit' => $price,
            'harvest_date' => $request->input('harvest_date'),
            'status' => 'Active',
        ];

        if ($request->filled('description')) {
            $data['description'] = $request->input('description');
        }

        if ($request->filled('location')) {
            $data['location'] = $request->input('location');
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('product_images', 'public');
            $data['image_path'] = $path;
            $data['image_url'] = Storage::disk('public')->url($path);
        }

        $listing = ProductListing::create($data);

        return response()->json([
            'message' => 'Product listing created successfully',
            'listing' => $this->serializeListing($listing),
        ], 201);
    }

    public function updateProductListing(Request $request, $listingId)
    {
        $user = Auth::user();
        $producer = Producer::where('user_id', $user->user_id)->first();
        $listing = ProductListing::where('listing_id', $listingId)
            ->where('producer_id', $producer->producer_id)
            ->firstOrFail();

        $request->validate([
            'product_name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:255',
            'quantity' => 'sometimes|required|numeric|min:0',
            'unit' => 'sometimes|required|string|max:50',
            'price_per_unit' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'harvest_date' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $updates = [];

        if ($request->has('product_name')) {
            $updates['product_name'] = $request->input('product_name');
        }

        if ($request->has('category')) {
            $category = $request->input('category');
            $updates['product_category'] = $category;
            $updates['category'] = $category;
        }

        if ($request->has('quantity')) {
            $quantity = (float) $request->input('quantity');
            $updates['quantity_available'] = $quantity;
            $updates['quantity'] = $quantity;
        }

        if ($request->has('unit')) {
            $unit = $request->input('unit');
            $updates['unit_of_measure'] = $unit;
            $updates['unit'] = $unit;
        }

        if ($request->has('price_per_unit')) {
            $price = (float) $request->input('price_per_unit');
            $updates['current_price_per_unit'] = $price;
            $updates['price_per_unit'] = $price;
        }

        if ($request->has('description')) {
            $updates['description'] = $request->input('description');
        }

        if ($request->has('location')) {
            $updates['location'] = $request->input('location');
        }

        if ($request->has('status')) {
            $updates['status'] = $request->input('status');
        }

        if ($request->has('harvest_date')) {
            $updates['harvest_date'] = $request->input('harvest_date');
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('product_images', 'public');
            $updates['image_path'] = $path;
            $updates['image_url'] = Storage::disk('public')->url($path);
        }

        $listing->fill($updates);
        $listing->save();

        return response()->json([
            'message' => 'Product listing updated successfully',
            'listing' => $this->serializeListing($listing),
        ]);
    }

    public function deleteProductListing($listingId)
    {
        $user = Auth::user();
        $producer = Producer::where('user_id', $user->user_id)->first();
        $listing = ProductListing::where('listing_id', $listingId)
            ->where('producer_id', $producer->producer_id)
            ->firstOrFail();

        $listing->delete();

        return response()->json(['message' => 'Product listing deleted successfully']);
    }

    protected function serializeListing(ProductListing $listing): array
    {
        $category = $listing->product_category ?? $listing->category ?? 'General';
        $unit = $listing->unit_of_measure ?? $listing->unit ?? 'unit';
        $price = $listing->current_price_per_unit ?? $listing->price_per_unit ?? 0;
        $quantity = $listing->quantity_available ?? $listing->quantity ?? 0;

        return [
            'listing_id' => $listing->listing_id,
            'id' => $listing->listing_id,
            'producer_id' => $listing->producer_id,
            'product_name' => $listing->product_name,
            'product_category' => $category,
            'category' => $category,
            'current_price_per_unit' => (float) $price,
            'price_per_unit' => (float) $price,
            'price' => (float) $price,
            'unit_of_measure' => $unit,
            'unit' => $unit,
            'quantity_available' => (float) $quantity,
            'quantity' => (float) $quantity,
            'status' => $listing->status ?? 'Active',
            'views' => 0,
            'orders' => 0,
            'image_path' => $listing->image_path,
            'image_url' => $listing->image_path
                ? Storage::disk('public')->url(ltrim(str_replace('public/', '', $listing->image_path), '/'))
                : $listing->image_url,
            'description' => $listing->description,
            'location' => $listing->location,
            'harvest_date' => $listing->harvest_date,
            'created_at' => $listing->created_at,
            'updated_at' => $listing->updated_at,
        ];
    }
}