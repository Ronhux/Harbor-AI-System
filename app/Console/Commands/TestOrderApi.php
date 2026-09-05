<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Http\Request;

class TestOrderApi extends Command
{
    protected $signature = 'test:order-api';

    protected $description = 'Test the order API endpoint';

    public function handle()
    {
        echo "\n=== Testing Order API Response ===\n\n";

        try {
            // Get a buyer user
            $buyerUser = User::where('user_type', 'Buyer')->first();
            
            if (!$buyerUser) {
                echo "❌ No buyer users found\n";
                return;
            }

            // Create a mock request
            $request = new Request();
            $request->setUserResolver(function () use ($buyerUser) {
                return $buyerUser;
            });

            // Call the controller method
            $controller = new \App\Http\Controllers\OrderController();
            $response = $controller->buyerOrders($request);

            echo "API Response:\n";
            echo $response->getContent() . "\n";
            
            // Parse and display
            $orders = json_decode($response->getContent(), true);
            echo "\n=== Parsed Response ===\n";
            echo "Total orders: " . count($orders) . "\n";
            
            if (count($orders) > 0) {
                echo "\nFirst order structure:\n";
                echo json_encode($orders[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            }

        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
        }
    }
}
