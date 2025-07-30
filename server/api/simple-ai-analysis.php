<?php
// Simple AI Analysis - ChatGPT-4o for tree media analysis
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in production

try {
    $quote_id = $_POST['quote_id'] ?? $_GET['quote_id'] ?? null;
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

    // Helper to extract key frames and audio from video using ffmpeg
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

// Helper to extract and transcribe audio from video
function extractAndTranscribeAudio($videoPath) {
    global $OPENAI_API_KEY;
    
    if (!file_exists('/usr/bin/ffmpeg') && !shell_exec('which ffmpeg')) {
        return null; // ffmpeg not available
    }
    
    $tmpAudio = sys_get_temp_dir() . '/audio_' . uniqid() . '.mp3';
    
    // Extract audio (max 25MB for OpenAI Whisper API)
    $cmd = sprintf('ffmpeg -hide_banner -loglevel error -i %s -vn -acodec mp3 -ab 64k -ar 16000 -t 300 %s',
        escapeshellarg($videoPath),
        escapeshellarg($tmpAudio)
    );
    shell_exec($cmd);
    
    if (!file_exists($tmpAudio) || filesize($tmpAudio) == 0) {
        return null; // No audio or extraction failed
    }
    
    // Transcribe with OpenAI Whisper
    try {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($tmpAudio, 'audio/mp3', 'audio.mp3'),
                'model' => 'whisper-1',
                'language' => 'en',
                'response_format' => 'text'
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $OPENAI_API_KEY
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        unlink($tmpAudio); // Cleanup
        
        if ($http_code === 200 && !empty(trim($response))) {
            return trim($response);
        }
    } catch (Exception $e) {
        error_log("Audio transcription failed: " . $e->getMessage());
    }
    
    return null;
}

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
            // Extract key frames for analysis
            $videoPathOptions = [
                __DIR__ . '/../../uploads/' . $quote_id . '/' . $filename,
                __DIR__ . '/../../uploads/quote_' . $quote_id . '/' . $filename
            ];
            $framesAdded = false;
            foreach ($videoPathOptions as $vp) {
                if (file_exists($vp)) {
                    $frames = extractVideoFrames($vp, 5, 0); // Every 5s, entire video duration
                    $transcription = extractAndTranscribeAudio($vp); // Extract audio transcription
                    
                    if ($frames) {
                        $media_content = array_merge($media_content, $frames);
                        $has_images = true;
                        $framesAdded = true;
                    }
                    
                    // Add transcription if available
                    if ($transcription) {
                        $media_content[] = [
                            'type' => 'text',
                            'text' => "🎤 Audio transcription from $filename: \"$transcription\""
                        ];
                        $media_summary[] = "🎬 $filename (" . count($frames) . " frames + audio)";
                    } else {
                        $media_summary[] = "🎬 $filename (" . count($frames) . " frames)";
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
                            }
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
            'content' => 'You are a certified arborist with 20+ years experience providing detailed tree care estimates. Analyze uploaded media to provide professional tree service quotes with: (1) Species identification with confidence level, (2) Tree health assessment using ISA standards, (3) Structural defect analysis, (4) Safety risk evaluation, (5) Specific service recommendations with pricing considerations, (6) Equipment requirements and access challenges. Provide estimates that require minimal editing for professional use.'
        ],
        [
            'role' => 'user',
            'content' => $user_content
        ]
    ];

    // ChatGPT o3 - Your proven tree care specialist with minimal editing required
    $openai_request = [
        'model' => 'o3',
        'messages' => $messages,
        // 'temperature' => 0.2, // o3 only supports default temperature (1)
        'max_completion_tokens' => 2000  // gpt-4o uses max_tokens
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
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($http_code !== 200) {
        error_log("OpenAI API error: HTTP $http_code, Response: $response, Curl error: $curl_error");
        throw new Exception("OpenAI API error: HTTP $http_code - $response");
    }

    $ai_result = json_decode($response, true);
    
    if (!isset($ai_result['choices'][0]['message']['content'])) {
        throw new Exception("Invalid AI response format");
    }

    $ai_analysis = $ai_result['choices'][0]['message']['content'];

    // Format the analysis  
    $analysis_summary = "🤖 ChatGPT o3 Professional Tree Analysis Complete\n\n";
    $analysis_summary .= "📁 Media analyzed: " . implode(', ', $media_summary) . "\n\n";
    $analysis_summary .= "🔍 AI Analysis:\n" . $ai_analysis;

    // Update quote with AI analysis
    $stmt = $pdo->prepare("
        UPDATE quotes 
        SET ai_response_json = ?, ai_analysis_complete = 1, quote_status = 'draft_ready' 
        WHERE id = ?
    ");
    $stmt->execute([json_encode(['analysis' => $ai_analysis, 'media' => $media_summary]), $quote_id]);

    // Send or update admin notification with AI analysis details
    try {
        require_once __DIR__ . '/admin-notification.php';
        sendAdminNotification($quote_id);
    } catch (Exception $e) {
        error_log("Admin notification after AI failed: " . $e->getMessage());
    }

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