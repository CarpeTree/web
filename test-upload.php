<?php
require_once 'server/config/database-simple.php';
require_once 'server/utils/fileHandler.php';

echo "Testing file upload...\n";

// Create a test file
$test_content = "This is a test image content";
$test_file = [
    'tmp_name' => '/tmp/test_image.jpg',
    'name' => 'test_image.jpg',
    'size' => strlen($test_content),
    'type' => 'image/jpeg'
];

// Write test content to temp file
file_put_contents($test_file['tmp_name'], $test_content);

$upload_dir = __DIR__ . '/uploads/test_quote';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

try {
    $result = processUploadedFile($test_file, 999, $upload_dir, $pdo);
    echo "Upload successful:\n";
    print_r($result);
    
    // Check if file was actually created
    $file_path = $upload_dir . '/' . $result['filename'];
    if (file_exists($file_path)) {
        echo "File exists: $file_path\n";
        echo "File size: " . filesize($file_path) . " bytes\n";
    } else {
        echo "File does not exist: $file_path\n";
    }
    
} catch (Exception $e) {
    echo "Upload failed: " . $e->getMessage() . "\n";
}

// Clean up
unlink($test_file['tmp_name']);
?> 