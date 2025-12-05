<?php
/**
 * Gemini 3 Pro Analysis for Quotes with Extended Thinking
 * Uses Google's latest Gemini 3 Pro model with maximum thinking tokens
 * for advanced tree assessment and detailed arborist analysis
 */

require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../config/secure-config.php';
require_once __DIR__ . '/../utils/ai-run-guard.php';

function persist_gemini_analysis($pdo, $quoteId, array $normalized): void {
    $payload = json_encode($normalized, JSON_UNESCAPED_SLASHES);
    try {
        $stmt = $pdo->prepare("UPDATE quotes SET ai_gemini_analysis = ?, gemini_analyzed_at = NOW() WHERE id = ?");
        $stmt->execute([$payload, $quoteId]);
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("UPDATE quotes SET gemini_analysis = ?, gemini_analyzed_at = NOW() WHERE id = ?");
        $stmt->execute([$payload, $quoteId]);
    }
}

header('Content-Type: application/json');

// Accept JSON bodies as well as form/query
$raw = file_get_contents('php://input');
$json = json_decode($raw ?: '[]', true);
$quote_id = $json['quote_id'] ?? ($_POST['quote_id'] ?? ($_GET['quote_id'] ?? null));
$context = $json['context'] ?? ($_POST['context'] ?? '');

if (!$quote_id) {
    echo json_encode(['success' => false, 'error' => 'Quote ID is required.', 'quote_id' => null, 'line' => __LINE__, 'file' => __FILE__]);
    exit;
}

$queueHandle = null;

try {
    // Get quote data
    $stmt = $pdo->prepare("
        SELECT q.*, c.name as customer_name, c.email, c.phone, c.address
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quote) {
        echo json_encode(['success' => false, 'error' => 'Quote not found']);
        exit;
    }
    
    // Get media files - be tolerant of schema differences
    $media_files = [];
    try {
        $media_stmt = $pdo->prepare("SELECT file_path, original_name FROM uploaded_files WHERE quote_id = ?");
        $media_stmt->execute([$quote_id]);
        $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $media_files = []; }
    // Fallback to 'media' table
    if (empty($media_files)) {
        try {
            $m2 = $pdo->prepare("SELECT file_path, original_filename as original_name, filename FROM media WHERE quote_id = ? ORDER BY id ASC");
            $m2->execute([$quote_id]);
            $rows = $m2->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                if (!empty($r['file_path'])) {
                    $media_files[] = ['file_path' => $r['file_path'], 'original_name' => ($r['original_name'] ?? $r['filename'] ?? basename($r['file_path']))];
                }
            }
        } catch (Throwable $e3) { /* ignore */ }
    }
    
    // Prepare Gemini API request
    $api_key = $GOOGLE_GEMINI_API_KEY ?? '';
    if (empty($api_key)) {
        echo json_encode(['success' => false, 'error' => 'Gemini API key not configured']);
        exit;
    }
    
    // Use Gemini 3 Pro with thinking enabled for maximum reasoning
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-preview:generateContent?key=" . $api_key;
    
    // Build the prompt (load from system_prompts.json - prefer gemini3 key for Gemini 3 Pro)
    $system_prompt = 'You are an expert ISA Certified Arborist analyzing tree service requirements. Provide detailed, actionable recommendations based on visual evidence and context. Output JSON only with {"services":[], "frames":[], "raw":""}.';
    try {
        $spPath = __DIR__ . '/../ai/system_prompts.json';
        if (is_file($spPath)) {
            $sp = json_decode(file_get_contents($spPath), true);
            // Prefer gemini3 key for Gemini 3 Pro, then gemini as fallback
            if (isset($sp['gemini3']['prompt']) && is_string($sp['gemini3']['prompt'])) {
                $system_prompt = $sp['gemini3']['prompt'];
            } elseif (isset($sp['gemini']['prompt']) && is_string($sp['gemini']['prompt'])) {
                $system_prompt = $sp['gemini']['prompt'];
            }
        }
    } catch (Throwable $e) { /* ignore and keep default */ }
    
    $user_prompt = "Analyze this tree service request:\n";
    $user_prompt .= "Customer: {$quote['customer_name']}\n";
    $user_prompt .= "Location: {$quote['address']}\n";
    if (!empty($context)) { $user_prompt .= "Additional context: {$context}\n"; }
    
    // Try to include stored transcription early
    $transcript_text = '';
    try {
        $tstmt = $pdo->prepare("SELECT ai_transcription FROM quotes WHERE id = ?");
        $tstmt->execute([$quote_id]);
        $trow = $tstmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($trow['ai_transcription'])) {
            $transcript_text = $trow['ai_transcription'];
        } else {
            // Attempt on-demand transcription if none present
            $tx = @file_get_contents((isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '') . "/server/api/media-transcribe.php?quote_id=" . urlencode($quote_id));
            $tj = @json_decode($tx, true);
            if (is_array($tj) && !empty($tj['transcription'])) {
                $transcript_text = $tj['transcription'];
            }
        }
    } catch (Throwable $e) { /* optional column */ }
    if (!empty($transcript_text)) {
        $user_prompt .= "\nTranscription (include in analysis; multi-tree context):\n" . substr($transcript_text, 0, 8000) . "\n";
    }
    $user_prompt .= "Return ONLY JSON. If unsure, leave arrays empty and put full text in raw.";
    
    $parts = [ ['text' => $system_prompt], ['text' => $user_prompt] ];
    
    // Gather media from uploads dir and extract frames from videos (limited)
    $uploads_dir = realpath(__DIR__ . '/../uploads/quote_' . $quote_id) ?: null;
    $image_candidates = [];
    if ($uploads_dir && is_dir($uploads_dir)) {
        $scan = scandir($uploads_dir) ?: [];
        foreach ($scan as $fn) {
            if ($fn === '.' || $fn === '..') continue;
            $lower = strtolower($fn);
            $full = $uploads_dir . '/' . $fn;
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $lower)) {
                $image_candidates[] = $full;
            }
        }
        // Extract frames for videos if we have few/no images
        $videos = array_values(array_filter($scan, function($fn){ return preg_match('/\.(mov|mp4|m4v)$/i', $fn); }));
        if (count($image_candidates) < 3 && !empty($videos)) {
            $frames_dir = $uploads_dir . '/frames_gemini';
            if (!is_dir($frames_dir)) @mkdir($frames_dir, 0775, true);
            $max_frames_total = 6;
            $frames_added = 0;
            foreach ($videos as $vf) {
                if ($frames_added >= $max_frames_total) break;
                $in = $uploads_dir . '/' . $vf;
                $pattern = $frames_dir . '/' . pathinfo($vf, PATHINFO_FILENAME) . '_%03d.jpg';
                // Extract up to 3 frames per video at ~0.5 fps
                $cmd = "ffmpeg -hide_banner -loglevel error -y -i " . escapeshellarg($in) . " -vf fps=0.5,scale=640:-2 -qscale:v 4 " . escapeshellarg($pattern) . " 2>&1";
                @exec($cmd, $outLines, $ret);
                // Collect newly created frames
                $frame_scan = @scandir($frames_dir) ?: [];
                foreach ($frame_scan as $ff) {
                    if ($frames_added >= $max_frames_total) break;
                    if (preg_match('/\.(jpg|jpeg)$/i', $ff)) {
                        $image_candidates[] = $frames_dir . '/' . $ff;
                        $frames_added++;
                    }
                }
            }
        }
    }
    // Fallback to DB media table images
    if (empty($image_candidates)) {
        foreach ($media_files as $file) {
            $path = $file['file_path'] ?? '';
            if ($path && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path)) {
                $image_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
                if (file_exists($image_path)) {
                    $image_candidates[] = $image_path;
                }
            }
        }
    }

    $context_concat = trim((string)($quote['context'] ?? '') . ' ' . (string)$context . ' ' . substr($transcript_text, 0, 1500));
    $prompt_md5 = md5($system_prompt);
    $file_fps = [];
    foreach ($media_files as $file) {
        $path = $file['file_path'] ?? '';
        if (!$path) {
            continue;
        }
        $abs = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
        $file_fps[] = [$path, @filesize($abs) ?: 0, @filemtime($abs) ?: 0];
    }
    foreach ($image_candidates as $candidate) {
        $abs = realpath($candidate) ?: $candidate;
        $file_fps[] = [$abs, @filesize($abs) ?: 0, @filemtime($abs) ?: 0];
    }
    $input_hash = sha1(json_encode([
        'model' => 'gemini',
        'files' => $file_fps,
        'context' => $context_concat,
        'prompt' => $prompt_md5
    ]));

    $mode = ai_mode();
    $cachedPayload = ai_cache_fetch('gemini', $input_hash);

    if ($mode === 'mock') {
        $mock = ai_mock_payload('gemini');
        if (!$mock) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Mock Gemini payload not found. Add server/fixtures/ai/gemini/mock.json or switch AI_MODE.',
                'mode' => 'mock',
                'quote_id' => $quote_id
            ]);
            return;
        }
        persist_gemini_analysis($pdo, $quote_id, $mock);
        echo json_encode([
            'success' => true,
            'quote_id' => $quote_id,
            'analysis' => $mock,
            'model' => 'gemini-3-pro-preview',
            'thinking_budget' => 32768,
            'timestamp' => date('Y-m-d H:i:s'),
            'cached' => false,
            'mode' => 'mock'
        ]);
        return;
    }

    if ($mode === 'replay') {
        if (!$cachedPayload) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'Replay cache miss for Gemini. Run in live mode once to capture a fixture.',
                'mode' => 'replay',
                'quote_id' => $quote_id
            ]);
            return;
        }
        persist_gemini_analysis($pdo, $quote_id, $cachedPayload);
        echo json_encode([
            'success' => true,
            'quote_id' => $quote_id,
            'analysis' => $cachedPayload,
            'model' => 'gemini-3-pro-preview',
            'thinking_budget' => 32768,
            'timestamp' => date('Y-m-d H:i:s'),
            'cached' => true,
            'mode' => 'replay'
        ]);
        return;
    }

    if ($cachedPayload) {
        persist_gemini_analysis($pdo, $quote_id, $cachedPayload);
        echo json_encode([
            'success' => true,
            'quote_id' => $quote_id,
            'analysis' => $cachedPayload,
            'model' => 'gemini-3-pro-preview',
            'thinking_budget' => 32768,
            'timestamp' => date('Y-m-d H:i:s'),
            'cached' => true,
            'mode' => 'live'
        ]);
        return;
    }
    $budgetCheck = ai_budget_check('gemini');
    if (!$budgetCheck['ok']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => $budgetCheck['message'],
            'mode' => 'live',
            'quote_id' => $quote_id,
            'snapshot' => $budgetCheck['snapshot'] ?? null
        ]);
        return;
    }

    $queue = ai_queue_acquire('global');
    if (!$queue['ok']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => $queue['message'] ?? 'AI queue busy. Try again shortly.',
            'mode' => 'live',
            'quote_id' => $quote_id
        ]);
        return;
    }
    $queueHandle = $queue['handle'] ?? null;

    // Helper: try local path then HTTP fetch
    $fetch_image_bytes = function($absOrRel) {
        // Try as absolute/local
        if (is_file($absOrRel)) {
            return ['bytes' => @file_get_contents($absOrRel), 'mime' => @mime_content_type($absOrRel) ?: 'image/jpeg'];
        }
        // Try relative to document root
        $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($doc) {
            $local = $doc . '/' . ltrim($absOrRel, '/');
            if (is_file($local)) {
                return ['bytes' => @file_get_contents($local), 'mime' => @mime_content_type($local) ?: 'image/jpeg'];
            }
        }
        // Try HTTP(S)
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host) {
            foreach (['https','http'] as $scheme) {
                $url = $scheme . '://' . $host . '/' . ltrim($absOrRel, '/');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $data = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                if ($code === 200 && $data) {
                    return ['bytes' => $data, 'mime' => $ctype ?: 'image/jpeg'];
                }
            }
        }
        return ['bytes' => null, 'mime' => null];
    };

    // Attach up to 8 images/frames
    $attached_frames = [];
    $limit = 8;
    foreach ($image_candidates as $abs) {
        if ($limit-- <= 0) break;
        $f = $fetch_image_bytes($abs);
        if (!empty($f['bytes'])) {
            $parts[] = ['inline_data' => ['mime_type' => ($f['mime'] ?: 'image/jpeg'), 'data' => base64_encode($f['bytes'])]];
            $attached_frames[] = basename($abs);
        }
    }
    
    $request_body = [
        'contents' => [ [ 'parts' => $parts ] ],
        'generationConfig' => [ 
            'temperature' => 0.2,
            'topK' => 40, 
            'topP' => 0.9, 
            'thinking_level' => 'high'  // Maximum reasoning depth for Gemini 3 Pro
            // No maxOutputTokens limit - let model use full 65536 capacity
        ]
    ];
    
    // Make API request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes for image analysis
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Gemini API error: HTTP $http_code - $response - cURL: $curl_error");
        echo json_encode([
            'success' => false, 
            'error' => 'Gemini API request failed (HTTP ' . $http_code . ')', 
            'details' => $response,
            'curl_error' => $curl_error,
            'quote_id' => $quote_id
        ]);
        exit;
    }
    
    if (empty($response)) {
        error_log("Gemini API returned empty response for quote $quote_id");
        echo json_encode([
            'success' => false, 
            'error' => 'Gemini API returned empty response',
            'quote_id' => $quote_id
        ]);
        exit;
    }
    
    $result = json_decode($response, true);
    // Extract text or join parts
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($text === null && isset($result['candidates'][0]['content']['parts'])) {
        $acc = [];
        foreach ($result['candidates'][0]['content']['parts'] as $p) {
            if (isset($p['text'])) { $acc[] = $p['text']; }
        }
        $text = implode("\n\n", $acc);
    }
    $text = (string)($text ?? '');
    
    // Tolerant JSON extraction and normalization
    $original_text = $text;
    if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) { $text = $m[1]; }
    $start = strpos($text, '{'); $end = strrpos($text, '}');
    $parsed = null;
    if ($start !== false && $end !== false && $end > $start) {
        $maybe = substr($text, $start, $end - $start + 1);
        $parsed = json_decode($maybe, true);
        if ($parsed === null) { $parsed = json_decode($text, true); }
    }
    
    if (is_array($parsed)) {
        $services = [];
        if (isset($parsed['services']) && is_array($parsed['services'])) {
            $services = $parsed['services'];
        } elseif (isset($parsed['trees']) && is_array($parsed['trees'])) {
            foreach ($parsed['trees'] as $t) {
                if (isset($t['services']) && is_array($t['services'])) {
                    foreach ($t['services'] as $s) { $services[] = $s; }
                }
            }
        }
        $normalized = [
            'model' => 'gemini-3-pro-preview',
            'thinking_budget' => 32768,
            'services' => $services,
            'frames' => !empty($parsed['frames']) ? $parsed['frames'] : array_map(function($f){ return ['filename' => $f]; }, (count($attached_frames) ? $attached_frames : array_map('basename', $image_candidates))),
            'raw' => $original_text,
            'errors' => []
        ];
    } else {
        $normalized = [
            'model' => 'gemini-3-pro-preview',
            'thinking_budget' => 32768,
            'services' => [],
            'frames' => array_map(function($f){ return ['filename' => $f]; }, (count($attached_frames) ? $attached_frames : array_map('basename', $image_candidates))),
            'raw' => $original_text,
            'errors' => ['nonJSON']
        ];
    }
    
    persist_gemini_analysis($pdo, $quote_id, $normalized);
    ai_cache_store('gemini', $input_hash, $normalized);
    ai_budget_register('gemini');
    
    $debug = [
        'frames_attached' => count($attached_frames),
        'transcript_chars' => strlen($transcript_text),
        'image_candidates' => array_map('basename', $image_candidates)
    ];
    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'analysis' => $normalized,
        'model' => 'gemini-3-pro-preview',
        'thinking_budget' => 32768,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => $debug,
        'cached' => false,
        'mode' => 'live'
    ]);
    
} catch (Throwable $e) {
    error_log('Gemini analysis error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'mode' => ai_mode()]);
} finally {
    if (is_resource($queueHandle)) {
        ai_queue_release($queueHandle);
    }
}
?>
