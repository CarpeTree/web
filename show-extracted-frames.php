<?php
// Display extracted video frames for a quote
$quote_id = $_GET['quote_id'] ?? 69;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Extracted Frames - Quote #{$quote_id}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .frame { 
            display: inline-block; 
            margin: 10px; 
            border: 2px solid #ddd; 
            border-radius: 8px;
            overflow: hidden;
        }
        .frame img { 
            max-width: 300px; 
            height: auto; 
            display: block;
        }
        .frame-info {
            padding: 8px;
            background: #f5f5f5;
            font-size: 12px;
            text-align: center;
        }
        .no-frames {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }
    </style>
</head>
<body>";

echo "<h1>🎬 Extracted Video Frames - Quote #{$quote_id}</h1>";

$frames_dir = "uploads/frames/quote_{$quote_id}";
$frame_files = [];

if (is_dir($frames_dir)) {
    $frame_files = glob($frames_dir . "/*.jpg");
    sort($frame_files);
}

if (!empty($frame_files)) {
    echo "<p>✅ Found " . count($frame_files) . " extracted frames:</p>";
    
    foreach ($frame_files as $frame_file) {
        $filename = basename($frame_file);
        $relative_path = str_replace(__DIR__ . '/', '', $frame_file);
        $file_size = round(filesize($frame_file) / 1024, 1);
        
        // Extract time from filename (e.g., frame_01_5s.jpg)
        preg_match('/frame_(\d+)_(\d+)s\.jpg/', $filename, $matches);
        $frame_num = $matches[1] ?? '?';
        $time_seconds = $matches[2] ?? '?';
        
        echo "<div class='frame'>";
        echo "<img src='{$relative_path}' alt='Frame {$frame_num}'>";
        echo "<div class='frame-info'>";
        echo "Frame {$frame_num} @ {$time_seconds}s ({$file_size}KB)";
        echo "</div>";
        echo "</div>";
    }
    
    echo "<br style='clear: both;'>";
    echo "<p><strong>🤖 AI Analysis Impact:</strong> These frames provide the AI with actual visual data of your trees instead of just a text description, resulting in much more accurate species identification, health assessment, and pruning recommendations.</p>";
    
} else {
    echo "<div class='no-frames'>";
    echo "<h3>📹 No Frames Extracted Yet</h3>";
    echo "<p>Frames will be extracted when the video is processed for AI analysis.</p>";
    echo "<p>Directory checked: <code>{$frames_dir}</code></p>";
    echo "</div>";
}

// Show link to trigger frame extraction
echo "<hr>";
echo "<p><strong>🚀 Actions:</strong></p>";
echo "<a href='test-video-processing-final.php' style='margin-right: 10px;'>Test Video Processing</a>";
echo "<a href='trigger-single-model.php?quote_id={$quote_id}&model=o4-mini' style='margin-right: 10px;'>Trigger AI Analysis</a>";
echo "<a href='view-ai-analysis.php'>View AI Results</a>";

echo "</body></html>";
?>