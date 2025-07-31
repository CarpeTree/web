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
        return "You are an expert certified arborist analyzing this video for Carpe Tree'em, a professional tree care service in British Columbia, Canada.

ANALYSIS REQUIREMENTS:
1. Identify tree species, health, and condition from the video
2. Assess safety risks and structural integrity visible in the footage
3. Evaluate proximity to structures, power lines, and property features
4. Note any movement, wind response, or dynamic behavior of trees
5. Identify hazardous conditions that may not be visible in still photos

SERVICES REQUESTED: " . implode(', ', $services) . "

CUSTOMER NOTES: $notes

Please provide a detailed analysis including:
- Tree species identification
- Health assessment (disease, pest damage, structural issues)
- Safety concerns and risk factors
- Specific recommendations for the requested services
- Estimated scope of work
- Any urgent safety issues requiring immediate attention

Focus on details that are uniquely visible in video format (movement, wind response, dynamic load behavior).";
    }
    
    private function buildImageAnalysisPrompt($services, $notes) {
        return "You are an expert certified arborist analyzing this image for Carpe Tree'em, a professional tree care service in British Columbia, Canada.

ANALYSIS REQUIREMENTS:
1. Identify tree species, health, and condition
2. Assess safety risks and structural integrity
3. Evaluate proximity to structures, power lines, and property features
4. Provide specific service recommendations

SERVICES REQUESTED: " . implode(', ', $services) . "

CUSTOMER NOTES: $notes

Please provide a detailed analysis including:
- Tree species identification
- Health assessment (disease, pest damage, structural issues)
- Safety concerns and risk factors
- Specific recommendations for the requested services
- Estimated scope of work
- Priority level for intervention";
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