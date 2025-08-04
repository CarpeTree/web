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
     * Convert video to Gemini-optimized format for maximum compatibility
     * Handles iPhone MOV, H.265, and other problematic encodings
     */
    private function convertVideoForGemini($inputPath, $outputPath = null) {
        $ffmpeg_path = '/home/u230128646/bin/ffmpeg';
        
        if (!file_exists($ffmpeg_path)) {
            error_log("FFmpeg not found - cannot convert video: " . basename($inputPath));
            return $inputPath; // Return original if conversion unavailable
        }
        
        // Generate optimized output path if not provided
        if ($outputPath === null) {
            $pathInfo = pathinfo($inputPath);
            $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_gemini_optimized.mp4';
        }
        
        // Gemini-optimized conversion settings
        $cmd = [
            $ffmpeg_path,
            '-hide_banner',
            '-loglevel', 'error',
            '-i', $inputPath,
            // Video codec: H.264 (widely compatible)
            '-c:v', 'libx264',
            '-preset', 'fast', // Balance speed vs compression
            '-crf', '23', // Good quality-size balance
            // Audio codec: AAC (universally supported)
            '-c:a', 'aac',
            '-b:a', '128k',
            '-ar', '44100', // Standard sample rate
            // Format optimizations
            '-movflags', '+faststart', // Enable streaming/progressive download
            '-pix_fmt', 'yuv420p', // Maximum compatibility pixel format
            // Resolution: Keep original but ensure even dimensions
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2',
            // Duration limit for processing efficiency (10 minutes max)
            '-t', '600',
            '-y', // Overwrite output
            $outputPath
        ];
        
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout  
            2 => ['pipe', 'w']   // stderr
        ];
        
        error_log("Converting video for Gemini compatibility: " . basename($inputPath));
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (!is_resource($process)) {
            error_log("Failed to start video conversion process");
            return $inputPath; // Return original on failure
        }
        
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $return_code = proc_close($process);
        
        if ($return_code === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
            $originalSize = round(filesize($inputPath) / (1024*1024), 1);
            $convertedSize = round(filesize($outputPath) / (1024*1024), 1);
            error_log("✅ Video conversion successful: {$originalSize}MB → {$convertedSize}MB (" . basename($outputPath) . ")");
            return $outputPath;
        } else {
            error_log("❌ Video conversion failed: return_code={$return_code}, stderr={$stderr}");
            // Clean up failed output file
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
            return $inputPath; // Return original on failure
        }
    }
    
    /**
     * Check if video needs conversion for Gemini compatibility
     */
    private function needsGeminiConversion($videoPath) {
        $ffmpeg_path = '/home/u230128646/bin/ffmpeg';
        
        if (!file_exists($ffmpeg_path)) {
            return false; // Can't convert anyway
        }
        
        // Get video info using ffprobe
        $cmd = [
            str_replace('ffmpeg', 'ffprobe', $ffmpeg_path),
            '-v', 'quiet',
            '-show_format',
            '-show_streams',
            '-of', 'json',
            $videoPath
        ];
        
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (!is_resource($process)) {
            return false;
        }
        
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        
        $info = json_decode($output, true);
        
        if (!$info || !isset($info['streams'])) {
            return false;
        }
        
        // Check for problematic encodings that need conversion
        foreach ($info['streams'] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $codec = strtolower($stream['codec_name'] ?? '');
                
                // Convert if using problematic codecs
                if (in_array($codec, ['hevc', 'h265', 'av1', 'vp9', 'prores'])) {
                    error_log("Video uses {$codec} codec - conversion recommended for Gemini compatibility");
                    return true;
                }
                
                // Check pixel format
                $pix_fmt = strtolower($stream['pix_fmt'] ?? '');
                if (!in_array($pix_fmt, ['yuv420p', 'yuv422p', 'yuv444p'])) {
                    error_log("Video uses {$pix_fmt} pixel format - conversion recommended");
                    return true;
                }
            }
        }
        
        // Check file size (convert if over 50MB for better processing)
        if (filesize($videoPath) > 50 * 1024 * 1024) {
            error_log("Large video file (" . round(filesize($videoPath)/(1024*1024), 1) . "MB) - conversion recommended for efficiency");
            return true;
        }
        
        return false;
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
            $file_type = $media['mime_type'] ?? $media['file_type'] ?? 'unknown';
            $filename = $media['filename'];
            
            if (!file_exists($file_path)) {
                error_log("Media file not found: $file_path");
                continue;
            }
            
            // Process based on file type
            if (strpos(strtolower($file_type), 'video') !== false || 
                preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $filename)) {
                $this->processVideo($file_path, $filename);
            } else if (strpos(strtolower($file_type), 'image') !== false || 
                      preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
                $this->processImage($file_path, $filename);
            } else if (strpos(strtolower($file_type), 'audio') !== false || 
                      preg_match('/\.(mp3|wav|m4a|aac)$/i', $filename)) {
                $this->processAudio($file_path, $filename);
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
    
    /**
     * Preprocess media specifically for Gemini API
     * Converts videos to optimal format for Gemini analysis
     */
    public function preprocessForGemini() {
        $processed_files = [];
        
        foreach ($this->media_files as $file) {
            $file_path = $file['file_path'];
            $filename = $file['filename'];
            $mime_type = $file['mime_type'];
            
            // Handle video files with automatic conversion
            if (strpos(strtolower($mime_type), 'video') !== false || 
                preg_match('/\.(mp4|mov|avi|mkv|webm)$/i', $filename)) {
                
                error_log("🎬 Preprocessing video for Gemini: {$filename}");
                
                // Check if conversion is needed
                if ($this->needsGeminiConversion($file_path)) {
                    error_log("🔄 Converting {$filename} for Gemini compatibility...");
                    $convertedPath = $this->convertVideoForGemini($file_path);
                    
                    if ($convertedPath !== $file_path) {
                        // Use converted file for Gemini
                        $processed_files[] = [
                            'file_path' => $convertedPath,
                            'filename' => $filename . ' (Gemini-optimized)',
                            'mime_type' => 'video/mp4',
                            'original_path' => $file_path,
                            'converted' => true
                        ];
                        error_log("✅ Video converted successfully for Gemini: " . basename($convertedPath));
                    } else {
                        // Conversion failed, use original
                        $processed_files[] = [
                            'file_path' => $file_path,
                            'filename' => $filename,
                            'mime_type' => $mime_type,
                            'converted' => false
                        ];
                        error_log("⚠️ Video conversion failed, using original file");
                    }
                } else {
                    // No conversion needed
                    $processed_files[] = [
                        'file_path' => $file_path,
                        'filename' => $filename,
                        'mime_type' => $mime_type,
                        'converted' => false
                    ];
                    error_log("✅ Video is already Gemini-compatible: {$filename}");
                }
            } else {
                // Non-video files pass through unchanged
                $processed_files[] = [
                    'file_path' => $file_path,
                    'filename' => $filename,
                    'mime_type' => $mime_type,
                    'converted' => false
                ];
            }
        }
        
        return $processed_files;
    }
    
    /**
     * Clean up any converted files after processing
     */
    public function cleanupConvertedFiles($processed_files) {
        foreach ($processed_files as $file) {
            if (isset($file['converted']) && $file['converted'] && 
                isset($file['original_path']) && 
                $file['file_path'] !== $file['original_path'] && 
                file_exists($file['file_path'])) {
                
                @unlink($file['file_path']);
                error_log("🧹 Cleaned up converted file: " . basename($file['file_path']));
            }
        }
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
        // 🎯 AUTOMATIC GEMINI VIDEO CONVERSION
        $originalPath = $file_path;
        $convertedPath = null;
        
        // Check if video needs conversion for Gemini compatibility
        if ($this->needsGeminiConversion($file_path)) {
            error_log("🔄 Converting {$filename} for optimal Gemini compatibility...");
            $convertedPath = $this->convertVideoForGemini($file_path);
            
            if ($convertedPath !== $file_path) {
                // Conversion successful, use converted file
                $file_path = $convertedPath;
                error_log("✅ Using converted video for processing: " . basename($convertedPath));
            }
        } else {
            error_log("✅ Video {$filename} is already Gemini-compatible, no conversion needed");
        }
        
        // Use the working proc_open method instead of shell_exec method
        $frames = $this->extractVideoFrames($file_path, 5, 6);
        
        if (!empty($frames)) {
            // Success! We have actual frames
            $this->aggregated_context['visual_content'] = array_merge(
                $this->aggregated_context['visual_content'],
                $frames
            );
            
            $frame_count = count($frames);
            $this->aggregated_context['media_summary'][] = "🎬 {$filename} ({$frame_count} frames extracted via proc_open)";
            
            // Also try audio transcription
            $transcription = $this->extractAndTranscribeAudio($file_path);
            if ($transcription) {
                $this->aggregated_context['transcriptions'][] = [
                    'source' => "Video: {$filename}",
                    'text' => $transcription
                ];
            }

            error_log("Successfully processed video {$filename} with {$frame_count} frames for Quote #{$this->quote_id}");
            
            // Clean up converted file if we created one
            if ($convertedPath && $convertedPath !== $originalPath && file_exists($convertedPath)) {
                @unlink($convertedPath);
                error_log("🧹 Cleaned up converted video file: " . basename($convertedPath));
            }
            return;
        }
        
        // Fallback: Provide detailed video description for AI analysis
        error_log("Using video fallback description for: {$filename}");
        $this->describeVideoFallback($file_path, $filename);
        
        // Clean up converted file if we created one (even on fallback)
        if ($convertedPath && $convertedPath !== $originalPath && file_exists($convertedPath)) {
            @unlink($convertedPath);
            error_log("🧹 Cleaned up converted video file: " . basename($convertedPath));
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
        // Use proc_open instead of shell_exec since it's disabled on Hostinger
        $ffmpeg_path = '/home/u230128646/bin/ffmpeg';
        
        if (!file_exists($ffmpeg_path)) {
            error_log("FFmpeg not found at {$ffmpeg_path} - cannot extract frames from video: " . basename($videoPath));
            return [];
        }
        
        $frames = [];
        $tmpDir = sys_get_temp_dir() . '/video_frames_' . uniqid();
        @mkdir($tmpDir);
        
        try {
            // Extract frames using proc_open with Method 4 (-c:v copy) approach that works on Hostinger
            for ($i = 0; $i < $maxFrames; $i++) {
                $timePos = $i * $secondsInterval;
                $frameFile = sprintf('%s/frame_%03d.jpg', $tmpDir, $i + 1);
                
                // Use single-threaded JPEG encoding that works on Hostinger
                $cmd = [
                    $ffmpeg_path,
                    '-hide_banner',
                    '-loglevel', 'error',
                    '-ss', (string)$timePos,
                    '-i', $videoPath,
                    '-vframes', '1',
                    '-threads', '1',
                    '-q:v', '2',
                    '-y',
                    $frameFile
                ];
                
                $descriptorspec = [
                    0 => ['pipe', 'r'],  // stdin
                    1 => ['pipe', 'w'],  // stdout  
                    2 => ['pipe', 'w']   // stderr
                ];
                
                $process = proc_open($cmd, $descriptorspec, $pipes);
                
                if (is_resource($process)) {
                    fclose($pipes[0]);
                    $stdout = stream_get_contents($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    
                    $return_code = proc_close($process);
                    
                    if ($return_code === 0 && file_exists($frameFile)) {
                    $imageData = base64_encode(file_get_contents($frameFile));
                    $frames[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:image/jpeg;base64,' . $imageData,
                            'detail' => 'high'
                        ]
                    ];
                        error_log("Successfully extracted frame {$i} at {$timePos}s from " . basename($videoPath));
                    } else {
                        error_log("Failed to extract frame {$i} at {$timePos}s: return_code={$return_code}, stderr={$stderr}");
                    }
                }
            }
            
        } finally {
            // Clean up frame files
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
        
        error_log("Extracted " . count($frames) . " frames from " . basename($videoPath));
        return $frames;
    }
    
    private function extractAndTranscribeAudio($videoPath) {
        global $OPENAI_API_KEY;
        
        if (empty($OPENAI_API_KEY)) {
            return null;
        }

        // Use proc_open instead of shell_exec since it's disabled on Hostinger
        $ffmpeg_path = '/home/u230128646/bin/ffmpeg';
        
        if (!file_exists($ffmpeg_path)) {
            error_log("FFmpeg not found at {$ffmpeg_path} - cannot extract audio from video: " . basename($videoPath));
            return null;
        }
        
        $tmpAudio = sys_get_temp_dir() . '/audio_' . uniqid() . '.mp3';
        
        // Extract audio (max 25MB for OpenAI Whisper) using proc_open
        $cmd = [
            $ffmpeg_path,
            '-hide_banner',
            '-loglevel', 'error',
            '-i', $videoPath,
            '-vn',
            '-acodec', 'mp3',
            '-ab', '64k',
            '-ar', '16000',
            '-t', '300',
            $tmpAudio
        ];
        
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout  
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (!is_resource($process)) {
            error_log("Failed to start audio extraction process for " . basename($videoPath));
            return null;
        }
        
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $return_code = proc_close($process);
        
        if ($return_code !== 0) {
            error_log("Audio extraction failed: return_code={$return_code}, stderr={$stderr}");
            return null;
        }
        
        if (!file_exists($tmpAudio) || filesize($tmpAudio) == 0) {
            error_log("Audio extraction produced no output file");
            return null;
        }
        
        error_log("Successfully extracted audio from " . basename($videoPath) . " (" . round(filesize($tmpAudio)/1024, 1) . "KB)");
        
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
    
    /**
     * Convert processed content to Google Gemini API format
     */
    public function formatForGemini() {
        // First run the standard preprocessing
        $standard_context = $this->preprocessAllMedia();
        
        // Convert visual content to Gemini's format
        $media_parts = [];
        foreach ($this->aggregated_context['visual_content'] as $visual_item) {
            if ($visual_item['type'] === 'image_url') {
                // Convert OpenAI format to Gemini format
                $base64_data = $visual_item['image_url']['url'];
                if (preg_match('/data:([^;]+);base64,(.+)/', $base64_data, $matches)) {
                    $mime_type = $matches[1];
                    $base64_content = $matches[2];
                    
                    $media_parts[] = [
                        'inlineData' => [
                            'mimeType' => $mime_type,
                            'data' => $base64_content
                        ]
                    ];
                }
            }
        }
        
        return [
            'context_text' => $standard_context['context_text'],
            'media_parts' => $media_parts,
            'media_summary' => $standard_context['media_summary']
        ];
    }

    /**
     * Fallback when ffmpeg is not available - provide detailed description
     */
    private function describeVideoFallback($file_path, $filename) {
        $size = round(filesize($file_path) / (1024*1024), 1);
        $mime_type = mime_content_type($file_path);
        
        // Create a comprehensive fallback description
        $description = "📹 VIDEO FILE ANALYSIS (without frame extraction):\n";
        $description .= "• File: {$filename}\n";
        $description .= "• Size: {$size}MB\n";
        $description .= "• Type: {$mime_type}\n";
        $description .= "• Analysis: This is a video file containing tree footage that requires professional arborist assessment.\n";
        $description .= "• Recommendation: Based on the video submission, conduct comprehensive on-site evaluation for accurate species identification, health assessment, and pruning specifications.\n";
        
        $this->aggregated_context['visual_content'][] = [
            'type' => 'text',
            'text' => $description
        ];
        
        $this->aggregated_context['media_summary'][] = "🎬 {$filename} ({$size}MB video - detailed description provided)";
        
        return true;
    }

    /**
     * Extract video frames and save to disk for display and AI analysis
     */
    private function extractVideoFramesAdvanced($video_path, $quote_id) {
        $frames_dir = __DIR__ . "/../../uploads/frames/quote_{$quote_id}";
        if (!is_dir($frames_dir)) {
            mkdir($frames_dir, 0755, true);
        }
        
        // Clean existing frames
        $existing_frames = glob($frames_dir . "/*.jpg");
        foreach ($existing_frames as $frame) {
            unlink($frame);
        }
        
        $frames = [];
        $frame_paths = [];
        
        // Check if shell_exec and ffmpeg are available
        $ffmpeg_available = false;
        $ffmpeg_path = null;
        
        // First check if shell_exec is available
        if (function_exists('shell_exec') && !str_contains(ini_get('disable_functions'), 'shell_exec')) {
            error_log("shell_exec is available, checking for ffmpeg...");
            $ffmpeg_paths = ["/usr/bin/ffmpeg", "/usr/local/bin/ffmpeg", "ffmpeg"];
            
            foreach ($ffmpeg_paths as $path) {
                $test = shell_exec("which {$path} 2>/dev/null");
                if (!empty($test)) {
                    $ffmpeg_path = trim($test);
                    $ffmpeg_available = true;
                    break;
                }
            }
        } else {
            error_log("shell_exec is NOT available on this server - using fallback for Quote #{$quote_id}");
        }
        
        if ($ffmpeg_available && function_exists("shell_exec") && !str_contains(ini_get("disable_functions"), "shell_exec")) {
            // Extract 6 frames at different intervals
            $intervals = [5, 15, 30, 45, 60, 90]; // seconds
            
            foreach ($intervals as $i => $seconds) {
                $frame_file = "{$frames_dir}/frame_" . sprintf("%02d", $i+1) . "_{$seconds}s.jpg";
                $cmd = "{$ffmpeg_path} -ss {$seconds} -i " . escapeshellarg($video_path) . 
                       " -vframes 1 -q:v 2 -y " . escapeshellarg($frame_file) . " 2>/dev/null";
                
                shell_exec($cmd);
                
                if (file_exists($frame_file) && filesize($frame_file) > 1000) {
                    $frame_paths[] = $frame_file;
                    
                    // Convert to base64 for AI
                    $imageData = base64_encode(file_get_contents($frame_file));
                    $frames[] = [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:image/jpeg;base64," . $imageData,
                            "detail" => "high"
                        ]
                    ];
                    
                    error_log("Extracted frame " . ($i+1) . " at {$seconds}s for Quote #{$quote_id}");
                }
            }
            
            if (!empty($frames)) {
                error_log("Successfully extracted " . count($frames) . " frames for Quote #{$quote_id}");
            }
        }
        
        return [
            "frames" => $frames,
            "frame_paths" => $frame_paths,
            "method" => $ffmpeg_available ? "ffmpeg" : "fallback"
        ];
    }
}