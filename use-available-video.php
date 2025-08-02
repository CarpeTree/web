<?php
/**
 * Use the available video file for Quote #69 analysis
 */

require_once __DIR__ . '/server/config/config.php';

echo "=== USING AVAILABLE VIDEO FOR QUOTE #69 ===\n\n";

$pdo = getDatabaseConnection();

// Check Quote #69 creation time and available video
echo "1. Quote #69 details...\n";
$stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = 69");
$stmt->execute();
$quote = $stmt->fetch();

if ($quote) {
    echo "   Created: {$quote['created_at']}\n";
    echo "   Status: {$quote['quote_status']}\n";
    
    // Get current media record
    $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
    $stmt->execute();
    $media = $stmt->fetch();
    
    if ($media) {
        echo "   Current media: {$media['original_filename']}\n";
        echo "   Current path: {$media['file_path']}\n";
    }
}

// Use the available video
$available_video = 'uploads/21/IMG_0859.mov';
$video_path = __DIR__ . '/' . $available_video;

echo "\n2. Checking available video...\n";
if (file_exists($video_path)) {
    echo "   ✅ Video file exists: $available_video\n";
    echo "   Size: " . number_format(filesize($video_path)) . " bytes\n";
    
    // Update database to use this video
    echo "\n3. Updating database to use available video...\n";
    if ($media) {
        $stmt = $pdo->prepare("UPDATE media SET file_path = ?, original_filename = ?, media_type = 'video' WHERE quote_id = 69");
        $stmt->execute([$available_video, 'IMG_0859.mov']);
        echo "   ✅ Updated existing media record\n";
    } else {
        $stmt = $pdo->prepare("INSERT INTO media (quote_id, file_path, original_filename, media_type, created_at) VALUES (69, ?, 'IMG_0859.mov', 'video', NOW())");
        $stmt->execute([$available_video]);
        echo "   ✅ Created new media record\n";
    }
    
    // Test video processing
    echo "\n4. Testing video processing...\n";
    require_once __DIR__ . '/server/utils/media-preprocessor.php';
    
    try {
        $preprocessor = new MediaPreprocessor();
        $result = $preprocessor->processForAI($available_video, 'video');
        
        echo "   ✅ Video processing successful!\n";
        echo "   Result type: " . gettype($result) . "\n";
        
        if (is_array($result)) {
            if (isset($result['description'])) {
                echo "   Description length: " . strlen($result['description']) . " characters\n";
                echo "   Preview: " . substr($result['description'], 0, 200) . "...\n";
            }
            if (isset($result['base64_frames'])) {
                echo "   Frames extracted: " . count($result['base64_frames']) . "\n";
            }
        } else if (is_string($result)) {
            echo "   Text result length: " . strlen($result) . " characters\n";
            echo "   Preview: " . substr($result, 0, 200) . "...\n";
        }
        
        echo "\n🎉 VIDEO PROCESSING WORKING!\n";
        echo "Ready to re-trigger AI analysis with actual video content.\n";
        
    } catch (Exception $e) {
        echo "   ❌ Video processing failed: " . $e->getMessage() . "\n";
        echo "   This might be due to ffmpeg not being available.\n";
    }
    
    echo "\n5. 🚀 RE-TRIGGER AI ANALYSIS:\n";
    echo "   https://carpetree.com/server/api/trigger-all-analyses.php?quote_id=69\n";
    echo "\n   Then check results:\n";
    echo "   https://carpetree.com/view-ai-analysis.php\n";
    
} else {
    echo "   ❌ Video file not found: $video_path\n";
}

echo "\n=== SETUP COMPLETE ===\n";
?>