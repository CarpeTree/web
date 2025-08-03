<?php
// Simple debug script for video processing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SIMPLE VIDEO DEBUG ===\n";

try {
    // Test basic PHP
    echo "✅ PHP working\n";
    
    // Test file includes
    if (file_exists('server/config/config.php')) {
        echo "✅ Config file exists\n";
        require_once 'server/config/config.php';
        echo "✅ Config loaded\n";
    } else {
        echo "❌ Config file missing\n";
        exit;
    }
    
    // Test database
    $pdo = getDatabaseConnection();
    if ($pdo) {
        echo "✅ Database connected\n";
    } else {
        echo "❌ Database failed\n";
        exit;
    }
    
    // Test MediaPreprocessor class
    if (file_exists('server/utils/media-preprocessor.php')) {
        echo "✅ MediaPreprocessor file exists\n";
        require_once 'server/utils/media-preprocessor.php';
        echo "✅ MediaPreprocessor loaded\n";
    } else {
        echo "❌ MediaPreprocessor file missing\n";
        exit;
    }
    
    // Test Quote #69 data
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quotes WHERE id = 69");
    $stmt->execute();
    $quote_count = $stmt->fetch()['count'];
    echo "Quote #69 exists: " . ($quote_count > 0 ? "✅ YES" : "❌ NO") . "\n";
    
    // Test media data
    $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
    $stmt->execute();
    $media = $stmt->fetch();
    
    if ($media) {
        echo "✅ Media record found\n";
        echo "Path: {$media['file_path']}\n";
        echo "Filename: {$media['filename']}\n";
        
        $full_path = __DIR__ . "/" . $media['file_path'];
        if (file_exists($full_path)) {
            $size = round(filesize($full_path) / (1024*1024), 1);
            echo "✅ Video file exists ({$size}MB)\n";
        } else {
            echo "❌ Video file missing: {$full_path}\n";
        }
    } else {
        echo "❌ No media record found\n";
    }
    
    echo "\n=== TEST COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>