<?php
// Fix missing media file record for Quote #69
header('Content-Type: text/plain');
echo "=== FIXING QUOTE #69 MEDIA RECORD ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    // Check current state
    $stmt = $pdo->prepare("SELECT COUNT(*) as file_count FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $current_count = $stmt->fetch(PDO::FETCH_ASSOC)['file_count'];
    
    echo "Current media files for Quote #69: {$current_count}\n";
    
    // Check if video file exists
    $video_path = 'uploads/21/IMG_0859.mov';
    
    if (file_exists($video_path)) {
        $size = filesize($video_path);
        $size_mb = round($size / (1024*1024), 1);
        echo "✅ Video file exists: {$video_path} ({$size_mb}MB)\n";
        
        if ($current_count == 0) {
            echo "\n🔧 CREATING MEDIA RECORD:\n";
            
            $stmt = $pdo->prepare("
                INSERT INTO uploaded_files (
                    quote_id, 
                    filename, 
                    file_path, 
                    file_size, 
                    file_type, 
                    upload_date
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                69,
                'IMG_0859.mov',
                $video_path,
                $size,
                'video/quicktime'
            ]);
            
            if ($result) {
                echo "✅ Media record created successfully!\n";
                echo "- Quote ID: 69\n";
                echo "- Filename: IMG_0859.mov\n";
                echo "- Path: {$video_path}\n";
                echo "- Size: {$size_mb}MB\n";
                echo "- Type: video/quicktime\n";
            } else {
                echo "❌ Failed to create media record\n";
            }
        } else {
            echo "✅ Media record already exists\n";
        }
        
    } else {
        echo "❌ Video file not found at {$video_path}\n";
        
        // Try to find it elsewhere
        $search_paths = [
            'uploads/69/IMG_0859.mov',
            'uploads/quote_69/IMG_0859.mov',
            '../uploads/quote_69/upload_0_1753847985_IMG_0867.mov'
        ];
        
        echo "\nSearching alternative paths:\n";
        foreach ($search_paths as $path) {
            if (file_exists($path)) {
                $size = filesize($path);
                $size_mb = round($size / (1024*1024), 1);
                echo "✅ Found at: {$path} ({$size_mb}MB)\n";
                
                if ($current_count == 0) {
                    echo "Creating record for found file...\n";
                    
                    $filename = basename($path);
                    $stmt = $pdo->prepare("
                        INSERT INTO uploaded_files (
                            quote_id, 
                            filename, 
                            file_path, 
                            file_size, 
                            file_type, 
                            upload_date
                        ) VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        69,
                        $filename,
                        $path,
                        $size,
                        'video/quicktime'
                    ]);
                    
                    echo "✅ Media record created for {$filename}\n";
                }
                break;
            } else {
                echo "❌ Not found: {$path}\n";
            }
        }
    }
    
    // Verify final state
    echo "\n📊 FINAL VERIFICATION:\n";
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Media files for Quote #69: " . count($files) . "\n";
    foreach ($files as $file) {
        $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
        $size = file_exists($file['file_path']) ? round(filesize($file['file_path']) / (1024*1024), 1) . "MB" : "N/A";
        echo "- {$file['filename']}: {$file['file_path']} ({$exists}, {$size})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIX COMPLETE ===\n";

if (isset($files) && !empty($files)) {
    echo "\n✅ SUCCESS! Quote #69 now has media files.\n";
    echo "🔗 Ready to test: https://carpetree.com/test-mediaprocessor-with-frames.php\n";
} else {
    echo "\n❌ Unable to restore media files. Manual intervention may be needed.\n";
}
?>