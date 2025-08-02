<?php
/**
 * Direct SQL fix - no error handling, just force the update
 */

require_once __DIR__ . '/server/config/config.php';

echo "=== DIRECT SQL FIX ===\n\n";

$pdo = getDatabaseConnection();

echo "1. Current state:\n";
$stmt = $pdo->query("SELECT id, quote_id, file_path, original_filename FROM media WHERE quote_id = 69");
$current = $stmt->fetch();
print_r($current);

echo "\n2. Executing direct UPDATE...\n";

// Direct SQL update
$sql = "UPDATE media SET 
        file_path = 'uploads/21/IMG_0859.mov', 
        original_filename = 'IMG_0859.mov',
        media_type = 'video'
        WHERE quote_id = 69";

echo "SQL: $sql\n";

$result = $pdo->exec($sql);
echo "Rows affected: $result\n";

echo "\n3. Verifying update:\n";
$stmt = $pdo->query("SELECT id, quote_id, file_path, original_filename, media_type FROM media WHERE quote_id = 69");
$updated = $stmt->fetch();
print_r($updated);

echo "\n4. File check:\n";
$file_path = __DIR__ . '/' . $updated['file_path'];
echo "Full path: $file_path\n";
echo "Exists: " . (file_exists($file_path) ? "YES" : "NO") . "\n";
if (file_exists($file_path)) {
    echo "Size: " . number_format(filesize($file_path)) . " bytes\n";
}

echo "\n5. Testing MediaPreprocessor:\n";
require_once __DIR__ . '/server/utils/media-preprocessor.php';

try {
    $preprocessor = new MediaPreprocessor();
    echo "Class loaded\n";
    
    // Test with the updated path
    $result = $preprocessor->processForAI($updated['file_path'], 'video');
    
    if ($result) {
        echo "✅ Processing successful!\n";
        if (is_string($result)) {
            echo "Result preview: " . substr($result, 0, 200) . "...\n";
        }
    } else {
        echo "❌ No result returned\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== IF SUCCESSFUL, TRIGGER AI: ===\n";
echo "https://carpetree.com/server/api/trigger-all-analyses.php?quote_id=69\n";
?>