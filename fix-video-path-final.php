<?php
// Fix the video file path in database for Quote #69

require_once 'server/config/config.php';

echo "=== FIXING VIDEO PATH FOR QUOTE #69 ===\n";

$pdo = getDatabaseConnection();
if (!$pdo) {
    die("Database connection failed");
}

// First, let's see what we have
echo "Current media record:\n";
$stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
$stmt->execute();
$current = $stmt->fetch();

if ($current) {
    echo "ID: {$current['id']}\n";
    echo "Current path: {$current['file_path']}\n";
    echo "Current filename: {$current['filename']}\n";
} else {
    echo "No media record found for Quote #69\n";
}

// Check if the correct video exists
$correct_path = "uploads/21/IMG_0859.mov";
$full_path = __DIR__ . "/" . $correct_path;
$exists = file_exists($full_path);
$size = $exists ? round(filesize($full_path) / (1024*1024), 1) : 0;

echo "\nCorrect video file check:\n";
echo "Path: {$correct_path}\n";
echo "Full path: {$full_path}\n";
echo "Exists: " . ($exists ? "✅ YES" : "❌ NO") . "\n";
echo "Size: {$size}MB\n";

if ($exists && $current) {
    echo "\n=== UPDATING DATABASE ===\n";
    
    try {
        // Use a direct UPDATE with explicit transaction
        $pdo->beginTransaction();
        
        $update_sql = "UPDATE media SET file_path = ?, filename = ? WHERE id = ?";
        $stmt = $pdo->prepare($update_sql);
        $result = $stmt->execute([$correct_path, "IMG_0859.mov", $current['id']]);
        
        if ($result) {
            $affected = $stmt->rowCount();
            echo "✅ Update executed, affected rows: {$affected}\n";
            
            // Verify the update
            $verify_stmt = $pdo->prepare("SELECT file_path, filename FROM media WHERE id = ?");
            $verify_stmt->execute([$current['id']]);
            $updated = $verify_stmt->fetch();
            
            if ($updated && $updated['file_path'] === $correct_path) {
                $pdo->commit();
                echo "✅ DATABASE UPDATED SUCCESSFULLY!\n";
                echo "New path: {$updated['file_path']}\n";
                echo "New filename: {$updated['filename']}\n";
            } else {
                $pdo->rollback();
                echo "❌ Verification failed - rollback\n";
                echo "Expected: {$correct_path}\n";
                echo "Got: " . ($updated['file_path'] ?? 'NULL') . "\n";
            }
        } else {
            $pdo->rollback();
            echo "❌ Update failed\n";
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    if (!$exists) {
        echo "❌ Cannot update - video file doesn't exist at correct path\n";
    }
    if (!$current) {
        echo "❌ Cannot update - no media record found\n";
    }
}

echo "\n=== FINAL STATUS ===\n";
$final_stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = 69");
$final_stmt->execute();
$final = $final_stmt->fetch();

if ($final) {
    $final_full_path = __DIR__ . "/" . $final['file_path'];
    $final_exists = file_exists($final_full_path);
    echo "Final path: {$final['file_path']}\n";
    echo "Final filename: {$final['filename']}\n";
    echo "File exists: " . ($final_exists ? "✅ YES" : "❌ NO") . "\n";
    
    if ($final_exists) {
        echo "🎉 READY FOR AI ANALYSIS!\n";
    }
}
?>