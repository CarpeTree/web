<?php
/**
 * Save Quote Context API
 * Saves additional context for AI analysis
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database-simple.php';

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

