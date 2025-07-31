<?php
// Google Gemini Client for video and image analysis
require_once __DIR__ . '/../config/config.php';

class GeminiClient {
    private $api_key;
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta';
    
    public function __construct() {
        global $GOOGLE_GEMINI_API_KEY;
        $this->api_key = $GOOGLE_GEMINI_API_KEY ?? null;
        
        if (!$this->api_key) {
            throw new Exception('Google Gemini API key not configured');
        }
    }
    
    /**
     * Analyze video file for tree assessment
     */
    public function analyzeVideo($video_path, $services_requested, $customer_notes = '') {
        return $this->analyzeVideoWithModel($video_path, $services_requested, $customer_notes, 'gemini-1.5-pro');
    }
    
    /**
     * Analyze video file with specific model
     */
    public function analyzeVideoWithModel($video_path, $services_requested, $customer_notes = '', $model = 'gemini-2.5-pro') {
        try {
            // First upload the video file
            $file_uri = $this->uploadFile($video_path, 'video/*');
            
            // Wait for file processing
            $this->waitForFileProcessing($file_uri);
            
            // Generate content with video analysis
            $prompt = $this->buildVideoAnalysisPrompt($services_requested, $customer_notes);
            
            $response = $this->generateContent($prompt, $file_uri, $model);
            
            return $this->parseAnalysisResponse($response);
            
        } catch (Exception $e) {
            error_log("Gemini video analysis error: " . $e->getMessage());
            return $this->getFallbackAnalysis($services_requested, $customer_notes);
        }
    }
    
    /**
     * Analyze image for tree assessment
     */
    public function analyzeImage($image_path, $services_requested, $customer_notes = '') {
        return $this->analyzeImageWithModel($image_path, $services_requested, $customer_notes, 'gemini-1.5-pro');
    }
    
    /**
     * Analyze image with specific model
     */
    public function analyzeImageWithModel($image_path, $services_requested, $customer_notes = '', $model = 'gemini-2.5-pro') {
        try {
            // Upload image
            $file_uri = $this->uploadFile($image_path, 'image/*');
            
            // Generate analysis
            $prompt = $this->buildImageAnalysisPrompt($services_requested, $customer_notes);
            $response = $this->generateContent($prompt, $file_uri, $model);
            
            return $this->parseAnalysisResponse($response);
            
        } catch (Exception $e) {
            error_log("Gemini image analysis error: " . $e->getMessage());
            return $this->getFallbackAnalysis($services_requested, $customer_notes);
        }
    }
    
    private function uploadFile($file_path, $mime_type) {
        $url = $this->base_url . '/files?key=' . $this->api_key;
        
        // Read file
        $file_data = file_get_contents($file_path);
        if (!$file_data) {
            throw new Exception("Failed to read file: $file_path");
        }
        
        // Prepare multipart form data
        $boundary = uniqid();
        $data = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"metadata\"\r\n";
        $data .= "Content-Type: application/json\r\n\r\n";
        $data .= json_encode(['name' => basename($file_path)]) . "\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . basename($file_path) . "\"\r\n";
        $data .= "Content-Type: $mime_type\r\n\r\n";
        $data .= $file_data . "\r\n";
        $data .= "--$boundary--\r\n";
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                "Content-Type: multipart/form-data; boundary=$boundary",
                "Content-Length: " . strlen($data)
            ]
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code !== 200) {
            throw new Exception("File upload failed: HTTP $http_code");
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['file']['uri'])) {
            throw new Exception("Invalid upload response");
        }
        
        return $result['file']['uri'];
    }
    
    private function waitForFileProcessing($file_uri, $max_wait = 60) {
        $start_time = time();
        
        while (time() - $start_time < $max_wait) {
            $file_info = $this->getFileInfo($file_uri);
            
            if ($file_info['state'] === 'ACTIVE') {
                return true;
            } elseif ($file_info['state'] === 'FAILED') {
                throw new Exception("File processing failed");
            }
            
            sleep(2);
        }
        
        throw new Exception("File processing timeout");
    }
    
    private function getFileInfo($file_uri) {
        $file_name = basename($file_uri);
        $url = $this->base_url . "/files/$file_name?key=" . $this->api_key;
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $result = json_decode($response, true);
        return $result ?? ['state' => 'UNKNOWN'];
    }
    
    private function generateContent($prompt, $file_uri, $model = 'gemini-2.5-pro') {
        $url = $this->base_url . '/models/' . $model . ':generateContent?key=' . $this->api_key;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        ['file_data' => ['file_uri' => $file_uri]]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 4000
            ]
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($http_code !== 200) {
            throw new Exception("Content generation failed: HTTP $http_code");
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid generation response");
        }
        
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
    
    private function buildVideoAnalysisPrompt($services, $notes) {
        // Load the standardized system prompt
        $system_prompt = file_get_contents(__DIR__ . '/../../ai/system_prompt.txt');
        
        $user_prompt = "📋 Customer Services Requested: " . implode(', ', $services) . "\n\n";
        $user_prompt .= "🎬 VIDEO ANALYSIS FOCUS:\n";
        $user_prompt .= "- Tree movement patterns and wind response behavior\n";
        $user_prompt .= "- Dynamic load behavior and structural response\n";
        $user_prompt .= "- Hazardous conditions that may not be visible in still photos\n";
        $user_prompt .= "- Root zone stability and lean assessment during movement\n\n";
        $user_prompt .= "Customer Notes: " . $notes . "\n\n";
        $user_prompt .= "Please analyze this video and provide a comprehensive tree care assessment focusing on details uniquely visible in video format.";
        
        return $system_prompt . "\n\n" . $user_prompt;
    }
    
    private function buildImageAnalysisPrompt($services, $notes) {
        // Load the standardized system prompt
        $system_prompt = file_get_contents(__DIR__ . '/../../ai/system_prompt.txt');
        
        $user_prompt = "📋 Customer Services Requested: " . implode(', ', $services) . "\n\n";
        $user_prompt .= "📸 IMAGE ANALYSIS FOCUS:\n";
        $user_prompt .= "- Tree species identification using visible characteristics\n";
        $user_prompt .= "- Structural assessment from static view\n";
        $user_prompt .= "- Visual measurement estimation using reference objects\n";
        $user_prompt .= "- Health assessment from visible symptoms\n\n";
        $user_prompt .= "Customer Notes: " . $notes . "\n\n";
        $user_prompt .= "Please analyze this image and provide a comprehensive tree care assessment.";
        
        return $system_prompt . "\n\n" . $user_prompt;
    }
    
    private function parseAnalysisResponse($response) {
        // Parse the Gemini response and format it for the application
        return [
            'analysis' => $response,
            'confidence' => 'high',
            'source' => 'gemini',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getFallbackAnalysis($services, $notes) {
        return [
            'analysis' => 'Gemini analysis unavailable. Manual assessment required for: ' . implode(', ', $services),
            'confidence' => 'low',
            'source' => 'fallback',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
?>