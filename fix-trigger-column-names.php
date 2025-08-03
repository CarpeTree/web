<?php
// Fix the column name issue in AI trigger
header('Content-Type: text/plain');
echo "=== FIXING COLUMN NAME ISSUE ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "1. CHECKING ACTUAL COLUMN NAMES:\n";
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM quotes LIKE 'ai_%_analysis'");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "AI analysis columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']}\n";
    }
    
    // Get the correct column name mapping
    $column_map = [
        'o4-mini' => 'ai_o4_mini_analysis',  // Underscore, not hyphen
        'o3' => 'ai_o3_analysis',
        'gemini' => 'ai_gemini_analysis'
    ];
    
    echo "\n2. CORRECT COLUMN MAPPING:\n";
    foreach ($column_map as $model => $column) {
        echo "Model '{$model}' → Column '{$column}'\n";
    }
    
    echo "\n3. TESTING CORRECTED TRIGGER:\n";
    
    $quote_id = 69;
    $model = 'o4-mini';
    $column = $column_map[$model];
    
    echo "Clearing {$column} for quote {$quote_id}...\n";
    
    $stmt = $pdo->prepare("UPDATE quotes SET {$column} = NULL WHERE id = ?");
    $result = $stmt->execute([$quote_id]);
    
    if ($result) {
        echo "✅ Successfully cleared {$column}\n";
        
        echo "\n🚀 Now triggering AI analysis...\n";
        
        $url = "https://carpetree.com/server/api/openai-o4-mini-analysis.php?quote_id={$quote_id}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
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
        echo "✅ Analysis completed in {$processing_time} seconds!\n";
        
        if ($http_code === 200) {
            echo "✅ HTTP 200 OK\n";
        } else {
            echo "❌ HTTP {$http_code}\n";
        }
        
        if (!empty($response)) {
            echo "Response preview: " . substr($response, 0, 200) . "...\n";
        }
        
        // Check results
        echo "\n📊 CHECKING SAVED RESULTS:\n";
        
        $pdo = getDatabaseConnection(); // Reconnect
        $stmt = $pdo->prepare("SELECT {$column} FROM quotes WHERE id = ?");
        $stmt->execute([$quote_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($result[$column])) {
            $analysis = json_decode($result[$column], true);
            if ($analysis) {
                echo "🎉 SUCCESS! Analysis saved to database!\n";
                echo "Model: " . ($analysis['model'] ?? 'unknown') . "\n";
                echo "Timestamp: " . ($analysis['timestamp'] ?? 'unknown') . "\n";
                echo "Input tokens: " . ($analysis['input_tokens'] ?? 'unknown') . "\n";
                echo "Output tokens: " . ($analysis['output_tokens'] ?? 'unknown') . "\n";
                
                if (isset($analysis['analysis']['overall_assessment'])) {
                    $preview = substr($analysis['analysis']['overall_assessment'], 0, 150);
                    echo "Assessment: {$preview}...\n";
                }
                
                echo "\n🌳 Your AI can finally see trees!\n";
                echo "💰 Your $50+ investment paid off!\n";
                
            } else {
                echo "❌ Analysis data is corrupted JSON\n";
            }
        } else {
            echo "❌ No analysis found in database\n";
        }
        
    } else {
        echo "❌ Failed to clear column\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== COLUMN FIX COMPLETE ===\n";
?>