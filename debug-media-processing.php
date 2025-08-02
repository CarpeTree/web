<?php
/**
 * Debug media processing for Quote #69
 */

require_once __DIR__ . '/server/config/config.php';

echo "=== MEDIA PROCESSING DEBUG ===\n\n";

$pdo = getDatabaseConnection();
if (!$pdo) {
    die("Database connection failed");
}

// Check media files for Quote #69
echo "1. Checking media files for Quote #69...\n";
$stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
$stmt->execute();
$media_files = $stmt->fetchAll();

if (empty($media_files)) {
    echo "   ❌ No media files found for Quote #69\n";
} else {
    echo "   ✅ Found " . count($media_files) . " media files:\n";
    foreach ($media_files as $media) {
        echo "   - ID: {$media['id']}, Type: {$media['media_type']}, Path: {$media['file_path']}\n";
        echo "     Original: {$media['original_filename']}\n";
        
        // Check if file exists
        $full_path = __DIR__ . '/' . $media['file_path'];
        if (file_exists($full_path)) {
            echo "     ✅ File exists (" . number_format(filesize($full_path)) . " bytes)\n";
        } else {
            echo "     ❌ File missing: $full_path\n";
        }
    }
}

echo "\n2. Testing MediaPreprocessor...\n";
require_once __DIR__ . '/server/utils/media-preprocessor.php';

if (class_exists('MediaPreprocessor')) {
    echo "   ✅ MediaPreprocessor class found\n";
    
    if (!empty($media_files)) {
        $preprocessor = new MediaPreprocessor();
        
        foreach ($media_files as $media) {
            echo "   Testing preprocessing for: {$media['original_filename']}\n";
            
            try {
                $processed = $preprocessor->processForAI($media['file_path'], $media['media_type']);
                echo "     ✅ Preprocessing successful\n";
                echo "     Result type: " . gettype($processed) . "\n";
                if (is_array($processed)) {
                    echo "     Contains " . count($processed) . " elements\n";
                    if (isset($processed['description'])) {
                        echo "     Description: " . substr($processed['description'], 0, 100) . "...\n";
                    }
                }
            } catch (Exception $e) {
                echo "     ❌ Preprocessing failed: " . $e->getMessage() . "\n";
            }
        }
    }
} else {
    echo "   ❌ MediaPreprocessor class not found\n";
}

echo "\n3. Checking ffmpeg availability...\n";
$ffmpeg_test = shell_exec('ffmpeg -version 2>&1');
if ($ffmpeg_test && strpos($ffmpeg_test, 'ffmpeg version') !== false) {
    echo "   ✅ ffmpeg is available\n";
    echo "   Version: " . substr($ffmpeg_test, 0, 100) . "...\n";
} else {
    echo "   ❌ ffmpeg not available or not working\n";
    echo "   Output: " . ($ffmpeg_test ?: 'No output') . "\n";
}

echo "\n4. Checking uploads directory...\n";
$uploads_dir = __DIR__ . '/uploads';
if (is_dir($uploads_dir)) {
    echo "   ✅ Uploads directory exists\n";
    $files = scandir($uploads_dir);
    echo "   Contains " . (count($files) - 2) . " files\n"; // -2 for . and ..
} else {
    echo "   ❌ Uploads directory missing\n";
}

echo "\n=== DIAGNOSIS COMPLETE ===\n";
echo "If media files exist but aren't being processed for AI,\n";
echo "the issue is likely in the MediaPreprocessor or ffmpeg setup.\n";
?>