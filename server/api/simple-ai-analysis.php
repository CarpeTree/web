<?php
// Simple AI Analysis - ChatGPT-4o for tree media analysis
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in production

try {
    $quote_id = $_GET['quote_id'] ?? null;
    if (!$quote_id) {
        throw new Exception("Quote ID required");
    }

    require_once __DIR__ . '/../config/database-simple.php';
    require_once __DIR__ . '/../config/config.php';

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
        $filename = $media['filename'] ?? 'unknown';
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
                'text' => "🎬 Video file: $filename ($size MB). Contains tree/site footage requiring manual review."
            ];
            $media_summary[] = "🎬 $filename";
        }
    }

    if (empty($media_content)) {
        throw new Exception("No processable media found");
    }

    // Build OpenAI request
    $services = json_decode($quote['selected_services'], true) ?: [];
    $services_text = implode(', ', $services);
    
    $prompt = "Analyze this tree service request. Customer selected: $services_text. " . 
              ($quote['notes'] ? "Customer notes: " . $quote['notes'] : "No additional notes.");

    $user_content = [
        ['type' => 'text', 'text' => $prompt]
    ];
    $user_content = array_merge($user_content, $media_content);

    $messages = [
        [
            'role' => 'system',
            'content' => 'You are an expert arborist analyzing photos/videos for tree service quotes. Provide detailed analysis of tree species, condition, safety risks, and service recommendations. Be specific about what you observe.'
        ],
        [
            'role' => 'user',
            'content' => $user_content
        ]
    ];

    // Call OpenAI ChatGPT-4o
    $openai_request = [
        'model' => 'gpt-4o',
        'messages' => $messages,
        'temperature' => 0.2,
        'max_tokens' => 1500
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($openai_request),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $OPENAI_API_KEY
        ],
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code !== 200) {
        throw new Exception("OpenAI API error: HTTP $http_code");
    }

    $ai_result = json_decode($response, true);
    
    if (!isset($ai_result['choices'][0]['message']['content'])) {
        throw new Exception("Invalid AI response format");
    }

    $ai_analysis = $ai_result['choices'][0]['message']['content'];

    // Format the analysis
    $analysis_summary = "🤖 ChatGPT-4o Analysis Complete\n\n";
    $analysis_summary .= "📁 Media analyzed: " . implode(', ', $media_summary) . "\n\n";
    $analysis_summary .= "🔍 AI Analysis:\n" . $ai_analysis;

    // Update quote with AI analysis
    $stmt = $pdo->prepare("
        UPDATE quotes 
        SET ai_response_json = ?, quote_status = 'draft_ready' 
        WHERE id = ?
    ");
    $stmt->execute([json_encode(['analysis' => $ai_analysis, 'media' => $media_summary]), $quote_id]);

    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'analysis' => $analysis_summary,
        'media_count' => count($media_files),
        'images_analyzed' => $has_images
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'quote_id' => $quote_id ?? null
    ]);
}
?> 