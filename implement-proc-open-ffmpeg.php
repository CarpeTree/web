<?php
// Implement video frame extraction using proc_open instead of shell_exec
header('Content-Type: text/plain');
echo "=== PROC_OPEN FFMPEG IMPLEMENTATION ===\n";

function extractVideoFrameWithProcOpen($video_path, $output_path, $time_seconds = 10) {
    $ffmpeg_path = '/home/u230128646/bin/ffmpeg';
    
    if (!file_exists($ffmpeg_path)) {
        return ['success' => false, 'error' => 'ffmpeg not found'];
    }
    
    if (!file_exists($video_path)) {
        return ['success' => false, 'error' => 'Video file not found'];
    }
    
    // Build ffmpeg command
    $cmd = [
        $ffmpeg_path,
        '-hide_banner',
        '-loglevel', 'error',
        '-ss', (string)$time_seconds,
        '-i', $video_path,
        '-vframes', '1',
        '-q:v', '2',
        '-y',
        $output_path
    ];
    
    echo "Command: " . implode(' ', array_map('escapeshellarg', $cmd)) . "\n";
    
    // Use proc_open instead of shell_exec
    $descriptorspec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w']   // stderr
    ];
    
    $process = proc_open($cmd, $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        return ['success' => false, 'error' => 'Failed to start process'];
    }
    
    // Close stdin
    fclose($pipes[0]);
    
    // Read stdout and stderr
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    // Wait for process to complete
    $return_code = proc_close($process);
    
    $result = [
        'success' => $return_code === 0 && file_exists($output_path),
        'return_code' => $return_code,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'output_file' => $output_path
    ];
    
    if ($result['success']) {
        $result['file_size'] = filesize($output_path);
    }
    
    return $result;
}

// Test the function
echo "\n1. TESTING PROC_OPEN FFMPEG:\n";

$video_path = 'uploads/21/IMG_0859.mov';
$output_path = 'test_frame_proc_open.jpg';

if (file_exists($video_path)) {
    echo "Testing frame extraction from: {$video_path}\n";
    
    $result = extractVideoFrameWithProcOpen($video_path, $output_path, 10);
    
    echo "Result:\n";
    echo "Success: " . ($result['success'] ? "✅ YES" : "❌ NO") . "\n";
    echo "Return code: {$result['return_code']}\n";
    echo "Stdout: " . (empty($result['stdout']) ? "(empty)" : $result['stdout']) . "\n";
    echo "Stderr: " . (empty($result['stderr']) ? "(empty)" : $result['stderr']) . "\n";
    
    if ($result['success']) {
        echo "✅ SUCCESS! Frame extracted: {$output_path}\n";
        echo "File size: " . round($result['file_size'] / 1024, 1) . "KB\n";
        
        // Test multiple frames
        echo "\n2. TESTING MULTIPLE FRAMES:\n";
        $frame_times = [5, 15, 30, 45];
        $extracted_frames = [];
        
        foreach ($frame_times as $time) {
            $frame_path = "test_frame_{$time}s.jpg";
            $frame_result = extractVideoFrameWithProcOpen($video_path, $frame_path, $time);
            
            if ($frame_result['success']) {
                $size = round($frame_result['file_size'] / 1024, 1);
                echo "✅ Frame at {$time}s: {$frame_path} ({$size}KB)\n";
                $extracted_frames[] = $frame_path;
            } else {
                echo "❌ Failed frame at {$time}s: " . $frame_result['stderr'] . "\n";
            }
        }
        
        if (!empty($extracted_frames)) {
            echo "\n3. SUCCESS SUMMARY:\n";
            echo "✅ Extracted " . count($extracted_frames) . " frames successfully!\n";
            echo "✅ proc_open method works with ffmpeg on Hostinger!\n";
            echo "✅ Ready to integrate into MediaPreprocessor!\n";
            
            echo "\nFrames for display:\n";
            foreach ($extracted_frames as $frame) {
                echo "- {$frame}\n";
            }
        }
        
    } else {
        echo "❌ Frame extraction failed\n";
        echo "This could be due to:\n";
        echo "- Video codec not supported\n";
        echo "- Permissions issues\n";
        echo "- ffmpeg version incompatibility\n";
        echo "- File corruption\n";
    }
} else {
    echo "❌ Video file not found: {$video_path}\n";
}

echo "\n=== TEST COMPLETE ===\n";

if (isset($result) && $result['success']) {
    echo "\n🎉 BREAKTHROUGH: We can extract video frames using proc_open!\n";
    echo "Next step: Update MediaPreprocessor to use this method.\n";
} else {
    echo "\n⚠️ Frame extraction failed. Will need alternative solution.\n";
}
?>