<?php
// AI-Powered Distance Calculator using OpenAI O3
require_once __DIR__ . '/../config/config.php';

function calculateDistanceWithAI($customer_address, $timeout = 5) {
    global $OPENAI_API_KEY;
    
    // Use OpenAI O3 to calculate accurate distances
    $prompt = "Calculate the accurate driving distance in kilometers from Nelson, BC, Canada (base location: 4530 Blewitt Rd, Nelson BC V1L 6X1) to the following address: " . $customer_address . "

Please provide:
1. The exact driving distance in kilometers
2. Brief reasoning for the calculation

Consider:
- Actual road routes, not straight-line distance
- Mountain terrain and highway routes in BC
- Return ONLY a JSON object with: {\"distance_km\": number, \"reasoning\": \"string\"}";

    $data = [
        'model' => (getenv('AI_MODEL_DISTANCE') ?: 'gpt-4o-mini'), // Better for quick distance calculations  
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 150,
        'temperature' => 0.1 // Low temperature for accurate, consistent results
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $OPENAI_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => $timeout // Use configurable timeout
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code !== 200 || !$response) {
        error_log("AI distance calculation failed for: $customer_address");
        // Fallback to reasonable estimate
        return fallbackDistanceEstimate($customer_address);
    }

    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        error_log("Invalid AI response for distance calculation");
        return fallbackDistanceEstimate($customer_address);
    }

    // Parse AI response (handle markdown code blocks)
    $ai_content = $result['choices'][0]['message']['content'];
    
    // Remove markdown code blocks if present
    $ai_content = preg_replace('/```json\s*/', '', $ai_content);
    $ai_content = preg_replace('/```\s*$/', '', $ai_content);
    $ai_content = trim($ai_content);
    
    $ai_data = json_decode($ai_content, true);
    
    if (isset($ai_data['distance_km']) && is_numeric($ai_data['distance_km'])) {
        $distance = (int)round($ai_data['distance_km']);
        
        // Log successful AI calculation
        error_log("AI calculated distance to '$customer_address': {$distance}km - {$ai_data['reasoning']}");
        
        return $distance;
    } else {
        error_log("Could not parse AI distance response: $ai_content");
        return fallbackDistanceEstimate($customer_address);
    }
}

function fallbackDistanceEstimate($customer_address) {
    // Quick fallback estimates while AI system stabilizes
    $address_lower = strtolower($customer_address);
    
    if (strpos($address_lower, 'nelson') !== false) {
        return 10;
    } elseif (strpos($address_lower, 'moyie') !== false) {
        return 200;
    } elseif (strpos($address_lower, 'slocan') !== false) {
        return 55;
    } elseif (strpos($address_lower, 'castlegar') !== false) {
        return 25;
    } elseif (strpos($address_lower, 'trail') !== false) {
        return 45;
    } elseif (strpos($address_lower, 'cranbrook') !== false) {
        return 180;
    } elseif (strpos($address_lower, 'vancouver') !== false) {
        return 650;
    } elseif (strpos($address_lower, 'calgary') !== false) {
        return 420;
    } else {
        return 40; // Regional estimate
    }
}
?> 