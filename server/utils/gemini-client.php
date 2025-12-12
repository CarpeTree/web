<?php
class GeminiClient {
    private $apiKey;
    private $apiUrl;

    public function __construct($apiKey) {
        if (empty($apiKey)) {
            throw new Exception("Google Gemini API key is required.");
        }
        $this->apiKey = $apiKey;
    }

    /**
     * Main method to analyze the full context object from the MediaPreprocessor.
     */
    public function analyzeAggregatedContextWithModel($aggregated_context, $model = 'gemini-3.0-pro') {
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";
        
        list($requestBody, $system_instruction) = $this->buildRequestPayload($aggregated_context);

        // The system instruction is now a top-level key in the request
        $requestBody['system_instruction'] = ['parts' => [['text' => $system_instruction]]];

        $response = $this->sendRequest($requestBody);

        return $this->parseAnalysisResponse($response);
    }

    /**
     * Builds the main payload for the Gemini API request.
     */
    private function buildRequestPayload($context) {
        $system_prompt_text = file_get_contents(__DIR__ . '/../../ai/system_prompt.txt');
        $json_schema_string = file_get_contents(__DIR__ . '/../../ai/schema.json');
        
        if (!$system_prompt_text || !$json_schema_string) {
            throw new Exception("Failed to load Gemini system prompt or function schema.");
        }
        
        $json_schema = json_decode($json_schema_string, true);

        // Prepare the 'parts' array with text and visual content
        $content_parts = [];
        $content_parts[] = ['text' => $context['context_text']];

        foreach ($context['visual_content'] as $visual) {
            if ($visual['type'] === 'image_url') {
                // Convert OpenAI's format to Google's format
                list($mime, $data) = explode(';', $visual['image_url']['url']);
                list(, $mime_type) = explode(':', $mime);
                list(, $base64_data) = explode(',', $data);
                
                $content_parts[] = [
                    'inline_data' => [
                        'mime_type' => $mime_type,
                        'data' => $base64_data
                    ]
                ];
            }
        }
        
        $requestBody = [
            'contents' => [['role' => 'user', 'parts' => $content_parts]],
            'tools' => [['function_declarations' => [$json_schema]]],
            'tool_config' => [
                'function_calling_config' => [
                    'mode' => 'ANY',
                    'allowed_function_names' => ['draft_tree_quote']
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 32768,
            ]
        ];

        // The system prompt text is returned separately to be added at the top level
        return [$requestBody, $system_prompt_text];
    }

    /**
     * Sends the cURL request to the Gemini API.
     */
    private function sendRequest($requestBody) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 180
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($http_code !== 200) {
            throw new Exception("Gemini API request failed. HTTP Code: {$http_code}. Error: {$curl_error}. Response: " . $response);
        }

        return json_decode($response, true);
    }

    /**
     * Parses the response from Gemini to extract the analysis and token counts.
     */
    private function parseAnalysisResponse($response) {
        $function_call = $response['candidates'][0]['content']['parts'][0]['functionCall'] ?? null;
        
        if (!$function_call || !isset($function_call['args'])) {
            throw new Exception("Invalid Gemini response: function call or arguments not found. Response: " . json_encode($response));
        }

        return [
            'analysis' => json_encode($function_call['args']), // Return as JSON string to match other models
            'input_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
        ];
    }
}
?>