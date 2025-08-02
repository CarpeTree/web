<?php
/**
 * Fix media file paths and test video processing
 */

require_once __DIR__ . '/server/config/config.php';

echo "=== FIXING MEDIA PATHS ===\n\n";

$pdo = getDatabaseConnection();

// Check current uploads directory structure
echo "1. Checking uploads directory structure...\n";
$uploads_dir = __DIR__ . '/uploads';
echo "   Base uploads dir: $uploads_dir\n";

if (is_dir($uploads_dir)) {
    echo "   ✅ Uploads directory exists\n";
    
    // List subdirectories
    $subdirs = glob($uploads_dir . '/*', GLOB_ONLYDIR);
    echo "   Found " . count($subdirs) . " quote directories:\n";
    foreach ($subdirs as $dir) {
        $quote_dir = basename($dir);
        echo "     - $quote_dir\n";
        
        // List files in this directory
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                echo "       * " . basename($file) . " (" . number_format(filesize($file)) . " bytes)\n";
            }
        }
    }
} else {
    echo "   ❌ Uploads directory doesn't exist\n";
}

echo "\n2. Checking Quote #69 media files...\n";
$stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
$stmt->execute();
$media_files = $stmt->fetchAll();

foreach ($media_files as $media) {
    echo "   Media ID {$media['id']}: {$media['original_filename']}\n";
    echo "   Current path: {$media['file_path']}\n";
    
    // Try different path variations
    $possible_paths = [
        __DIR__ . '/uploads/quote_69/' . basename($media['file_path']),
        __DIR__ . '/uploads/' . basename($media['file_path']),
        __DIR__ . '/' . $media['file_path'],
        str_replace('../uploads', __DIR__ . '/uploads', $media['file_path'])
    ];
    
    $found_path = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $found_path = $path;
            echo "   ✅ Found at: $path\n";
            break;
        }
    }
    
    if ($found_path) {
        // Update database with correct path
        $correct_relative_path = str_replace(__DIR__ . '/', '', $found_path);
        echo "   Updating database to: $correct_relative_path\n";
        
        $update_stmt = $pdo->prepare("UPDATE media SET file_path = ? WHERE id = ?");
        $update_stmt->execute([$correct_relative_path, $media['id']]);
        echo "   ✅ Database updated\n";
        
        // Test video processing
        echo "   Testing video processing...\n";
        require_once __DIR__ . '/server/utils/media-preprocessor.php';
        
        try {
            $preprocessor = new MediaPreprocessor();
            $result = $preprocessor->processForAI($correct_relative_path, 'video');
            
            echo "   ✅ Video processing successful!\n";
            echo "   Result type: " . gettype($result) . "\n";
            
            if (is_array($result) && isset($result['description'])) {
                echo "   Description length: " . strlen($result['description']) . " characters\n";
                echo "   First 200 chars: " . substr($result['description'], 0, 200) . "...\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Video processing failed: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "   ❌ File not found in any location\n";
    }
    echo "\n";
}

echo "3. Next step: Re-trigger AI analysis with corrected media paths\n";
echo "   Run: https://carpetree.com/server/api/trigger-all-analyses.php?quote_id=69\n";

echo "\n=== MEDIA PATH FIX COMPLETE ===\n";
?>