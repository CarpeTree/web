<?php
// Test the new JPEG encoding ffmpeg command
header('Content-Type: text/plain');
echo "=== TESTING JPEG ENCODING FFMPEG COMMAND ===\n";

$video_path = 'uploads/21/IMG_0859.mov';
$ffmpeg_path = '/home/u230128646/bin/ffmpeg';

if (!file_exists($video_path)) {
    echo "❌ Video file not found\n";
    exit;
}

if (!file_exists($ffmpeg_path)) {
    echo "❌ ffmpeg not found\n";
    exit;
}

echo "✅ Video file exists: {$video_path}\n";
echo "✅ ffmpeg exists: {$ffmpeg_path}\n";

$tmpDir = sys_get_temp_dir() . '/jpeg_test_' . uniqid();
@mkdir($tmpDir);
echo "Test directory: {$tmpDir}\n";

// Test the new JPEG encoding command
$timePos = 10;
$frameFile = $tmpDir . '/test_jpeg_frame.jpg';

echo "\n1. TESTING NEW JPEG ENCODING METHOD:\n";

$cmd = [
    $ffmpeg_path,
    '-hide_banner',
    '-loglevel', 'error',
    '-ss', (string)$timePos,
    '-i', $video_path,
    '-vframes', '1',
    '-f', 'image2',
    '-vcodec', 'mjpeg',
    '-q:v', '2',
    '-y',
    $frameFile
];

echo "Command: " . implode(' ', array_map('escapeshellarg', $cmd)) . "\n";

$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$process = proc_open($cmd, $descriptorspec, $pipes);

if (is_resource($process)) {
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $return_code = proc_close($process);
    
    echo "Return code: {$return_code}\n";
    echo "Stdout: " . (empty($stdout) ? "(empty)" : $stdout) . "\n";
    echo "Stderr: " . (empty($stderr) ? "(empty)" : $stderr) . "\n";
    
    if (file_exists($frameFile)) {
        $size = filesize($frameFile);
        echo "✅ JPEG file created! Size: " . round($size / 1024, 1) . "KB\n";
        
        // Test if it's a valid JPEG
        $image_info = @getimagesize($frameFile);
        if ($image_info !== false) {
            echo "✅ Valid image: {$image_info[0]}x{$image_info[1]}, Type: {$image_info['mime']}\n";
            
            // Test base64 encoding
            $imageData = base64_encode(file_get_contents($frameFile));
            $data_url = 'data:image/jpeg;base64,' . $imageData;
            echo "✅ Base64 encoded! Length: " . strlen($data_url) . " chars\n";
            echo "Preview: " . substr($data_url, 0, 100) . "...\n";
            
        } else {
            echo "❌ Invalid image file\n";
        }
        
        @unlink($frameFile);
    } else {
        echo "❌ No JPEG file created\n";
    }
} else {
    echo "❌ Failed to start proc_open\n";
}

echo "\n2. TESTING FALLBACK TO WORKING METHOD:\n";

// If JPEG encoding fails, test our original working method
$frameFile2 = $tmpDir . '/test_copy_frame.jpg';

$cmd2 = [
    $ffmpeg_path,
    '-hide_banner',
    '-loglevel', 'error',
    '-ss', (string)$timePos,
    '-i', $video_path,
    '-vframes', '1',
    '-c:v', 'copy',
    '-y',
    $frameFile2
];

echo "Fallback command: " . implode(' ', array_map('escapeshellarg', $cmd2)) . "\n";

$process2 = proc_open($cmd2, $descriptorspec, $pipes2);

if (is_resource($process2)) {
    fclose($pipes2[0]);
    $stdout2 = stream_get_contents($pipes2[1]);
    $stderr2 = stream_get_contents($pipes2[2]);
    fclose($pipes2[1]);
    fclose($pipes2[2]);
    
    $return_code2 = proc_close($process2);
    
    echo "Return code: {$return_code2}\n";
    echo "Stderr: " . (empty($stderr2) ? "(empty)" : $stderr2) . "\n";
    
    if (file_exists($frameFile2)) {
        $size2 = filesize($frameFile2);
        echo "✅ Copy method works! Size: " . round($size2 / 1024, 1) . "KB\n";
        
        $image_info2 = @getimagesize($frameFile2);
        if ($image_info2 !== false) {
            echo "✅ Valid image: {$image_info2[0]}x{$image_info2[1]}, Type: {$image_info2['mime']}\n";
        } else {
            echo "❌ Copy method produces invalid image\n";
        }
        
        @unlink($frameFile2);
    } else {
        echo "❌ Copy method also fails\n";
    }
}

@rmdir($tmpDir);

echo "\n=== JPEG ENCODING TEST COMPLETE ===\n";
?>