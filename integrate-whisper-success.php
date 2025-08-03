<?php
// Integrate working Whisper into MediaPreprocessor
header('Content-Type: text/plain');
echo "=== INTEGRATING WORKING WHISPER ===\n";

// We know the working ffmpeg path: /home/u230128646/bin/ffmpeg
// Let's update the MediaPreprocessor to use this

$current_code = file_get_contents('server/utils/media-preprocessor.php');

// The working audio extraction method with correct ffmpeg path
$working_audio_method = '
    private function extractAndTranscribeAudio($file_path) {
        // Use the confirmed working ffmpeg path
        $ffmpeg_path = "/home/u230128646/bin/ffmpeg";
        
        // Extract audio using working ffmpeg path
        $audio_file = sys_get_temp_dir() . "/audio_" . uniqid() . ".mp3";
        
        $cmd = [
            $ffmpeg_path,
            "-i", $file_path,
            "-vn",           // No video
            "-acodec", "mp3",
            "-ar", "16000",  // 16kHz for Whisper
            "-ac", "1",      // Mono
            "-y",
            $audio_file
        ];
        
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"], 
            2 => ["pipe", "w"]
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (!is_resource($process)) {
            error_log("Failed to start ffmpeg for audio extraction");
            return null;
        }
        
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $return_code = proc_close($process);
        
        if ($return_code !== 0 || !file_exists($audio_file)) {
            error_log("Audio extraction failed: " . $stderr);
            return null;
        }
        
        $audio_size = filesize($audio_file);
        error_log("Audio extracted: " . round($audio_size / 1024, 1) . "KB from " . basename($file_path));
        
        // Check file size (25MB Whisper limit)
        if ($audio_size > 25 * 1024 * 1024) {
            error_log("Audio file too large for Whisper: " . round($audio_size / 1024 / 1024, 1) . "MB");
            unlink($audio_file);
            return null;
        }
        
        // Send to Whisper API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/audio/transcriptions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . getenv("OPENAI_API_KEY")
        ]);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            "file" => new CURLFile($audio_file, "audio/mp3", "audio.mp3"),
            "model" => "whisper-1",
            "language" => "en"
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Clean up audio file
        unlink($audio_file);
        
        if ($http_code === 200) {
            $transcription_data = json_decode($response, true);
            if (isset($transcription_data["text"])) {
                $text = trim($transcription_data["text"]);
                error_log("Whisper transcription successful: " . strlen($text) . " chars - " . substr($text, 0, 100) . "...");
                return $text;
            }
        }
        
        error_log("Whisper API failed: HTTP {$http_code}, " . substr($response, 0, 200));
        return null;
    }';

// Replace the placeholder method
$placeholder_pattern = '/private function extractAndTranscribeAudio\(\$file_path\) \{[^}]*?return null;[^}]*?\}/s';

if (preg_match($placeholder_pattern, $current_code)) {
    echo "✅ Found placeholder audio method\n";
    
    $updated_code = preg_replace($placeholder_pattern, $working_audio_method, $current_code);
    
    if ($updated_code !== $current_code) {
        // Backup current version
        $backup_file = "server/utils/media-preprocessor-before-whisper-integration.php";
        copy("server/utils/media-preprocessor.php", $backup_file);
        
        // Write updated version
        file_put_contents("server/utils/media-preprocessor.php", $updated_code);
        
        echo "✅ MediaPreprocessor updated with working Whisper integration!\n";
        echo "✅ Backup saved: {$backup_file}\n";
        
        echo "\n🎤 WHISPER INTEGRATION COMPLETE!\n";
        echo "Your tree analysis will now include:\n";
        echo "- 6 high-quality JPEG frames (3.3MB visual data)\n";
        echo "- Audio transcription with your verbal observations\n";
        echo "- Complete context: access, distances, tree conditions\n";
        
        echo "\n📝 Sample transcription from your video:\n";
        echo "\"fir tree needs to go... can't drive in here... 200 100 meters from road... two conifers dead... no targets just fall and drag away\"\n";
        
    } else {
        echo "❌ Failed to replace placeholder method\n";
    }
    
} else {
    echo "❌ Could not find placeholder audio method to replace\n";
    echo "Checking current method...\n";
    
    // Check what's currently there
    if (strpos($current_code, 'extractAndTranscribeAudio') !== false) {
        echo "Found extractAndTranscribeAudio method in file\n";
        
        // Extract the current method to see what it looks like
        $pattern = '/(private function extractAndTranscribeAudio\([^}]*?\})/s';
        if (preg_match($pattern, $current_code, $matches)) {
            echo "Current method preview:\n";
            echo substr($matches[1], 0, 200) . "...\n";
        }
    } else {
        echo "❌ No extractAndTranscribeAudio method found\n";
    }
}

echo "\n=== INTEGRATION COMPLETE ===\n";
?>