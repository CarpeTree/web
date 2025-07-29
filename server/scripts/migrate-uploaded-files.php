<?php
// One-time migration: copy rows from uploaded_files → media if missing
// Run: php server/scripts/migrate-uploaded-files.php
header('Content-Type: text/plain');

require_once __DIR__ . '/../config/database-simple.php';

try {
    // Check if uploaded_files table exists
    $check = $pdo->query("SHOW TABLES LIKE 'uploaded_files'")->rowCount();
    if ($check === 0) {
        echo "❌ uploaded_files table not found – nothing to migrate.\n";
        exit;
    }

    // Ensure media table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quote_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_type VARCHAR(20) DEFAULT NULL,
        exif_data JSON DEFAULT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_quote_id (quote_id),
        FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");

    // Count already-migrated rows
    $stmt = $pdo->query("SELECT COUNT(*) FROM media");
    $before = (int)$stmt->fetchColumn();

    // Insert rows that are not yet in media (match by quote & filename)
    $sql = "INSERT INTO media (quote_id, filename, original_filename, file_path, file_size, mime_type, file_type, exif_data, uploaded_at)
            SELECT uf.quote_id, uf.filename, uf.original_filename, uf.file_path, uf.file_size, uf.mime_type,
                   CASE WHEN uf.mime_type LIKE 'image/%' THEN 'image'
                        WHEN uf.mime_type LIKE 'video/%' THEN 'video'
                        WHEN uf.mime_type LIKE 'audio/%' THEN 'audio' ELSE 'other' END AS file_type,
                   uf.exif_data,
                   uf.uploaded_at
            FROM uploaded_files uf
            LEFT JOIN media m ON m.quote_id = uf.quote_id AND m.filename = uf.filename
            WHERE m.id IS NULL";

    $inserted = $pdo->exec($sql);

    $after = (int)$pdo->query("SELECT COUNT(*) FROM media")->fetchColumn();

    echo "✅ Migration complete. New rows inserted: $inserted\n";
    echo "Total rows in media table: $after (previously $before)\n";

} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
?> 