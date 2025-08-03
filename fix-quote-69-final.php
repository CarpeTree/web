<?php
// Fix Quote #69 by linking it to the existing video file from Quote 21
header('Content-Type: text/plain');
echo "=== FIXING QUOTE #69 MEDIA LINKAGE ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "1. CURRENT STATUS:\n";
    
    // Check Quote #69 current files
    $stmt = $pdo->prepare("SELECT COUNT(*) as file_count FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $quote69_files = $stmt->fetch(PDO::FETCH_ASSOC)['file_count'];
    echo "Quote #69 files: {$quote69_files}\n";
    
    // Check Quote #21 files (where video exists)
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 21");
    $stmt->execute();
    $quote21_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Quote #21 files: " . count($quote21_files) . "\n";
    
    foreach ($quote21_files as $file) {
        $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
        $size = file_exists($file['file_path']) ? round(filesize($file['file_path']) / (1024*1024), 1) . "MB" : "N/A";
        echo "- {$file['filename']}: ({$exists}, {$size})\n";
    }
    
    echo "\n2. SOLUTION OPTIONS:\n";
    echo "We can either:\n";
    echo "A) Copy the video file record from Quote 21 to Quote 69\n";
    echo "B) Update Quote 21 to be the active quote for testing\n";
    echo "C) Create new upload record for Quote 69 pointing to existing video\n";
    
    echo "\nChoosing Option C: Create new record for Quote 69\n";
    
    if (!empty($quote21_files) && $quote69_files == 0) {
        $video_file = null;
        foreach ($quote21_files as $file) {
            if (strpos(strtolower($file['filename']), '.mov') !== false) {
                $video_file = $file;
                break;
            }
        }
        
        if ($video_file && file_exists($video_file['file_path'])) {
            echo "\n3. CREATING MEDIA RECORD FOR QUOTE #69:\n";
            
            // Create new upload record for Quote 69
            $stmt = $pdo->prepare("
                INSERT INTO uploaded_files (
                    quote_id, 
                    filename, 
                    original_filename,
                    file_path, 
                    file_size, 
                    mime_type,
                    file_hash,
                    uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                69,  // quote_id
                $video_file['filename'],
                $video_file['original_filename'] ?? $video_file['filename'],
                $video_file['file_path'],
                $video_file['file_size'],
                $video_file['mime_type'] ?? 'video/quicktime',
                $video_file['file_hash'] ?? md5_file($video_file['file_path'])
            ]);
            
            if ($result) {
                echo "✅ Media record created for Quote #69!\n";
                echo "- Filename: {$video_file['filename']}\n";
                echo "- Path: {$video_file['file_path']}\n";
                echo "- Size: " . round($video_file['file_size'] / (1024*1024), 1) . "MB\n";
            } else {
                echo "❌ Failed to create media record\n";
            }
        } else {
            echo "❌ Video file not accessible\n";
        }
        
    } else if ($quote69_files > 0) {
        echo "✅ Quote #69 already has media files\n";
    } else {
        echo "❌ No source video file found\n";
    }
    
    echo "\n4. VERIFICATION:\n";
    
    // Verify final state
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $final_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Final media files for Quote #69: " . count($final_files) . "\n";
    foreach ($final_files as $file) {
        $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
        $size = file_exists($file['file_path']) ? round(filesize($file['file_path']) / (1024*1024), 1) . "MB" : "N/A";
        echo "- {$file['filename']}: {$file['file_path']} ({$exists}, {$size})\n";
    }
    
    // Get customer info for Quote #69
    echo "\n5. CUSTOMER INFO:\n";
    $stmt = $pdo->prepare("
        SELECT q.id, q.customer_id, q.quote_status, c.name, c.email 
        FROM quotes q 
        LEFT JOIN customers c ON q.customer_id = c.id 
        WHERE q.id = 69
    ");
    $stmt->execute();
    $quote_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quote_info) {
        echo "Quote #69 customer: {$quote_info['name']} ({$quote_info['email']})\n";
        echo "Status: {$quote_info['quote_status']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIX COMPLETE ===\n";

if (isset($final_files) && !empty($final_files)) {
    echo "\n🎉 SUCCESS! Quote #69 now has media files!\n";
    echo "🔗 Test MediaPreprocessor: https://carpetree.com/test-mediaprocessor-with-frames.php\n";
    echo "🎬 Ready for ffmpeg frame extraction breakthrough!\n";
} else {
    echo "\n⚠️ Still no media files. May need different approach.\n";
}
?>