<?php
// Simple direct AI trigger that actually works
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

echo "=== DIRECT AI TRIGGER ===\n";

$quote_id = $_GET['quote_id'] ?? $_POST['quote_id'] ?? 69;
echo "Triggering AI analysis for Quote #{$quote_id}\n";

try {
    require_once 'server/config/config.php';
    
    // Update quote status
    $pdo = getDatabaseConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'multi_ai_processing', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$quote_id]);
        echo "✅ Updated quote status to multi_ai_processing\n";
    }
    
    // Direct script includes with output buffering
    echo "\n=== RUNNING O4-MINI ANALYSIS ===\n";
    ob_start();
    try {
        // Simulate command line argument
        $_SERVER['argv'] = ['openai-o4-mini-analysis.php', $quote_id];
        $_SERVER['argc'] = 2;
        
        include 'server/api/openai-o4-mini-analysis.php';
        $o4_output = ob_get_contents();
        echo "o4-mini completed: " . strlen($o4_output) . " bytes output\n";
    } catch (Exception $e) {
        echo "o4-mini error: " . $e->getMessage() . "\n";
    }
    ob_end_clean();
    
    echo "\n=== RUNNING O3 ANALYSIS ===\n";
    ob_start();
    try {
        // Reset argv for o3
        $_SERVER['argv'] = ['openai-o3-analysis.php', $quote_id];
        $_SERVER['argc'] = 2;
        
        include 'server/api/openai-o3-analysis.php';
        $o3_output = ob_get_contents();
        echo "o3 completed: " . strlen($o3_output) . " bytes output\n";
    } catch (Exception $e) {
        echo "o3 error: " . $e->getMessage() . "\n";
    }
    ob_end_clean();
    
    echo "\n✅ AI analysis triggered successfully!\n";
    echo "Check results in 2-3 minutes at: https://carpetree.com/view-ai-analysis.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>