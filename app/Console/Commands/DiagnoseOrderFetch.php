<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Buyer;
use App\Models\Order;

class DiagnoseOrderFetch extends Command
{
    protected $signature = 'diagnose:order-fetch';

    protected $description = 'Diagnose order fetching issues';

    public function handle()
    {
        echo "\n=== Diagnosing Order Fetch Issues ===\n\n";

        try {
            // Check users
            $users = User::all();
            echo "Total Users: " . count($users) . "\n";
            foreach ($users as $user) {
                echo "  - User {$user->user_id}: {$user->email} (buyer_id: {$user->buyer_id})\n";
            }

            // Check buyers
            echo "\nTotal Buyers: " . count(Buyer::all()) . "\n";
            $buyers = Buyer::all();
            foreach ($buyers as $buyer) {
                echo "  - Buyer {$buyer->buyer_id}: {$buyer->organization_name}\n";
            }

            // Check orders
            echo "\nTotal Orders: " . count(Order::all()) . "\n";
            $orders = Order::all();
            foreach ($orders as $order) {
                echo "  - Order {$order->order_id}: Buyer {$order->buyer_id}, Total: ₱{$order->total_amount}\n";
            }

            // Test order fetching for each buyer
            echo "\n=== Testing Order Fetching ===\n";
            foreach ($buyers as $buyer) {
                echo "\nBuyer {$buyer->buyer_id} ({$buyer->organization_name}):\n";
                
                $buyerOrders = Order::where('buyer_id', $buyer->buyer_id)->get();
                echo "  Orders found: " . count($buyerOrders) . "\n";
                
                foreach ($buyerOrders as $order) {
                    echo "    - ORD-{$order->order_id}: ₱{$order->total_amount}\n";
                    
                    // Check details
                    $details = \DB::table('order_details')->where('order_id', $order->order_id)->get();
                    echo "      Details: " . count($details) . "\n";
                }
            }

            // Test with relationships
            echo "\n=== Testing with Relationships ===\n";
            $ordersWithDetails = Order::with('details.listing.producer')->get();
            echo "Orders with relationships loaded: " . count($ordersWithDetails) . "\n";
            foreach ($ordersWithDetails as $order) {
                echo "  - ORD-{$order->order_id}: Details count: " . count($order->details) . "\n";
            }

        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
        }
    }
}
