<?php
// View AI conversation transcript for debugging and transparency
header('Content-Type: text/html; charset=UTF-8');

$quote_id = $_GET['quote_id'] ?? 69;

require_once 'server/config/config.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    die("Database connection failed");
}

// Get the quote data
$stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch();

if (!$quote) {
    die("Quote #{$quote_id} not found");
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>AI Transcript - Quote #{$quote_id}</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        .transcript-section { 
            background: white; 
            margin: 20px 0; 
            border-radius: 12px; 
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .section-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            padding: 15px 20px; 
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-content { padding: 20px; }
        .message { 
            margin: 15px 0; 
            padding: 15px; 
            border-radius: 8px; 
            border-left: 4px solid #ddd;
        }
        .system-message { 
            background: #f1f3f4; 
            border-left-color: #34a853;
        }
        .user-message { 
            background: #e3f2fd; 
            border-left-color: #1976d2;
        }
        .assistant-message { 
            background: #f3e5f5; 
            border-left-color: #7b1fa2;
        }
        .message-role { 
            font-weight: bold; 
            margin-bottom: 8px; 
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .message-content { 
            white-space: pre-wrap; 
            line-height: 1.5;
        }
        .json-data { 
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
            border-radius: 6px; 
            padding: 15px; 
            font-family: 'Courier New', monospace; 
            font-size: 13px;
            overflow-x: auto;
        }
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin: 20px 0;
        }
        .stat-card { 
            background: white; 
            padding: 15px; 
            border-radius: 8px; 
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-value { font-size: 24px; font-weight: bold; color: #1976d2; }
        .stat-label { font-size: 12px; color: #666; margin-top: 5px; }
        .image-preview { 
            max-width: 200px; 
            max-height: 150px; 
            border-radius: 8px; 
            margin: 10px 0;
            border: 2px solid #ddd;
        }
        .frames-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .frame-item {
            text-align: center;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
        }
        .nav-links {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .nav-links a {
            color: #1976d2;
            text-decoration: none;
            margin-right: 15px;
            padding: 8px 12px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .nav-links a:hover {
            background: #e3f2fd;
        }
    </style>
</head>
<body>";

echo "<div class='container'>";
echo "<h1>🤖 AI Conversation Transcript - Quote #{$quote_id}</h1>";

// Navigation
echo "<div class='nav-links'>";
echo "<a href='admin-dashboard.html'>← Admin Dashboard</a>";
echo "<a href='view-ai-analysis.php'>View Analysis</a>";
echo "<a href='show-extracted-frames.php?quote_id={$quote_id}'>View Frames</a>";
echo "<a href='trigger-single-model.php?quote_id={$quote_id}&model=o4-mini'>Trigger o4-mini</a>";
echo "<a href='trigger-single-model.php?quote_id={$quote_id}&model=o3'>Trigger o3</a>";
echo "</div>";

// Check for extracted frames
echo "<div class='transcript-section'>";
echo "<div class='section-header'>📸 Extracted Video Frames</div>";
echo "<div class='section-content'>";

$frames_dir = "uploads/frames/quote_{$quote_id}";
$frame_files = [];
if (is_dir($frames_dir)) {
    $frame_files = glob($frames_dir . "/*.jpg");
    sort($frame_files);
}

if (!empty($frame_files)) {
    echo "<p>✅ " . count($frame_files) . " frames extracted from video:</p>";
    echo "<div class='frames-gallery'>";
    foreach ($frame_files as $frame_file) {
        $filename = basename($frame_file);
        $relative_path = str_replace(__DIR__ . '/', '', $frame_file);
        echo "<div class='frame-item'>";
        echo "<img src='{$relative_path}' alt='{$filename}' class='image-preview'>";
        echo "<div style='font-size: 11px; margin-top: 5px;'>{$filename}</div>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<p>⚠️ No frames extracted yet. Video processing may be using text fallback.</p>";
}
echo "</div></div>";

// Process each AI model
$models = [
    'ai_o4_mini_analysis' => ['name' => '📱 OpenAI o4-mini', 'color' => '#28a745'],
    'ai_o3_analysis' => ['name' => '🧠 OpenAI o3', 'color' => '#17a2b8'],
    'ai_gemini_analysis' => ['name' => '💎 Google Gemini', 'color' => '#ffc107']
];

foreach ($models as $column => $model_info) {
    if (empty($quote[$column])) {
        continue;
    }
    
    $analysis_data = json_decode($quote[$column], true);
    if (!$analysis_data) {
        continue;
    }
    
    echo "<div class='transcript-section'>";
    echo "<div class='section-header' style='background: {$model_info['color']};'>";
    echo "<span>{$model_info['name']} Conversation</span>";
    if (isset($analysis_data['timestamp'])) {
        echo "<span style='margin-left: auto; font-size: 12px;'>{$analysis_data['timestamp']}</span>";
    }
    echo "</div>";
    
    echo "<div class='section-content'>";
    
    // Stats
    if (isset($analysis_data['input_tokens']) || isset($analysis_data['output_tokens'])) {
        echo "<div class='stats'>";
        if (isset($analysis_data['input_tokens'])) {
            echo "<div class='stat-card'>";
            echo "<div class='stat-value'>" . number_format($analysis_data['input_tokens']) . "</div>";
            echo "<div class='stat-label'>Input Tokens</div>";
            echo "</div>";
        }
        if (isset($analysis_data['output_tokens'])) {
            echo "<div class='stat-card'>";
            echo "<div class='stat-value'>" . number_format($analysis_data['output_tokens']) . "</div>";
            echo "<div class='stat-label'>Output Tokens</div>";
            echo "</div>";
        }
        if (isset($analysis_data['cost'])) {
            echo "<div class='stat-card'>";
            echo "<div class='stat-value'>$" . number_format($analysis_data['cost'], 4) . "</div>";
            echo "<div class='stat-label'>API Cost</div>";
            echo "</div>";
        }
        if (isset($analysis_data['processing_time_ms'])) {
            echo "<div class='stat-card'>";
            echo "<div class='stat-value'>" . round($analysis_data['processing_time_ms'] / 1000, 1) . "s</div>";
            echo "<div class='stat-label'>Processing Time</div>";
            echo "</div>";
        }
        echo "</div>";
    }
    
    // Analysis Result
    if (isset($analysis_data['analysis'])) {
        echo "<h4>📋 Analysis Result:</h4>";
        echo "<div class='json-data'>" . json_encode($analysis_data['analysis'], JSON_PRETTY_PRINT) . "</div>";
    }
    
    // Raw data
    echo "<h4>🔧 Raw API Response:</h4>";
    echo "<div class='json-data'>" . json_encode($analysis_data, JSON_PRETTY_PRINT) . "</div>";
    
    echo "</div>";
    echo "</div>";
}

if (empty($quote['ai_o4_mini_analysis']) && empty($quote['ai_o3_analysis']) && empty($quote['ai_gemini_analysis'])) {
    echo "<div class='transcript-section'>";
    echo "<div class='section-header'>⚠️ No AI Analysis Available</div>";
    echo "<div class='section-content'>";
    echo "<p>No AI analysis found for this quote. Try triggering the analysis:</p>";
    echo "<a href='trigger-single-model.php?quote_id={$quote_id}&model=o4-mini' style='margin-right: 10px;'>🚀 Trigger o4-mini</a>";
    echo "<a href='trigger-single-model.php?quote_id={$quote_id}&model=o3'>🚀 Trigger o3</a>";
    echo "</div>";
    echo "</div>";
}

echo "</div>";
echo "</body></html>";
?>