<?php
// Fix ffmpeg codec issues and test multiple extraction methods
header('Content-Type: text/plain');
echo "=== FIXING FFMPEG CODEC ISSUES ===\n";

function extractVideoFrameWithProcOpen($video_path, $output_path, $time_seconds = 10, $method = 1) {
    $ffmpeg_path = '/home/u230128646/bin/ffmpeg';
    
    if (!file_exists($ffmpeg_path)) {
        return ['success' => false, 'error' => 'ffmpeg not found'];
    }
    
    if (!file_exists($video_path)) {
        return ['success' => false, 'error' => 'Video file not found'];
    }
    
    // Multiple extraction methods with different codec parameters
    $methods = [
        1 => [ // Original method
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', (string)$time_seconds,
            '-i', $video_path, '-vframes', '1', '-q:v', '2', '-y', $output_path
        ],
        2 => [ // PNG output (more compatible)
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', (string)$time_seconds,
            '-i', $video_path, '-vframes', '1', '-f', 'image2', '-y', str_replace('.jpg', '.png', $output_path)
        ],
        3 => [ // Force libx264 codec
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', (string)$time_seconds,
            '-i', $video_path, '-vframes', '1', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-y', $output_path
        ],
        4 => [ // Simple copy method
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', (string)$time_seconds,
            '-i', $video_path, '-vframes', '1', '-c:v', 'copy', '-y', $output_path
        ],
        5 => [ // BMP output (very compatible)
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', (string)$time_seconds,
            '-i', $video_path, '-vframes', '1', '-f', 'bmp', '-y', str_replace('.jpg', '.bmp', $output_path)
        ],
        6 => [ // Force mjpeg encoder
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', (string)$time_seconds,
            '-i', $video_path, '-vframes', '1', '-c:v', 'mjpeg', '-q:v', '3', '-y', $output_path
        ]
    ];
    
    $cmd = $methods[$method] ?? $methods[1];
    $actual_output = $cmd[count($cmd) - 1]; // Get the actual output path from command
    
    echo "Method {$method} Command: " . implode(' ', array_map('escapeshellarg', $cmd)) . "\n";
    
    // Use proc_open
    $descriptorspec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w']   // stderr
    ];
    
    $process = proc_open($cmd, $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return ['success' => false, 'error' => 'Failed to start process'];
    }
    
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $return_code = proc_close($process);
    
    $result = [
        'success' => $return_code === 0 && file_exists($actual_output),
        'return_code' => $return_code,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'output_file' => $actual_output,
        'method' => $method
    ];
    
    if ($result['success']) {
        $result['file_size'] = filesize($actual_output);
    }
    
    return $result;
}

// Test video info first
function getVideoInfo($video_path) {
    $ffmpeg_path = '/home/u230128646/bin/ffmpeg';
    
    $cmd = [
        $ffmpeg_path, '-hide_banner', '-i', $video_path
    ];
    
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    
    $process = proc_open($cmd, $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return "Failed to get video info";
    }
    
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    proc_close($process);
    
    return $stderr; // ffmpeg outputs info to stderr
}

// Main test
$video_path = 'uploads/21/IMG_0859.mov';

if (!file_exists($video_path)) {
    echo "❌ Video file not found: {$video_path}\n";
    echo "=== TEST COMPLETE ===\n";
    exit;
}

echo "\n1. VIDEO INFO:\n";
$video_info = getVideoInfo($video_path);
echo $video_info . "\n";

echo "\n2. TESTING MULTIPLE EXTRACTION METHODS:\n";

$successful_methods = [];

// Test all 6 methods
for ($method = 1; $method <= 6; $method++) {
    echo "\n--- METHOD {$method} ---\n";
    
    $output_path = "test_frame_method_{$method}.jpg";
    $result = extractVideoFrameWithProcOpen($video_path, $output_path, 10, $method);
    
    echo "Success: " . ($result['success'] ? "✅ YES" : "❌ NO") . "\n";
    echo "Return code: {$result['return_code']}\n";
    
    if (!empty($result['stderr'])) {
        echo "Error: " . $result['stderr'] . "\n";
    }
    
    if ($result['success']) {
        $size = round($result['file_size'] / 1024, 1);
        echo "✅ SUCCESS! File: {$result['output_file']} ({$size}KB)\n";
        $successful_methods[] = $method;
        
        // Test multiple time points with successful method
        if (count($successful_methods) == 1) {
            echo "\n3. TESTING MULTIPLE FRAMES WITH METHOD {$method}:\n";
            $frame_times = [5, 15, 30];
            
            foreach ($frame_times as $time) {
                $frame_path = "success_frame_{$time}s.jpg";
                $frame_result = extractVideoFrameWithProcOpen($video_path, $frame_path, $time, $method);
                
                if ($frame_result['success']) {
                    $size = round($frame_result['file_size'] / 1024, 1);
                    echo "✅ Frame at {$time}s: ({$size}KB)\n";
                } else {
                    echo "❌ Failed frame at {$time}s\n";
                }
            }
        }
        break; // Stop at first successful method
    }
}

echo "\n4. FINAL RESULTS:\n";

if (!empty($successful_methods)) {
    echo "🎉 SUCCESS! Working methods: " . implode(', ', $successful_methods) . "\n";
    echo "✅ Frame extraction is possible on Hostinger!\n";
    echo "✅ Ready to integrate into MediaPreprocessor!\n";
    echo "\n🔗 View extracted frames:\n";
    
    // List all created image files
    $image_files = glob('test_frame_*.{jpg,png,bmp}', GLOB_BRACE);
    $success_files = glob('success_frame_*.{jpg,png,bmp}', GLOB_BRACE);
    
    foreach (array_merge($image_files, $success_files) as $file) {
        if (file_exists($file)) {
            $size = round(filesize($file) / 1024, 1);
            echo "- https://carpetree.com/{$file} ({$size}KB)\n";
        }
    }
    
} else {
    echo "❌ All methods failed. Codec/format issues persist.\n";
    echo "Alternative: Consider video format conversion or client-side extraction.\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>