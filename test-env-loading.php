<?php
// Simple test to verify .env file loading
header('Content-Type: text/plain');

echo "=== ENVIRONMENT VARIABLE LOADING TEST ===\n\n";

// 1. Check if .env file exists
$env_file = __DIR__ . '/.env';
echo "1. .env file exists: " . (file_exists($env_file) ? 'YES' : 'NO') . "\n";

if (file_exists($env_file)) {
    $env_content = file_get_contents($env_file);
    echo "2. .env file size: " . strlen($env_content) . " bytes\n";
    
    // Show first few lines (without revealing full keys)
    $lines = explode("\n", $env_content);
    echo "3. .env file preview:\n";
    foreach (array_slice($lines, 0, 5) as $line) {
        if (strpos($line, 'API_KEY') !== false) {
            $parts = explode('=', $line);
            echo "   " . $parts[0] . "=***HIDDEN***\n";
        } else {
            echo "   " . $line . "\n";
        }
    }
}

echo "\n4. Loading config.php...\n";
require_once __DIR__ . '/server/config/config.php';

echo "5. Testing variables after config load:\n";
echo "   OPENAI_API_KEY: " . (!empty($OPENAI_API_KEY) ? 'SET (' . strlen($OPENAI_API_KEY) . ' chars)' : 'NOT SET') . "\n";
echo "   GOOGLE_GEMINI_API_KEY: " . (!empty($GOOGLE_GEMINI_API_KEY) ? 'SET (' . strlen($GOOGLE_GEMINI_API_KEY) . ' chars)' : 'NOT SET') . "\n";

echo "\n6. Testing getenv() directly:\n";
echo "   getenv('OPENAI_API_KEY'): " . (getenv('OPENAI_API_KEY') ? 'SET' : 'NOT SET') . "\n";
echo "   getenv('GOOGLE_GEMINI_API_KEY'): " . (getenv('GOOGLE_GEMINI_API_KEY') ? 'SET' : 'NOT SET') . "\n";

echo "\n=== TEST COMPLETE ===\n";
?>