<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $id = Illuminate\Support\Facades\DB::table('product_listings')->insertGetId([
        'producer_id' => 1,
        'product_category' => 'Test',
        'product_name' => 'TestProduct',
        'current_price_per_unit' => 10.5,
        'unit_of_measure' => 'kg',
        'quantity_available' => 5,
        'harvest_date' => date('Y-m-d'),
        'status' => 'active',
    ]);
    echo "Inserted listing id: $id\n";
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
