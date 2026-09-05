<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $data = [
        'producer_id' => 1,
        'product_category' => 'Grains',
        'product_name' => 'Organic Corn',
        'current_price_per_unit' => 45,
        'unit_of_measure' => 'kg',
        'quantity_available' => 800,
        'harvest_date' => date('Y-m-d'),
        'status' => 'Active',
        'image_path' => null,
        'image_url' => null,
    ];

    $id = Illuminate\Support\Facades\DB::table('product_listings')->insertGetId($data);
    $row = Illuminate\Support\Facades\DB::table('product_listings')->where('listing_id', $id)->first();
    echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}
