<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database-simple.php';

function read_quote_row(PDO $pdo, int $quote_id) {
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ? LIMIT 1");
    $stmt->execute([$quote_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function safe_json_str($value) {
    if ($value === null || $value === '') return null;
    if (is_array($value) || is_object($value)) return json_encode($value, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if (is_string($value)) return $value;
    return json_encode($value, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
}

function coerce_analysis_text($rawCol) {
    if (!$rawCol) return null;
    $decoded = json_decode($rawCol, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // not JSON → return raw string
        return $rawCol;
    }
    // Prefer nested 'analysis' if present, else full object
    $payload = $decoded['analysis'] ?? $decoded['canonical'] ?? $decoded;
    // If canonical object exists with 'raw', include that; else stringify payload
    if (is_array($payload) && isset($payload['raw']) && is_string($payload['raw'])) {
        return $payload['raw'];
    }
    return json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
}

function collect_images(int $quote_id) {
    $images = [];
    $base_dir = realpath(__DIR__ . '/../uploads/quote_' . $quote_id) ?: null;
    $push_file = function($absPath) use (&$images, $quote_id) {
        $doc = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        $url = null;
        if ($doc && strpos($absPath, $doc) === 0) {
            $rel = substr($absPath, strlen($doc));
            $url = $rel;
        } else {
            // assume server directory structure: /server/uploads/quote_<id>/...
            $rel = '/server/uploads/quote_' . $quote_id . '/' . basename($absPath);
            $url = $rel;
        }
        $images[] = [ 'url' => $url, 'source' => basename($absPath) ];
    };
    if ($base_dir && is_dir($base_dir)) {
        // Top-level images
        $scan = @scandir($base_dir) ?: [];
        foreach ($scan as $fn) {
            if ($fn === '.' || $fn === '..') continue;
            $lower = strtolower($fn);
            $full = $base_dir . '/' . $fn;
            if (is_file($full) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $lower)) {
                $push_file($full);
            }
        }
        // Frames subdirectories (common names)
        foreach (['frames', 'frames_gpt5', 'frames_gemini'] as $dir) {
            $fd = $base_dir . '/' . $dir;
            if (!is_dir($fd)) continue;
            $fscan = @scandir($fd) ?: [];
            foreach ($fscan as $ff) {
                if ($ff === '.' || $ff === '..') continue;
                $lower = strtolower($ff);
                $full = $fd . '/' . $ff;
                if (is_file($full) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $lower)) {
                    $images[] = [ 'url' => '/server/uploads/quote_' . $quote_id . '/' . $dir . '/' . $ff, 'source' => $ff ];
                }
            }
        }
    }
    return $images;
}

try {
    $quote_id = isset($_GET['quote_id']) ? intval($_GET['quote_id']) : (isset($_POST['quote_id']) ? intval($_POST['quote_id']) : 0);
    if ($quote_id <= 0) { echo json_encode(['success' => false, 'error' => 'quote_id required']); exit; }

    global $pdo;
    $row = read_quote_row($pdo, $quote_id);
    if (!$row) { echo json_encode(['success' => false, 'error' => 'Quote not found']); exit; }

    // Pull GPT-5 and Gemini from flexible columns
    $gpt_text = null;
    foreach (['ai_gpt5_analysis','ai_o4_mini_analysis','gpt_analysis'] as $col) {
        if (!empty($row[$col])) { $gpt_text = coerce_analysis_text($row[$col]); break; }
    }
    $gem_text = null;
    foreach (['ai_gemini_analysis','gemini_analysis'] as $col) {
        if (!empty($row[$col])) { $gem_text = coerce_analysis_text($row[$col]); break; }
    }

    // Transcription (try ai_transcription; if JSON, pass JSON)
    $transcription = null;
    if (!empty($row['ai_transcription'])) {
        $try = json_decode($row['ai_transcription'], true);
        $transcription = (json_last_error() === JSON_ERROR_NONE) ? $try : $row['ai_transcription'];
    }

    $images = collect_images($quote_id);

    echo json_encode([
        'success' => true,
        'quote_id' => $quote_id,
        'gpt5_analysis' => $gpt_text,
        'gemini_analysis' => $gem_text,
        'transcription' => $transcription,
        'images' => $images
    ], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>




