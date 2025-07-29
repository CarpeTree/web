<?php
// Minimal test version - step by step
header('Content-Type: application/json');

try {
    echo json_encode(['step' => 1, 'message' => 'Headers set']);
    
    // Test database connection
    require_once __DIR__ . '/../config/database-simple.php';
    echo json_encode(['step' => 2, 'message' => 'Database loaded']);
    
    // Test config
    require_once __DIR__ . '/../config/config.php';
    echo json_encode(['step' => 3, 'message' => 'Config loaded']);
    
    // Test file handler
    require_once __DIR__ . '/../utils/fileHandler.php';
    echo json_encode(['step' => 4, 'message' => 'FileHandler loaded']);
    
    // Test mailer
    require_once __DIR__ . '/../utils/mailer.php';
    echo json_encode(['step' => 5, 'message' => 'Mailer loaded']);
    
    // Test database query
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo json_encode(['step' => 6, 'message' => 'Database query works', 'customer_count' => $count]);
    
    echo json_encode(['success' => true, 'message' => 'All components loaded successfully']);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
} catch (Error $e) {
    echo json_encode(['fatal_error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
} 