<?php
/**
 * Save Quote Context API
 * Saves additional context for AI analysis
 */

header('Content-Type: application/json');
// CORS not needed beyond same-origin; remove permissive wildcard

require_once __DIR__ . '/../config/database-simple.php';

// Admin API key guard (optional; enforced if ADMIN_API_KEY is set)
function require_admin_key() {
    $expected = getenv('ADMIN_API_KEY') ?: ($_ENV['ADMIN_API_KEY'] ?? null);
    if (!$expected) return;
    $provided = $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? ($_GET['admin_key'] ?? $_POST['admin_key'] ?? null);
    if (!$provided || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
require_admin_key();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['quote_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Quote ID required']);
    exit;
}

$quote_id = (int)$input['quote_id'];
$context = $input['context'] ?? '';

try {
    $stmt = $pdo->prepare("UPDATE quotes SET context = ? WHERE id = ?");
    $stmt->execute([$context, $quote_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Context saved',
        'quote_id' => $quote_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

