<?php
// Ensures legacy code that references uploaded_files continues to work by creating a view that points to media
header('Content-Type: text/plain');
require_once __DIR__ . '/../config/database-simple.php';

echo "🔧 Creating compatibility view 'uploaded_files' → 'media'...\n";

try {
    // If real uploaded_files table exists, do nothing
    $exists = $pdo->query("SHOW TABLES LIKE 'uploaded_files'")->rowCount() > 0;
    if ($exists) {
        echo "✅ Physical table 'uploaded_files' already exists – skipping view creation.\n";
        exit;
    }

    // Drop existing view if present
    $pdo->exec("DROP VIEW IF EXISTS uploaded_files");

    // Create view exposing compatible columns
    $pdo->exec("CREATE VIEW uploaded_files AS
        SELECT 
            id,
            quote_id,
            filename,
            original_filename,
            file_path,
            file_size,
            mime_type,
            uploaded_at,
            NULL AS file_hash,
            exif_data
        FROM media");

    echo "✅ View 'uploaded_files' created – legacy queries now map to 'media'.\n";

} catch (Exception $e) {
    echo "❌ Failed to create view: " . $e->getMessage() . "\n";
}
?> 