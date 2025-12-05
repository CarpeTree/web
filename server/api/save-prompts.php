<?php
/**
 * Save System Prompts API
 * Saves GPT-5.1 and Gemini 3 Pro prompts to system_prompts.json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

$prompts_file = __DIR__ . '/../ai/system_prompts.json';

try {
    // Load existing prompts
    $existing = [];
    if (file_exists($prompts_file)) {
        $existing = json_decode(file_get_contents($prompts_file), true) ?: [];
    }
    
    // Merge new prompts
    foreach ($input as $key => $value) {
        if (isset($value['prompt'])) {
            $existing[$key] = [
                'prompt' => $value['prompt'],
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Save
    $result = file_put_contents($prompts_file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($result === false) {
        throw new Exception('Failed to write prompts file');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Prompts saved successfully',
        'keys_updated' => array_keys($input)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

