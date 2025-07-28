<?php
// Simple config test
echo "=== CONFIG TEST ===\n";

$config_path = __DIR__ . '/config/config.php';
echo "Looking for config at: " . $config_path . "\n";

if (file_exists($config_path)) {
    echo "✅ config.php exists\n";
    echo "File size: " . filesize($config_path) . " bytes\n";
    echo "Readable: " . (is_readable($config_path) ? 'Yes' : 'No') . "\n";
    
    // Try to include it
    try {
        require_once $config_path;
        echo "✅ config.php loaded successfully\n";
        
        echo "Variables found:\n";
        echo "  OPENAI_API_KEY: " . (isset($OPENAI_API_KEY) ? 'SET' : 'NOT SET') . "\n";
        echo "  DB_HOST: " . (isset($DB_HOST) ? $DB_HOST : 'NOT SET') . "\n";
        echo "  DB_NAME: " . (isset($DB_NAME) ? $DB_NAME : 'NOT SET') . "\n";
        echo "  DB_USER: " . (isset($DB_USER) ? $DB_USER : 'NOT SET') . "\n";
        echo "  DB_PASS: " . (isset($DB_PASS) ? '[HIDDEN]' : 'NOT SET') . "\n";
        
    } catch (Exception $e) {
        echo "❌ Error loading config: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ config.php NOT FOUND\n";
    echo "Checking config directory contents:\n";
    
    $config_dir = __DIR__ . '/config/';
    if (is_dir($config_dir)) {
        $files = scandir($config_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                echo "  - " . $file . "\n";
            }
        }
    } else {
        echo "❌ Config directory doesn't exist\n";
    }
}
?> 