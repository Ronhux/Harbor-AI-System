<?php

require_once 'config/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute(['test13@example.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo 'User found: ' . ($user ? 'Yes' : 'No') . PHP_EOL;
    if($user) {
        echo 'User ID: ' . $user['user_id'] . PHP_EOL;
        echo 'Email: ' . $user['email'] . PHP_EOL;
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}