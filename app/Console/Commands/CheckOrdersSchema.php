<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckOrdersSchema extends Command
{
    protected $signature = 'check:orders-schema';

    protected $description = 'Check orders table schema';

    public function handle()
    {
        echo "=== Orders Table Schema ===\n";
        $columns = \DB::select('DESCRIBE orders');
        foreach ($columns as $col) {
            echo "{$col->Field} | {$col->Type} | {$col->Null} | {$col->Key}\n";
        }

        echo "\n=== Sample Order ===\n";
        $order = \DB::select('SELECT * FROM orders LIMIT 1');
        if ($order) {
            echo json_encode($order[0], JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "No orders found\n";
        }

        echo "\n=== Buyers Count ===\n";
        $buyerCount = \DB::select('SELECT COUNT(*) as count FROM buyers');
        echo "Total buyers: " . $buyerCount[0]->count . "\n";

        echo "\n=== Product Listings Count ===\n";
        $listingCount = \DB::select('SELECT COUNT(*) as count FROM product_listings');
        echo "Total listings: " . $listingCount[0]->count . "\n";
    }
}
