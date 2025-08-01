<?php
// Media Preprocessing Pipeline - Aggregate ALL context before main LLM analysis
require_once __DIR__ . '/../config/config.php';

class MediaPreprocessor {
    private $quote_id;
    private $media_files;
    private $quote_data;
    private $aggregated_context;
    
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
    
    /**
     * Process ALL media files and aggregate context BEFORE main LLM analysis
     */
    public function preprocessAllMedia() {
        $context_parts = [];
        
        // Add COMPLETE customer context from form submission
        $services = json_decode($this->quote_data['selected_services'], true) ?: [];
        
        $context_parts[] = "📋 COMPLETE CUSTOMER SUBMISSION";
        $context_parts[] = "Quote ID: " . $this->quote_id;
        $context_parts[] = "Submission Date: " . ($this->quote_data['quote_created_at'] ?? 'Unknown');
        $context_parts[] = "";
        
        // Customer Information
        $context_parts[] = "👤 CUSTOMER INFORMATION";
        $context_parts[] = "Name: " . ($this->quote_data['customer_name'] ?? $this->quote_data['name'] ?? 'Not provided');
        $context_parts[] = "Email: " . ($this->quote_data['customer_email'] ?? $this->quote_data['email'] ?? 'Not provided');
        $context_parts[] = "Phone: " . ($this->quote_data['customer_phone'] ?? $this->quote_data['phone'] ?? 'Not provided');
        $context_parts[] = "Address: " . ($this->quote_data['address'] ?? 'Not provided');
        $context_parts[] = "";
        
        // Service Request Details
        $context_parts[] = "🌲 SERVICE REQUEST";
        $context_parts[] = "Services Requested: " . implode(', ', $services);
        $context_parts[] = "Customer Notes/Description: " . ($this->quote_data['notes'] ?? 'None provided');
        $context_parts[] = "Quote Status: " . ($this->quote_data['quote_status'] ?? 'Unknown');
        $context_parts[] = "";
        
        // Location & GPS Data
        $this->addLocationContext($context_parts);
        
        // Marketing & Referral Information
        $this->addMarketingContext($context_parts);
        
        // Technical Context
        $this->addTechnicalContext($context_parts);
        
        // Process each media file and extract ALL information first
        foreach ($this->media_files as $media) {
            $file_path = $media['file_path'];
            $file_type = $media['file_type'];
            $filename = $media['filename'];
            
            if (!file_exists($file_path)) {
                error_log("Media file not found: $file_path");
                continue;
            }
            
            switch ($file_type) {
                case 'image':
                    $this->processImage($file_path, $filename);
                    break;
                case 'video':
                    $this->processVideo($file_path, $filename);
                    break;
                case 'audio':
                    $this->processAudio($file_path, $filename);
                    break;
            }
        }
        
        // Aggregate all transcriptions into context
        if (!empty($this->aggregated_context['transcriptions'])) {
            $context_parts[] = "🎤 AUDIO TRANSCRIPTIONS";
            foreach ($this->aggregated_context['transcriptions'] as $transcription) {
                $context_parts[] = "- {$transcription['source']}: \"{$transcription['text']}\"";
            }
            $context_parts[] = "";
        }
        
        // Add media summary
        if (!empty($this->aggregated_context['media_summary'])) {
            $context_parts[] = "📁 MEDIA FILES ANALYZED";
            foreach ($this->aggregated_context['media_summary'] as $summary) {
                $context_parts[] = "- " . $summary;
            }
            $context_parts[] = "";
        }
        
        $context_parts[] = "Please analyze ALL the above context and provide a comprehensive tree care assessment.";
        
        $this->aggregated_context['context_text'] = implode("\n", $context_parts);
        
        return $this->aggregated_context;
    }
    
    private function processImage($file_path, $filename) {
        $imageData = base64_encode(file_get_contents($file_path));
        $mime_type = mime_content_type($file_path);
        
        $this->aggregated_context['visual_content'][] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:' . $mime_type . ';base64,' . $imageData,
                'detail' => 'high'
            ]
        ];
        
        $size = round(filesize($file_path) / (1024*1024), 1);
        $this->aggregated_context['media_summary'][] = "📸 {$filename} (Image, {$size}MB)";
    }
    
    private function processVideo($file_path, $filename) {
        // Extract video frames
        $frames = $this->extractVideoFrames($file_path);

        // Extract and transcribe audio from video
        $transcription = $this->extractAndTranscribeAudio($file_path);
        if ($transcription) {
            $this->aggregated_context['transcriptions'][] = [
                'source' => "Video: {$filename}",
                'text' => $transcription
            ];
        }

        if (!empty($frames)) {
            $this->aggregated_context['visual_content'] = array_merge(
                $this->aggregated_context['visual_content'],
                $frames
            );
            $frame_count = count($frames);
            $audio_note = $transcription ? " + audio transcription" : "";
            $this->aggregated_context['media_summary'][] = "🎬 {$filename} ({$frame_count} frames extracted{$audio_note})";
        } else {
            // This 'else' block now correctly handles all video processing failures
            $audio_note = $transcription ? " (audio only transcribed)" : "";
            $this->aggregated_context['media_summary'][] = "🎬 {$filename} (Video frame extraction failed{$audio_note})";
        }
    }
    
    private function processAudio($file_path, $filename) {
        // Transcribe standalone audio file
        $transcription = $this->transcribeAudioFile($file_path);
        if ($transcription) {
            $this->aggregated_context['transcriptions'][] = [
                'source' => "Audio: {$filename}",
                'text' => $transcription
            ];
        }
        
        $size = round(filesize($file_path) / (1024*1024), 1);
        $transcription_note = $transcription ? " (transcribed)" : " (transcription failed)";
        $this->aggregated_context['media_summary'][] = "🎤 {$filename} (Audio, {$size}MB{$transcription_note})";
    }
    
    private function extractVideoFrames($videoPath, $secondsInterval = 5, $maxFrames = 6) {
        if (!function_exists('shell_exec') || (function_exists('ini_get') && str_contains(ini_get('disable_functions'), 'shell_exec'))) {
            error_log("shell_exec() disabled on server - cannot extract video frames for " . basename($videoPath));
            return [];
        }

        $ffmpeg_paths = [
            '/opt/homebrew/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/usr/bin/ffmpeg',
            '/home/u230128646/bin/ffmpeg',
            trim(shell_exec('which ffmpeg') ?? '')
        ];
        
        $ffmpeg_path = null;
        foreach ($ffmpeg_paths as $path) {
            if (!empty($path) && file_exists($path)) {
                $ffmpeg_path = $path;
                break;
            }
        }
        
        if (!$ffmpeg_path) {
            error_log("FFmpeg not found - cannot extract video frames for " . basename($videoPath));
            return [];
        }
        
        $frames = [];
        $tmpDir = sys_get_temp_dir() . '/video_frames_' . uniqid();
        @mkdir($tmpDir);
        
        try {
            // Extract frames
            $cmd = sprintf('%s -hide_banner -loglevel error -i %s -vf "fps=1/%d,scale=1024:-1" -q:v 5 -frames:v %d %s/frame_%%03d.jpg',
                escapeshellarg($ffmpeg_path),
                escapeshellarg($videoPath),
                $secondsInterval,
                $maxFrames,
                escapeshellarg($tmpDir)
            );
            
            shell_exec($cmd);
            
            // Convert frames to base64
            for ($i = 1; $i <= $maxFrames; $i++) {
                $frameFile = sprintf('%s/frame_%03d.jpg', $tmpDir, $i);
                if (file_exists($frameFile)) {
                    $imageData = base64_encode(file_get_contents($frameFile));
                    $frames[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:image/jpeg;base64,' . $imageData,
                            'detail' => 'high'
                        ]
                    ];
                }
            }
            
        } finally {
            // Clean up remaining frame files if they exist
            if (is_dir($tmpDir)) {
                $files = glob($tmpDir . '/*');
                foreach($files as $file){
                    if(is_file($file)) {
                        @unlink($file);
                    }
                }
                @rmdir($tmpDir);
            }
        }
        
        return $frames;
    }
    
    private function extractAndTranscribeAudio($videoPath) {
        global $OPENAI_API_KEY;
        
        if (empty($OPENAI_API_KEY)) {
            return null;
        }

        if (!function_exists('shell_exec') || (function_exists('ini_get') && str_contains(ini_get('disable_functions'), 'shell_exec'))) {
            error_log("shell_exec() disabled on server - cannot extract audio from video: " . basename($videoPath));
            return null;
        }
        
        $ffmpeg_paths = [
            '/opt/homebrew/bin/ffmpeg',
            '/usr/local/bin/ffmpeg', 
            '/usr/bin/ffmpeg',
            '/home/u230128646/bin/ffmpeg',
            trim(shell_exec('which ffmpeg') ?? '')
        ];
        
        $ffmpeg_path = null;
        foreach ($ffmpeg_paths as $path) {
            if (!empty($path) && file_exists($path)) {
                $ffmpeg_path = $path;
                break;
            }
        }
        
        if (!$ffmpeg_path) {
            error_log("FFmpeg not found - cannot extract audio from video: " . basename($videoPath));
            return null;
        }
        
        $tmpAudio = sys_get_temp_dir() . '/audio_' . uniqid() . '.mp3';
        
        // Extract audio (max 25MB for OpenAI Whisper)
        $cmd = sprintf('%s -hide_banner -loglevel error -i %s -vn -acodec mp3 -ab 64k -ar 16000 -t 300 %s',
            escapeshellarg($ffmpeg_path),
            escapeshellarg($videoPath),
            escapeshellarg($tmpAudio)
        );
        
        shell_exec($cmd);
        
        if (!file_exists($tmpAudio) || filesize($tmpAudio) == 0) {
            return null;
        }
        
        $transcription = $this->transcribeWithWhisper($tmpAudio);
        @unlink($tmpAudio);
        
        return $transcription;
    }
    
    private function transcribeAudioFile($audioPath) {
        global $OPENAI_API_KEY;
        
        if (empty($OPENAI_API_KEY)) {
            return null;
        }
        
        // Check file size (max 25MB for Whisper)
        if (filesize($audioPath) > 25 * 1024 * 1024) {
            error_log("Audio file too large for transcription: " . $audioPath);
            return null;
        }
        
        return $this->transcribeWithWhisper($audioPath);
    }
    
    private function transcribeWithWhisper($audioPath) {
        global $OPENAI_API_KEY;
        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'file' => new CURLFile($audioPath, mime_content_type($audioPath), basename($audioPath)),
                    'model' => 'whisper-1',
                    'language' => 'en',
                    'response_format' => 'text'
                ],
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $OPENAI_API_KEY
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($http_code === 200 && !empty(trim($response))) {
                return trim($response);
            }
            
        } catch (Exception $e) {
            error_log("Audio transcription failed: " . $e->getMessage());
        }
        
        return null;
    }
    
    private function addLocationContext(&$context_parts) {
        $has_location_data = false;
        
        $context_parts[] = "📍 LOCATION DATA";
        
        // GPS coordinates from browser/device
        if (!empty($this->quote_data['geo_latitude']) && !empty($this->quote_data['geo_longitude'])) {
            $context_parts[] = "GPS Coordinates: {$this->quote_data['geo_latitude']}, {$this->quote_data['geo_longitude']}";
            $context_parts[] = "GPS Accuracy: " . ($this->quote_data['geo_accuracy'] ?? 'Unknown') . " meters";
            $has_location_data = true;
        }
        
        // EXIF coordinates from photos
        if (!empty($this->quote_data['exif_lat']) && !empty($this->quote_data['exif_lng'])) {
            $context_parts[] = "Photo EXIF Coordinates: {$this->quote_data['exif_lat']}, {$this->quote_data['exif_lng']}";
            $has_location_data = true;
        }
        
        if (!$has_location_data) {
            $context_parts[] = "No GPS coordinates provided";
        }
        
        $context_parts[] = "";
    }
    
    private function addMarketingContext(&$context_parts) {
        $context_parts[] = "📈 MARKETING & REFERRAL INFO";
        $context_parts[] = "How they heard about us: " . ($this->quote_data['referral_source'] ?? 'Not specified');
        
        if (!empty($this->quote_data['referrer_name'])) {
            $context_parts[] = "Referrer name: " . $this->quote_data['referrer_name'];
        }
        
        $newsletter_status = 'Not specified';
        if (isset($this->quote_data['newsletter_opt_in'])) {
            $newsletter_status = $this->quote_data['newsletter_opt_in'] ? 'Yes' : 'No';
        }
        $context_parts[] = "Newsletter signup: " . $newsletter_status;
        $context_parts[] = "";
    }
    
    private function addTechnicalContext(&$context_parts) {
        $context_parts[] = "💻 TECHNICAL CONTEXT";
        $context_parts[] = "IP Address: " . ($this->quote_data['ip_address'] ?? 'Unknown');
        
        if (!empty($this->quote_data['user_agent'])) {
            // Parse user agent for useful info
            $ua = $this->quote_data['user_agent'];
            $device_info = $this->parseUserAgent($ua);
            $context_parts[] = "Device: " . $device_info;
        }
        
        $context_parts[] = "";
    }
    
    private function parseUserAgent($userAgent) {
        // Simple user agent parsing for device context
        if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
            return 'Mobile device';
        } elseif (strpos($userAgent, 'iPad') !== false || strpos($userAgent, 'Tablet') !== false) {
            return 'Tablet';
        } elseif (strpos($userAgent, 'Macintosh') !== false) {
            return 'Mac computer';
        } elseif (strpos($userAgent, 'Windows') !== false) {
            return 'Windows computer';
        } else {
            return 'Desktop/Unknown device';
        }
    }
    
    /**
     * Get aggregated context for main LLM
     */
    public function getAggregatedContext() {
        return $this->aggregated_context;
    }
}
?>