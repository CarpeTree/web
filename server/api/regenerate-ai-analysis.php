<?php
// Regenerate AI Analysis with Additional Context
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$quote_id = $input['quote_id'] ?? null;
$additional_context = $input['context'] ?? '';

if (!$quote_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quote ID required']);
    exit();
}

require_once '../config/database-simple.php';
require_once '../config/config.php';

try {
    // Get quote and customer information
    $stmt = $pdo->prepare("
        SELECT q.*, c.email, c.name, c.phone 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        throw new Exception("Quote not found");
    }

    // Get uploaded media files
    $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ? ORDER BY id ASC");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll();

    if (empty($media_files)) {
        throw new Exception("No media files found for analysis");
    }

    // Prepare AI analysis request
    $media_content = [];
    $media_summary = [];
    $has_images = false;

    foreach ($media_files as $media) {
        $filename = $media['filename'];
        $file_type = $media['mime_type'] ?? $media['file_type'] ?? '';
        
        // Handle images for AI vision analysis
        if (strpos($file_type, 'image/') === 0) {
            // Try to find the image file
            $upload_paths = [
                __DIR__ . '/../../uploads/' . $quote_id . '/' . $filename,
                __DIR__ . '/../../uploads/quote_' . $quote_id . '/' . $filename
            ];
            
            foreach ($upload_paths as $path) {
                if (file_exists($path)) {
                    $image_data = base64_encode(file_get_contents($path));
                    $media_content[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:$file_type;base64,$image_data",
                            'detail' => 'high'
                        ]
                    ];
                    $media_summary[] = "📷 $filename";
                    $has_images = true;
                    break;
                }
            }
        } elseif (strpos($file_type, 'video/') === 0) {
            $size = isset($media['file_size']) ? round($media['file_size'] / (1024*1024), 1) : 'unknown';
            $media_content[] = [
                'type' => 'text',
                'text' => "🎬 Video file: $filename ($size MB). Contains tree/site footage requiring detailed analysis."
            ];
            $media_summary[] = "🎬 $filename";
        }
    }

    if (empty($media_content)) {
        throw new Exception("No processable media found");
    }

    // Build OpenAI request with additional context
    $services = json_decode($quote['selected_services'], true) ?: [];
    $services_text = implode(', ', $services);
    
    $base_prompt = "Analyze this tree service request thoroughly. Customer selected: $services_text.";
    
    if (!empty($additional_context)) {
        $base_prompt .= "\n\nADDITIONAL CONTEXT FROM ADMIN: " . $additional_context;
    }
    
    if (!empty($quote['notes'])) {
        $base_prompt .= "\n\nCustomer notes: " . $quote['notes'];
    }
    
    $base_prompt .= "\n\nProvide detailed analysis including specific recommendations, estimated costs, and safety considerations. Focus on what you can see in the images/videos.";

    // Prepare user content with media
    $user_content = [
        ['type' => 'text', 'text' => $base_prompt]
    ];
    $user_content = array_merge($user_content, $media_content);

    // OpenAI API request
    $openai_request = [
        'model' => 'gpt-4o',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert arborist and tree service professional. Analyze images/videos to provide detailed, professional tree service recommendations and cost estimates.'
            ],
            [
                'role' => 'user',
                'content' => $user_content
            ]
        ],
        'temperature' => 0.3,
        'max_tokens' => 2000
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($openai_request),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $OPENAI_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        throw new Exception("OpenAI API request failed: HTTP $http_code");
    }

    $ai_result = json_decode($response, true);
    
    if (!isset($ai_result['choices'][0]['message']['content'])) {
        throw new Exception("Invalid response from OpenAI API");
    }

    $analysis = $ai_result['choices'][0]['message']['content'];
    
    // Update the quote with new AI analysis
    $analysis_data = [
        'regenerated_analysis' => $analysis,
        'admin_context' => $additional_context,
        'regenerated_at' => date('Y-m-d H:i:s')
    ];
    
    $stmt = $pdo->prepare("UPDATE quotes SET ai_response_json = ?, quote_status = 'admin_review' WHERE id = ?");
    $stmt->execute([json_encode($analysis_data), $quote_id]);

    echo json_encode([
        'success' => true,
        'analysis' => "🤖 AI Analysis Regenerated\n\n" . $analysis,
        'message' => 'AI analysis regenerated successfully'
    ]);

} catch (Exception $e) {
    error_log("AI regeneration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 