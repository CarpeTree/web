<?php
// Log admin dashboard usage for data analysis
header('Content-Type: application/json');
require_once '../config/database-simple.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

try {
    // Create usage log table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_usage_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quote_id INT NULL,
            action VARCHAR(100) NOT NULL,
            field VARCHAR(100) NULL,
            value TEXT NULL,
            ai_type VARCHAR(50) NULL,
            context TEXT NULL,
            user_ip VARCHAR(45) NULL,
            user_agent TEXT NULL,
            session_id VARCHAR(100) NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_quote_id (quote_id),
            INDEX idx_action (action),
            INDEX idx_timestamp (timestamp)
        )
    ");

    // Log the usage
    $stmt = $pdo->prepare("
        INSERT INTO admin_usage_log 
        (quote_id, action, field, value, ai_type, context, user_ip, user_agent, session_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $input['quote_id'] ?? null,
        $input['action'] ?? 'unknown',
        $input['field'] ?? null,
        $input['value'] ?? null,
        $input['ai_type'] ?? null,
        $input['context'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        session_id()
    ]);

    echo json_encode([
        'success' => true,
        'logged_id' => $pdo->lastInsertId(),
        'message' => 'Usage logged successfully'
    ]);

} catch (Exception $e) {
    error_log("Admin usage logging failed: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to log usage',
        'message' => $e->getMessage()
    ]);
}
?>