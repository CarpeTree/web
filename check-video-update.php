<?php
/**
 * Quick check if video update worked and trigger re-analysis
 */

require_once __DIR__ . '/server/config/config.php';

echo "=== VIDEO UPDATE STATUS CHECK ===\n\n";

$pdo = getDatabaseConnection();

// Check updated media record
echo "1. Checking updated media record...\n";
$stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
$stmt->execute();
$media = $stmt->fetch();

if ($media) {
    echo "   ✅ Media record found\n";
    echo "   Filename: {$media['original_filename']}\n";
    echo "   Path: {$media['file_path']}\n";
    echo "   Type: {$media['media_type']}\n";
    
    // Check if file exists
    $full_path = __DIR__ . '/' . $media['file_path'];
    if (file_exists($full_path)) {
        echo "   ✅ File exists (" . number_format(filesize($full_path)) . " bytes)\n";
        
        // Test quick video processing
        echo "\n2. Testing video processing...\n";
        require_once __DIR__ . '/server/utils/media-preprocessor.php';
        
        try {
            $preprocessor = new MediaPreprocessor();
            
            // Try a simple check first
            echo "   MediaPreprocessor class loaded\n";
            
            // Test basic functionality
            if (method_exists($preprocessor, 'processForAI')) {
                echo "   processForAI method exists\n";
                
                $result = $preprocessor->processForAI($media['file_path'], 'video');
                
                if ($result) {
                    echo "   ✅ Video processing successful!\n";
                    
                    if (is_string($result)) {
                        echo "   Result is string, length: " . strlen($result) . "\n";
                    } else if (is_array($result)) {
                        echo "   Result is array with keys: " . implode(', ', array_keys($result)) . "\n";
                    }
                } else {
                    echo "   ❌ Processing returned empty result\n";
                }
            }
            
        } catch (Exception $e) {
            echo "   ❌ Processing failed: " . $e->getMessage() . "\n";
        }
        
        echo "\n3. Ready to re-trigger AI analysis!\n";
        echo "   🚀 Trigger: https://carpetree.com/server/api/trigger-all-analyses.php?quote_id=69\n";
        echo "   📊 View: https://carpetree.com/view-ai-analysis.php\n";
        
    } else {
        echo "   ❌ File not found: $full_path\n";
    }
} else {
    echo "   ❌ No media record found\n";
}

echo "\n=== STATUS CHECK COMPLETE ===\n";
?>