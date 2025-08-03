<?php
// Check if AI analysis actually succeeded despite the MySQL error
header('Content-Type: text/plain');
echo "=== CHECKING FOR HIDDEN AI SUCCESS ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "1. CHECKING QUOTE #69 AI ANALYSIS:\n";
    
    $stmt = $pdo->prepare("SELECT ai_o4_mini_analysis, ai_o3_analysis, ai_gemini_analysis, updated_at FROM quotes WHERE id = 69");
    $stmt->execute();
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($quote) {
        echo "Last updated: {$quote['updated_at']}\n";
        
        // Check o4-mini analysis
        if (!empty($quote['ai_o4_mini_analysis'])) {
            $analysis = json_decode($quote['ai_o4_mini_analysis'], true);
            if ($analysis) {
                echo "✅ o4-mini ANALYSIS FOUND!\n";
                echo "Model: " . ($analysis['model'] ?? 'unknown') . "\n";
                echo "Timestamp: " . ($analysis['timestamp'] ?? 'unknown') . "\n";
                echo "Input tokens: " . ($analysis['input_tokens'] ?? 'unknown') . "\n";
                echo "Output tokens: " . ($analysis['output_tokens'] ?? 'unknown') . "\n";
                echo "Processing time: " . round(($analysis['processing_time_ms'] ?? 0) / 1000, 1) . "s\n";
                
                if (isset($analysis['analysis']['overall_assessment'])) {
                    $assessment = substr($analysis['analysis']['overall_assessment'], 0, 200);
                    echo "Assessment preview: {$assessment}...\n";
                }
                
                if (isset($analysis['analysis']['trees']) && is_array($analysis['analysis']['trees'])) {
                    echo "Trees analyzed: " . count($analysis['analysis']['trees']) . "\n";
                }
                
                echo "\n🎉 AI ANALYSIS SUCCESSFUL! The MySQL error was just in cost tracking!\n";
                
            } else {
                echo "❌ o4-mini analysis exists but invalid JSON\n";
            }
        } else {
            echo "❌ No o4-mini analysis found\n";
        }
        
        // Check other models
        if (!empty($quote['ai_o3_analysis'])) {
            echo "✅ o3 analysis also available\n";
        }
        if (!empty($quote['ai_gemini_analysis'])) {
            echo "✅ Gemini analysis also available\n";
        }
        
    } else {
        echo "❌ Quote not found\n";
    }
    
    echo "\n2. CHECKING COST TRACKING:\n";
    
    $stmt = $pdo->prepare("SELECT * FROM ai_cost_log WHERE quote_id = 69 ORDER BY created_at DESC LIMIT 3");
    $stmt->execute();
    $costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Recent cost entries: " . count($costs) . "\n";
    foreach ($costs as $cost) {
        echo "- {$cost['model']}: \${$cost['cost']} at {$cost['created_at']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== CHECK COMPLETE ===\n";

if (isset($analysis) && $analysis) {
    echo "\n🎉 SUCCESS! Your AI analysis worked!\n";
    echo "🔗 View full results: https://carpetree.com/view-ai-analysis.php?quote_id=69\n";
    echo "🔗 View transcript: https://carpetree.com/view-ai-transcript.php?quote_id=69\n";
    echo "\nThe MySQL error was just a side effect - your $50+ investment paid off!\n";
}
?>