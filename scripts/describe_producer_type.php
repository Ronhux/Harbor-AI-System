<?php
require_once __DIR__ . '/../config/db.php';

$db = Database::getInstance()->getConnection();
$result = $db->query('DESCRIBE producers');
foreach ($result as $row) {
    if ($row['Field'] === 'producer_type') {
        print_r($row);
    }
}
