<?php
// Minimal GPS submission test
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/server/config/database-simple.php';
    
    // Test data
    $test_data = [
        'email' => 'gps.test@example.com',
        'name' => 'GPS Test Customer',
        'phone' => '778-555-GPS1',
        'address' => 'Vancouver, BC',
        'geo_latitude' => 49.2827,
        'geo_longitude' => -123.1207,
        'geo_accuracy' => 15.0,
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Test Agent'
    ];
    
    // Test customer insert with GPS
    $stmt = $pdo->prepare("
        INSERT INTO customers (
            name, email, phone, address, 
            geo_latitude, geo_longitude, geo_accuracy, 
            ip_address, user_agent, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $test_data['name'],
        $test_data['email'],
        $test_data['phone'],
        $test_data['address'],
        $test_data['geo_latitude'],
        $test_data['geo_longitude'],
        $test_data['geo_accuracy'],
        $test_data['ip_address'],
        $test_data['user_agent']
    ]);
    
    if ($result) {
        $customer_id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'customer_id' => $customer_id,
            'message' => 'GPS customer insert test successful'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Insert failed'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>