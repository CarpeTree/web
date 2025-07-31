<?php
// API to track AI model costs in real-time
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database-simple.php';

try {
    // Decode JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $quote_id = $input['quote_id'] ?? null;
    $model_name = $input['model_name'] ?? null;
    $provider = $input['provider'] ?? null;
    $input_tokens = intval($input['input_tokens'] ?? 0);
    $output_tokens = intval($input['output_tokens'] ?? 0);
    $processing_time_ms = intval($input['processing_time_ms'] ?? 0);
    $reasoning_effort = $input['reasoning_effort'] ?? 'medium';
    $media_files_processed = intval($input['media_files_processed'] ?? 0);
    $transcriptions_generated = intval($input['transcriptions_generated'] ?? 0);
    $tools_used = json_encode($input['tools_used'] ?? []);
    
    if (!$quote_id || !$model_name || !$provider) {
        throw new Exception('Missing required fields: quote_id, model_name, provider');
    }
    
    // Get current pricing for the model
    $stmt = $pdo->prepare("
        SELECT input_token_cost, output_token_cost 
        FROM ai_model_pricing 
        WHERE model_name = ? AND provider = ? AND is_active = 1
        ORDER BY effective_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$model_name, $provider]);
    $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pricing) {
        // Use default pricing if not found
        $pricing = [
            'input_token_cost' => 0.000001,
            'output_token_cost' => 0.000004
        ];
        error_log("No pricing found for $model_name ($provider), using defaults");
    }
    
    // Calculate costs
    $total_tokens = $input_tokens + $output_tokens;
    $input_cost = $input_tokens * $pricing['input_token_cost'];
    $output_cost = $output_tokens * $pricing['output_token_cost'];
    $total_cost = $input_cost + $output_cost;
    
    // Calculate performance metrics
    $tokens_per_second = $processing_time_ms > 0 ? ($total_tokens / ($processing_time_ms / 1000)) : 0;
    $context_length = $input_tokens; // Approximate
    $function_calls_used = !empty($input['tools_used']);
    
    // Insert cost tracking record
    $stmt = $pdo->prepare("
        INSERT INTO ai_cost_tracking (
            quote_id, model_name, provider, input_tokens, output_tokens, total_tokens,
            input_cost, output_cost, total_cost, processing_time_ms, tokens_per_second,
            context_length, reasoning_effort, function_calls_used, tools_used,
            media_files_processed, transcriptions_generated, started_at, completed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $quote_id, $model_name, $provider, $input_tokens, $output_tokens, $total_tokens,
        $input_cost, $output_cost, $total_cost, $processing_time_ms, $tokens_per_second,
        $context_length, $reasoning_effort, $function_calls_used, $tools_used,
        $media_files_processed, $transcriptions_generated
    ]);
    
    $tracking_id = $pdo->lastInsertId();
    
    // Update daily cost summary
    updateDailyCostSummary($pdo, $model_name, $provider, $total_cost, $total_tokens, $processing_time_ms);
    
    // Update running totals
    updateRunningTotals($pdo, $total_cost);
    
    echo json_encode([
        'success' => true,
        'tracking_id' => $tracking_id,
        'cost_breakdown' => [
            'input_cost' => $input_cost,
            'output_cost' => $output_cost,
            'total_cost' => $total_cost,
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'total_tokens' => $total_tokens
        ],
        'performance' => [
            'processing_time_ms' => $processing_time_ms,
            'tokens_per_second' => round($tokens_per_second, 2),
            'cost_per_token' => $total_tokens > 0 ? ($total_cost / $total_tokens) : 0
        ]
    ]);
    
} catch (Exception $e) {
    error_log("AI cost tracking error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function updateDailyCostSummary($pdo, $model_name, $provider, $cost, $tokens, $processing_time_ms) {
    $today = date('Y-m-d');
    
    // Check if summary exists for today
    $stmt = $pdo->prepare("
        SELECT id, total_requests, total_tokens, total_cost 
        FROM ai_cost_summary 
        WHERE summary_date = ? AND model_name = ? AND provider = ?
    ");
    $stmt->execute([$today, $model_name, $provider]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing summary
        $stmt = $pdo->prepare("
            UPDATE ai_cost_summary 
            SET 
                total_requests = total_requests + 1,
                total_tokens = total_tokens + ?,
                total_cost = total_cost + ?,
                avg_processing_time_ms = (
                    (avg_processing_time_ms * total_requests + ?) / (total_requests + 1)
                ),
                quotes_processed = quotes_processed + 1,
                last_updated = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$tokens, $cost, $processing_time_ms, $existing['id']]);
    } else {
        // Create new summary
        $stmt = $pdo->prepare("
            INSERT INTO ai_cost_summary (
                summary_date, model_name, provider, total_requests, total_tokens, 
                total_cost, avg_processing_time_ms, quotes_processed
            ) VALUES (?, ?, ?, 1, ?, ?, ?, 1)
        ");
        $stmt->execute([$today, $model_name, $provider, $tokens, $cost, $processing_time_ms]);
    }
}

function updateRunningTotals($pdo, $cost) {
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $month_start = date('Y-m-01');
    $year_start = date('Y-01-01');
    
    // Get or create running totals record
    $stmt = $pdo->prepare("SELECT id FROM ai_running_totals ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $totals_id = $stmt->fetchColumn();
    
    if (!$totals_id) {
        // Create initial record
        $stmt = $pdo->prepare("INSERT INTO ai_running_totals () VALUES ()");
        $stmt->execute();
        $totals_id = $pdo->lastInsertId();
    }
    
    // Update all running totals
    $stmt = $pdo->prepare("
        UPDATE ai_running_totals 
        SET 
            today_cost = today_cost + ?,
            this_week_cost = this_week_cost + ?,
            this_month_cost = this_month_cost + ?,
            this_year_cost = this_year_cost + ?,
            all_time_cost = all_time_cost + ?,
            today_requests = today_requests + 1,
            this_week_requests = this_week_requests + 1,
            this_month_requests = this_month_requests + 1,
            this_year_requests = this_year_requests + 1,
            all_time_requests = all_time_requests + 1,
            last_updated = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$cost, $cost, $cost, $cost, $cost, $totals_id]);
}
?>