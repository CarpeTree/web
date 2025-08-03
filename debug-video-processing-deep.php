<?php
// Deep debug of video processing in MediaPreprocessor
header('Content-Type: text/plain');
echo "=== DEEP DEBUG: VIDEO PROCESSING ===\n";

require_once 'server/config/config.php';

try {
    $pdo = getDatabaseConnection();
    
    // Get the media file
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = 69");
    $stmt->execute();
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($media_files)) {
        echo "❌ No media files found\n";
        exit;
    }
    
    $media = $media_files[0];
    echo "1. MEDIA FILE DETAILS:\n";
    echo "- Filename: {$media['filename']}\n";
    echo "- Path: {$media['file_path']}\n";
    echo "- MIME type: {$media['mime_type']}\n";
    echo "- Size: " . round($media['file_size'] / (1024*1024), 1) . "MB\n";
    
    $file_path = $media['file_path'];
    $file_type = $media['mime_type'];
    $filename = $media['filename'];
    
    echo "\n2. FILE TYPE DETECTION:\n";
    
    // Check how MediaPreprocessor detects file types
    $is_video_mime = strpos(strtolower($file_type), 'video') !== false;
    $is_video_extension = preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $filename);
    
    echo "- MIME contains 'video': " . ($is_video_mime ? "✅ YES" : "❌ NO") . "\n";
    echo "- Extension is video: " . ($is_video_extension ? "✅ YES" : "❌ NO") . "\n";
    echo "- Should be processed as video: " . (($is_video_mime || $is_video_extension) ? "✅ YES" : "❌ NO") . "\n";
    
    echo "\n3. TESTING VIDEO FRAME EXTRACTION DIRECTLY:\n";
    
    if (file_exists($file_path)) {
        echo "✅ Video file exists\n";
        
        // Test ffmpeg availability
        $ffmpeg_path = '/home/u230128646/bin/ffmpeg';
        echo "ffmpeg path: {$ffmpeg_path}\n";
        echo "ffmpeg exists: " . (file_exists($ffmpeg_path) ? "✅ YES" : "❌ NO") . "\n";
        
        if (file_exists($ffmpeg_path)) {
            echo "\n4. TESTING PROC_OPEN FRAME EXTRACTION:\n";
            
            $tmpDir = sys_get_temp_dir() . '/video_frames_test_' . uniqid();
            @mkdir($tmpDir);
            echo "Temp dir: {$tmpDir}\n";
            
            // Test single frame extraction with our working Method 4
            $frameFile = $tmpDir . '/test_frame.jpg';
            
            $cmd = [
                $ffmpeg_path,
                '-hide_banner',
                '-loglevel', 'error',
                '-ss', '10',
                '-i', $file_path,
                '-vframes', '1',
                '-c:v', 'copy',
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
                    $frame_size = filesize($frameFile);
                    echo "✅ Frame extracted! Size: " . round($frame_size / 1024, 1) . "KB\n";
                    
                    // Test base64 encoding
                    $imageData = base64_encode(file_get_contents($frameFile));
                    $data_url = 'data:image/jpeg;base64,' . $imageData;
                    echo "✅ Base64 encoded! Data URL length: " . strlen($data_url) . " chars\n";
                    echo "Data URL preview: " . substr($data_url, 0, 100) . "...\n";
                    
                    @unlink($frameFile);
                } else {
                    echo "❌ No frame file created\n";
                }
            } else {
                echo "❌ Failed to start proc_open\n";
            }
            
            @rmdir($tmpDir);
        }
        
        echo "\n5. CHECKING MEDIAPROCESSOR PROCESSING LOGIC:\n";
        
        // Check if the file would be processed by MediaPreprocessor
        echo "File type check in MediaPreprocessor:\n";
        
        if (strpos(strtolower($file_type), 'video') !== false || 
            preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $filename)) {
            echo "✅ Would be processed as video\n";
            
            // Check what method would be called
            echo "Processing method: processVideo()\n";
            
        } else if (strpos(strtolower($file_type), 'image') !== false || 
                  preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            echo "Would be processed as image\n";
            
        } else if (strpos(strtolower($file_type), 'audio') !== false || 
                  preg_match('/\.(mp3|wav|m4a|aac)$/i', $filename)) {
            echo "Would be processed as audio\n";
            
        } else {
            echo "❌ Would NOT be processed (unknown type)\n";
        }
        
    } else {
        echo "❌ Video file missing\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEEP DEBUG COMPLETE ===\n";
?>