<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductListing;
use App\Models\Buyer;
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

        // Get buyer through relationship
        $buyer = $user->buyer;

        if (!$buyer) {
            Log::warning('No buyer found for user', [
                'user_id' => $user->user_id,
                'email' => $user->email,
            ]);

            return response()->json([
                'message' => 'Buyer account not found.',
                'orders' => []
            ], 200);
        }

        $orders = Order::with([
            'details.listing.producer'
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
        $validator = Validator::make($request->all(), [
            'listing_id' => 'required|integer|exists:product_listings,listing_id',
            'quantity' => 'required|numeric|min:0.01',
            'shipping_address' => 'required|string|max:500',
            'delivery_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            Log::error('Order validation failed', [
                'errors' => $validator->errors(),
                'input' => $request->all(),
            ]);
            
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user) {
                Log::warning('Order creation attempted without authentication');
                
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Find buyer connected to logged-in user
            $buyer = Buyer::where('user_id', $user->user_id)->first();

            if (!$buyer) {
                Log::warning('Buyer not found for user', [
                    'user_id' => $user->user_id,
                ]);
                
                return response()->json([
                    'message' => 'Buyer account not found.'
                ], 404);
            }

            // Find product listing
            $listing = ProductListing::where(
                'listing_id',
                $request->listing_id
            )->first();

            if (!$listing) {
                Log::warning('Product listing not found', [
                    'listing_id' => $request->listing_id,
                ]);
                
                return response()->json([
                    'message' => 'Product listing not found.'
                ], 404);
            }

            // Check available quantity
            if (
                $listing->quantity_available !== null &&
                $request->quantity > $listing->quantity_available
            ) {
                return response()->json([
                    'message' => 'Insufficient product quantity available.',
                    'available_quantity' => $listing->quantity_available
                ], 422);
            }

            // Determine price
            $unitPrice = $listing->current_price_per_unit
                ?? $listing->price_per_unit
                ?? 0;

            $quantity = (float) $request->quantity;
            $totalPrice = $unitPrice * $quantity;

            // Create order - using total_amount field as per database schema
            $order = Order::create([
                'buyer_id' => $buyer->buyer_id,
                'order_date' => now()->toDateString(),
                'total_amount' => $totalPrice,
                'shipping_address' => $request->shipping_address,
                'fulfillment_status' => 'Confirmed',
                'payment_status' => 'Pending',
            ]);

            Log::info('Order created successfully', [
                'order_id' => $order->order_id,
                'buyer_id' => $buyer->buyer_id,
                'listing_id' => $listing->listing_id,
            ]);

            // Create order detail
            $orderDetail = OrderDetail::create([
                'order_id' => $order->order_id,
                'listing_id' => $listing->listing_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $totalPrice,
            ]);

            // Reduce available product quantity
            if ($listing->quantity_available !== null) {
                $listing->quantity_available =
                    $listing->quantity_available - $quantity;

                $listing->save();
            }

            return response()->json([
                'message' => 'Order placed successfully.',
                'order' => $order,
                'order_detail' => $orderDetail,
            ], 201);

        } catch (\Exception $e) {

            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'user_id' => optional($request->user())->user_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to place order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($orderId)
    {
        $order = Order::with([
            'details.listing.producer'
        ])->findOrFail($orderId);

        return response()->json($order);
    }
}