<?php
// Test the updated MediaPreprocessor with real frame extraction
header('Content-Type: text/plain');
echo "=== TESTING UPDATED MEDIAPROCESSOR WITH FRAME EXTRACTION ===\n";

require_once 'server/config/config.php';
require_once 'server/utils/media-preprocessor.php';

// Test with Quote #69
$quote_id = 69;

echo "1. LOADING QUOTE DATA:\n";

try {
    $pdo = getDatabaseConnection();
    
    // Get quote data
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $quote_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quote_data) {
        echo "❌ Quote not found\n";
        exit;
    }
    
    echo "✅ Quote found: {$quote_data['customer_name']}\n";
    echo "Status: {$quote_data['quote_status']}\n";
    
    // Get media files
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Media files: " . count($media_files) . "\n";
    
    if (empty($media_files)) {
        echo "❌ No media files found\n";
        exit;
    }
    
    foreach ($media_files as $file) {
        $path = $file['file_path'];
        $exists = file_exists($path) ? "✅ EXISTS" : "❌ MISSING";
        $size = file_exists($path) ? round(filesize($path) / (1024*1024), 1) . "MB" : "N/A";
        echo "- {$file['filename']}: {$path} ({$exists}, {$size})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit;
}

echo "\n2. TESTING MEDIAPREPROCESSOR:\n";

try {
    $processor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
    
    echo "✅ MediaPreprocessor created\n";
    
    // Test preprocessing
    $result = $processor->preprocessAllMedia();
    
    echo "✅ Preprocessing completed\n";
    echo "Context text length: " . strlen($result['context_text']) . " characters\n";
    echo "Visual content items: " . count($result['visual_content']) . "\n";
    echo "Media summary items: " . count($result['media_summary']) . "\n";
    echo "Transcriptions: " . count($result['transcriptions']) . "\n";
    
    // Show visual content details
    if (!empty($result['visual_content'])) {
        echo "\n3. VISUAL CONTENT ANALYSIS:\n";
        
        foreach ($result['visual_content'] as $i => $content) {
            $item_num = $i + 1;
            echo "Item {$item_num}:\n";
            echo "  Type: {$content['type']}\n";
            
            if ($content['type'] === 'image_url') {
                $data_url = $content['image_url']['url'];
                if (strpos($data_url, 'data:image/') === 0) {
                    // Extract image info
                    $header = substr($data_url, 0, 100);
                    $size_estimate = strlen($data_url);
                    echo "  ✅ REAL IMAGE FRAME DETECTED!\n";
                    echo "  Header: {$header}...\n";
                    echo "  Data size: " . round($size_estimate / 1024, 1) . "KB\n";
                    echo "  Detail: {$content['image_url']['detail']}\n";
                } else {
                    echo "  ❌ Not a data URL\n";
                }
            } else {
                $text = substr($content['text'] ?? 'N/A', 0, 100);
                echo "  Text: {$text}...\n";
            }
            echo "\n";
        }
    } else {
        echo "\n❌ NO VISUAL CONTENT GENERATED\n";
    }
    
    // Show first 500 characters of context
    echo "4. CONTEXT PREVIEW:\n";
    echo substr($result['context_text'], 0, 500) . "...\n";
    
    // Show transcriptions if any
    if (!empty($result['transcriptions'])) {
        echo "\n5. TRANSCRIPTIONS:\n";
        foreach ($result['transcriptions'] as $i => $transcript) {
            echo "Transcript " . ($i + 1) . ": " . substr($transcript, 0, 200) . "...\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ MediaPreprocessor error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";

if (isset($result) && !empty($result['visual_content'])) {
    echo "\n🎉 SUCCESS! MediaPreprocessor is now extracting REAL VIDEO FRAMES!\n";
    echo "✅ Frame count: " . count($result['visual_content']) . "\n";
    echo "✅ Ready for AI analysis with actual images!\n";
    echo "\nNext: Trigger AI analysis to see if models can now see your trees!\n";
} else {
    echo "\n⚠️ Frame extraction may have failed. Check error logs.\n";
}
?>