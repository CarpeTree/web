<?php
/**
 * Whisper Transcription API
 * Transcribes audio/video files using OpenAI Whisper API
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';

// Accept JSON bodies as well as form/query
$raw = file_get_contents('php://input');
$json = json_decode($raw ?: '[]', true);
$quote_id = $json['quote_id'] ?? ($_POST['quote_id'] ?? ($_GET['quote_id'] ?? null));

if (!$quote_id) {
    echo json_encode(['success' => false, 'error' => 'Quote ID is required']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if transcription already exists
    $stmt = $pdo->prepare("SELECT ai_transcription FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($existing['ai_transcription'])) {
        echo json_encode([
            'success' => true,
            'transcription' => $existing['ai_transcription'],
            'cached' => true,
            'quote_id' => $quote_id
        ]);
        exit;
    }
    
    // Get media files for this quote
    $media_stmt = $pdo->prepare("SELECT file_path, filename, mime_type FROM media WHERE quote_id = ? ORDER BY id ASC");
    $media_stmt->execute([$quote_id]);
    $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($media_files)) {
        echo json_encode(['success' => false, 'error' => 'No media files found for this quote']);
        exit;
    }
    
    // Find audio/video files
    $audio_video_files = [];
    $uploads_dir = realpath(__DIR__ . '/../uploads/quote_' . $quote_id);
    
    if ($uploads_dir && is_dir($uploads_dir)) {
        $scan = scandir($uploads_dir) ?: [];
        foreach ($scan as $fn) {
            if ($fn === '.' || $fn === '..') continue;
            $lower = strtolower($fn);
            if (preg_match('/\.(mp3|mp4|m4a|wav|webm|mov|avi|ogg|flac)$/i', $lower)) {
                $audio_video_files[] = $uploads_dir . '/' . $fn;
            }
        }
    }
    
    if (empty($audio_video_files)) {
        echo json_encode([
            'success' => false, 
            'error' => 'No audio/video files found for transcription',
            'quote_id' => $quote_id
        ]);
        exit;
    }
    
    // Get OpenAI API key
    $api_key = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?? '';
    if (empty($api_key)) {
        echo json_encode(['success' => false, 'error' => 'OpenAI API key not configured']);
        exit;
    }
    
    $all_transcriptions = [];
    
    foreach ($audio_video_files as $file_path) {
        $file_size = filesize($file_path);
        
        // Whisper has a 25MB limit
        if ($file_size > 25 * 1024 * 1024) {
            $all_transcriptions[] = "[File " . basename($file_path) . " too large for Whisper API (>25MB)]";
            continue;
        }
        
        // Prepare the file for upload
        $cfile = new CURLFile($file_path);
        
        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => $cfile,
            'model' => 'whisper-1',
            'response_format' => 'text'
        ]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200 && !empty($response)) {
            $all_transcriptions[] = "[" . basename($file_path) . "]: " . trim($response);
        } else {
            error_log("Whisper API error for $file_path: HTTP $http_code - $response - $curl_error");
            $all_transcriptions[] = "[" . basename($file_path) . "]: (transcription failed)";
        }
    }
    
    $full_transcription = implode("\n\n", $all_transcriptions);
    
    // Save to database
    if (!empty($full_transcription)) {
        $update_stmt = $pdo->prepare("UPDATE quotes SET ai_transcription = ? WHERE id = ?");
        $update_stmt->execute([$full_transcription, $quote_id]);
    }
    
    echo json_encode([
        'success' => true,
        'transcription' => $full_transcription,
        'files_processed' => count($audio_video_files),
        'cached' => false,
        'quote_id' => $quote_id
    ]);
    
} catch (Exception $e) {
    error_log('Whisper transcription error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'quote_id' => $quote_id
    ]);
}
?>

