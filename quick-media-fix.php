<?php
// Quick fix for Quote #69 media record with correct column names
header('Content-Type: text/plain');
echo "=== QUICK MEDIA FIX FOR QUOTE #69 ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    // Check current state
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $current_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Current files for Quote #69: {$current_count}\n";
    
    if ($current_count == 0) {
        // Video file we know exists
        $video_path = 'uploads/21/IMG_0859.mov';
        
        if (file_exists($video_path)) {
            $size = filesize($video_path);
            $size_mb = round($size / (1024*1024), 1);
            echo "✅ Video found: {$video_path} ({$size_mb}MB)\n";
            
            echo "\nCreating media record with correct columns...\n";
            
            // Use correct column names from schema
            $stmt = $pdo->prepare("
                INSERT INTO uploaded_files (
                    quote_id, 
                    filename, 
                    original_filename,
                    file_path, 
                    file_size, 
                    mime_type,
                    uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                69,                        // quote_id
                'IMG_0859.mov',           // filename
                'IMG_0859.mov',           // original_filename  
                $video_path,              // file_path
                $size,                    // file_size
                'video/quicktime'         // mime_type (not file_type!)
            ]);
            
            if ($result) {
                echo "✅ SUCCESS! Media record created!\n";
                echo "- Quote ID: 69\n";
                echo "- Filename: IMG_0859.mov\n";
                echo "- Path: {$video_path}\n";
                echo "- Size: {$size_mb}MB\n";
                echo "- Type: video/quicktime\n";
            } else {
                echo "❌ Insert failed\n";
            }
            
        } else {
            echo "❌ Video file not found at {$video_path}\n";
        }
        
    } else {
        echo "✅ Files already exist\n";
    }
    
    // Final verification
    echo "\n📊 FINAL STATUS:\n";
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Quote #69 media files: " . count($files) . "\n";
    foreach ($files as $file) {
        $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
        $size = file_exists($file['file_path']) ? round(filesize($file['file_path']) / (1024*1024), 1) . "MB" : "N/A";
        echo "- {$file['filename']}: {$file['file_path']} ({$exists}, {$size})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== QUICK FIX COMPLETE ===\n";

if (isset($files) && !empty($files)) {
    echo "\n🎉 SUCCESS! Quote #69 now has media files!\n";
    echo "🔗 Test MediaPreprocessor: https://carpetree.com/test-mediaprocessor-with-frames.php\n";
    echo "🎬 Finally ready for REAL FRAME EXTRACTION!\n";
} else {
    echo "\n❌ Still no luck. Something else is wrong.\n";
}
?>