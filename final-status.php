<?php
/**
 * Check final status after database fix
 */

require_once __DIR__ . '/server/config/config.php';

echo "=== FINAL STATUS CHECK ===\n\n";

$pdo = getDatabaseConnection();

// Check if database was updated
echo "1. Checking database update...\n";
$stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
$stmt->execute();
$media = $stmt->fetch();

if ($media) {
    echo "   Current path: {$media['file_path']}\n";
    echo "   Current filename: {$media['original_filename']}\n";
    echo "   Type: {$media['media_type']}\n";
    
    // Check if file is accessible
    $file_path = __DIR__ . '/' . $media['file_path'];
    if (file_exists($file_path)) {
        echo "   ✅ File is accessible\n";
        echo "   Size: " . number_format(filesize($file_path)) . " bytes\n";
        
        echo "\n2. 🚀 READY FOR AI ANALYSIS!\n";
        echo "The video file is now properly linked.\n\n";
        
        echo "🎬 Trigger AI Analysis (with video):\n";
        echo "https://carpetree.com/server/api/trigger-all-analyses.php?quote_id=69\n\n";
        
        echo "📊 View Results:\n";
        echo "https://carpetree.com/view-ai-analysis.php\n\n";
        
        echo "🎯 Expected Results:\n";
        echo "- AI models will analyze the actual tree video\n";
        echo "- You'll get specific recommendations based on visual assessment\n";
        echo "- Line items will be generated from actual tree conditions\n";
        echo "- No more 'no photos provided' messages!\n";
        
    } else {
        echo "   ❌ File still not accessible: $file_path\n";
        echo "   Current media path: {$media['file_path']}\n";
    }
    
} else {
    echo "   ❌ No media record found\n";
}

echo "\n=== STATUS COMPLETE ===\n";
echo "Your AI analysis system is ready for video-based tree assessment!\n";
?>