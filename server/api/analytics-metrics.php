<?php
// Analytics Metrics API - Comprehensive Business Intelligence
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database-simple.php';

try {
    // Total quotes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM quotes");
    $total_quotes = $stmt->fetchColumn();
    
    // Total customer interactions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customer_interactions");
    $total_interactions = $stmt->fetchColumn();
    
    // Conversion rate (quotes that became quote_accepted)
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN journey_stage = 'quote_accepted' THEN 1 END) as accepted,
            COUNT(DISTINCT customer_id) as total_customers
        FROM customer_journey
    ");
    $conversion_data = $stmt->fetch();
    $conversion_rate = $conversion_data['total_customers'] > 0 ? 
        round(($conversion_data['accepted'] / $conversion_data['total_customers']) * 100, 1) : 0;
    
    // AI processing costs
    $stmt = $pdo->query("
        SELECT 
            SUM(api_cost_usd) as total_cost,
            AVG(api_cost_usd) as avg_cost,
            COUNT(*) as total_processings,
            SUM(total_tokens) as total_tokens
        FROM ai_processing_logs 
        WHERE processing_status = 'completed'
    ");
    $ai_costs = $stmt->fetch();
    
    // Average session time
    $stmt = $pdo->query("
        SELECT AVG(time_in_stage_seconds) as avg_time 
        FROM customer_journey 
        WHERE time_in_stage_seconds > 0
    ");
    $avg_session_time = round($stmt->fetchColumn() ?? 0);
    
    // Active sessions (last 15 minutes)
    $stmt = $pdo->query("
        SELECT COUNT(*) as active 
        FROM live_sessions 
        WHERE last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $active_sessions = $stmt->fetchColumn();
    
    // Quote submissions over time (last 30 days)
    $stmt = $pdo->query("
        SELECT 
            DATE(quote_created_at) as date,
            COUNT(*) as count
        FROM quotes 
        WHERE quote_created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(quote_created_at)
        ORDER BY date ASC
    ");
    $quotes_timeline = $stmt->fetchAll();
    
    // Service popularity
    $stmt = $pdo->query("
        SELECT 
            service_name,
            COUNT(*) as selection_count,
            AVG(customer_hesitation_score) as avg_hesitation
        FROM service_analytics 
        WHERE action = 'added'
        GROUP BY service_name
        ORDER BY selection_count DESC
    ");
    $service_popularity = $stmt->fetchAll();
    
    // Device breakdown
    $stmt = $pdo->query("
        SELECT 
            JSON_EXTRACT(interaction_data, '$.device_type') as device_type,
            COUNT(*) as count
        FROM customer_interactions 
        WHERE JSON_EXTRACT(interaction_data, '$.device_type') IS NOT NULL
        GROUP BY JSON_EXTRACT(interaction_data, '$.device_type')
    ");
    $device_breakdown = $stmt->fetchAll();
    
    // Recent performance metrics
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN quote_created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as quotes_24h,
            COUNT(CASE WHEN quote_created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as quotes_7d,
            COUNT(CASE WHEN quote_created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as quotes_30d
        FROM quotes
    ");
    $recent_quotes = $stmt->fetch();
    
    // Form abandonment rate
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as abandonments,
            AVG(time_spent_seconds) as avg_time_before_abandon
        FROM form_abandonments 
        WHERE abandoned_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $abandonment_data = $stmt->fetch();
    
    // Customer satisfaction metrics (based on journey progression)
    $stmt = $pdo->query("
        SELECT 
            AVG(conversion_score) as avg_satisfaction,
            COUNT(CASE WHEN conversion_score > 0.8 THEN 1 END) as high_satisfaction_count,
            COUNT(*) as total_journeys
        FROM customer_journey
    ");
    $satisfaction_data = $stmt->fetch();
    
    // Peak hours analysis
    $stmt = $pdo->query("
        SELECT 
            HOUR(interaction_timestamp) as hour,
            COUNT(*) as interaction_count
        FROM customer_interactions 
        WHERE interaction_timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(interaction_timestamp)
        ORDER BY interaction_count DESC
        LIMIT 5
    ");
    $peak_hours = $stmt->fetchAll();
    
    $response = [
        'success' => true,
        'generated_at' => date('Y-m-d H:i:s'),
        
        // Core metrics
        'total_quotes' => (int)$total_quotes,
        'total_interactions' => (int)$total_interactions,
        'conversion_rate' => $conversion_rate,
        'ai_cost_total' => round($ai_costs['total_cost'] ?? 0, 4),
        'avg_session_time' => $avg_session_time,
        'active_sessions' => (int)$active_sessions,
        
        // Recent performance
        'quotes_24h' => (int)$recent_quotes['quotes_24h'],
        'quotes_7d' => (int)$recent_quotes['quotes_7d'],
        'quotes_30d' => (int)$recent_quotes['quotes_30d'],
        
        // AI metrics
        'ai_metrics' => [
            'total_processings' => (int)($ai_costs['total_processings'] ?? 0),
            'total_cost' => round($ai_costs['total_cost'] ?? 0, 4),
            'avg_cost' => round($ai_costs['avg_cost'] ?? 0, 6),
            'total_tokens' => (int)($ai_costs['total_tokens'] ?? 0),
            'avg_tokens_per_processing' => $ai_costs['total_processings'] > 0 ? 
                round($ai_costs['total_tokens'] / $ai_costs['total_processings']) : 0
        ],
        
        // Customer insights
        'customer_insights' => [
            'avg_satisfaction_score' => round($satisfaction_data['avg_satisfaction'] ?? 0, 2),
            'high_satisfaction_rate' => $satisfaction_data['total_journeys'] > 0 ? 
                round(($satisfaction_data['high_satisfaction_count'] / $satisfaction_data['total_journeys']) * 100, 1) : 0,
            'form_abandonment_rate' => round($abandonment_data['abandonments'] ?? 0),
            'avg_time_before_abandon' => round($abandonment_data['avg_time_before_abandon'] ?? 0)
        ],
        
        // Charts data
        'charts' => [
            'quotes_timeline' => $quotes_timeline,
            'service_popularity' => $service_popularity,
            'device_breakdown' => $device_breakdown,
            'peak_hours' => $peak_hours
        ],
        
        // Performance indicators
        'performance' => [
            'quote_growth_rate' => calculateGrowthRate($recent_quotes),
            'ai_efficiency_score' => calculateAIEfficiency($ai_costs),
            'customer_engagement_score' => calculateEngagementScore($total_interactions, $active_sessions),
            'system_health_score' => calculateSystemHealth($pdo)
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch metrics',
        'message' => $e->getMessage()
    ]);
}

function calculateGrowthRate($recent_quotes) {
    $daily_rate = $recent_quotes['quotes_24h'];
    $weekly_rate = $recent_quotes['quotes_7d'] / 7;
    
    if ($weekly_rate > 0) {
        return round((($daily_rate - $weekly_rate) / $weekly_rate) * 100, 1);
    }
    
    return 0;
}

function calculateAIEfficiency($ai_costs) {
    if (!$ai_costs['total_processings'] || $ai_costs['total_processings'] == 0) {
        return 0;
    }
    
    $avg_cost = $ai_costs['avg_cost'] ?? 0;
    $avg_tokens = $ai_costs['total_tokens'] / $ai_costs['total_processings'];
    
    // Efficiency score based on cost per token (lower is better)
    $cost_per_token = $avg_tokens > 0 ? $avg_cost / $avg_tokens : 0;
    
    // Convert to 0-100 scale (assuming $0.00001 per token is 100% efficient)
    $efficiency = max(0, 100 - ($cost_per_token * 1000000));
    
    return round($efficiency, 1);
}

function calculateEngagementScore($total_interactions, $active_sessions) {
    // Simple engagement metric based on interaction density
    if ($total_interactions == 0) return 0;
    
    $base_score = min(100, ($total_interactions / 100) * 10); // Scale interactions
    $activity_bonus = min(20, $active_sessions * 5); // Bonus for active sessions
    
    return round($base_score + $activity_bonus, 1);
}

function calculateSystemHealth($pdo) {
    try {
        // Check recent errors
        $stmt = $pdo->query("
            SELECT COUNT(*) as error_count 
            FROM ai_processing_logs 
            WHERE processing_status = 'failed' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $recent_errors = $stmt->fetchColumn();
        
        // Check response times
        $stmt = $pdo->query("
            SELECT AVG(processing_time_ms) as avg_response_time 
            FROM ai_processing_logs 
            WHERE processing_status = 'completed' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $avg_response_time = $stmt->fetchColumn() ?? 0;
        
        // Calculate health score
        $health_score = 100;
        
        // Deduct for errors
        $health_score -= min(50, $recent_errors * 10);
        
        // Deduct for slow response times (>30 seconds is concerning)
        if ($avg_response_time > 30000) {
            $health_score -= 20;
        } elseif ($avg_response_time > 10000) {
            $health_score -= 10;
        }
        
        return max(0, round($health_score, 1));
        
    } catch (Exception $e) {
        return 50; // Neutral score if we can't determine health
    }
}
?> 