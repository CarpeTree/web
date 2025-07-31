<?php
// API to get daily cost tracking data
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database-simple.php';

try {
    $model = $_GET['model'] ?? 'all';
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Get daily cost summary
    if ($model === 'all') {
        $stmt = $pdo->prepare("
            SELECT 
                model_name,
                provider,
                total_cost,
                total_requests,
                total_tokens,
                avg_processing_time_ms,
                quotes_processed
            FROM ai_cost_summary 
            WHERE summary_date = ?
            ORDER BY total_cost DESC
        ");
        $stmt->execute([$date]);
        $daily_costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get running totals
        $stmt = $pdo->prepare("SELECT * FROM ai_running_totals ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $running_totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'date' => $date,
            'daily_costs' => $daily_costs,
            'running_totals' => $running_totals
        ]);
        
    } else {
        // Get specific model costs
        $stmt = $pdo->prepare("
            SELECT 
                SUM(total_cost) as today_cost,
                SUM(total_requests) as today_requests,
                SUM(total_tokens) as today_tokens,
                AVG(avg_processing_time_ms) as avg_latency
            FROM ai_cost_summary 
            WHERE summary_date = ? AND model_name LIKE ?
        ");
        $stmt->execute([$date, "%$model%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent cost tracking for this model
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                q.quote_created_at
            FROM ai_cost_tracking c
            JOIN quotes q ON c.quote_id = q.id
            WHERE c.model_name LIKE ? 
            AND DATE(c.started_at) = ?
            ORDER BY c.started_at DESC
            LIMIT 10
        ");
        $stmt->execute(["%$model%", $date]);
        $recent_usage = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'model' => $model,
            'date' => $date,
            'today_cost' => floatval($result['today_cost'] ?? 0),
            'today_requests' => intval($result['today_requests'] ?? 0),
            'today_tokens' => intval($result['today_tokens'] ?? 0),
            'avg_latency' => floatval($result['avg_latency'] ?? 0),
            'recent_usage' => $recent_usage
        ]);
    }
    
} catch (Exception $e) {
    error_log("Get daily costs error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>