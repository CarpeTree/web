<?php
/**
 * Find the correct video file for Quote #69 and fix the database
 */

require_once __DIR__ . '/server/config/config.php';

echo "=== FINDING CORRECT VIDEO FOR QUOTE #69 ===\n\n";

$pdo = getDatabaseConnection();

// Get Quote #69 details
echo "1. Quote #69 details...\n";
$stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = 69");
$stmt->execute();
$quote = $stmt->fetch();

if ($quote) {
    echo "   Created: {$quote['created_at']}\n";
    echo "   Customer: {$quote['customer_id']}\n";
}

// Check all video files in uploads
echo "\n2. Scanning all video files in uploads...\n";
$uploads_dir = __DIR__ . '/uploads';
$video_files = [];

$subdirs = glob($uploads_dir . '/*', GLOB_ONLYDIR);
foreach ($subdirs as $dir) {
    $dir_name = basename($dir);
    $files = glob($dir . '/*');
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['mov', 'mp4', 'avi', 'mkv'])) {
                $video_files[] = [
                    'dir' => $dir_name,
                    'filename' => $filename,
                    'full_path' => $file,
                    'relative_path' => 'uploads/' . $dir_name . '/' . $filename,
                    'size' => filesize($file),
                    'created' => filemtime($file)
                ];
                echo "   Found video: $dir_name/$filename (" . number_format(filesize($file)) . " bytes)\n";
            }
        }
    }
}

// Look for IMG_0867.mov or similar
echo "\n3. Looking for IMG_0867.mov or similar files...\n";
$target_files = [];
foreach ($video_files as $video) {
    if (strpos($video['filename'], 'IMG_0867') !== false || 
        strpos($video['filename'], '0867') !== false) {
        $target_files[] = $video;
        echo "   ✅ Potential match: {$video['dir']}/{$video['filename']}\n";
    }
}

// If no exact match, look for videos uploaded around the same time as quote creation
if (empty($target_files) && $quote) {
    echo "\n   No exact filename match. Looking by upload time...\n";
    $quote_time = strtotime($quote['created_at']);
    
    foreach ($video_files as $video) {
        $time_diff = abs($video['created'] - $quote_time);
        if ($time_diff < 3600) { // Within 1 hour
            $target_files[] = $video;
            echo "   ✅ Time-based match: {$video['dir']}/{$video['filename']} (uploaded " . 
                 round($time_diff/60) . " minutes from quote)\n";
        }
    }
}

// Update database with correct path
if (!empty($target_files)) {
    $best_match = $target_files[0]; // Use first match
    echo "\n4. Updating database with: {$best_match['relative_path']}\n";
    
    $stmt = $pdo->prepare("UPDATE media SET file_path = ?, media_type = 'video' WHERE quote_id = 69");
    $stmt->execute([$best_match['relative_path']]);
    
    echo "   ✅ Database updated\n";
    
    // Test video processing
    echo "\n5. Testing video processing...\n";
    if (file_exists($best_match['full_path'])) {
        echo "   ✅ File exists: {$best_match['full_path']}\n";
        echo "   Size: " . number_format($best_match['size']) . " bytes\n";
        
        // Try to process with MediaPreprocessor
        require_once __DIR__ . '/server/utils/media-preprocessor.php';
        
        try {
            $preprocessor = new MediaPreprocessor();
            $result = $preprocessor->processForAI($best_match['relative_path'], 'video');
            
            echo "   ✅ Video processing successful!\n";
            if (is_array($result) && isset($result['description'])) {
                echo "   Generated description (" . strlen($result['description']) . " chars):\n";
                echo "   " . substr($result['description'], 0, 300) . "...\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Video processing failed: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🚀 READY TO RE-TRIGGER AI ANALYSIS!\n";
    echo "Run: https://carpetree.com/server/api/trigger-all-analyses.php?quote_id=69\n";
    
} else {
    echo "\n❌ No suitable video file found for Quote #69\n";
    echo "Available videos:\n";
    foreach ($video_files as $video) {
        echo "   - {$video['dir']}/{$video['filename']}\n";
    }
}

echo "\n=== SEARCH COMPLETE ===\n";
?>