<?php
// Debug o3-pro analysis endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "🔍 DEBUGGING O3-PRO ANALYSIS ENDPOINT\n";
echo "====================================\n\n";

$quote_id = $_GET['quote_id'] ?? 75;

try {
    echo "1️⃣ BASIC CHECKS\n";
    echo "Quote ID: $quote_id\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Memory Limit: " . ini_get('memory_limit') . "\n";
    echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n\n";
    
    echo "2️⃣ LOADING DEPENDENCIES\n";
    
    // Check config
    if (file_exists('server/config/config.php')) {
        echo "✅ config.php exists\n";
        require_once 'server/config/config.php';
        echo "✅ config.php loaded\n";
    } else {
        echo "❌ config.php missing\n";
        exit;
    }
    
    // Check database
    if (file_exists('server/config/database-simple.php')) {
        echo "✅ database-simple.php exists\n";
        require_once 'server/config/database-simple.php';
        echo "✅ database connected\n";
    } else {
        echo "❌ database-simple.php missing\n";
        exit;
    }
    
    // Check API key
    global $OPENAI_API_KEY;
    if (!empty($OPENAI_API_KEY)) {
        echo "✅ OpenAI API key present (" . strlen($OPENAI_API_KEY) . " chars)\n";
    } else {
        echo "❌ OpenAI API key missing\n";
    }
    
    echo "\n3️⃣ CHECKING QUOTE DATA\n";
    $stmt = $pdo->prepare("SELECT id, quote_status FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();
    
    if ($quote) {
        echo "✅ Quote #$quote_id found\n";
        echo "   Status: {$quote['quote_status']}\n";
    } else {
        echo "❌ Quote #$quote_id not found\n";
        exit;
    }
    
    echo "\n4️⃣ CHECKING MEDIA FILES\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM media WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $media_count = $stmt->fetchColumn();
    echo "Media files: $media_count\n";
    
    echo "\n5️⃣ CHECKING REQUIRED CLASSES\n";
    
    // Check MediaPreprocessor
    if (file_exists('server/utils/media-preprocessor.php')) {
        echo "✅ media-preprocessor.php exists\n";
        require_once 'server/utils/media-preprocessor.php';
        if (class_exists('MediaPreprocessor')) {
            echo "✅ MediaPreprocessor class available\n";
        } else {
            echo "❌ MediaPreprocessor class not found\n";
        }
    } else {
        echo "❌ media-preprocessor.php missing\n";
    }
    
    // Check CostTracker
    if (file_exists('server/utils/cost-tracker.php')) {
        echo "✅ cost-tracker.php exists\n";
        require_once 'server/utils/cost-tracker.php';
        if (class_exists('CostTracker')) {
            echo "✅ CostTracker class available\n";
        } else {
            echo "❌ CostTracker class not found\n";
        }
    } else {
        echo "❌ cost-tracker.php missing\n";
    }
    
    echo "\n6️⃣ CHECKING SYSTEM PROMPT & SCHEMA\n";
    
    if (file_exists('ai/system_prompt.txt')) {
        echo "✅ system_prompt.txt exists\n";
        $prompt_size = filesize('ai/system_prompt.txt');
        echo "   Size: $prompt_size bytes\n";
    } else {
        echo "❌ system_prompt.txt missing\n";
    }
    
    if (file_exists('ai/schema.json')) {
        echo "✅ schema.json exists\n";
        $schema_content = file_get_contents('ai/schema.json');
        $schema = json_decode($schema_content, true);
        if ($schema) {
            echo "   Valid JSON schema\n";
        } else {
            echo "❌ Invalid JSON schema\n";
        }
    } else {
        echo "❌ schema.json missing\n";
    }
    
    echo "\n7️⃣ SIMULATING O3-PRO ANALYSIS REQUEST\n";
    echo "Testing basic OpenAI connection...\n";
    
    $test_data = [
        'model' => 'o3-pro-2025-06-10',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello, this is a test message.']
        ],
        'max_tokens' => 100
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($test_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $OPENAI_API_KEY
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n";
    if ($http_code == 200) {
        echo "✅ OpenAI API connection successful\n";
    } else {
        echo "❌ OpenAI API connection failed\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
    }
    
    echo "\n🎯 SUMMARY\n";
    echo "If all checks passed, the issue might be in the o3-pro analysis logic.\n";
    echo "Check the actual openai-o3-analysis.php file for syntax errors.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>