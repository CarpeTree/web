<?php
// Complete database schema check and media file fix in one script
header('Content-Type: text/plain');
echo "=== COMPLETE DATABASE FIX FOR QUOTE #69 ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "1. CHECKING DATABASE SCHEMA:\n";
    
    // Get quotes table structure
    $stmt = $pdo->prepare("SHOW COLUMNS FROM quotes");
    $stmt->execute();
    $quote_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Quotes table columns:\n";
    foreach ($quote_columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    // Get uploaded_files table structure  
    $stmt = $pdo->prepare("SHOW COLUMNS FROM uploaded_files");
    $stmt->execute();
    $file_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nUploaded_files table columns:\n";
    foreach ($file_columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n2. FINDING QUOTE #69:\n";
    
    // Try different possible column combinations
    $possible_queries = [
        "SELECT * FROM quotes WHERE id = 69",
        "SELECT id, name, email, quote_status FROM quotes WHERE id = 69", 
        "SELECT id, customer_name, customer_email, quote_status FROM quotes WHERE id = 69",
        "SELECT id, first_name, last_name, email, status FROM quotes WHERE id = 69"
    ];
    
    $quote_data = null;
    foreach ($possible_queries as $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $quote_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($quote_data) {
                echo "✅ Quote found with query: {$query}\n";
                foreach ($quote_data as $key => $value) {
                    $display_value = is_string($value) && strlen($value) > 50 ? substr($value, 0, 50) . "..." : $value;
                    echo "- {$key}: {$display_value}\n";
                }
                break;
            }
        } catch (Exception $e) {
            echo "❌ Query failed: {$query} - {$e->getMessage()}\n";
        }
    }
    
    if (!$quote_data) {
        echo "❌ Quote #69 not found with any query\n";
        exit;
    }
    
    echo "\n3. CHECKING UPLOADED FILES:\n";
    
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $existing_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current uploaded files for Quote #69: " . count($existing_files) . "\n";
    
    if (!empty($existing_files)) {
        foreach ($existing_files as $file) {
            $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
            echo "- {$file['filename']}: {$file['file_path']} ({$exists})\n";
        }
    }
    
    echo "\n4. SEARCHING FOR VIDEO FILES:\n";
    
    $video_paths = [
        'uploads/21/IMG_0859.mov',
        'uploads/69/IMG_0859.mov', 
        'uploads/quote_69/IMG_0859.mov'
    ];
    
    $found_video = null;
    foreach ($video_paths as $path) {
        if (file_exists($path)) {
            $size = filesize($path);
            $size_mb = round($size / (1024*1024), 1);
            echo "✅ Found video: {$path} ({$size_mb}MB)\n";
            $found_video = ['path' => $path, 'size' => $size];
            break;
        } else {
            echo "❌ Not found: {$path}\n";
        }
    }
    
    echo "\n5. FIXING MEDIA RECORD:\n";
    
    if ($found_video && empty($existing_files)) {
        echo "Creating missing media record...\n";
        
        $filename = basename($found_video['path']);
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
            $filename,
            $found_video['path'],
            $found_video['size'],
            'video/quicktime'
        ]);
        
        if ($result) {
            echo "✅ Media record created successfully!\n";
            echo "- Quote ID: 69\n";
            echo "- Filename: {$filename}\n";
            echo "- Path: {$found_video['path']}\n";
            echo "- Size: " . round($found_video['size'] / (1024*1024), 1) . "MB\n";
        } else {
            echo "❌ Failed to create media record\n";
        }
        
    } else if ($found_video && !empty($existing_files)) {
        echo "✅ Media record already exists\n";
    } else {
        echo "❌ No video file found to link\n";
    }
    
    echo "\n6. FINAL VERIFICATION:\n";
    
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $final_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Final media files for Quote #69: " . count($final_files) . "\n";
    foreach ($final_files as $file) {
        $exists = file_exists($file['file_path']) ? "✅ EXISTS" : "❌ MISSING";
        $size = file_exists($file['file_path']) ? round(filesize($file['file_path']) / (1024*1024), 1) . "MB" : "N/A";
        echo "- {$file['filename']}: {$file['file_path']} ({$exists}, {$size})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== COMPLETE FIX FINISHED ===\n";

if (isset($final_files) && !empty($final_files)) {
    echo "\n🎉 SUCCESS! Quote #69 media files are ready!\n";
    echo "🔗 Test MediaPreprocessor: https://carpetree.com/test-mediaprocessor-with-frames.php\n";
    echo "🎬 Ready for ffmpeg frame extraction breakthrough!\n";
} else {
    echo "\n⚠️ Media files still not available. Manual investigation needed.\n";
}
?>