<?php
// Comprehensive Customer Interaction Tracking
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database-simple.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['interaction_type'])) {
        throw new Exception('Invalid input data');
    }
    
    // Extract data
    $session_id = $input['session_id'] ?? generateSessionId();
    $interaction_type = $input['interaction_type'];
    $interaction_data = $input['data'] ?? [];
    $customer_id = $input['customer_id'] ?? null;
    $quote_id = $input['quote_id'] ?? null;
    
    // Capture client information
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = getClientIP();
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $page_url = $input['page_url'] ?? '';
    
    // Enhanced interaction data
    $enhanced_data = array_merge($interaction_data, [
        'timestamp' => time(),
        'timezone' => $input['timezone'] ?? '',
        'screen_resolution' => $input['screen_resolution'] ?? '',
        'viewport_size' => $input['viewport_size'] ?? '',
        'device_type' => detectDeviceType($user_agent),
        'browser' => detectBrowser($user_agent),
        'os' => detectOS($user_agent)
    ]);
    
    // Store main interaction
    $stmt = $pdo->prepare("
        INSERT INTO customer_interactions (
            customer_id, quote_id, session_id, interaction_type, 
            interaction_data, page_url, user_agent, ip_address, 
            referrer, interaction_timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $customer_id,
        $quote_id,
        $session_id,
        $interaction_type,
        json_encode($enhanced_data),
        $page_url,
        $user_agent,
        $ip_address,
        $referrer
    ]);
    
    $interaction_id = $pdo->lastInsertId();
    
    // Handle specific interaction types
    switch ($interaction_type) {
        case 'service_select':
        case 'service_deselect':
            trackServiceInteraction($pdo, $quote_id, $interaction_data, $interaction_type);
            break;
            
        case 'form_submit':
            trackCustomerJourney($pdo, $customer_id, $quote_id, 'form_complete', $enhanced_data);
            break;
            
        case 'quote_view':
            trackCustomerJourney($pdo, $customer_id, $quote_id, 'quote_received', $enhanced_data);
            break;
            
        case 'quote_accept':
            trackCustomerJourney($pdo, $customer_id, $quote_id, 'quote_accepted', $enhanced_data);
            break;
            
        case 'quote_decline':
            trackCustomerJourney($pdo, $customer_id, $quote_id, 'quote_declined', $enhanced_data);
            break;
    }
    
    // Update live session
    updateLiveSession($pdo, $session_id, $customer_id, $quote_id, $page_url, $enhanced_data);
    
    echo json_encode([
        'success' => true,
        'interaction_id' => $interaction_id,
        'session_id' => $session_id,
        'message' => 'Interaction tracked successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

function trackServiceInteraction($pdo, $quote_id, $data, $action) {
    if (!$quote_id || !isset($data['service_name'])) return;
    
    $action_type = ($action === 'service_select') ? 'added' : 'removed';
    
    // Calculate hesitation score based on interaction timing
    $hesitation_score = calculateHesitationScore($data);
    
    $stmt = $pdo->prepare("
        INSERT INTO service_analytics (
            quote_id, service_name, action, is_optional, 
            price_when_selected, selection_order, time_spent_considering_seconds,
            customer_hesitation_score, interaction_timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $quote_id,
        $data['service_name'],
        $action_type,
        $data['is_optional'] ?? false,
        $data['price'] ?? 0,
        $data['selection_order'] ?? 0,
        $data['time_spent'] ?? 0,
        $hesitation_score
    ]);
}

function trackCustomerJourney($pdo, $customer_id, $quote_id, $stage, $data) {
    if (!$customer_id) return;
    
    // Calculate time in previous stage
    $prev_stmt = $pdo->prepare("
        SELECT stage_timestamp FROM customer_journey 
        WHERE customer_id = ? 
        ORDER BY stage_timestamp DESC 
        LIMIT 1
    ");
    $prev_stmt->execute([$customer_id]);
    $prev_stage = $prev_stmt->fetch();
    
    $time_in_stage = $prev_stage ? 
        (time() - strtotime($prev_stage['stage_timestamp'])) : 0;
    
    // Calculate conversion score
    $conversion_score = calculateConversionScore($stage, $data);
    
    $stmt = $pdo->prepare("
        INSERT INTO customer_journey (
            customer_id, quote_id, journey_stage, stage_data,
            time_in_stage_seconds, conversion_score, stage_timestamp
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $customer_id,
        $quote_id,
        $stage,
        json_encode($data),
        $time_in_stage,
        $conversion_score
    ]);
}

function updateLiveSession($pdo, $session_id, $customer_id, $quote_id, $page_url, $data) {
    $device_info = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'device_type' => $data['device_type'] ?? '',
        'browser' => $data['browser'] ?? '',
        'os' => $data['os'] ?? '',
        'screen_resolution' => $data['screen_resolution'] ?? '',
        'viewport_size' => $data['viewport_size'] ?? ''
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO live_sessions (
            session_id, customer_id, quote_id, current_page, 
            session_data, device_info, last_activity
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        customer_id = VALUES(customer_id),
        quote_id = VALUES(quote_id),
        current_page = VALUES(current_page),
        session_data = VALUES(session_data),
        last_activity = NOW()
    ");
    
    $stmt->execute([
        $session_id,
        $customer_id,
        $quote_id,
        $page_url,
        json_encode($data),
        json_encode($device_info)
    ]);
}

function calculateHesitationScore($data) {
    $score = 0.0;
    
    // Factors that indicate hesitation
    $hover_time = $data['hover_time'] ?? 0;
    $clicks_before_selection = $data['clicks_before_selection'] ?? 0;
    $time_spent = $data['time_spent'] ?? 0;
    
    // Higher hover time = more hesitation
    if ($hover_time > 5) $score += 0.3;
    if ($hover_time > 10) $score += 0.2;
    
    // Multiple clicks = uncertainty
    if ($clicks_before_selection > 2) $score += 0.2;
    if ($clicks_before_selection > 5) $score += 0.3;
    
    // Long consideration time
    if ($time_spent > 30) $score += 0.2;
    
    return min(1.0, $score);
}

function calculateConversionScore($stage, $data) {
    $score_map = [
        'discovery' => 0.1,
        'form_start' => 0.3,
        'form_progress' => 0.5,
        'form_complete' => 0.7,
        'quote_received' => 0.8,
        'quote_reviewed' => 0.9,
        'quote_accepted' => 1.0,
        'quote_declined' => 0.0,
        'service_completed' => 1.0
    ];
    
    $base_score = $score_map[$stage] ?? 0.0;
    
    // Adjust based on engagement data
    $engagement_bonus = 0.0;
    if (isset($data['time_spent']) && $data['time_spent'] > 60) {
        $engagement_bonus += 0.1;
    }
    
    return min(1.0, $base_score + $engagement_bonus);
}

function generateSessionId() {
    return 'session_' . time() . '_' . uniqid();
}

function getClientIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            return $_SERVER[$header];
        }
    }
    
    return '0.0.0.0';
}

function detectDeviceType($user_agent) {
    if (preg_match('/mobile|android|iphone|ipad/i', $user_agent)) {
        return 'mobile';
    } elseif (preg_match('/tablet|ipad/i', $user_agent)) {
        return 'tablet';
    }
    return 'desktop';
}

function detectBrowser($user_agent) {
    if (preg_match('/Chrome/i', $user_agent)) return 'Chrome';
    if (preg_match('/Firefox/i', $user_agent)) return 'Firefox';
    if (preg_match('/Safari/i', $user_agent)) return 'Safari';
    if (preg_match('/Edge/i', $user_agent)) return 'Edge';
    return 'Unknown';
}

function detectOS($user_agent) {
    if (preg_match('/Windows/i', $user_agent)) return 'Windows';
    if (preg_match('/Mac/i', $user_agent)) return 'macOS';
    if (preg_match('/Linux/i', $user_agent)) return 'Linux';
    if (preg_match('/Android/i', $user_agent)) return 'Android';
    if (preg_match('/iOS/i', $user_agent)) return 'iOS';
    return 'Unknown';
}
?> 