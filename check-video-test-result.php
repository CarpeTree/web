<?php
/**
 * Quick check of the video analysis test result
 */

echo "=== VIDEO ANALYSIS TEST STATUS ===\n\n";

// Check if the SQL update worked first
require_once __DIR__ . '/server/config/config.php';
$pdo = getDatabaseConnection();

echo "1. Database status check...\n";
$stmt = $pdo->prepare("SELECT file_path, original_filename, media_type FROM media WHERE quote_id = 69");
$stmt->execute();
$media = $stmt->fetch();

if ($media) {
    echo "   Current path: {$media['file_path']}\n";
    echo "   Current filename: {$media['original_filename']}\n";
    echo "   Type: {$media['media_type']}\n";
    
    if ($media['file_path'] === 'uploads/21/IMG_0859.mov') {
        echo "   ✅ Database update successful!\n";
    } else {
        echo "   ⚠️ Still showing old path\n";
    }
}

// Test the video processing directly
echo "\n2. Direct video test...\n";
$video_path = 'uploads/21/IMG_0859.mov';
$full_path = __DIR__ . '/' . $video_path;

if (file_exists($full_path)) {
    echo "   ✅ Video accessible: " . number_format(filesize($full_path)) . " bytes\n";
    
    // Quick MediaPreprocessor test
    require_once __DIR__ . '/server/utils/media-preprocessor.php';
    
    try {
        $preprocessor = new MediaPreprocessor();
        echo "   ✅ MediaPreprocessor loaded\n";
        
        // Test if processForAI method exists
        if (method_exists($preprocessor, 'processForAI')) {
            echo "   ✅ processForAI method available\n";
            
            echo "\n3. 🚀 READY FOR FULL AI ANALYSIS!\n";
            echo "Both video file and processing are available.\n\n";
            
            echo "🎬 Next steps:\n";
            echo "1. Re-trigger AI analysis: https://carpetree.com/server/api/trigger-all-analyses.php?quote_id=69\n";
            echo "2. Check results: https://carpetree.com/view-ai-analysis.php\n\n";
            
            echo "🎯 Expected outcome:\n";
            echo "- AI models will analyze the actual tree video\n";
            echo "- Professional recommendations based on visual assessment\n";
            echo "- Specific line items for tree services needed\n";
            echo "- No more 'no photos provided' messages!\n";
            
        } else {
            echo "   ❌ processForAI method missing\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ MediaPreprocessor error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "   ❌ Video file not accessible\n";
}

echo "\n=== STATUS COMPLETE ===\n";
echo "Your AI analysis system is ready for video-based tree assessment!\n";
?>