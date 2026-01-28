<?php
/**
 * OpenAI GPT-5 Analysis API
 * Provides advanced reasoning and creative tree care solutions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../utils/ai-run-guard.php';

function persist_gpt5_analysis($pdo, $quoteId, array $normalized): void {
    $stmt = $pdo->prepare("UPDATE quotes SET 
        ai_gpt5_analysis = ?,
        gpt5_analyzed_at = NOW(),
        status = CASE 
            WHEN status = 'new' THEN 'ai_processing'
            WHEN status = 'multi_ai_processing' THEN 'multi_ai_processing'
            ELSE status 
        END
        WHERE id = ?");
    $stmt->execute([json_encode($normalized, JSON_UNESCAPED_SLASHES), $quoteId]);
}

// Get request data (accept both JSON body and query)
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '[]', true);
$quote_id = $input['quote_id'] ?? ($_GET['quote_id'] ?? null);

if (!$quote_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Quote ID required']);
    exit;
}

$queueHandle = null;

try {
    // Get quote data
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();
    
    if (!$quote) {
        throw new Exception("Quote not found");
    }
    
    // Get media files
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $files = $stmt->fetchAll();
    
    // Prepare context for GPT-5
    $context = [
        'quote_id' => $quote['id'],
        'customer' => $quote['customer_name'],
        'location' => $quote['address'],
        'phone' => $quote['phone'],
        'email' => $quote['customer_email'],
        'media_count' => count($files),
        'current_status' => $quote['status'],
        'distance' => $quote['distance_km'] ?? 'unknown',
        'previous_analysis' => $quote['ai_gemini_analysis'] ?? null
    ];
    
    // Load system prompts (from editor-managed JSON)
    $prompts_file = __DIR__ . '/../ai/system_prompts.json';
    $system_prompt = 'You are an expert arborist. Return structured JSON as specified by the system. If unsure, state needs_distance_lookup or needs_geocoding.';
    if (file_exists($prompts_file)) {
        $prompts_json = json_decode(file_get_contents($prompts_file), true);
        if (isset($prompts_json['gpt5']['prompt'])) {
            $system_prompt = $prompts_json['gpt5']['prompt'];
        }
    }
    $prompt_md5 = md5($system_prompt);
    // Build user prompt with context (kept minimal; models will use system schema)
    $prompt = "QUOTE CONTEXT\n- Customer: {$context['customer']}\n- Location: {$context['location']}\n- Media Files: {$context['media_count']}\n- Distance: {$context['distance']} km\n- Current Status: {$context['current_status']}\n\nProvide analysis per the system instructions and return ONLY the JSON object.";

    // -------- Idempotent cache + mode handling --------
    $context_text = $quote['context'] ?? '';
    $file_fps = [];
    foreach ($files as $f) {
        $p = $f['file_path'] ?? '';
        if (!$p) { continue; }
        $abs = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($p, '/');
        $size = @filesize($abs) ?: 0;
        $mtime = @filemtime($abs) ?: 0;
        $file_fps[] = [$p, $size, $mtime];
    }
    $input_hash = sha1(json_encode(['model'=>'gpt5','files'=>$file_fps,'context'=>$context_text,'prompt'=>$prompt_md5]));
    $mode = ai_mode();
    $cachedPayload = ai_cache_fetch('gpt5', $input_hash);

    if ($mode === 'mock') {
        $mock = ai_mock_payload('gpt5');
        if (!$mock) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Mock GPT-5 payload not found. Add server/fixtures/ai/gpt5/mock.json or switch AI_MODE.',
                'mode' => 'mock',
                'quote_id' => $quote_id
            ]);
            return;
        }
        persist_gpt5_analysis($pdo, $quote_id, $mock);
        echo json_encode([
            'success' => true,
            'analysis' => $mock,
            'model' => 'gpt-5',
            'quote_id' => $quote_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'tokens_used' => 0,
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
                'error' => 'Replay cache miss for GPT-5. Run in live mode once to capture a fixture.',
                'mode' => 'replay',
                'quote_id' => $quote_id
            ]);
            return;
        }
        persist_gpt5_analysis($pdo, $quote_id, $cachedPayload);
        echo json_encode([
            'success' => true,
            'analysis' => $cachedPayload,
            'model' => 'gpt-5',
            'quote_id' => $quote_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'tokens_used' => 0,
            'cached' => true,
            'mode' => 'replay'
        ]);
        return;
    }

    if ($cachedPayload) {
        persist_gpt5_analysis($pdo, $quote_id, $cachedPayload);
        echo json_encode([
            'success' => true,
            'analysis' => $cachedPayload,
            'model' => 'gpt-5',
            'quote_id' => $quote_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'tokens_used' => 0,
            'cached' => true,
            'mode' => 'live'
        ]);
        return;
    }

    $budgetCheck = ai_budget_check('gpt5');
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

    // Call GPT-5 API (using OpenAI's latest model)
    $api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : (getenv('OPENAI_API_KEY') ?: '');
    
    $gpt_data = [
        'model' => 'gpt-4o', // Will be updated to gpt-5 when available
        'messages' => [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 2000,
        'temperature' => 0.7
    ];
    
    $analysis = null;
    $tokens_used = 0;
    if ($api_key) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gpt_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code === 200) {
            $gpt_response = json_decode($response, true);
            $analysis = $gpt_response['choices'][0]['message']['content'] ?? null;
            $tokens_used = $gpt_response['usage']['total_tokens'] ?? 0;
        }
    }
    // Tolerant JSON extraction and normalization so UI always renders
    $text = (string)($analysis ?? '');
    if ($text === '') {
        $text = "GPT-5 analysis (fallback) for Quote #{$quote_id}.";
    }
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
            'model' => 'gpt5',
            'services' => $services,
            'frames' => $parsed['frames'] ?? [],
            'raw' => $original_text,
            'errors' => []
        ];
    } else {
        $normalized = [
            'model' => 'gpt5',
            'services' => [],
            'frames' => [],
            'raw' => $original_text,
            'errors' => ['nonJSON']
        ];
    }

    persist_gpt5_analysis($pdo, $quote_id, $normalized);
    ai_cache_store('gpt5', $input_hash, $normalized);
    ai_budget_register('gpt5');
    
    // Log the analysis
    error_log("GPT-5 analysis completed for quote $quote_id");
    
    echo json_encode([
        'success' => true,
        'analysis' => $normalized,
        'model' => 'gpt-5',
        'quote_id' => $quote_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'tokens_used' => $tokens_used,
        'cached' => false,
        'mode' => 'live'
    ]);
    
    // Trigger media migration to static storage after successful analysis
    if (file_exists(__DIR__ . '/../utils/media_migrator.php')) {
        require_once __DIR__ . '/../utils/media_migrator.php';
        trigger_post_ai_migration($pdo, (int)$quote_id);
    }
    
} catch (Exception $e) {
    error_log("GPT-5 analysis error: " . $e->getMessage());
    
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'quote_id' => $quote_id,
        'mode' => ai_mode()
    ]);
} finally {
    if (is_resource($queueHandle)) {
        ai_queue_release($queueHandle);
    }
}
?>
