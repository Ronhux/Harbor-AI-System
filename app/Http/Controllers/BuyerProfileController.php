<?php

namespace App\Http\Controllers;

use App\Models\Buyer;
use App\Rules\LoginNumberNotTaken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BuyerProfileController extends Controller
{
    public function show(Request $request)
    {
        $buyer = Buyer::with('user')
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();

        return response()->json(['data' => $this->present($buyer)]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $buyer = Buyer::where('user_id', $user->user_id)->firstOrFail();

        $data = $request->validate([
            'organization_name' => 'required|string|max:255',
            'buyer_type' => 'nullable|string|max:100',
            'contact_person' => 'required|string|max:255',
            'shipping_address' => 'nullable|string|max:2000',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->user_id, 'user_id'),
            ],
            'contact_number' => ['nullable', 'string', 'max:50', new LoginNumberNotTaken($user->user_id)],
        ]);

        DB::transaction(function () use ($buyer, $user, $data) {
            $buyer->update([
                'organization_name' => $data['organization_name'],
                'buyer_type' => $data['buyer_type'] ?? null,
                'contact_person' => $data['contact_person'],
                'shipping_address' => $data['shipping_address'] ?? null,
            ]);

            $user->update([
                'email' => $data['email'],
                'contact_number' => $data['contact_number'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Buyer profile updated.',
            'data' => $this->present($buyer->fresh()->load('user')),
        ]);
    }

    private function present(Buyer $buyer): array
    {
        return [
            'buyer_id' => $buyer->buyer_id,
            'organization_name' => $buyer->organization_name,
            'buyer_type' => $buyer->buyer_type,
            'verification_status' => $buyer->verification_status,
            'contact_person' => $buyer->contact_person,
            'shipping_address' => $buyer->shipping_address,
            'email' => $buyer->user?->email,
            'contact_number' => $buyer->user?->contact_number,
        ];
    }
}
