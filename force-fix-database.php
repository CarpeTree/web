<?php
/**
 * Force fix the database with correct video path
 */

require_once __DIR__ . '/server/config/config.php';

echo "=== FORCE DATABASE FIX ===\n\n";

$pdo = getDatabaseConnection();

// Show current state
echo "1. Current media record:\n";
$stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
$stmt->execute();
$media = $stmt->fetch();

if ($media) {
    echo "   ID: {$media['id']}\n";
    echo "   Current filename: {$media['original_filename']}\n";
    echo "   Current path: {$media['file_path']}\n";
    echo "   Current type: {$media['media_type']}\n";
}

// Force update with correct path
echo "\n2. Force updating to correct video...\n";
$correct_path = 'uploads/21/IMG_0859.mov';
$correct_filename = 'IMG_0859.mov';

// Verify the file exists first
$full_path = __DIR__ . '/' . $correct_path;
if (file_exists($full_path)) {
    echo "   ✅ Target file exists: $full_path\n";
    echo "   Size: " . number_format(filesize($full_path)) . " bytes\n";
    
    // Update database
    $update_sql = "UPDATE media SET file_path = ?, original_filename = ?, media_type = 'video' WHERE quote_id = 69";
    $stmt = $pdo->prepare($update_sql);
    $success = $stmt->execute([$correct_path, $correct_filename]);
    
    if ($success) {
        echo "   ✅ Database update successful\n";
        
        // Verify the update
        echo "\n3. Verifying update...\n";
        $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
        $stmt->execute();
        $updated_media = $stmt->fetch();
        
        if ($updated_media) {
            echo "   New filename: {$updated_media['original_filename']}\n";
            echo "   New path: {$updated_media['file_path']}\n";
            echo "   New type: {$updated_media['media_type']}\n";
            
            // Test file access
            $test_path = __DIR__ . '/' . $updated_media['file_path'];
            if (file_exists($test_path)) {
                echo "   ✅ Updated path is accessible\n";
                
                echo "\n4. Testing video processing...\n";
                require_once __DIR__ . '/server/utils/media-preprocessor.php';
                
                try {
                    $preprocessor = new MediaPreprocessor();
                    echo "   Attempting to process: {$updated_media['file_path']}\n";
                    
                    $result = $preprocessor->processForAI($updated_media['file_path'], 'video');
                    
                    if ($result) {
                        echo "   🎉 VIDEO PROCESSING SUCCESSFUL!\n";
                        
                        if (is_string($result)) {
                            echo "   Generated description (" . strlen($result) . " chars):\n";
                            echo "   " . substr($result, 0, 300) . "...\n";
                        } else if (is_array($result) && isset($result['description'])) {
                            echo "   Generated description (" . strlen($result['description']) . " chars):\n";
                            echo "   " . substr($result['description'], 0, 300) . "...\n";
                        }
                        
                        echo "\n🚀 READY FOR AI ANALYSIS!\n";
                        echo "Now the AI models will receive actual video content!\n";
                        
                    } else {
                        echo "   ❌ Processing returned empty result\n";
                    }
                    
                } catch (Exception $e) {
                    echo "   ❌ Video processing error: " . $e->getMessage() . "\n";
                    echo "   This may be due to ffmpeg not being available on Hostinger\n";
                }
                
            } else {
                echo "   ❌ Updated path still not accessible: $test_path\n";
            }
        }
        
    } else {
        echo "   ❌ Database update failed\n";
    }
    
} else {
    echo "   ❌ Target file does not exist: $full_path\n";
}

echo "\n=== FORCE FIX COMPLETE ===\n";
echo "If video processing worked, trigger AI analysis:\n";
echo "https://carpetree.com/server/api/trigger-all-analyses.php?quote_id=69\n";
?>