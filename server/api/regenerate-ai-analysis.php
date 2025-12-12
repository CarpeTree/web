<?php
// Regenerate AI Analysis with Additional Context
header('Content-Type: application/json');

// CORS not needed beyond same-origin; remove permissive wildcard

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Admin API key guard (optional; enforced if ADMIN_API_KEY is set)
function require_admin_key() {
    $expected = getenv('ADMIN_API_KEY') ?: ($_ENV['ADMIN_API_KEY'] ?? null);
    if (!$expected) return;
    $provided = $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? ($_GET['admin_key'] ?? $_POST['admin_key'] ?? null);
    if (!$provided || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
}
require_admin_key();

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
                __DIR__ . '/../../uploads/quote_' . $quote_id . '/' . $filename,
                __DIR__ . '/../uploads/' . $quote_id . '/' . $filename,
                __DIR__ . '/../uploads/quote_' . $quote_id . '/' . $filename
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
    
    $base_prompt .= "\n\nProvide detailed analysis including specific recommendations, estimated costs, and safety considerations. Focus on what you can see in the images/videos.

CRITICAL: Use deep reasoning and analysis. Take time to:
1. Carefully examine each image/video frame for details
2. Consider multiple possible interpretations of what you observe
3. Cross-reference observations with arboricultural best practices
4. Provide step-by-step reasoning for your conclusions
5. Justify all cost estimates with detailed breakdowns
6. Include confidence levels and explain any uncertainties

This analysis will be used for professional tree service quotes - accuracy and thoroughness are essential.";

    // Prepare user content with media
    $user_content = [
        ['type' => 'text', 'text' => $base_prompt]
    ];
    $user_content = array_merge($user_content, $media_content);

    // OpenAI API request - GPT-5.2 with maximum thinking
    $openai_request = [
        'model' => 'gpt-5.2',
        'reasoning_effort' => 'xhigh',
        'temperature' => 0.1,
        'messages' => [
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

REGENERATION CONTEXT:
Admin has requested regenerated analysis with additional context. Provide enhanced assessment incorporating any new information while maintaining professional standards and confidence ratings.

REASONING REQUIREMENTS:
- Take time to thoroughly analyze all visual evidence before drawing conclusions
- Show your reasoning process for complex assessments
- Consider multiple hypotheses and explain why you selected your final assessment
- Provide detailed justification for all recommendations and cost estimates
- Cross-reference visual observations with industry best practices
- Question initial assumptions and validate conclusions against available evidence

OUTPUT REQUIREMENTS (Ed Gilman Specification Format):
Generate detailed quotes following Ed Gilman\'s framework:
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