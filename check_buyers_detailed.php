<?php

require_once 'config/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->query('DESCRIBE buyers');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Buyers table structure:\n";
    foreach($columns as $col) {
        echo $col['Field'] . ' - ' . $col['Key'] . ' - ' . ($col['Extra'] == 'auto_increment' ? 'AUTO' : '') . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}