<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemandRequestController extends Controller
{
    public function index()
    {
        $buyer = $this->buyer();

        if (!$buyer) {
            return response()->json(['message' => 'Buyer profile not found'], 404);
        }

        return response()->json([
            'data' => $buyer->demandRequests()->latest('request_id')->get()->map(function ($demand) {
                return array_merge($demand->toArray(), ['id' => $demand->getKey()]);
            })->values(),
        ]);
    }

    public function store(Request $request)
    {
        $buyer = $this->buyer();

        if (!$buyer) {
            return response()->json(['message' => 'Buyer profile not found'], 404);
        }

        if ($buyer->verification_status !== 'Verified') {
            return response()->json(['message' => 'Only verified buyers can post demand notices'], 403);
        }

        $data = $request->validate([
            'product_name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'quantity_needed' => 'required|numeric|min:1',
            'unit' => 'required|string|max:50',
            'max_price_per_unit' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'deadline' => 'required|date',
        ]);

        $demand = $buyer->demandRequests()->create($data + ['status' => 'open']);

        return response()->json([
            'message' => 'Demand notice posted successfully',
            'demand' => array_merge($demand->toArray(), ['id' => $demand->getKey()]),
        ], 201);
    }

    private function buyer(): ?Buyer
    {
        return Buyer::where('user_id', Auth::id())->first();
    }
}