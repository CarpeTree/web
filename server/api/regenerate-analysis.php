<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils/error-handler.php';

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error(405, 'Method Not Allowed');
}

// Get and validate input from request body
$input = json_decode(file_get_contents('php://input'), true);
$quote_id = $input['quote_id'] ?? null;
$ai_type = $input['ai_type'] ?? null;

if (empty($quote_id) || !is_numeric($quote_id)) {
    send_error(400, 'Invalid or missing quote ID.');
}

if (empty($ai_type)) {
    send_error(400, 'Invalid or missing AI type.');
}

// Map the AI type to the correct script
$script_map = [
    'google-gemini-analysis' => 'google-gemini-analysis.php',
    'openai-o3-analysis' => 'openai-o3-analysis.php',
    'openai-o4-mini-analysis' => 'openai-o4-mini-analysis.php'
];

if (!isset($script_map[$ai_type])) {
    send_error(400, 'Unsupported AI type specified.');
}

$script_path = __DIR__ . '/' . $script_map[$ai_type];

if (!file_exists($script_path)) {
    send_error(500, "Analysis script not found for {$ai_type}.");
}

try {
    // Execute the appropriate AI analysis script in the background
    $command = "nohup php " . escapeshellarg($script_path) . " " . escapeshellarg($quote_id) . " > /dev/null 2>&1 &";
    
    // Execute the command
    shell_exec($command);

    // Immediately respond with a success message
    echo json_encode([
        'success' => true,
        'queued' => true,
        'message' => "Successfully queued {$ai_type} analysis for quote #{$quote_id}."
    ]);

} catch (Exception $e) {
    send_error(500, 'Failed to trigger analysis: ' . $e->getMessage());
}
?>
