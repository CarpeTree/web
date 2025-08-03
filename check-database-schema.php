<?php
// Check actual database schema to find correct column names
header('Content-Type: text/plain');
echo "=== CHECKING DATABASE SCHEMA ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "1. QUOTES TABLE SCHEMA:\n";
    $stmt = $pdo->prepare("DESCRIBE quotes");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Quotes table columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n2. UPLOADED_FILES TABLE SCHEMA:\n";
    $stmt = $pdo->prepare("DESCRIBE uploaded_files");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Uploaded_files table columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n3. SAMPLE QUOTE DATA:\n";
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = 69 LIMIT 1");
    $stmt->execute();
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quote) {
        echo "Quote #69 data:\n";
        foreach ($quote as $key => $value) {
            $display_value = strlen($value) > 50 ? substr($value, 0, 50) . "..." : $value;
            echo "- {$key}: {$display_value}\n";
        }
    } else {
        echo "❌ Quote #69 not found\n";
    }
    
    echo "\n4. UPLOADED FILES FOR QUOTE 69:\n";
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Files found: " . count($files) . "\n";
    foreach ($files as $file) {
        echo "- {$file['filename']}: {$file['file_path']}\n";
    }
    
    echo "\n5. ALL RECENT UPLOADED FILES:\n";
    $stmt = $pdo->prepare("SELECT quote_id, filename, file_path FROM uploaded_files ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $recent_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recent_files as $file) {
        echo "- Quote {$file['quote_id']}: {$file['filename']} → {$file['file_path']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SCHEMA CHECK COMPLETE ===\n";
?>