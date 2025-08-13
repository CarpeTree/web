<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../utils/progress-tracker.php';

$id = $_GET['id'] ?? '';
if ($id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}

$data = readProgress($id);
if (!$data) {
    echo json_encode([
        'success' => true,
        'progress_id' => $id,
        'stage' => 'waiting',
        'percent' => 0,
        'time' => time(),
    ]);
    exit;
}

echo json_encode(['success' => true] + $data);
?>


