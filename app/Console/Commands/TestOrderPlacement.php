<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Buyer;
use App\Models\ProductListing;
use App\Models\Order;
use App\Models\OrderDetail;

class TestOrderPlacement extends Command
{
    protected $signature = 'test:order-placement';

    protected $description = 'Test order placement functionality';

    public function handle()
    {
        echo "=== Testing Order Placement ===\n\n";

        try {
            // Get a buyer
            $buyer = Buyer::first();
            if (!$buyer) {
                echo "❌ No buyers found\n";
                return;
            }
            echo "✅ Found buyer: {$buyer->organization_name}\n";

            // Get a product listing
            $listing = ProductListing::first();
            if (!$listing) {
                echo "❌ No product listings found\n";
                return;
            }
            echo "✅ Found listing: {$listing->product_name}\n";

            // Check quantity
            if ($listing->quantity_available && $listing->quantity_available > 0) {
                echo "✅ Product available: {$listing->quantity_available} units\n";
            } else {
                echo "⚠️  Limited product availability\n";
            }

            // Calculate prices
            $unitPrice = $listing->current_price_per_unit ?? $listing->price_per_unit ?? 0;
            $quantity = 5;
            $totalPrice = $unitPrice * $quantity;

            echo "\n=== Creating Test Order ===\n";
            echo "Unit Price: ₱{$unitPrice}\n";
            echo "Quantity: {$quantity}\n";
            echo "Total: ₱{$totalPrice}\n";

            // Create order
            $order = Order::create([
                'buyer_id' => $buyer->buyer_id,
                'order_date' => now()->toDateString(),
                'total_amount' => $totalPrice,
                'shipping_address' => 'Test Address, Manila',
                'fulfillment_status' => 'Confirmed',
                'payment_status' => 'Pending',
            ]);

            echo "\n✅ Order created: ORD-{$order->order_id}\n";

            // Create order detail
            $orderDetail = OrderDetail::create([
                'order_id' => $order->order_id,
                'listing_id' => $listing->listing_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $totalPrice,
            ]);

            echo "✅ Order detail created: {$orderDetail->order_detail_id}\n";

            // Verify the order was created
            $createdOrder = Order::with(['details.listing.producer'])->find($order->order_id);
            echo "\n=== Verification ===\n";
            echo "Order ID: {$createdOrder->order_id}\n";
            echo "Buyer ID: {$createdOrder->buyer_id}\n";
            echo "Total Amount: ₱{$createdOrder->total_amount}\n";
            echo "Status: {$createdOrder->fulfillment_status}\n";
            echo "Details Count: " . count($createdOrder->details) . "\n";

            echo "\n✅ Order placement test successful!\n";

        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
        }
    }
}
