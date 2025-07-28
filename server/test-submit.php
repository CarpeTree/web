<?php
// Simplified quote submission test
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Handle both POST and GET for testing
$method = $_SERVER['REQUEST_METHOD'];

try {
    echo "=== QUOTE SUBMISSION DEBUG ===\n";
    echo "Method: $method\n";
    
    // Test 1: Config loading
    echo "\n1. Loading config files...\n";
    require_once '../config/database-simple.php';
    echo "✅ database-simple.php loaded\n";
    
    require_once '../config/config.php';
    echo "✅ config.php loaded\n";
    
    // Test 2: Database connection
    echo "\n2. Testing database...\n";
    if (isset($pdo)) {
        echo "✅ PDO connection exists\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers");
        $result = $stmt->fetch();
        echo "✅ Database query works. Customers table has {$result['count']} records\n";
    }
    
    // Test 3: POST data (if POST request)
    if ($method === 'POST') {
        echo "\n3. POST Data received:\n";
        foreach ($_POST as $key => $value) {
            echo "  $key: $value\n";
        }
        
        echo "\n4. FILES data:\n";
        if (isset($_FILES['files'])) {
            echo "  Files uploaded: " . count($_FILES['files']['name']) . "\n";
        } else {
            echo "  No files received\n";
        }
        
        // Test actual submission logic
        if (!empty($_POST['email'])) {
            echo "\n5. Testing customer insert...\n";
            $stmt = $pdo->prepare("
                INSERT INTO customers (email, name, phone, address) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['email'],
                $_POST['name'] ?? null,
                $_POST['phone'] ?? null,
                $_POST['address'] ?? null
            ]);
            echo "✅ Customer inserted successfully\n";
            
            $customer_id = $pdo->lastInsertId();
            echo "Customer ID: $customer_id\n";
        }
    }
    
    echo "\n=== ALL TESTS PASSED ===\n";
    echo json_encode(['success' => true, 'message' => 'Debug complete']);
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 