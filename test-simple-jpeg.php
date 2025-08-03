<?php
// Test simpler JPEG encoding methods that work on Hostinger
header('Content-Type: text/plain');
echo "=== TESTING SIMPLE JPEG ENCODING METHODS ===\n";

$video_path = 'uploads/21/IMG_0859.mov';
$ffmpeg_path = '/home/u230128646/bin/ffmpeg';

$tmpDir = sys_get_temp_dir() . '/simple_jpeg_test_' . uniqid();
@mkdir($tmpDir);
echo "Test directory: {$tmpDir}\n";

// Test different JPEG encoding approaches
$methods = [
    1 => [
        'name' => 'Simple JPEG without threading',
        'cmd' => [
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', '10',
            '-i', $video_path, '-vframes', '1', '-q:v', '2', '-y'
        ]
    ],
    2 => [
        'name' => 'PNG format (OpenAI supports PNG)',
        'cmd' => [
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', '10',
            '-i', $video_path, '-vframes', '1', '-f', 'image2', '-y'
        ]
    ],
    3 => [
        'name' => 'Force single thread JPEG',
        'cmd' => [
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', '10',
            '-i', $video_path, '-vframes', '1', '-threads', '1', '-q:v', '2', '-y'
        ]
    ],
    4 => [
        'name' => 'libx264 to JPEG',
        'cmd' => [
            $ffmpeg_path, '-hide_banner', '-loglevel', 'error', '-ss', '10',
            '-i', $video_path, '-vframes', '1', '-c:v', 'libx264', '-pix_fmt', 'yuv420p',
            '-f', 'image2', '-q:v', '2', '-y'
        ]
    ]
];

$successful_method = null;

foreach ($methods as $method_num => $method) {
    echo "\n{$method_num}. TESTING {$method['name']}:\n";
    
    $extension = ($method_num == 2) ? '.png' : '.jpg';
    $frameFile = $tmpDir . "/test_method_{$method_num}{$extension}";
    
    $cmd = array_merge($method['cmd'], [$frameFile]);
    
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
        if (!empty($stderr)) {
            echo "Stderr: {$stderr}\n";
        }
        
        if (file_exists($frameFile)) {
            $size = filesize($frameFile);
            echo "✅ File created! Size: " . round($size / 1024, 1) . "KB\n";
            
            // Test if it's a valid image
            $image_info = @getimagesize($frameFile);
            if ($image_info !== false) {
                echo "✅ Valid image: {$image_info[0]}x{$image_info[1]}, Type: {$image_info['mime']}\n";
                
                // Test base64 encoding for OpenAI
                $imageData = base64_encode(file_get_contents($frameFile));
                $mime_type = $image_info['mime'];
                $data_url = "data:{$mime_type};base64," . $imageData;
                echo "✅ OpenAI format ready! Length: " . strlen($data_url) . " chars\n";
                echo "Preview: " . substr($data_url, 0, 80) . "...\n";
                
                if (!$successful_method) {
                    $successful_method = [
                        'number' => $method_num,
                        'name' => $method['name'],
                        'cmd' => $cmd,
                        'extension' => $extension,
                        'mime_type' => $mime_type
                    ];
                }
                
            } else {
                echo "❌ Invalid image file\n";
            }
            
            @unlink($frameFile);
        } else {
            echo "❌ No file created\n";
        }
    } else {
        echo "❌ Failed to start proc_open\n";
    }
}

@rmdir($tmpDir);

echo "\n=== RESULTS ===\n";

if ($successful_method) {
    echo "🎉 SUCCESS! Method {$successful_method['number']} works: {$successful_method['name']}\n";
    echo "✅ Extension: {$successful_method['extension']}\n";
    echo "✅ MIME type: {$successful_method['mime_type']}\n";
    echo "✅ Ready to update MediaPreprocessor!\n";
} else {
    echo "❌ No methods worked. May need different approach.\n";
}

echo "\n=== SIMPLE JPEG TEST COMPLETE ===\n";
?>