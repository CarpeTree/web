<?php
// AI Cost Tracking Utility Class
require_once __DIR__ . '/../config/database-simple.php';

class CostTracker {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Track AI model usage and costs
     */
    public function trackUsage($params) {
        try {
            $start_time = microtime(true);
            
            // Extract parameters
            $quote_id = $params['quote_id'];
            $model_name = $params['model_name'];
            $provider = $params['provider'];
            $input_tokens = $params['input_tokens'] ?? 0;
            $output_tokens = $params['output_tokens'] ?? 0;
            $processing_time_ms = $params['processing_time_ms'] ?? 0;
            $reasoning_effort = $params['reasoning_effort'] ?? 'medium';
            $media_files_processed = $params['media_files_processed'] ?? 0;
            $transcriptions_generated = $params['transcriptions_generated'] ?? 0;
            $tools_used = json_encode($params['tools_used'] ?? []);
            $analysis_quality_score = $params['analysis_quality_score'] ?? null;
            
            // Get current pricing
            $pricing = $this->getModelPricing($model_name, $provider);
            
            // Calculate costs
            $total_tokens = $input_tokens + $output_tokens;
            $input_cost = $input_tokens * $pricing['input_token_cost'];
            $output_cost = $output_tokens * $pricing['output_token_cost'];
            $total_cost = $input_cost + $output_cost;
            
            // Calculate performance metrics
            $tokens_per_second = $processing_time_ms > 0 ? ($total_tokens / ($processing_time_ms / 1000)) : 0;
            $context_length = $input_tokens;
            $function_calls_used = !empty($params['tools_used']);
            
            // Insert tracking record
            $stmt = $this->pdo->prepare("
                INSERT INTO ai_cost_tracking (
                    quote_id, model_name, provider, input_tokens, output_tokens, total_tokens,
                    input_cost, output_cost, total_cost, processing_time_ms, 
                    first_token_latency_ms, tokens_per_second, context_length, 
                    reasoning_effort, function_calls_used, tools_used,
                    media_files_processed, transcriptions_generated, analysis_quality_score,
                    started_at, completed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $quote_id, $model_name, $provider, $input_tokens, $output_tokens, $total_tokens,
                $input_cost, $output_cost, $total_cost, $processing_time_ms,
                $params['first_token_latency_ms'] ?? null, $tokens_per_second, $context_length,
                $reasoning_effort, $function_calls_used, $tools_used,
                $media_files_processed, $transcriptions_generated, $analysis_quality_score
            ]);
            
            $tracking_id = $this->pdo->lastInsertId();
            
            // Update aggregated data
            $this->updateDailySummary($model_name, $provider, $total_cost, $total_tokens, $processing_time_ms);
            $this->updateRunningTotals($total_cost);
            
            return [
                'tracking_id' => $tracking_id,
                'cost_breakdown' => [
                    'input_cost' => $input_cost,
                    'output_cost' => $output_cost, 
                    'total_cost' => $total_cost,
                    'cost_per_token' => $total_tokens > 0 ? ($total_cost / $total_tokens) : 0
                ],
                'performance' => [
                    'processing_time_ms' => $processing_time_ms,
                    'tokens_per_second' => round($tokens_per_second, 2),
                    'input_tokens' => $input_tokens,
                    'output_tokens' => $output_tokens,
                    'total_tokens' => $total_tokens
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Cost tracking error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get model pricing
     */
    private function getModelPricing($model_name, $provider) {
        $stmt = $this->pdo->prepare("
            SELECT input_token_cost, output_token_cost 
            FROM ai_model_pricing 
            WHERE model_name = ? AND provider = ? AND is_active = 1
            ORDER BY effective_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$model_name, $provider]);
        $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pricing) {
            // Fallback pricing based on model type
            $fallback_pricing = [
                'o4-mini' => ['input' => 0.000001160, 'output' => 0.000004620],
                'o3-pro' => ['input' => 0.000020000, 'output' => 0.000080000],
                'o3-mini' => ['input' => 0.000001100, 'output' => 0.000004400],
                'gemini-2.5-pro' => ['input' => 0.000001250, 'output' => 0.000010000]
            ];
            
            foreach ($fallback_pricing as $model => $costs) {
                if (strpos(strtolower($model_name), $model) !== false) {
                    return [
                        'input_token_cost' => $costs['input'],
                        'output_token_cost' => $costs['output']
                    ];
                }
            }
            
            // Ultimate fallback
            return [
                'input_token_cost' => 0.000001,
                'output_token_cost' => 0.000004
            ];
        }
        
        return $pricing;
    }
    
    /**
     * Update daily cost summary
     */
    private function updateDailySummary($model_name, $provider, $cost, $tokens, $processing_time_ms) {
        $today = date('Y-m-d');
        
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_cost_summary (
                summary_date, model_name, provider, total_requests, total_tokens, 
                total_cost, avg_processing_time_ms, quotes_processed
            ) VALUES (?, ?, ?, 1, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                total_requests = total_requests + 1,
                total_tokens = total_tokens + ?,
                total_cost = total_cost + ?,
                avg_processing_time_ms = (
                    (avg_processing_time_ms * total_requests + ?) / (total_requests + 1)
                ),
                quotes_processed = quotes_processed + 1,
                last_updated = NOW()
        ");
        
        $stmt->execute([
            $today, $model_name, $provider, $tokens, $cost, $processing_time_ms,
            $tokens, $cost, $processing_time_ms
        ]);
    }
    
    /**
     * Update running totals
     */
    private function updateRunningTotals($cost) {
        // Get or create running totals record
        $stmt = $this->pdo->prepare("SELECT id FROM ai_running_totals ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $totals_id = $stmt->fetchColumn();
        
        if (!$totals_id) {
            $stmt = $this->pdo->prepare("INSERT INTO ai_running_totals () VALUES ()");
            $stmt->execute();
            $totals_id = $this->pdo->lastInsertId();
        }
        
        $stmt = $this->pdo->prepare("
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
    
    /**
     * Get cost summary for a specific period
     */
    public function getCostSummary($period = 'today', $model = null) {
        $date_filter = match($period) {
            'today' => "DATE(started_at) = CURDATE()",
            'week' => "started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'year' => "started_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)",
            default => "DATE(started_at) = CURDATE()"
        };
        
        $model_filter = $model ? "AND model_name LIKE :model" : "";
        
        $stmt = $this->pdo->prepare("
            SELECT 
                model_name,
                provider,
                COUNT(*) as requests,
                SUM(total_tokens) as total_tokens,
                SUM(total_cost) as total_cost,
                AVG(processing_time_ms) as avg_latency,
                AVG(tokens_per_second) as avg_tokens_per_sec
            FROM ai_cost_tracking 
            WHERE $date_filter $model_filter
            GROUP BY model_name, provider
            ORDER BY total_cost DESC
        ");
        
        if ($model) {
            $stmt->bindValue(':model', "%$model%");
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get the most expensive quotes by AI cost
     */
    public function getTopCostlyQuotes($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.quote_id,
                c.model_name,
                c.total_cost,
                c.total_tokens,
                c.processing_time_ms,
                c.started_at,
                q.notes,
                cust.name as customer_name,
                cust.email as customer_email
            FROM ai_cost_tracking c
            JOIN quotes q ON c.quote_id = q.id
            JOIN customers cust ON q.customer_id = cust.id
            ORDER BY c.total_cost DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>