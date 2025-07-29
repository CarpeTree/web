<?php
require_once 'server/config/database-simple.php';

echo "🔧 Updating database schema for video/EXIF support...\n";

try {
    // 1. Ensure media_locations table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS media_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            media_id INT NOT NULL,
            quote_id INT NOT NULL,
            exif_latitude DECIMAL(10,8) DEFAULT NULL,
            exif_longitude DECIMAL(11,8) DEFAULT NULL,
            exif_altitude DECIMAL(8,2) DEFAULT NULL,
            exif_timestamp DATETIME DEFAULT NULL,
            camera_make VARCHAR(100) DEFAULT NULL,
            camera_model VARCHAR(100) DEFAULT NULL,
            extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
            FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
        )
    ");
    echo "✅ Created media_locations table\n";
    
    // 2. Add missing columns to media table
    $columns_to_add = [
        'file_type' => 'VARCHAR(20) DEFAULT NULL',
        'exif_data' => 'TEXT DEFAULT NULL'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE media ADD COLUMN $column $definition");
            echo "✅ Added column: media.$column\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠️ Column already exists: media.$column\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n🎉 Database schema updated successfully!\n";
    echo "📹 Videos will now be properly detected and stored\n";
    echo "📷 EXIF location data will be extracted from photos\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 