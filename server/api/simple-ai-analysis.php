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
    error_log("extractVideoFrames called with: $videoPath");
    
    // Check multiple possible FFmpeg locations
    $ffmpeg_paths = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/home/u230128646/bin/ffmpeg'
    ];
    
    $ffmpeg_path = null;
    foreach ($ffmpeg_paths as $path) {
        error_log("Checking FFmpeg path: $path");
        if (file_exists($path)) {
            $ffmpeg_path = $path;
            error_log("FFmpeg found at: $path");
            break;
        } else {
            error_log("FFmpeg not found at: $path");
        }
    }
    
    if (!$ffmpeg_path) {
        error_log("FFmpeg not found in any of these paths: " . implode(', ', $ffmpeg_paths));
        return $frames; // ffmpeg not available
    }
    $tmpDir = sys_get_temp_dir() . '/frames_' . uniqid();
    mkdir($tmpDir);
    
    // If maxFrames is 0, extract frames for entire video duration
    if ($maxFrames == 0) {
        $cmd = sprintf('%s -hide_banner -loglevel error -i %s -vf fps=1/%d %s/frame_%%03d.jpg',
            escapeshellarg($ffmpeg_path),
            escapeshellarg($videoPath),
            (int)$secondsInterval,
            escapeshellarg($tmpDir)
        );
    } else {
        $cmd = sprintf('%s -hide_banner -loglevel error -i %s -vf fps=1/%d -frames:v %d %s/frame_%%03d.jpg',
            escapeshellarg($ffmpeg_path),
            escapeshellarg($videoPath),
            (int)$secondsInterval,
            (int)$maxFrames,
            escapeshellarg($tmpDir)
        );
    }
    error_log("Executing FFmpeg command: $cmd");
    if (function_exists('shell_exec')) {
        shell_exec($cmd);
        $files = glob($tmpDir . '/frame_*.jpg');
        error_log("Found " . count($files) . " extracted frames");
    } else {
        error_log("shell_exec() disabled on server - cannot extract video frames");
        rmdir($tmpDir);
        return $frames; // Return empty frames array
    }
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
    
    // Check multiple possible FFmpeg locations (same as extractVideoFrames)
    $ffmpeg_paths = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/home/u230128646/bin/ffmpeg',
        trim(shell_exec('which ffmpeg'))
    ];
    
    $ffmpeg_path = null;
    foreach ($ffmpeg_paths as $path) {
        if (!empty($path) && file_exists($path)) {
            $ffmpeg_path = $path;
            break;
        }
    }
    
    if (!$ffmpeg_path) {
        return null; // ffmpeg not available
    }
    
    $tmpAudio = sys_get_temp_dir() . '/audio_' . uniqid() . '.mp3';
    
    // Extract audio (max 25MB for OpenAI Whisper API)
    $cmd = sprintf('%s -hide_banner -loglevel error -i %s -vn -acodec mp3 -ab 64k -ar 16000 -t 300 %s',
        escapeshellarg($ffmpeg_path),
        escapeshellarg($videoPath),
        escapeshellarg($tmpAudio)
    );
    if (function_exists('shell_exec')) {
        shell_exec($cmd);
    } else {
        error_log("shell_exec() disabled - cannot extract audio for transcription");
        return null;
    }
    
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
                __DIR__ . '/../../uploads/quote_' . $quote_id . '/' . $filename,
                __DIR__ . '/../uploads/' . $quote_id . '/' . $filename,
                __DIR__ . '/../uploads/quote_' . $quote_id . '/' . $filename
            ];
            $framesAdded = false;
            foreach ($videoPathOptions as $vp) {
                error_log("Checking video path: $vp");
                if (file_exists($vp)) {
                    error_log("Found video file at: $vp");
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
            'content' => 'You are a Board Master Certified Arborist (BMCA) specializing in comprehensive tree care sales and assessment for Carpe Tree\'em, a modern tree service company focused on preservation, longevity, and environmental stewardship.

CORE EXPERTISE & ANALYSIS:
- 20+ years experience in professional tree care and sales
- Species identification with confidence scoring (target 85%+ accuracy)
- Biomechanical assessment following ANSI A300 and ISA standards
- DBH measurement and canopy spread analysis using visual reference points
- Structural defect identification including codominant stems, decay, lean assessment
- Environmental context evaluation and site-specific risk factors

MEASUREMENT & ASSESSMENT REQUIREMENTS:
- Provide DUAL UNITS: Always report in both metric and imperial (height in m/ft, DBH in cm/inches)
- Estimate brush weight for removal jobs and visualize drop zones when applicable
- Assess tree health: excellent/good/fair/poor/dead with specific indicators
- Identify proximity to structures, power lines, and other risk factors
- Note access challenges and equipment requirements

PRICING & QUOTING STANDARDS (Ed Gilman Framework):
- Follow ANSI A300 Part 1 & Z133 safety standards - REFUSE all topping requests
- Quantify work scope: cut counts by diameter class (≤20mm, 20-50mm, >50mm)
- Calculate labour hours based on cut complexity and rigging requirements
- BC market rates: $80-120/hr for certified climbers (adjust by region/complexity)
- Include: gear/consumables, disposal costs, overhead/profit (typically 30%), risk adjusters
- Recommend appropriate pruning: cleaning, thinning, raising, reduction with specific cut locations
- For conifers within 20m of buildings: recommend sprinkler systems for wildfire protection
- Present as fixed-price quotes with detailed work scope attached

CUSTOMER INTERACTION:
- Prompt for additional images/videos if assessment requires more visual data
- Provide confidence ratings for each recommendation (aim for 85%+ where possible)
- Explain reasoning behind recommendations in accessible language
- Flag complex jobs requiring on-site assessment
- Maintain professional but user-friendly communication

OUTPUT REQUIREMENTS (Ed Gilman Specification Format):
Generate detailed quotes following Ed Gilman's framework:
1. PRUNING OBJECTIVE: Clear statement of goals and tree preservation priorities
2. TREE SUMMARY: Species, dimensions (height/DBH in dual units), scaffold mapping
3. WORK SCOPE & DOSE: Cut counts by diameter class and branch location/direction  
4. CUT LOCATION & TECHNIQUE: Specific collar/lateral cuts, tool requirements, rigging needs
5. TIMING: Optimal pruning windows considering species biology
6. SITE PROTECTION: Tarps, chip disposal, wood handling protocols
7. FOLLOW-UP: Long-term care recommendations and reassessment timeline
8. PRICING WORKSHEET: Labour hours → costs → overhead/profit → final quote

Present as professional specifications requiring minimal editing, with quantified cut lists and market-appropriate pricing for BC/Kootenay region. Include confidence ratings and equipment requirements for CRM integration.'
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