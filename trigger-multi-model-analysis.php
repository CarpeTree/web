<?php
// Trigger all 3 AI models in parallel for comparison
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== MULTI-MODEL AI ANALYSIS TRIGGER ===\n\n";

$quote_id = $_GET['quote_id'] ?? null;

if (!$quote_id) {
    echo "Usage: trigger-multi-model-analysis.php?quote_id=123\n";
    exit;
}

try {
    // Get quote information
    $stmt = $pdo->prepare("
        SELECT q.*, c.name, c.email 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();
    
    if (!$quote) {
        echo "❌ Quote ID $quote_id not found\n";
        exit;
    }
    
    echo "🎯 Quote ID: $quote_id\n";
    echo "👤 Customer: {$quote['name']} ({$quote['email']})\n";
    echo "📋 Services: " . implode(', ', json_decode($quote['selected_services'], true) ?: []) . "\n\n";
    
    // Check for media files
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM media WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $media_count = $stmt->fetchColumn();
    
    if ($media_count == 0) {
        echo "❌ No media files found for quote $quote_id\n";
        exit;
    }
    
    echo "📁 Media files: $media_count\n\n";
    
    // Reset quote status for fresh analysis
    $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'multi_ai_processing' WHERE id = ?");
    $stmt->execute([$quote_id]);
    
    echo "🚀 Starting parallel AI analysis...\n\n";
    
    $start_time = microtime(true);
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $analysis_urls = [
        'o4-mini-2025-04-16' => "$base_url/server/api/openai-o4-mini-analysis.php?quote_id=$quote_id",
        'openai/o3-pro' => "$base_url/server/api/openai-o3-analysis.php?quote_id=$quote_id", 
        'gemini-2.5-pro' => "$base_url/server/api/gemini-2-5-pro-analysis.php?quote_id=$quote_id"
    ];
    
    $handles = [];
    $results = [];
    
    // Initialize parallel cURL handles
    $multi_handle = curl_multi_init();
    
    foreach ($analysis_urls as $model => $url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 180, // 3 minutes max per model
            CURLOPT_HEADER => false
        ]);
        curl_multi_add_handle($multi_handle, $ch);
        $handles[$model] = $ch;
        echo "📡 Queued: $model analysis\n";
    }
    
    echo "\n⏳ Running parallel analysis...\n";
    
    // Execute all handles in parallel
    $running = null;
    do {
        $status = curl_multi_exec($multi_handle, $running);
        if ($running > 0) {
            curl_multi_select($multi_handle, 1);
        }
    } while ($running > 0 && $status == CURLM_OK);
    
    // Collect results
    foreach ($handles as $model => $ch) {
        $response = curl_multi_getcontent($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $exec_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                echo "✅ $model: Success ({$exec_time}s, Cost: $" . number_format($result['cost'] ?? 0, 4) . ")\n";
                $results[$model] = $result;
            } else {
                echo "❌ $model: Failed - " . ($result['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "❌ $model: HTTP $http_code error\n";
        }
        
        curl_multi_remove_handle($multi_handle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multi_handle);
    
    $total_time = microtime(true) - $start_time;
    echo "\n⏱️  Total execution time: " . round($total_time, 2) . " seconds\n";
    
    // Update quote status
    $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'multi_ai_complete' WHERE id = ?");
    $stmt->execute([$quote_id]);
    
    echo "\n🎉 Multi-model analysis complete!\n";
    echo "📊 View comparison at: /model-comparison-dashboard.html?quote_id=$quote_id\n";
    
    // Send admin notification now that ALL AI models have completed
    echo "\n📧 Sending admin notification...\n";
    try {
        require_once __DIR__ . '/server/api/admin-notification.php';
        $admin_notification_sent = sendAdminNotification($quote_id);
        if ($admin_notification_sent) {
            echo "✅ Admin notification sent successfully!\n";
        } else {
            echo "❌ Failed to send admin notification\n";
        }
    } catch (Exception $e) {
        echo "❌ Admin notification error: " . $e->getMessage() . "\n";
    }
    
    // Summary
    $total_cost = array_sum(array_column($results, 'cost'));
    echo "\n💰 Total cost across all models: $" . number_format($total_cost, 4) . "\n";
    echo "🎯 Successful models: " . count($results) . "/3\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>