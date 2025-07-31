<?php
// Google Gemini 2.5 Pro Analysis - Advanced multimodal reasoning
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

    $services = json_decode($quote['selected_services'], true) ?: [];
    $services_text = implode(', ', $services);
    
    $media_summary = [];
    $ai_analysis = '';
    $total_cost = 0;
    
    if (!empty($GOOGLE_GEMINI_API_KEY)) {
        try {
            require_once __DIR__ . '/../utils/gemini-client.php';
            $gemini = new GeminiClient();
            
            // Process each media file with Gemini 2.5 Pro
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
                    $analysis = $gemini->analyzeImageWithModel(
                        $file_path, 
                        $services, 
                        $quote['notes'] ?? '',
                        'gemini-2.5-pro' // Specify the exact model
                    );
                    $ai_analysis .= "\n\n📸 IMAGE ANALYSIS - {$media['filename']} (Gemini 2.5 Pro):\n" . $analysis['analysis'];
                    $total_cost += $analysis['cost'] ?? 0;
                    
                } elseif ($file_type === 'video') {
                    $analysis = $gemini->analyzeVideoWithModel(
                        $file_path, 
                        $services, 
                        $quote['notes'] ?? '',
                        'gemini-2.5-pro' // Specify the exact model
                    );
                    $ai_analysis .= "\n\n🎥 VIDEO ANALYSIS - {$media['filename']} (Gemini 2.5 Pro):\n" . $analysis['analysis'];
                    $total_cost += $analysis['cost'] ?? 0;
                }
            }
            
        } catch (Exception $e) {
            error_log("Gemini 2.5 Pro analysis failed: " . $e->getMessage());
            $ai_analysis = "⚠️ Gemini 2.5 Pro analysis failed: " . $e->getMessage();
        }
    } else {
        $ai_analysis = "⚠️ Gemini 2.5 Pro analysis unavailable. API key not configured.";
    }

    // Format the analysis
    $analysis_summary = "🔮 Google Gemini 2.5 Pro Analysis (Advanced Multimodal)\n\n";
    $analysis_summary .= "📁 Media: " . implode(', ', array_column($media_summary, 'filename')) . "\n";
    $analysis_summary .= "💰 Cost: $" . number_format($total_cost, 4) . "\n\n";
    $analysis_summary .= "🔍 Multimodal Analysis:" . $ai_analysis;

    // Store results in database with model identifier
    $analysis_data = [
        'model' => 'gemini-2.5-pro',
        'analysis' => $ai_analysis,
        'cost' => $total_cost,
        'media_count' => count($media_files),
        'timestamp' => date('Y-m-d H:i:s')
    ];

    $stmt = $pdo->prepare("
        UPDATE quotes 
        SET ai_gemini_analysis = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([json_encode($analysis_data), $quote_id]);

    echo json_encode([
        'success' => true,
        'model' => 'gemini-2.5-pro',
        'quote_id' => $quote_id,
        'analysis' => $analysis_summary,
        'cost' => $total_cost,
        'media_count' => count($media_files)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'model' => 'gemini-2.5-pro',
        'error' => $e->getMessage(),
        'quote_id' => $quote_id ?? null
    ]);
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . 'MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . 'KB';
    }
    return $bytes . 'B';
}
?>