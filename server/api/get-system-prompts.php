<?php
header('Content-Type: application/json');

$file = __DIR__ . '/../ai/system_prompts.json';

try {
    if (!file_exists($file)) {
        echo json_encode(['error' => 'system_prompts.json not found', 'file_path' => $file]);
        exit;
    }
    $data = file_get_contents($file);
    $json = json_decode($data, true);
    if ($json === null) {
        echo json_encode(['error' => 'Invalid JSON in system_prompts.json', 'file_path' => $file]);
        exit;
    }
    echo json_encode([
        'file_path' => $file,
        'last_modified' => date('c', filemtime($file)),
        'prompts' => $json
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage(), 'file_path' => $file]);
}
?>











