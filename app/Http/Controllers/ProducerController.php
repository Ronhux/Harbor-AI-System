<?php

namespace App\Http\Controllers;

use App\Models\Producer;
use App\Models\ProductListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProducerController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $producer = Producer::where('user_id', $user->user_id)->first();

        if (!$producer) {
            return response()->json(['message' => 'Producer profile not found'], 404);
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
            'product_listings' => $producer->productListings,
        ]);
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
            'location' => 'required|string|max:255',
            'harvest_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
        ]);

        $listing = ProductListing::create([
            'producer_id' => $producer->id,
            ...$request->all(),
            'status' => 'active',
        ]);

        return response()->json(['message' => 'Product listing created successfully', 'listing' => $listing], 201);
    }
}