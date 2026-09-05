<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Order;

class TestOrderFetch extends Command
{
    protected $signature = 'test:order-fetch';

    protected $description = 'Test fetching orders by user';

    public function handle()
    {
        echo "\n=== Testing Order Fetch ===\n\n";

        try {
            // Get a buyer user
            $buyerUser = User::where('user_type', 'Buyer')->first();
            
            if (!$buyerUser) {
                echo "❌ No buyer users found\n";
                return;
            }

            echo "Testing with user: {$buyerUser->email}\n";
            echo "User Type: {$buyerUser->user_type}\n";

            // Test getting buyer through relationship
            $buyer = $buyerUser->buyer;
            
            if (!$buyer) {
                echo "❌ No buyer found for this user\n";
                return;
            }

            echo "✅ Found buyer: {$buyer->organization_name} (ID: {$buyer->buyer_id})\n";

            // Fetch orders
            $orders = Order::with(['details.listing.producer'])
                ->where('buyer_id', $buyer->buyer_id)
                ->orderBy('order_date', 'desc')
                ->get();

            echo "\n=== Orders for this Buyer ===\n";
            echo "Total orders: " . count($orders) . "\n";

            foreach ($orders as $order) {
                echo "\nOrder ORD-{$order->order_id}:\n";
                echo "  Date: {$order->order_date}\n";
                echo "  Total: ₱{$order->total_amount}\n";
                echo "  Status: {$order->fulfillment_status}\n";
                echo "  Payment: {$order->payment_status}\n";
                echo "  Details: " . count($order->details) . " item(s)\n";
                
                foreach ($order->details as $detail) {
                    echo "    - {$detail->listing->product_name}\n";
                    echo "      Qty: {$detail->quantity}\n";
                    echo "      Price: ₱{$detail->unit_price}\n";
                }
            }

            echo "\n✅ Order fetch test successful!\n";

        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
        }
    }
}
