<?php
// OpenAI o3 Client for image and video analysis
require_once __DIR__ . '/../config/config.php';

class OpenAIClient {
    private $api_key;
    private $base_url = 'https://api.openai.com/v1';
    
    public function __construct() {
        global $OPENAI_API_KEY;
        $this->api_key = $OPENAI_API_KEY ?? null;
        
        if (!$this->api_key) {
            throw new Exception('OpenAI API key not configured');
        }
    }
    
    /**
     * Analyze tree images/videos for quote generation
     */
    public function analyzeTreeMedia($files, $services_requested, $customer_notes = '') {
        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->getTreeAnalysisPrompt()
                ],
                [
                    'role' => 'user', 
                    'content' => $this->buildUserPrompt($services_requested, $customer_notes, $files)
                ]
            ];
            
            $response = $this->makeAPICall('chat/completions', [
                'model' => 'gpt-4o',  // Using latest model that supports vision
                'messages' => $messages,
                'max_tokens' => 4000,
                'temperature' => 0.3
            ]);
            
            return $this->parseTreeAnalysisResponse($response);
            
        } catch (Exception $e) {
            error_log("OpenAI API Error: " . $e->getMessage());
            return $this->getFallbackAnalysis($services_requested, $customer_notes);
        }
    }
    
    private function getTreeAnalysisPrompt() {
        return "You are an expert certified arborist analyzing tree images/videos for Carpe Tree'em, a professional tree care service in British Columbia, Canada.

ANALYSIS REQUIREMENTS:
1. Identify tree species, health, and condition
2. Assess safety risks and structural integrity  
3. Evaluate proximity to structures, power lines, and property features
4. Provide specific service recommendations and cost estimates
5. Consider BC climate, local regulations, and best practices

COST GUIDELINES (CAD):
- Tree Removal: $300-2000+ (depends on size, access, complexity)
- Tree Pruning: $200-800 (depends on tree size and extent)
- Stump Grinding: $150-500 per stump
- Tree Assessment: $150-300
- Emergency Services: $500-2500+ (urgent/hazardous)
- Tree Planting: $150-500 per tree
- Cabling/Bracing: $300-1000+ per tree
- Wildfire Risk Reduction: $500-2000+
- Sprinkler System: $2000-5000+

OUTPUT FORMAT: Respond with a JSON object containing detailed analysis, recommendations, and cost breakdowns. Be specific about tree species, risks, and methods.";
    }
    
    private function buildUserPrompt($services_requested, $customer_notes, $files) {
        $prompt = "QUOTE REQUEST ANALYSIS\n\n";
        $prompt .= "Services Requested: " . implode(', ', $services_requested) . "\n";
        if ($customer_notes) {
            $prompt .= "Customer Notes: $customer_notes\n";
        }
        $prompt .= "Files Provided: " . count($files) . " media file(s)\n\n";
        
        $prompt .= "Please analyze the provided images/videos and generate a comprehensive quote including:\n";
        $prompt .= "1. Tree species identification and health assessment\n";
        $prompt .= "2. Safety and risk evaluation\n";
        $prompt .= "3. Specific service recommendations\n";
        $prompt .= "4. Detailed cost breakdown with Canadian pricing\n";
        $prompt .= "5. Timeline and scheduling considerations\n\n";
        
        // Add file content for analysis
        $content = [];
        foreach ($files as $file) {
            if ($this->isImageFile($file['mime_type'])) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $this->encodeImageAsBase64($file['file_path'])
                    ]
                ];
            }
        }
        
        return [
            'type' => 'text',
            'text' => $prompt
        ] + $content;
    }
    
    private function isImageFile($mime_type) {
        return strpos($mime_type, 'image/') === 0;
    }
    
    private function encodeImageAsBase64($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception("Image file not found: $file_path");
        }
        
        $image_data = file_get_contents($file_path);
        $mime_type = mime_content_type($file_path);
        $base64 = base64_encode($image_data);
        
        return "data:$mime_type;base64,$base64";
    }
    
    private function makeAPICall($endpoint, $data) {
        $url = $this->base_url . '/' . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ],
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("API Error: HTTP $http_code - $response");
        }
        
        $decoded = json_decode($response, true);
        if (!$decoded) {
            throw new Exception('Invalid JSON response from API');
        }
        
        return $decoded;
    }
    
    private function parseTreeAnalysisResponse($response) {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Invalid API response structure');
        }
        
        $content = $response['choices'][0]['message']['content'];
        
        // Try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json_data = json_decode($matches[0], true);
            if ($json_data) {
                return $json_data;
            }
        }
        
        // Fallback: parse text response
        return $this->parseTextResponse($content);
    }
    
    private function parseTextResponse($content) {
        // Basic text parsing fallback
        return [
            'quote_summary' => [
                'analysis_method' => 'AI_Vision_Analysis',
                'ai_confidence' => 0.8,
                'estimated_total_cost' => 0
            ],
            'trees_analyzed' => [],
            'recommendations' => [
                [
                    'type' => 'ai_analysis',
                    'description' => 'AI analysis completed. See detailed response.',
                    'priority' => 'medium'
                ]
            ],
            'cost_breakdown' => [],
            'raw_ai_response' => $content,
            'processing_timestamp' => date('c')
        ];
    }
    
    private function getFallbackAnalysis($services_requested, $customer_notes) {
        // Fallback when API is unavailable
        $base_costs = [
            'removal' => 800,
            'pruning' => 400,
            'assessment' => 150,
            'cabling' => 600,
            'stump_grinding' => 300,
            'emergency' => 1200,
            'planting' => 200,
            'wildfire_risk' => 500,
            'sprinkler_system' => 2500
        ];
        
        $total = 0;
        $breakdown = [];
        
        foreach ($services_requested as $service) {
            if (isset($base_costs[$service])) {
                $cost = $base_costs[$service];
                $total += $cost;
                $breakdown[] = [
                    'service' => $service,
                    'estimated_cost' => $cost,
                    'notes' => 'Fallback estimate - AI analysis unavailable'
                ];
            }
        }
        
        return [
            'quote_summary' => [
                'analysis_method' => 'Fallback_Estimation',
                'estimated_total_cost' => $total,
                'requires_in_person_assessment' => true
            ],
            'cost_breakdown' => $breakdown,
            'recommendations' => [
                [
                    'type' => 'assessment_required',
                    'description' => 'In-person assessment required for accurate analysis',
                    'priority' => 'high'
                ]
            ],
            'notes' => 'AI analysis unavailable. Estimates based on service type averages.',
            'processing_timestamp' => date('c')
        ];
    }
}
?> 