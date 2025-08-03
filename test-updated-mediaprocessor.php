<?php
// Test the updated MediaPreprocessor without ffmpeg
header('Content-Type: text/plain');
echo "=== TESTING UPDATED MEDIAPROCESSOR ===\n";

require_once 'server/config/config.php';
require_once 'server/utils/media-preprocessor.php';

$quote_id = 69;

try {
    // Get quote data
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $quote_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get media files
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Quote ID: {$quote_id}\n";
    echo "Media files: " . count($media_files) . "\n\n";
    
    if (count($media_files) == 0) {
        echo "❌ No media files found\n";
        exit;
    }
    
    // Test the updated MediaPreprocessor
    echo "=== TESTING UPDATED MEDIAPROCESSOR ===\n";
    
    $processor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
    $context = $processor->preprocessAllMedia();
    
    echo "✅ MediaPreprocessor executed successfully!\n";
    echo "Context text length: " . strlen($context['context_text']) . " chars\n";
    echo "Visual content items: " . count($context['visual_content']) . "\n";
    echo "Media summary items: " . count($context['media_summary']) . "\n";
    echo "Transcriptions: " . count($context['transcriptions']) . "\n\n";
    
    if (count($context['visual_content']) > 0) {
        echo "🎉 SUCCESS! VISUAL CONTENT GENERATED!\n\n";
        
        foreach ($context['visual_content'] as $i => $item) {
            echo "=== VISUAL ITEM #{$i} ===\n";
            echo "Type: " . $item['type'] . "\n";
            
            if ($item['type'] === 'text') {
                echo "Text content preview:\n";
                echo substr($item['text'], 0, 300) . "...\n";
            } else if ($item['type'] === 'image_url') {
                echo "Image URL length: " . strlen($item['image_url']['url']) . " chars\n";
                echo "Image detail: " . $item['image_url']['detail'] . "\n";
                
                // Check if it's base64 encoded image
                if (strpos($item['image_url']['url'], 'data:image') === 0) {
                    $base64_part = explode(',', $item['image_url']['url'])[1] ?? '';
                    $decoded_size = strlen(base64_decode($base64_part));
                    echo "Decoded image size: " . round($decoded_size / 1024, 1) . "KB\n";
                }
            }
            echo "\n";
        }
        
        if (!empty($context['media_summary'])) {
            echo "=== MEDIA SUMMARY ===\n";
            foreach ($context['media_summary'] as $summary) {
                echo "- {$summary}\n";
            }
            echo "\n";
        }
        
        echo "=== CONTEXT PREVIEW ===\n";
        echo substr($context['context_text'], 0, 500) . "...\n\n";
        
        echo "🚀 READY FOR AI ANALYSIS!\n";
        echo "The MediaPreprocessor now generates visual content for your 51.7MB video!\n";
        
    } else {
        echo "❌ Still no visual content generated\n";
        echo "Debugging further...\n\n";
        
        // Check what went wrong
        foreach ($media_files as $file) {
            echo "File: {$file['filename']}\n";
            echo "MIME: {$file['mime_type']}\n";
            echo "Path: {$file['file_path']}\n";
            echo "Exists: " . (file_exists($file['file_path']) ? "Yes" : "No") . "\n";
            
            if (file_exists($file['file_path'])) {
                echo "Size: " . round(filesize($file['file_path']) / 1024 / 1024, 1) . "MB\n";
            }
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>