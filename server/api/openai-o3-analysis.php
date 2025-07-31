<?php
// OpenAI o3-pro-2025-06-10 Analysis - Premium reasoning model
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

    // Prepare media content for o3 (high detail)
    $media_content = [];
    $media_summary = [];
    
    foreach ($media_files as $media) {
        $file_path = $media['file_path'];
        $file_type = $media['file_type'];
        
        if (!file_exists($file_path)) {
            error_log("Media file not found: $file_path");
            continue;
        }
        
        $media_summary[] = [
            'filename' => $media['filename'],
            'type' => $file_type,
            'size' => formatFileSize($media['file_size'] ?? 0)
        ];
        
        if ($file_type === 'image') {
            $imageData = base64_encode(file_get_contents($file_path));
            $media_content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $media['mime_type'] . ';base64,' . $imageData,
                    'detail' => 'high' // o3 can handle high detail
                ]
            ];
        } elseif ($file_type === 'video') {
            // Extract frames for o3 analysis
            $size = round($media['file_size'] / (1024*1024), 1);
            $frames = extractVideoFrames($file_path, 5, 6); // Extract 6 frames
            if (!empty($frames)) {
                $media_content = array_merge($media_content, $frames);
            } else {
                $media_content[] = [
                    'type' => 'text',
                    'text' => "Video file: {$media['filename']} ({$size}MB). Frame extraction failed."
                ];
            }
        }
    }

    $services = json_decode($quote['selected_services'], true) ?: [];
    $services_text = implode(', ', $services);
    
    // o3 optimized prompt (detailed reasoning)
    $system_prompt = 'You are a Board Master Certified Arborist (BMCA) with 20+ years experience specializing in comprehensive tree care assessment for Carpe Tree\'em, a professional tree service company in British Columbia, Canada.

EXPERTISE & ANALYSIS REQUIREMENTS:
- Species identification with confidence scoring (target 90%+ accuracy)
- Biomechanical assessment following ANSI A300 and ISA standards  
- DBH measurement and canopy spread analysis using visual reference points
- Structural defect identification including codominant stems, decay, lean assessment
- Environmental context evaluation and site-specific risk factors
- Quantified risk assessment with priority rankings

MEASUREMENT & ASSESSMENT:
- Always report in DUAL UNITS: metric and imperial (height in m/ft, DBH in cm/inches)
- Estimate brush weight for removal jobs and visualize drop zones
- Assess tree health: excellent/good/fair/poor/dead with specific indicators
- Identify proximity to structures, power lines, risk factors
- Provide detailed cut lists and equipment requirements

Present as professional specifications requiring minimal editing, with quantified assessments and market-appropriate pricing for BC/Kootenay region.';

    $user_prompt = "Analyze this tree service request with detailed reasoning.

SERVICES REQUESTED: $services_text
CUSTOMER NOTES: " . ($quote['notes'] ?? 'None') . "

Provide comprehensive analysis including:
1. Detailed species identification with reasoning
2. Complete structural assessment 
3. Risk factor analysis with quantified ratings
4. Specific service recommendations with methodology
5. Equipment requirements and crew size
6. Detailed cost breakdown with justification
7. Timeline and seasonal considerations
8. Follow-up recommendations

Use your advanced reasoning to provide the most thorough professional assessment.";

    if (!empty($OPENAI_API_KEY) && !empty($media_content)) {
        $messages = [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => array_merge([['type' => 'text', 'text' => $user_prompt]], $media_content)
            ]
        ];

        $openai_request = [
            'model' => 'o3-pro-2025-06-10',
            'messages' => $messages,
            'max_completion_tokens' => 4000 // Higher limit for detailed analysis
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
            CURLOPT_TIMEOUT => 120 // Longer timeout for detailed reasoning
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code !== 200) {
            throw new Exception("OpenAI o3-pro-2025-06-10 API error: HTTP $http_code");
        }

        $ai_result = json_decode($response, true);
        
        if (!isset($ai_result['choices'][0]['message']['content'])) {
            throw new Exception("Invalid o3-pro-2025-06-10 response format");
        }

        $ai_analysis = $ai_result['choices'][0]['message']['content'];
        
        // Calculate cost (o3 pricing - higher cost for premium reasoning)
        $input_tokens = $ai_result['usage']['prompt_tokens'] ?? 0;
        $output_tokens = $ai_result['usage']['completion_tokens'] ?? 0;
        $cost = ($input_tokens * 0.000015) + ($output_tokens * 0.000060); // o3 premium rates
        
    } else {
        $ai_analysis = "⚠️ o3-pro-2025-06-10 analysis unavailable. API key: " . (empty($OPENAI_API_KEY) ? "missing" : "set") . ", Media: " . count($media_content) . " items";
        $cost = 0;
    }

    // Format the analysis
    $analysis_summary = "🧠 OpenAI o3-pro-2025-06-10 Analysis (Premium Reasoning)\n\n";
    $analysis_summary .= "📁 Media: " . implode(', ', array_column($media_summary, 'filename')) . "\n";
    $analysis_summary .= "💰 Cost: $" . number_format($cost, 4) . "\n\n";
    $analysis_summary .= "🔍 Detailed Analysis:\n" . $ai_analysis;

    // Store results in database with model identifier
    $analysis_data = [
        'model' => 'o3-pro-2025-06-10',
        'analysis' => $ai_analysis,
        'cost' => $cost,
        'media_count' => count($media_files),
        'timestamp' => date('Y-m-d H:i:s'),
        'tokens_used' => ($input_tokens ?? 0) + ($output_tokens ?? 0)
    ];

    $stmt = $pdo->prepare("
        UPDATE quotes 
        SET ai_o3_analysis = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([json_encode($analysis_data), $quote_id]);

    echo json_encode([
        'success' => true,
        'model' => 'o3',
        'quote_id' => $quote_id,
        'analysis' => $analysis_summary,
        'cost' => $cost,
        'media_count' => count($media_files)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'o3',
        'error' => $e->getMessage(),
        'quote_id' => $quote_id ?? null
    ]);
}

// Helper functions
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . 'MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . 'KB';
    }
    return $bytes . 'B';
}

function extractVideoFrames($videoPath, $secondsInterval = 5, $maxFrames = 6) {
    $frames = [];
    $ffmpeg_paths = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/opt/homebrew/bin/ffmpeg',
        'ffmpeg'
    ];
    
    $ffmpeg_path = null;
    foreach ($ffmpeg_paths as $path) {
        if (shell_exec("which $path 2>/dev/null")) {
            $ffmpeg_path = $path;
            break;
        }
    }
    
    if (!$ffmpeg_path) {
        return $frames;
    }
    
    $tmpDir = sys_get_temp_dir() . '/frames_' . uniqid();
    mkdir($tmpDir);
    
    $cmd = sprintf('%s -hide_banner -loglevel error -i %s -vf fps=1/%d -frames:v %d %s/frame_%%03d.jpg',
        escapeshellarg($ffmpeg_path),
        escapeshellarg($videoPath),
        (int)$secondsInterval,
        (int)$maxFrames,
        escapeshellarg($tmpDir)
    );
    
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
?>