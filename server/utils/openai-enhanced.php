<?php
// Enhanced OpenAI Client with Full Token Tracking and Reasoning Traces

class EnhancedOpenAIClient {
    private $api_key;
    private $pdo;
    private $quote_id;
    private $processing_log_id;
    
    public function __construct($api_key, $pdo, $quote_id) {
        $this->api_key = $api_key;
        $this->pdo = $pdo;
        $this->quote_id = $quote_id;
        $this->initializeProcessingLog();
    }
    
    private function initializeProcessingLog() {
        $stmt = $this->pdo->prepare("
            INSERT INTO ai_processing_logs (
                quote_id, ai_model, processing_status, created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$this->quote_id, 'o1-mini', 'started']);
        $this->processing_log_id = $this->pdo->lastInsertId();
    }
    
    public function analyzeTreeMediaWithFullTracking($files, $customer_data, $services) {
        $start_time = microtime(true);
        
        try {
            // Prepare detailed prompt
            $prompt = $this->buildDetailedPrompt($customer_data, $services, $files);
            
            // Prepare request payload
            $request_payload = $this->buildRequestPayload($prompt, $files);
            
            // Log the request
            $this->updateProcessingLog([
                'request_payload' => $request_payload,
                'prompt_tokens' => $this->estimateTokens($prompt)
            ]);
            
            // Make API call with enhanced tracking
            $response = $this->makeTrackedAPICall($request_payload);
            
            // Process and analyze response
            $analysis_result = $this->processFullResponse($response, $start_time);
            
            // Log completion
            $this->updateProcessingLog([
                'processing_status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'response_payload' => $response,
                'total_tokens' => $analysis_result['token_usage']['total_tokens'] ?? 0,
                'prompt_tokens' => $analysis_result['token_usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $analysis_result['token_usage']['completion_tokens'] ?? 0,
                'processing_time_ms' => $analysis_result['processing_time_ms'],
                'api_cost_usd' => $this->calculateCost($analysis_result['token_usage'] ?? []),
                'reasoning_trace' => $analysis_result['reasoning_trace'] ?? []
            ]);
            
            return $analysis_result;
            
        } catch (Exception $e) {
            $this->updateProcessingLog([
                'processing_status' => 'failed',
                'error_details' => $e->getMessage(),
                'processing_time_ms' => (microtime(true) - $start_time) * 1000
            ]);
            
            // Return fallback analysis with error details
            return $this->getFallbackAnalysisWithTracking($customer_data, $services, $e->getMessage());
        }
    }
    
    private function buildDetailedPrompt($customer_data, $services, $files) {
        $has_media = !empty($files);
        $services_list = implode(', ', $services);
        
        $prompt = "You are an expert arborist analyzing a tree service request. Provide detailed reasoning for your assessment.\n\n";
        
        $prompt .= "CUSTOMER REQUEST:\n";
        $prompt .= "- Name: {$customer_data['name']}\n";
        $prompt .= "- Location: {$customer_data['address']}\n";
        $prompt .= "- Services Requested: $services_list\n";
        $prompt .= "- Notes: {$customer_data['notes']}\n";
        
        if ($has_media) {
            $prompt .= "- Media Files: " . count($files) . " photo(s)/video(s) provided\n\n";
            $prompt .= "ANALYSIS INSTRUCTIONS:\n";
            $prompt .= "1. Carefully examine all provided images/videos\n";
            $prompt .= "2. Identify tree species, health, and structure\n";
            $prompt .= "3. Assess safety risks and concerns\n";
            $prompt .= "4. Provide specific recommendations\n";
            $prompt .= "5. Estimate pricing based on complexity\n\n";
        } else {
            $prompt .= "- Media Files: None provided (text-only analysis)\n\n";
            $prompt .= "ANALYSIS INSTRUCTIONS:\n";
            $prompt .= "1. Analyze based on customer description only\n";
            $prompt .= "2. Note limitations of text-only assessment\n";
            $prompt .= "3. Recommend in-person evaluation\n";
            $prompt .= "4. Provide preliminary estimates\n\n";
        }
        
        $prompt .= "REASONING REQUIREMENTS:\n";
        $prompt .= "- Show your step-by-step thinking process\n";
        $prompt .= "- Explain the basis for each recommendation\n";
        $prompt .= "- Detail any assumptions made\n";
        $prompt .= "- Identify areas of uncertainty\n";
        $prompt .= "- Provide confidence levels for assessments\n\n";
        
        $prompt .= "RESPONSE FORMAT:\n";
        $prompt .= "Provide a detailed JSON response with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"reasoning_trace\": {\n";
        $prompt .= "    \"initial_assessment\": \"string\",\n";
        $prompt .= "    \"tree_analysis\": \"string\",\n";
        $prompt .= "    \"safety_evaluation\": \"string\",\n";
        $prompt .= "    \"pricing_rationale\": \"string\",\n";
        $prompt .= "    \"confidence_level\": \"high|medium|low\",\n";
        $prompt .= "    \"assumptions_made\": [\"string\"],\n";
        $prompt .= "    \"limitations\": [\"string\"]\n";
        $prompt .= "  },\n";
        $prompt .= "  \"analysis_summary\": {\n";
        $prompt .= "    \"total_trees_identified\": number,\n";
        $prompt .= "    \"primary_tree_species\": [\"string\"],\n";
        $prompt .= "    \"health_assessment\": \"healthy|declining|hazardous\",\n";
        $prompt .= "    \"urgency_level\": \"low|medium|high|emergency\",\n";
        $prompt .= "    \"complexity_score\": number\n";
        $prompt .= "  },\n";
        $prompt .= "  \"recommendations\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"service\": \"string\",\n";
        $prompt .= "      \"priority\": \"high|medium|low\",\n";
        $prompt .= "      \"description\": \"string\",\n";
        $prompt .= "      \"rationale\": \"string\",\n";
        $prompt .= "      \"estimated_hours\": number,\n";
        $prompt .= "      \"equipment_needed\": [\"string\"],\n";
        $prompt .= "      \"safety_considerations\": [\"string\"]\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"cost_analysis\": {\n";
        $prompt .= "    \"base_labor_hours\": number,\n";
        $prompt .= "    \"complexity_multiplier\": number,\n";
        $prompt .= "    \"equipment_costs\": number,\n";
        $prompt .= "    \"disposal_costs\": number,\n";
        $prompt .= "    \"total_estimate\": number,\n";
        $prompt .= "    \"price_range_low\": number,\n";
        $prompt .= "    \"price_range_high\": number\n";
        $prompt .= "  },\n";
        $prompt .= "  \"risk_assessment\": {\n";
        $prompt .= "    \"safety_risks\": [\"string\"],\n";
        $prompt .= "    \"property_risks\": [\"string\"],\n";
        $prompt .= "    \"weather_considerations\": [\"string\"],\n";
        $prompt .= "    \"permit_requirements\": [\"string\"]\n";
        $prompt .= "  }\n";
        $prompt .= "}\n\n";
        
        $prompt .= "Ed Gilman Pruning Standards (if applicable):\n";
        $prompt .= "- Use proper pruning cuts following ANSI A300 standards\n";
        $prompt .= "- Specify number and size of cuts needed\n";
        $prompt .= "- Consider tree response and compartmentalization\n";
        $prompt .= "- Account for seasonal timing\n\n";
        
        return $prompt;
    }
    
    private function buildRequestPayload($prompt, $files) {
        $messages = [
            [
                "role" => "system",
                "content" => "You are an expert certified arborist with 20+ years of experience in tree care, risk assessment, and Ed Gilman pruning standards. Provide detailed, professional analysis with clear reasoning."
            ],
            [
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => $prompt]
                ]
            ]
        ];
        
        // Add images if provided
        foreach ($files as $file) {
            if (strpos($file['mime_type'], 'image/') === 0) {
                $image_data = base64_encode(file_get_contents($file['file_path']));
                $messages[1]["content"][] = [
                    "type" => "image_url",
                    "image_url" => [
                        "url" => "data:{$file['mime_type']};base64,{$image_data}",
                        "detail" => "high"
                    ]
                ];
            }
        }
        
        return [
            "model" => "o1-mini",
            "messages" => $messages,
            "max_tokens" => 16000,
            "temperature" => 0.1,
            "top_p" => 0.9
        ];
    }
    
    private function makeTrackedAPICall($payload) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ],
            CURLOPT_TIMEOUT => 300,
            CURLOPT_VERBOSE => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("cURL Error: $curl_error");
        }
        
        if ($http_code !== 200) {
            throw new Exception("API Error: HTTP $http_code - $response");
        }
        
        $decoded = json_decode($response, true);
        if (!$decoded) {
            throw new Exception("Invalid JSON response from API");
        }
        
        return $decoded;
    }
    
    private function processFullResponse($response, $start_time) {
        $processing_time_ms = (microtime(true) - $start_time) * 1000;
        
        // Extract content and usage
        $content = $response['choices'][0]['message']['content'] ?? '';
        $token_usage = $response['usage'] ?? [];
        
        // Parse JSON content
        $analysis_data = null;
        $reasoning_trace = null;
        
        // Try to extract JSON from content
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json_str = $matches[0];
            $analysis_data = json_decode($json_str, true);
            
            if ($analysis_data && isset($analysis_data['reasoning_trace'])) {
                $reasoning_trace = $analysis_data['reasoning_trace'];
            }
        }
        
        // If no structured data, create from content
        if (!$analysis_data) {
            $analysis_data = $this->parseUnstructuredResponse($content);
        }
        
        return [
            'success' => true,
            'analysis_data' => $analysis_data,
            'reasoning_trace' => $reasoning_trace,
            'raw_content' => $content,
            'token_usage' => $token_usage,
            'processing_time_ms' => $processing_time_ms,
            'model_used' => $response['model'] ?? 'o1-mini',
            'response_metadata' => [
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? '',
                'response_id' => $response['id'] ?? '',
                'created' => $response['created'] ?? time()
            ]
        ];
    }
    
    private function parseUnstructuredResponse($content) {
        // Fallback parsing for unstructured responses
        return [
            'reasoning_trace' => [
                'initial_assessment' => $content,
                'confidence_level' => 'medium',
                'limitations' => ['Unstructured response format']
            ],
            'analysis_summary' => [
                'total_trees_identified' => 1,
                'health_assessment' => 'unknown',
                'urgency_level' => 'medium',
                'complexity_score' => 0.5
            ],
            'recommendations' => [
                [
                    'service' => 'assessment',
                    'priority' => 'high',
                    'description' => 'Professional in-person assessment recommended',
                    'rationale' => 'Detailed analysis required for accurate evaluation'
                ]
            ],
            'cost_analysis' => [
                'total_estimate' => 500,
                'price_range_low' => 300,
                'price_range_high' => 800
            ]
        ];
    }
    
    private function getFallbackAnalysisWithTracking($customer_data, $services, $error) {
        return [
            'success' => false,
            'fallback_used' => true,
            'error_message' => $error,
            'analysis_data' => [
                'reasoning_trace' => [
                    'initial_assessment' => 'API unavailable - using fallback analysis',
                    'confidence_level' => 'low',
                    'limitations' => ['API error', 'No AI analysis available'],
                    'fallback_reason' => $error
                ],
                'analysis_summary' => [
                    'total_trees_identified' => count($services) > 0 ? 1 : 0,
                    'health_assessment' => 'unknown',
                    'urgency_level' => 'medium',
                    'complexity_score' => 0.3
                ],
                'recommendations' => [
                    [
                        'service' => 'assessment',
                        'priority' => 'high',
                        'description' => 'In-person professional assessment required',
                        'rationale' => 'AI analysis unavailable - manual evaluation needed'
                    ]
                ],
                'cost_analysis' => $this->generateFallbackPricing($services)
            ],
            'token_usage' => ['total_tokens' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0],
            'processing_time_ms' => 0
        ];
    }
    
    private function generateFallbackPricing($services) {
        $base_prices = [
            'removal' => 800,
            'pruning' => 400,
            'assessment' => 150,
            'cabling' => 600,
            'planting' => 200,
            'wildfire_risk' => 500,
            'sprinkler_system' => 2500,
            'emergency' => 1200
        ];
        
        $total = 0;
        foreach ($services as $service) {
            $total += $base_prices[$service] ?? 300;
        }
        
        return [
            'base_labor_hours' => 4,
            'total_estimate' => $total,
            'price_range_low' => $total * 0.7,
            'price_range_high' => $total * 1.5
        ];
    }
    
    private function updateProcessingLog($data) {
        $set_clauses = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $set_clauses[] = "$key = ?";
            if (is_array($value)) {
                $values[] = json_encode($value);
            } else {
                $values[] = $value;
            }
        }
        
        $values[] = $this->processing_log_id;
        
        $sql = "UPDATE ai_processing_logs SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }
    
    private function estimateTokens($text) {
        // Rough estimation: ~4 characters per token
        return ceil(strlen($text) / 4);
    }
    
    private function calculateCost($token_usage) {
        // o1-mini pricing (approximate)
        $prompt_cost_per_token = 0.000003; // $3 per 1M tokens
        $completion_cost_per_token = 0.000012; // $12 per 1M tokens
        
        $prompt_cost = ($token_usage['prompt_tokens'] ?? 0) * $prompt_cost_per_token;
        $completion_cost = ($token_usage['completion_tokens'] ?? 0) * $completion_cost_per_token;
        
        return $prompt_cost + $completion_cost;
    }
}
?> 