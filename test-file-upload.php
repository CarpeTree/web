<?php
// Test file upload functionality
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== FILE UPLOAD TEST ===\n\n";

try {
    // Test upload directory creation
    $uploads_base = dirname(__DIR__) . '/uploads';
    echo "Upload base path: $uploads_base\n";
    echo "Upload base exists: " . (is_dir($uploads_base) ? 'YES' : 'NO') . "\n";
    
    if (!is_dir($uploads_base)) {
        echo "Creating upload base directory...\n";
        if (mkdir($uploads_base, 0755, true)) {
            echo "✅ Upload base directory created\n";
        } else {
            echo "❌ Failed to create upload base directory\n";
        }
    }
    
    // Test creating quote-specific directories
    for ($quote_id = 1; $quote_id <= 3; $quote_id++) {
        $quote_dir = "$uploads_base/$quote_id";
        echo "\nTesting quote $quote_id directory...\n";
        echo "Path: $quote_dir\n";
        echo "Exists: " . (is_dir($quote_dir) ? 'YES' : 'NO') . "\n";
        
        if (!is_dir($quote_dir)) {
            if (mkdir($quote_dir, 0755, true)) {
                echo "✅ Created directory for quote $quote_id\n";
            } else {
                echo "❌ Failed to create directory for quote $quote_id\n";
            }
        }
    }
    
    // Test file permissions
    echo "\n=== PERMISSION TEST ===\n";
    echo "Current user: " . get_current_user() . "\n";
    echo "Upload base writable: " . (is_writable($uploads_base) ? 'YES' : 'NO') . "\n";
    
    // Test creating a dummy file
    $test_file = "$uploads_base/test.txt";
    if (file_put_contents($test_file, "Test file content")) {
        echo "✅ Can write files to upload directory\n";
        unlink($test_file);
        echo "✅ Can delete files from upload directory\n";
    } else {
        echo "❌ Cannot write files to upload directory\n";
    }
    
    // Check PHP upload settings
    echo "\n=== PHP UPLOAD SETTINGS ===\n";
    echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
    echo "post_max_size: " . ini_get('post_max_size') . "\n";
    echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
    echo "file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "\n";
    
    // Check if any files actually exist in filesystem
    echo "\n=== SCANNING FOR EXISTING FILES ===\n";
    $scan_paths = [
        dirname(__DIR__) . '/uploads',
        __DIR__ . '/uploads',
        '/home/u230128646/domains/carpetree.com/uploads'
    ];
    
    foreach ($scan_paths as $path) {
        echo "Checking: $path\n";
        if (is_dir($path)) {
            $files = scandir($path);
            $actual_files = array_filter($files, function($f) { return !in_array($f, ['.', '..']); });
            echo "  Files found: " . count($actual_files) . "\n";
            if (!empty($actual_files)) {
                foreach ($actual_files as $file) {
                    echo "    - $file\n";
                }
            }
        } else {
            echo "  Directory doesn't exist\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 