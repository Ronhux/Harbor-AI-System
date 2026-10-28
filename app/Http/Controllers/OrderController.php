<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductListing;
use App\Models\Buyer;
use App\Models\Negotiation;
use App\Models\Producer;
use App\Models\TransactionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Get orders of the currently logged-in buyer.
     */
    public function buyerOrders(Request $request)
    {
        $user = $request->user();

        $buyer = Buyer::where('user_id', $user->user_id)->first();

        if (!$buyer) {
            Log::warning('No buyer found for user', [
                'user_id' => $user->user_id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Buyer account not found.',
                'orders' => []
            ], 403);
        }

        $orders = Order::with([
            'details.listing.producer.user', 'negotiation'
        ])
        ->where('buyer_id', $buyer->buyer_id)
        ->orderBy('order_date', 'desc')
        ->get();

        return response()->json($orders);
    }

    /**
     * Create a new order.
     */
    public function store(Request $request)
    {
        $buyerUser = $request->user();
        abort_unless($buyerUser, 401);

        $buyer = Buyer::where('user_id', $buyerUser->user_id)->first();
        abort_unless($buyer, 403, 'Buyer account not found.');

        $data = $request->validate([
            'negotiation_id' => 'nullable|integer|exists:negotiations,id',
            'listing_id' => 'nullable|integer|exists:product_listings,listing_id',
            'quantity' => 'nullable|numeric|min:0.001',
            'shipping_address' => 'nullable|string|max:500',
            'delivery_date' => 'nullable|date|after_or_equal:today',
        ]);

        $negotiation = null;

        if (!empty($data['negotiation_id'])) {
            $negotiation = Negotiation::with('listing')->whereKey($data['negotiation_id'])->firstOrFail();
            abort_unless((int) $negotiation->buyer_id === (int) $buyer->buyer_id, 403, 'This negotiation belongs to another buyer.');
        } else {
            $listingId = $data['listing_id'] ?? null;
            abort_unless($listingId, 422, 'A listing_id is required to create an order.');

            $negotiation = Negotiation::with('listing')
                ->where('buyer_id', $buyer->buyer_id)
                ->where('listing_id', $listingId)
                ->where('status', 'Agreement Confirmed')
                ->latest('updated_at')
                ->first();
        }

        abort_unless(
            $negotiation && $negotiation->status === 'Agreement Confirmed' && is_array($negotiation->agreement_snapshot),
            409,
            'Orders can only be submitted from a mutually confirmed negotiation. Contact the producer, record proposed terms, and wait for both confirmations.'
        );

        $terms = $negotiation->agreement_snapshot;
        $requestedQuantity = (float) ($terms['quantity'] ?? 0);
        abort_if($requestedQuantity <= 0, 422, 'The confirmed negotiation does not have a valid order quantity.');

        if (array_key_exists('quantity', $data) && (float) $data['quantity'] !== $requestedQuantity) {
            abort(422, 'The order quantity must match the confirmed negotiation terms.');
        }

        if (!empty($data['delivery_date'])) {
            $terms['delivery_date'] = $data['delivery_date'];
        }

        if (!empty($data['shipping_address'])) {
            $terms['delivery_arrangement'] = $data['shipping_address'];
        }

        $order = DB::transaction(function () use ($negotiation, $buyer, $data, $requestedQuantity, $terms) {
            $locked = Negotiation::whereKey($negotiation->id)->lockForUpdate()->firstOrFail();
            $existing = Order::where('negotiation_id', $locked->id)->first();
            if ($existing) {
                $updated = false;

                if (!empty($data['shipping_address']) && empty($existing->shipping_address)) {
                    $existing->shipping_address = $data['shipping_address'];
                    $updated = true;
                }

                if (!empty($data['delivery_date']) && empty($existing->delivery_date)) {
                    $existing->delivery_date = $data['delivery_date'];
                    $updated = true;
                }

                if ($updated) {
                    $existing->save();
                }

                return $existing;
            }

            $listing = ProductListing::where('listing_id', $locked->listing_id)->lockForUpdate()->firstOrFail();
            $available = (float) ($listing->quantity_available ?? $listing->quantity ?? 0);
            $futureSupplyDate = $locked->supplyOffer?->availability_date;
            $futureSupply = $futureSupplyDate && $futureSupplyDate->isFuture();
            abort_if($available < $requestedQuantity && ! $futureSupply, 422, 'The product no longer has enough available quantity.');

            $shippingAddress = $data['shipping_address'] ?? ($terms['delivery_arrangement'] ?? null);
            $order = Order::create([
                'buyer_id' => $buyer->buyer_id,
                'negotiation_id' => $locked->id,
                'order_date' => now()->toDateString(),
                'delivery_date' => $terms['delivery_date'] ?? ($data['delivery_date'] ?? null),
                'total_amount' => $terms['total'] ?? ($requestedQuantity * ($terms['unit_price'] ?? 0)),
                'shipping_address' => $shippingAddress,
                'fulfillment_status' => 'Order Submitted',
                'payment_status' => 'Pending',
            ]);

            OrderDetail::create([
                'order_id' => $order->order_id,
                'listing_id' => $listing->listing_id,
                'quantity' => $requestedQuantity,
                'unit_price' => $terms['unit_price'] ?? ($listing->current_price_per_unit ?? $listing->price_per_unit ?? 0),
                'subtotal' => ($requestedQuantity * ($terms['unit_price'] ?? ($listing->current_price_per_unit ?? $listing->price_per_unit ?? 0))),
            ]);

            $producerUserId = Producer::find($locked->producer_id)?->user_id;
            if ($producerUserId) {
                TransactionNotification::create([
                    'recipient_user_id' => $producerUserId,
                    'negotiation_id' => $locked->id,
                    'order_id' => $order->order_id,
                    'title' => 'Official order submitted',
                    'body' => 'Order #' . $order->order_id . ' was created from ' . $locked->reference . ' and is waiting for your acceptance.',
                ]);
            }

            TransactionNotification::create([
                'recipient_user_id' => $buyerUser->user_id,
                'negotiation_id' => $locked->id,
                'order_id' => $order->order_id,
                'title' => 'Official order submitted',
                'body' => 'Order #' . $order->order_id . ' is now in Orders and is waiting for producer acceptance.',
            ]);

            if (! $futureSupply) {
                if ($listing->quantity_available !== null) {
                    $listing->quantity_available = max(0, $available - $requestedQuantity);
                }
                if ($listing->quantity !== null) {
                    $listing->quantity = max(0, (float) $listing->quantity - $requestedQuantity);
                }
                $listing->save();
            }

            return $order;
        });

        return response()->json([
            'message' => 'Official order submitted.',
            'order' => $order->load(['details.listing', 'negotiation']),
        ], 201);
    }

    public function show($orderId)
    {
        $order = Order::with([
            'details.listing.producer', 'negotiation'
        ])->findOrFail($orderId);

        $user = request()->user();
        $buyer = Buyer::where('user_id', $user->user_id)->first();
        $producer = \App\Models\Producer::where('user_id', $user->user_id)->first();
        $producerOwnsOrder = $producer && $order->details()->whereHas('listing', fn ($q) => $q->where('producer_id', $producer->producer_id))->exists();
        abort_unless(($buyer && (int) $order->buyer_id === (int) $buyer->buyer_id) || $producerOwnsOrder, 403);

        return response()->json($order);
    }
}
