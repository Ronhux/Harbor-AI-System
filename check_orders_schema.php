<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$db = $app->make('db');

echo "=== Orders Table Schema ===\n";
$columns = $db->select('DESCRIBE orders');
foreach ($columns as $col) {
    echo "{$col->Field} | {$col->Type} | {$col->Null} | {$col->Key} | {$col->Default}\n";
}

echo "\n=== Sample Order ===\n";
$order = $db->select('SELECT * FROM orders LIMIT 1');
if ($order) {
    echo json_encode($order[0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No orders found\n";
}

echo "\n=== Buyer Test ===\n";
$buyers = $db->select('SELECT * FROM buyers LIMIT 1');
if ($buyers) {
    echo "Buyer found: " . json_encode($buyers[0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No buyers found\n";
}
