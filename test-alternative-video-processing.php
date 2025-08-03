<?php
// Test alternative video processing methods when shell_exec is disabled
header('Content-Type: text/plain');
echo "=== ALTERNATIVE VIDEO PROCESSING TEST ===\n";

require_once 'server/config/config.php';

// 1. Test if we can use other methods
echo "1. TESTING ALTERNATIVE METHODS:\n";

// Check if we can use proc_open (sometimes allowed when shell_exec isn't)
echo "proc_open available: " . (function_exists('proc_open') ? "✅ YES" : "❌ NO") . "\n";

// Check if GD extension can handle video frames (usually no, but worth checking)
echo "GD extension: " . (extension_loaded('gd') ? "✅ YES" : "❌ NO") . "\n";

// Check if Imagick extension is available (can sometimes extract video frames)
echo "Imagick extension: " . (extension_loaded('imagick') ? "✅ YES" : "❌ NO") . "\n";

// Check if FFMpeg PHP extension is available
echo "FFMpeg extension: " . (extension_loaded('ffmpeg') ? "✅ YES" : "❌ NO") . "\n";

// 2. Test video file access
echo "\n2. VIDEO FILE ACCESS TEST:\n";
$video_path = 'uploads/21/IMG_0859.mov';
if (file_exists($video_path)) {
    $size = round(filesize($video_path) / 1024 / 1024, 1);
    echo "✅ Video file found: {$video_path} ({$size}MB)\n";
    
    // Get MIME type
    $mime = mime_content_type($video_path);
    echo "MIME type: {$mime}\n";
    
    // Check if we can read the file
    $handle = fopen($video_path, 'rb');
    if ($handle) {
        $header = fread($handle, 1024);
        fclose($handle);
        echo "✅ Can read video file (first 1KB)\n";
        echo "File signature: " . bin2hex(substr($header, 0, 16)) . "\n";
    } else {
        echo "❌ Cannot read video file\n";
    }
} else {
    echo "❌ Video file not found: {$video_path}\n";
}

// 3. Test if Imagick can extract video frames
if (extension_loaded('imagick')) {
    echo "\n3. IMAGICK VIDEO FRAME EXTRACTION TEST:\n";
    try {
        $imagick = new Imagick();
        
        // Check supported formats
        $formats = $imagick->queryFormats();
        $video_formats = array_filter($formats, function($format) {
            return in_array(strtolower($format), ['mov', 'mp4', 'avi', 'mkv']);
        });
        
        echo "Imagick supported video formats: " . implode(', ', $video_formats) . "\n";
        
        if (in_array('MOV', $formats) && file_exists($video_path)) {
            echo "Attempting to extract frame with Imagick...\n";
            
            try {
                $imagick->readImage($video_path . '[0]'); // Try to read first frame
                $imagick->setImageFormat('jpeg');
                $frame_data = $imagick->getImageBlob();
                
                if (!empty($frame_data)) {
                    echo "✅ SUCCESS! Extracted frame with Imagick (" . strlen($frame_data) . " bytes)\n";
                    
                    // Save test frame
                    $test_frame_path = 'test_frame_imagick.jpg';
                    file_put_contents($test_frame_path, $frame_data);
                    echo "✅ Saved test frame: {$test_frame_path}\n";
                } else {
                    echo "❌ Imagick extraction returned empty data\n";
                }
            } catch (Exception $e) {
                echo "❌ Imagick extraction failed: " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ MOV format not supported or video not found\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Imagick error: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n3. IMAGICK NOT AVAILABLE\n";
}

// 4. Alternative: Client-side frame extraction suggestion
echo "\n4. ALTERNATIVE SOLUTIONS:\n";
echo "Since server-side execution is limited, consider:\n";
echo "A) Client-side frame extraction (JavaScript)\n";
echo "B) Pre-upload frame extraction\n";
echo "C) Third-party video processing API\n";
echo "D) Manual frame upload option\n";

// 5. Test what we CAN do with the video
echo "\n5. WHAT WE CAN DO:\n";
if (file_exists($video_path)) {
    echo "✅ Read video metadata\n";
    echo "✅ Provide detailed file information to AI\n";
    echo "✅ Calculate duration estimates\n";
    echo "✅ Analyze file structure\n";
    
    // Enhanced video description for AI
    $enhanced_description = "📹 ENHANCED VIDEO ANALYSIS:\n";
    $enhanced_description .= "• File: " . basename($video_path) . "\n";
    $enhanced_description .= "• Size: " . round(filesize($video_path) / 1024 / 1024, 1) . "MB\n";
    $enhanced_description .= "• MIME: " . mime_content_type($video_path) . "\n";
    $enhanced_description .= "• Platform: Tree service assessment video\n";
    $enhanced_description .= "• Context: Professional arborist evaluation request\n";
    $enhanced_description .= "• Expected content: Trees requiring pruning/assessment\n";
    $enhanced_description .= "• Quality: High-resolution mobile video\n";
    $enhanced_description .= "• Request: Provide professional tree assessment based on submitted video content\n";
    
    echo "\nEnhanced description for AI:\n";
    echo $enhanced_description;
}

echo "\n=== TEST COMPLETE ===\n";
?>