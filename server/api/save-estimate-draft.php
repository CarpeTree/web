<?php
/**
 * Save Estimate Draft API
 * Saves estimate items and totals to database
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
$items = $input['items'] ?? [];
$totals = $input['totals'] ?? [];
$learning_data = $input['learning_data'] ?? null;

try {
    // Check if estimate exists
    $stmt = $pdo->prepare("SELECT id FROM quote_estimates WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $existing = $stmt->fetch();
    
    $estimate_data = json_encode([
        'items' => $items,
        'totals' => $totals,
        'saved_at' => date('Y-m-d H:i:s'),
        'learning_data' => $learning_data // Track AI suggestions vs chosen items for future learning
    ]);
    
    if ($existing) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE quote_estimates SET 
            estimate_data = ?,
            status = 'draft',
            updated_at = NOW()
            WHERE quote_id = ?");
        $stmt->execute([$estimate_data, $quote_id]);
    } else {
        // Create new
        $stmt = $pdo->prepare("INSERT INTO quote_estimates 
            (quote_id, estimate_data, status, created_at, updated_at) 
            VALUES (?, ?, 'draft', NOW(), NOW())");
        $stmt->execute([$quote_id, $estimate_data]);
    }
    
    // Update quote status
    $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'draft_ready' WHERE id = ?");
    $stmt->execute([$quote_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Draft saved successfully',
        'quote_id' => $quote_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

