<?php
/**
 * View the complete AI analysis outputs for Quote #69
 */

require_once __DIR__ . '/server/config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>AI Analysis Results - Quote #69</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .model-section { border: 1px solid #ddd; margin: 20px 0; padding: 20px; border-radius: 8px; }
        .model-title { font-size: 24px; font-weight: bold; margin-bottom: 15px; }
        .analysis-content { background: #f9f9f9; padding: 15px; border-radius: 4px; white-space: pre-wrap; }
        .json-content { background: #f0f0f0; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; }
        .completed { border-left: 5px solid #4CAF50; }
        .failed { border-left: 5px solid #f44336; }
    </style>
</head>
<body>";

echo "<h1>🤖 Complete AI Analysis Results - Quote #69</h1>";

$pdo = getDatabaseConnection();
if (!$pdo) {
    echo "<p>❌ Database connection failed</p></body></html>";
    exit;
}

$stmt = $pdo->prepare("SELECT ai_o4_mini_analysis, ai_o3_analysis, ai_gemini_analysis, quote_status FROM quotes WHERE id = 69");
$stmt->execute();
$quote = $stmt->fetch();

if (!$quote) {
    echo "<p>❌ Quote #69 not found</p></body></html>";
    exit;
}

echo "<p><strong>Quote Status:</strong> {$quote['quote_status']}</p>";

// o4-mini Analysis
echo "<div class='model-section " . ($quote['ai_o4_mini_analysis'] ? 'completed' : 'failed') . "'>";
echo "<div class='model-title'>📱 OpenAI o4-mini Analysis</div>";
if ($quote['ai_o4_mini_analysis']) {
    $o4_data = json_decode($quote['ai_o4_mini_analysis'], true);
    if ($o4_data && isset($o4_data['analysis'])) {
        echo "<div class='analysis-content'>";
        echo "<strong>Overall Assessment:</strong>\n" . ($o4_data['analysis']['overall_assessment'] ?? 'Not available') . "\n\n";
        
        if (isset($o4_data['analysis']['recommendations'])) {
            echo "<strong>Recommendations:</strong>\n";
            foreach ($o4_data['analysis']['recommendations'] as $rec) {
                echo "• " . $rec . "\n";
            }
            echo "\n";
        }
        
        if (isset($o4_data['analysis']['line_items'])) {
            echo "<strong>Line Items:</strong>\n";
            foreach ($o4_data['analysis']['line_items'] as $item) {
                echo "• {$item['service']}: {$item['description']} - {$item['price_range']}\n";
            }
        }
        echo "</div>";
        
        echo "<details><summary>View Raw JSON</summary>";
        echo "<div class='json-content'>" . json_encode($o4_data, JSON_PRETTY_PRINT) . "</div>";
        echo "</details>";
    } else {
        echo "<div class='analysis-content'>Invalid JSON data</div>";
    }
} else {
    echo "<div class='analysis-content'>❌ No analysis available</div>";
}
echo "</div>";

// o3 Analysis
echo "<div class='model-section " . ($quote['ai_o3_analysis'] ? 'completed' : 'failed') . "'>";
echo "<div class='model-title'>🧠 OpenAI o3 Analysis</div>";
if ($quote['ai_o3_analysis']) {
    $o3_data = json_decode($quote['ai_o3_analysis'], true);
    if ($o3_data && isset($o3_data['analysis'])) {
        echo "<div class='analysis-content'>";
        echo "<strong>Overall Assessment:</strong>\n" . ($o3_data['analysis']['overall_assessment'] ?? 'Not available') . "\n\n";
        
        if (isset($o3_data['analysis']['recommendations'])) {
            echo "<strong>Recommendations:</strong>\n";
            foreach ($o3_data['analysis']['recommendations'] as $rec) {
                echo "• " . $rec . "\n";
            }
            echo "\n";
        }
        
        if (isset($o3_data['analysis']['line_items'])) {
            echo "<strong>Line Items:</strong>\n";
            foreach ($o3_data['analysis']['line_items'] as $item) {
                echo "• {$item['service']}: {$item['description']} - {$item['price_range']}\n";
            }
        }
        echo "</div>";
        
        echo "<details><summary>View Raw JSON</summary>";
        echo "<div class='json-content'>" . json_encode($o3_data, JSON_PRETTY_PRINT) . "</div>";
        echo "</details>";
    } else {
        echo "<div class='analysis-content'>Invalid JSON data</div>";
    }
} else {
    echo "<div class='analysis-content'>❌ No analysis available</div>";
}
echo "</div>";

// Gemini Analysis
echo "<div class='model-section " . ($quote['ai_gemini_analysis'] ? 'completed' : 'failed') . "'>";
echo "<div class='model-title'>💎 Google Gemini Analysis</div>";
if ($quote['ai_gemini_analysis']) {
    $gemini_data = json_decode($quote['ai_gemini_analysis'], true);
    if ($gemini_data && isset($gemini_data['analysis'])) {
        echo "<div class='analysis-content'>";
        echo "<strong>Overall Assessment:</strong>\n" . ($gemini_data['analysis']['overall_assessment'] ?? 'Not available') . "\n\n";
        
        if (isset($gemini_data['analysis']['recommendations'])) {
            echo "<strong>Recommendations:</strong>\n";
            foreach ($gemini_data['analysis']['recommendations'] as $rec) {
                echo "• " . $rec . "\n";
            }
        }
        echo "</div>";
        
        echo "<details><summary>View Raw JSON</summary>";
        echo "<div class='json-content'>" . json_encode($gemini_data, JSON_PRETTY_PRINT) . "</div>";
        echo "</details>";
    } else {
        echo "<div class='analysis-content'>Invalid JSON data</div>";
    }
} else {
    echo "<div class='analysis-content'>❌ Failed - API error (HTTP 400)</div>";
}
echo "</div>";

echo "</body></html>";
?>