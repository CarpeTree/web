<?php
// Direct debug test for analysis scripts
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== ANALYSIS SCRIPT DEBUG TEST ===\n\n";

$quote_id = $_GET['quote_id'] ?? '77';
echo "Testing Quote ID: {$quote_id}\n\n";

// Test 1: Basic PHP execution
echo "1. PHP Version: " . phpversion() . "\n";
echo "2. Current Directory: " . getcwd() . "\n";
echo "3. Script exists check:\n";
echo "   - openai-o3-analysis.php: " . (file_exists(__DIR__ . '/server/api/openai-o3-analysis.php') ? 'YES' : 'NO') . "\n";
echo "   - google-gemini-analysis.php: " . (file_exists(__DIR__ . '/server/api/google-gemini-analysis.php') ? 'YES' : 'NO') . "\n";
echo "   - MediaPreprocessor: " . (file_exists(__DIR__ . '/server/utils/media-preprocessor.php') ? 'YES' : 'NO') . "\n\n";

// Test 2: Database connection
echo "4. Database connection test:\n";
try {
    require_once __DIR__ . '/server/config/database-simple.php';
    echo "   ✅ Database connection successful\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $count = $stmt->fetchColumn();
    echo "   ✅ Quote #{$quote_id} exists: " . ($count > 0 ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n5. Environment variables:\n";
echo "   - OPENAI_API_KEY: " . (getenv('OPENAI_API_KEY') ? 'SET' : 'NOT SET') . "\n";
echo "   - GOOGLE_GEMINI_API_KEY: " . (getenv('GOOGLE_GEMINI_API_KEY') ? 'SET' : 'NOT SET') . "\n\n";

// Test 3: Try to include and run one script manually
echo "6. Manual script execution test:\n";
try {
    // Simulate the o3 script environment
    $_GET['quote_id'] = $quote_id;
    
    ob_start();
    include __DIR__ . '/server/api/openai-o3-analysis.php';
    $output = ob_get_clean();
    
    echo "   ✅ Script executed without fatal errors\n";
    echo "   Output (first 200 chars): " . substr($output, 0, 200) . "...\n";
} catch (Throwable $e) {
    echo "   ❌ Script execution failed: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
}

echo "\n=== DEBUG TEST COMPLETE ===\n";
?>