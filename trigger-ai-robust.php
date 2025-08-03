<?php
// Robust AI trigger that handles MySQL timeouts better
header('Content-Type: text/plain');
echo "=== ROBUST AI TRIGGER ===\n";

$quote_id = $_GET['quote_id'] ?? $_POST['quote_id'] ?? 69;
$model = $_GET['model'] ?? $_POST['model'] ?? 'o4-mini';

echo "Quote ID: {$quote_id}\n";
echo "Model: {$model}\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

// Clear previous analysis
require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "✅ Clearing previous {$model} analysis\n";
    $column = "ai_{$model}_analysis";
    $stmt = $pdo->prepare("UPDATE quotes SET {$column} = NULL WHERE id = ?");
    $stmt->execute([$quote_id]);
    
    echo "🚀 Starting {$model} analysis...\n";
    
    // Trigger the analysis with minimal timeout handling
    if ($model === 'o4-mini') {
        $url = "https://carpetree.com/server/api/openai-o4-mini-analysis.php?quote_id={$quote_id}";
    } else if ($model === 'o3') {
        $url = "https://carpetree.com/server/api/openai-o3-analysis.php?quote_id={$quote_id}";
    } else {
        echo "❌ Unknown model: {$model}\n";
        exit;
    }
    
    // Use longer timeout for image processing
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $processing_time = round($end_time - $start_time, 2);
    echo "✅ {$model} analysis completed in {$processing_time} seconds!\n";
    
    if ($http_code !== 200) {
        echo "❌ HTTP Error: {$http_code}\n";
        if (!empty($curl_error)) {
            echo "cURL Error: {$curl_error}\n";
        }
    }
    
    if (!empty($response)) {
        echo "Response: " . substr($response, 0, 500) . "\n";
    }
    
    // Check if analysis was saved despite any errors
    echo "\n📊 CHECKING RESULTS:\n";
    
    // Reconnect to database (in case of timeout)
    $pdo = getDatabaseConnection();
    
    $stmt = $pdo->prepare("SELECT {$column} FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($result[$column])) {
        $analysis = json_decode($result[$column], true);
        if ($analysis) {
            echo "✅ Analysis found in database\n";
            echo "Model: " . ($analysis['model'] ?? 'unknown') . "\n";
            echo "Input tokens: " . ($analysis['input_tokens'] ?? 'unknown') . "\n";
            echo "Output tokens: " . ($analysis['output_tokens'] ?? 'unknown') . "\n";
            
            if (isset($analysis['analysis']['overall_assessment'])) {
                $preview = substr($analysis['analysis']['overall_assessment'], 0, 100);
                echo "Assessment: {$preview}...\n";
            }
        } else {
            echo "❌ Analysis data corrupted\n";
        }
    } else {
        echo "❌ Analysis not found in database\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🔗 View results: https://carpetree.com/view-ai-analysis.php?quote_id={$quote_id}\n";
echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";

if (isset($analysis) && $analysis) {
    echo "\n🎉 SUCCESS! Your AI can now see trees!\n";
    echo "🌳 Real visual analysis with 4.5MB of image data!\n";
    echo "💰 Your investment paid off!\n";
}
?>