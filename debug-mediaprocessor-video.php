<?php
// Debug why MediaPreprocessor isn't extracting video frames
header('Content-Type: text/plain');
echo "=== DEBUGGING MEDIAPROCESSOR VIDEO PROCESSING ===\n";

require_once 'server/config/config.php';
require_once 'server/utils/media-preprocessor.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "1. LOADING QUOTE #69 DATA:\n";
    
    // Get quote data
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = 69");
    $stmt->execute();
    $quote_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get media files  
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Quote found: " . ($quote_data ? "✅ YES" : "❌ NO") . "\n";
    echo "Media files: " . count($media_files) . "\n";
    
    foreach ($media_files as $file) {
        $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
        $size = file_exists($file['file_path']) ? round(filesize($file['file_path']) / (1024*1024), 1) . "MB" : "N/A";
        echo "- {$file['filename']}: {$file['file_path']} ({$exists}, {$size})\n";
        echo "  MIME type: {$file['mime_type']}\n";
    }
    
    echo "\n2. TESTING VIDEO FILE DIRECTLY:\n";
    
    if (!empty($media_files)) {
        $video_file = $media_files[0];
        $video_path = $video_file['file_path'];
        
        echo "Testing video: {$video_path}\n";
        
        if (file_exists($video_path)) {
            echo "✅ File exists\n";
            echo "Size: " . round(filesize($video_path) / (1024*1024), 1) . "MB\n";
            echo "MIME: {$video_file['mime_type']}\n";
            
            // Test if it's being recognized as video
            $is_video = strpos(strtolower($video_file['mime_type']), 'video') !== false ||
                       strpos(strtolower($video_file['filename']), '.mov') !== false ||
                       strpos(strtolower($video_file['filename']), '.mp4') !== false;
            
            echo "Detected as video: " . ($is_video ? "✅ YES" : "❌ NO") . "\n";
            
        } else {
            echo "❌ File missing\n";
        }
    }
    
    echo "\n3. TESTING MEDIAPROCESSOR STEP BY STEP:\n";
    
    // Create MediaPreprocessor
    $processor = new MediaPreprocessor(69, $media_files, $quote_data);
    echo "✅ MediaPreprocessor created\n";
    
    // Check what happens in preprocessing
    echo "\nCalling preprocessAllMedia()...\n";
    
    // Enable error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $result = $processor->preprocessAllMedia();
    
    echo "✅ Preprocessing completed\n";
    echo "Result keys: " . implode(', ', array_keys($result)) . "\n";
    echo "Context length: " . strlen($result['context_text']) . "\n";
    echo "Visual content count: " . count($result['visual_content']) . "\n";
    echo "Media summary count: " . count($result['media_summary']) . "\n";
    echo "Transcriptions count: " . count($result['transcriptions']) . "\n";
    
    // Show media summary details
    if (!empty($result['media_summary'])) {
        echo "\nMedia summaries:\n";
        foreach ($result['media_summary'] as $i => $summary) {
            echo "- " . ($i + 1) . ": {$summary}\n";
        }
    } else {
        echo "\n❌ No media summaries generated\n";
    }
    
    // Show visual content details
    if (!empty($result['visual_content'])) {
        echo "\nVisual content:\n";
        foreach ($result['visual_content'] as $i => $content) {
            echo "- " . ($i + 1) . ": Type={$content['type']}\n";
            if ($content['type'] === 'image_url') {
                $url = $content['image_url']['url'];
                if (strpos($url, 'data:image/') === 0) {
                    echo "  ✅ REAL IMAGE FRAME! Size: " . round(strlen($url) / 1024, 1) . "KB\n";
                } else {
                    echo "  ❌ Not a data URL\n";
                }
            }
        }
    } else {
        echo "\n❌ No visual content generated\n";
    }
    
    echo "\n4. CHECKING ERROR LOGS:\n";
    
    // Check PHP error log
    $error_log = error_get_last();
    if ($error_log) {
        echo "Last PHP error: {$error_log['message']} in {$error_log['file']} line {$error_log['line']}\n";
    } else {
        echo "No recent PHP errors\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>