<?php
// Regenerate AI Analysis with Additional Context
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);

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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    $quote_id = $input['quote_id'] ?? null;
    $additional_context = $input['context'] ?? '';

    if (!$quote_id) {
        throw new Exception('Quote ID required');
    }

    error_log("Regenerating AI analysis for quote: $quote_id with context: $additional_context");

    require_once '../config/database-simple.php';
    require_once '../config/config.php';

    // Check if required config exists
    if (empty($OPENAI_API_KEY)) {
        throw new Exception('OpenAI API key not configured');
    }

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
        throw new Exception("Quote $quote_id not found");
    }

    error_log("Found quote: " . $quote['id'] . " for customer: " . $quote['name']);

    // Get uploaded media files
    $stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ? ORDER BY id ASC");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll();

    if (empty($media_files)) {
        throw new Exception("No media files found for quote $quote_id");
    }

    error_log("Found " . count($media_files) . " media files for quote $quote_id");

    // Prepare AI analysis request
    $media_content = [];
    $media_summary = [];
    $has_images = false;

    // Helper to extract key frames from video using ffmpeg (same as simple-ai-analysis.php)
    function extractVideoFrames($videoPath, $secondsInterval = 5, $maxFrames = 0) { // 0 = no limit
        $frames = [];
        if (!file_exists('/usr/bin/ffmpeg') && !shell_exec('which ffmpeg')) {
            return $frames; // ffmpeg not available
        }
        $tmpDir = sys_get_temp_dir() . '/frames_' . uniqid();
        mkdir($tmpDir);
        
        // If maxFrames is 0, extract frames for entire video duration
        if ($maxFrames == 0) {
            $cmd = sprintf('ffmpeg -hide_banner -loglevel error -i %s -vf fps=1/%d %s/frame_%%03d.jpg',
                escapeshellarg($videoPath),
                (int)$secondsInterval,
                escapeshellarg($tmpDir)
            );
        } else {
            $cmd = sprintf('ffmpeg -hide_banner -loglevel error -i %s -vf fps=1/%d -frames:v %d %s/frame_%%03d.jpg',
                escapeshellarg($videoPath),
                (int)$secondsInterval,
                (int)$maxFrames,
                escapeshellarg($tmpDir)
            );
        }
        shell_exec($cmd);
        $files = glob($tmpDir . '/frame_*.jpg');
        foreach ($files as $frameFile) {
            $imageData = base64_encode(file_get_contents($frameFile));
            $frames[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:image/jpeg;base64,' . $imageData,
                    'detail' => 'high'
                ]
            ];
            unlink($frameFile);
        }
        rmdir($tmpDir);
        return $frames;
    }

    foreach ($media_files as $media) {
        $filename = $media['filename'];
        $file_type = $media['mime_type'] ?? $media['file_type'] ?? '';
        
        error_log("Processing media file: $filename, type: $file_type");
        
        // Handle images for AI vision analysis
        if (strpos($file_type, 'image/') === 0) {
            // Try multiple possible upload paths
            $upload_paths = [
                __DIR__ . '/../../uploads/' . $quote_id . '/' . $filename,
                __DIR__ . '/../../uploads/quote_' . $quote_id . '/' . $filename,
                __DIR__ . '/../../uploads/' . $filename,
                __DIR__ . '/../uploads/' . $quote_id . '/' . $filename
            ];
            
            $found_file = false;
            foreach ($upload_paths as $path) {
                error_log("Checking path: $path");
                if (file_exists($path)) {
                    error_log("Found image file at: $path");
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
                    $found_file = true;
                    break;
                }
            }
            
            if (!$found_file) {
                error_log("Image file not found for: $filename in any of the checked paths");
            }
            
        } elseif (strpos($file_type, 'video/') === 0) {
            $size = isset($media['file_size']) ? round($media['file_size'] / (1024*1024), 1) : 'unknown';
            // Extract key frames for analysis
            $videoPathOptions = [
                __DIR__ . '/../../uploads/' . $quote_id . '/' . $filename,
                __DIR__ . '/../../uploads/quote_' . $quote_id . '/' . $filename
            ];
            $framesAdded = false;
            foreach ($videoPathOptions as $vp) {
                if (file_exists($vp)) {
                    $frames = extractVideoFrames($vp, 5, 0); // Every 5s, entire video duration
                    
                    if ($frames) {
                        $media_content = array_merge($media_content, $frames);
                        $has_images = true;
                        $framesAdded = true;
                        $media_summary[] = "🎬 $filename (" . count($frames) . " frames)";
                        error_log("Extracted " . count($frames) . " frames from video: $filename");
                    }
                    break;
                }
            }
            if (!$framesAdded) {
                // fallback text
                $media_content[] = [
                    'type' => 'text',
                    'text' => "🎬 Video file: $filename ($size MB). Frames unavailable."
                ];
                $media_summary[] = "🎬 $filename";
                error_log("Video found but could not extract frames: $filename");
            }
        } else {
            error_log("Unsupported media type: $file_type for file: $filename");
        }
    }

    error_log("Total media content items: " . count($media_content));

    if (empty($media_content)) {
        throw new Exception("No processable media found. Check file paths and media types.");
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
        'model' => 'o3',
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
        // 'temperature' => 0.3, // o3 only supports default temperature (1)
        'max_completion_tokens' => 2000
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