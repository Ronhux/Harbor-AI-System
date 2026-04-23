<?php

require_once 'config/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->query('DESCRIBE users');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($columns as $col) {
        if($col['Field'] == 'user_type') {
            echo 'user_type: ' . $col['Type'] . "\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}