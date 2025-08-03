<?php
// Debug why no media files are found for Quote #69
header('Content-Type: text/plain');
echo "=== DEBUGGING MEDIA FILES FOR QUOTE #69 ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "1. CHECKING QUOTE DATA:\n";
    $stmt = $pdo->prepare("SELECT id, customer_name, quote_status FROM quotes WHERE id = 69");
    $stmt->execute();
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quote) {
        echo "✅ Quote found: ID={$quote['id']}, Name={$quote['customer_name']}, Status={$quote['quote_status']}\n";
    } else {
        echo "❌ Quote #69 not found\n";
        exit;
    }
    
    echo "\n2. CHECKING UPLOADED_FILES TABLE:\n";
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Files found: " . count($files) . "\n";
    
    if (empty($files)) {
        echo "❌ No files in uploaded_files table for quote_id=69\n";
        
        // Check if any files exist for this quote with different quote_id
        echo "\n3. SEARCHING ALL UPLOADED_FILES:\n";
        $stmt = $pdo->prepare("SELECT quote_id, filename, file_path FROM uploaded_files ORDER BY quote_id DESC LIMIT 10");
        $stmt->execute();
        $all_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Recent uploaded files:\n";
        foreach ($all_files as $file) {
            echo "- Quote {$file['quote_id']}: {$file['filename']} → {$file['file_path']}\n";
        }
        
        // Check for IMG_0859.mov specifically
        echo "\n4. SEARCHING FOR IMG_0859.MOV:\n";
        $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE filename LIKE '%IMG_0859%' OR filename LIKE '%IMG_0867%'");
        $stmt->execute();
        $video_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($video_files)) {
            echo "Found video files:\n";
            foreach ($video_files as $file) {
                $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
                echo "- Quote {$file['quote_id']}: {$file['filename']} → {$file['file_path']} ({$exists})\n";
            }
        } else {
            echo "❌ No IMG_0859 or IMG_0867 files found\n";
        }
        
    } else {
        echo "✅ Files found:\n";
        foreach ($files as $file) {
            $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
            $size = file_exists($file['file_path']) ? round(filesize($file['file_path']) / (1024*1024), 1) . "MB" : "N/A";
            echo "- {$file['filename']}: {$file['file_path']} ({$exists}, {$size})\n";
        }
    }
    
    echo "\n5. CHECKING PHYSICAL FILES:\n";
    $video_paths = [
        'uploads/21/IMG_0859.mov',
        'uploads/69/IMG_0859.mov', 
        'uploads/quote_69/IMG_0859.mov',
        '../uploads/quote_69/upload_0_1753847985_IMG_0867.mov'
    ];
    
    foreach ($video_paths as $path) {
        $exists = file_exists($path) ? "✅ EXISTS" : "❌ MISSING";
        $size = file_exists($path) ? round(filesize($path) / (1024*1024), 1) . "MB" : "N/A";
        echo "- {$path}: {$exists} ({$size})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>