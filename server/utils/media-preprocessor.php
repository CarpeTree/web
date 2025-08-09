<?php
require_once __DIR__ . '/../config/config.php';

class MediaPreprocessor {
    private $quote_id;
    private $media_files;
    private $quote_data;
    private $aggregated_context;
    private $converted_files_for_cleanup = [];
    
    public function __construct($quote_id, $media_files, $quote_data) {
        $this->quote_id = $quote_id;
        $this->media_files = $media_files;
        $this->quote_data = $quote_data;
        $this->aggregated_context = [
            'transcriptions' => [],
            'visual_content' => [],
            'media_summary' => [],
            'context_text' => ''
        ];
    }
    
    public function __destruct() {
        $this->cleanupConvertedFiles();
    }

    public function preprocessForGemini() {
        $processed_files = [];
        foreach ($this->media_files as $file) {
            $file_path = $this->resolveMediaPath($file);
            if (!$file_path || !file_exists($file_path)) {
                error_log("Skipping non-existent media file for quote #{$this->quote_id}: " . print_r($file, true));
                continue;
            }

            $filename = $file['filename'] ?? basename($file_path);
            $mime_type = $file['mime_type'] ?? mime_content_type($file_path) ?? 'application/octet-stream';

            if (strpos($mime_type, 'video') !== false) {
                error_log("🎬 Processing video for Gemini: {$filename}");
        $convertedPath = $this->convertVideoForGemini($file_path);
                if ($convertedPath && $convertedPath !== $file_path) {
                    $processed_files[] = ['file_path' => $convertedPath, 'filename' => $filename, 'mime_type' => 'video/mp4'];
                    $this->converted_files_for_cleanup[] = $convertedPath;
                } else {
                    $processed_files[] = $file; // Use original if conversion failed
                }
            } else {
                $processed_files[] = $file; // Pass non-video files through
            }
        }
        return $processed_files;
    }
    
    public function preprocessAllMedia() {
        // This is the main orchestration method
        $context_parts = $this->buildInitialContext();

        foreach ($this->media_files as $media) {
            $file_path = $this->resolveMediaPath($media);
            if (!$file_path || !file_exists($file_path)) continue;

            $filename = $media['filename'] ?? basename($file_path);
            $file_type = $media['mime_type'] ?? mime_content_type($file_path) ?? 'application/octet-stream';
            
            if (strpos($file_type, 'video') !== false) {
                $this->processVideo($file_path, $filename);
            } elseif (strpos($file_type, 'image') !== false) {
                $this->processImage($file_path, $filename);
            } elseif (strpos($file_type, 'audio') !== false) {
                $this->processAudio($file_path, $filename);
            }
        }
        
        $this->aggregateFinalContext($context_parts);
        return $this->aggregated_context;
    }
    
    private function processImage($file_path, $filename) {
        $imageData = base64_encode(file_get_contents($file_path));
        $mime_type = mime_content_type($file_path);
        
        $this->aggregated_context['visual_content'][] = [
            'inlineData' => [ 'mimeType' => $mime_type, 'data' => $imageData]
        ];
        
        $size = round(filesize($file_path) / 1024, 1);
        $this->aggregated_context['media_summary'][] = "📸 {$filename} (Image, {$size}KB)";
    }
    
    private function processVideo($file_path, $filename) {
        $frames = $this->extractVideoFrames($file_path, 5, 6);
        
        if (!empty($frames)) {
            $this->aggregated_context['visual_content'] = array_merge($this->aggregated_context['visual_content'], $frames);
            $this->aggregated_context['media_summary'][] = "🎬 {$filename} (" . count($frames) . " frames extracted)";
        } else {
            error_log("Could not extract frames for video {$filename}.");
        }
        
        $transcription = $this->extractAndTranscribeAudio($file_path);
        if ($transcription) {
            $this->aggregated_context['transcriptions'][] = ['source' => "Video: {$filename}", 'text' => $transcription];
        }
    }
    
    private function processAudio($file_path, $filename) {
        $transcription = $this->transcribeAudioFile($file_path);
        if ($transcription) {
            $this->aggregated_context['transcriptions'][] = ['source' => "Audio: {$filename}",'text' => $transcription];
        }
        
        $size = round(filesize($file_path) / 1024, 1);
        $this->aggregated_context['media_summary'][] = "🎤 {$filename} (Audio, {$size}KB)";
    }
    
    private function convertVideoForGemini($inputPath) {
        $ffmpeg_path = $this->getFfmpegPath();
        if (!file_exists($ffmpeg_path)) {
            error_log("FFmpeg not found - cannot convert video.");
            return $inputPath;
        }
        if (!function_exists('exec')) {
            error_log("exec() disabled - skipping video conversion.");
            return $inputPath;
        }

        $pathInfo = pathinfo($inputPath);
        $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_gemini.mp4';

                $cmd = [
            $ffmpeg_path, '-y', '-i', $inputPath, '-c:v', 'libx264', '-preset', 'fast', 
            '-crf', '23', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', 
            '-pix_fmt', 'yuv420p', '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2', $outputPath
        ];
        
        $return_var = 0;
        $output = [];
        exec(implode(' ', array_map('escapeshellarg', $cmd)), $output, $return_var);

        if ($return_var === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
            error_log("✅ Video conversion successful: " . basename($outputPath));
            return $outputPath;
                    } else {
            error_log("❌ Video conversion failed for {$inputPath}. Error: " . implode("\n", $output));
            if(file_exists($outputPath)) @unlink($outputPath);
            return $inputPath;
        }
    }

    private function extractVideoFrames($videoPath, $secondsInterval = 5, $maxFrames = 4) {
        $ffmpeg_path = $this->getFfmpegPath();
        if (!file_exists($ffmpeg_path)) return [];
        if (!function_exists('exec')) {
            error_log("exec() disabled - skipping frame extraction.");
            return [];
        }

        $frames = [];
        $tmpDir = sys_get_temp_dir() . '/frames_' . uniqid();
        if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            error_log("Could not create temp directory for frames: $tmpDir");
            return [];
        }

        for ($i = 0; $i < $maxFrames; $i++) {
            $timePos = $i * $secondsInterval;
            $frameFile = "{$tmpDir}/frame_{$i}.jpg";
            $cmd = [$ffmpeg_path, '-y', '-ss', $timePos, '-i', $videoPath, '-vframes', '1', '-q:v', '2', $frameFile];
            
            $return_var = 0;
            $output = [];
            exec(implode(' ', array_map('escapeshellarg', $cmd)), $output, $return_var);

            if ($return_var === 0 && file_exists($frameFile)) {
                $imageData = base64_encode(file_get_contents($frameFile));
                $frames[] = ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $imageData]];
                unlink($frameFile);
            }
        }
        rmdir($tmpDir);
        return $frames;
    }
    
    private function extractAndTranscribeAudio($videoPath) {
        // Prefer direct transcription of the original file (Whisper accepts video formats)
        // If the file is <= 25MB, send it directly without ffmpeg.
        $fileSize = @filesize($videoPath) ?: 0;
        if ($fileSize > 0 && $fileSize <= 25 * 1024 * 1024) {
            $direct = $this->transcribeWithWhisper($videoPath);
            if (is_string($direct) && strlen($direct) > 0) {
                return $direct;
            }
        }

        // If larger than 25MB or direct failed, try extracting audio if ffmpeg is available and exec() is enabled
        $ffmpeg_path = $this->getFfmpegPath();
        if (!file_exists($ffmpeg_path) || !function_exists('exec')) {
            error_log("Audio transcription fallback not available (ffmpeg or exec missing). Skipping.");
            return null;
        }

        $tmpAudio = sys_get_temp_dir() . '/audio_' . uniqid() . '.mp3';
        $cmd = [$ffmpeg_path, '-y', '-i', $videoPath, '-vn', '-acodec', 'mp3', $tmpAudio];
        exec(implode(' ', array_map('escapeshellarg', $cmd)));

        if (!file_exists($tmpAudio)) return null;

        $transcription = $this->transcribeWithWhisper($tmpAudio);
        @unlink($tmpAudio);
        return $transcription;
    }
    
    private function transcribeAudioFile($audioPath) {
        return $this->transcribeWithWhisper($audioPath);
    }
    
    private function transcribeWithWhisper($audioPath) {
        global $OPENAI_API_KEY;
        if (empty($OPENAI_API_KEY) || filesize($audioPath) > 25 * 1024 * 1024) return null;
        
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => new CURLFile($audioPath), 'model' => 'whisper-1', 'response_format' => 'text'],
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $OPENAI_API_KEY],
        ]);
            $response = curl_exec($curl);
            curl_close($curl);
        return is_string($response) ? trim($response) : null;
    }

    private function buildInitialContext() {
        $services = json_decode($this->quote_data['selected_services'] ?? '[]', true);
        $address = $this->quote_data['address'] ?? ($this->quote_data['customer_address'] ?? '');
        return [
            "📋 CUSTOMER SUBMISSION",
            "Quote ID: {$this->quote_id}",
            "Customer: {$this->quote_data['customer_name']} ({$this->quote_data['customer_email']})",
            "Address: {$address}",
            "Requested Services: " . implode(', ', $services),
            "Notes: {$this->quote_data['notes']}",
            ""
        ];
    }
    
    private function resolveMediaPath(array $media) {
        $root = realpath(__DIR__ . '/../../'); // /var/www/carpetree.com
        $candidates = [];
        $declaredPath = $media['file_path'] ?? '';
        $quoteId = $media['quote_id'] ?? $this->quote_id;
        $filename = $media['filename'] ?? ($declaredPath ? basename($declaredPath) : null);

        if ($declaredPath) {
            // Absolute path as-is
            if ($declaredPath[0] === '/') {
                $candidates[] = $declaredPath;
            }
            // Try relative to app root (normalize ../)
            $normalized = ltrim(preg_replace('#^\./#', '', $declaredPath), '/');
            $normalized = ltrim(preg_replace('#^\.\./#', '', $normalized), '/');
            $candidates[] = $root . '/' . $normalized;
            // Try relative to server dir (legacy)
            $candidates[] = realpath(__DIR__ . '/..') . '/' . ltrim($declaredPath, '/');
        }

        if ($quoteId && $filename) {
            // Preferred current layout
            $candidates[] = $root . "/uploads/{$quoteId}/{$filename}";
            // Legacy quote_XX layout
            $candidates[] = $root . "/uploads/quote_{$quoteId}/{$filename}";
        }

        // As a last resort, search by filename across uploads subdirs (first match wins)
        if ($filename) {
            $matches = glob($root . '/uploads/*/' . $filename);
            if (!empty($matches)) {
                foreach ($matches as $m) { $candidates[] = $m; }
            }
        }

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        error_log("MediaPreprocessor: file not found. Quote #{$this->quote_id}, filename=" . ($filename ?: 'unknown') . "; candidates=" . json_encode($candidates));
        return null;
    }

    private function getFfmpegPath() {
        static $cached = null;
        if ($cached !== null) return $cached;
        $candidates = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/snap/bin/ffmpeg',
            '/home/u230128646/bin/ffmpeg'
        ];
        foreach ($candidates as $p) {
            if (file_exists($p)) { $cached = $p; return $cached; }
        }
        // Fallback: try `which ffmpeg` if exec is available
        if (function_exists('exec')) {
            $which = trim(shell_exec('which ffmpeg 2>/dev/null') ?: '');
            if ($which && file_exists($which)) { $cached = $which; return $cached; }
        }
        $cached = '';
        return $cached;
    }

    private function aggregateFinalContext(&$context_parts) {
        if (!empty($this->aggregated_context['transcriptions'])) {
            $context_parts[] = "🎤 AUDIO TRANSCRIPTIONS";
            foreach($this->aggregated_context['transcriptions'] as $t) {
                $context_parts[] = "- {$t['source']}: \"{$t['text']}\"";
            }
            $context_parts[] = "";
        }
        if (!empty($this->aggregated_context['media_summary'])) {
            $context_parts[] = "📁 MEDIA FILES ANALYZED";
            $context_parts = array_merge($context_parts, $this->aggregated_context['media_summary']);
            $context_parts[] = "";
        }
        $this->aggregated_context['context_text'] = implode("\n", $context_parts);
    }
    
    public function cleanupConvertedFiles() {
        foreach ($this->converted_files_for_cleanup as $file) {
            if (file_exists($file)) {
                @unlink($file);
                error_log("🧹 Cleaned up temporary converted file: " . basename($file));
            }
        }
        $this->converted_files_for_cleanup = [];
    }
}
