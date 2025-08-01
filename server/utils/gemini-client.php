
    /**
     * Analyze aggregated context with specific model
     */
    public function analyzeAggregatedContextWithModel($aggregated_context, $model = 'gemini-2.5-pro') {
        try {
            // Generate content with aggregated context
            $prompt = $this->buildAggregatedContextPrompt($aggregated_context['context_text']);
            
            $parts = [['text' => $prompt]];
            foreach ($aggregated_context['visual_content'] as $visual_content) {
                $parts[] = $visual_content;
            }

            $response = $this->generateContent($parts, $model);
            
            return $this->parseAnalysisResponse($response);
            
        } catch (Exception $e) {
            error_log("Gemini aggregated context analysis error: " . $e->getMessage());
            return $this->getFallbackAnalysis([], '');
        }
    }

    private function buildAggregatedContextPrompt($context_text) {
        // Load the standardized system prompt
        $system_prompt = file_get_contents(__DIR__ . '/../../ai/system_prompt.txt');
        
        $user_prompt = "📋 Aggregated Context:\n" . $context_text . "\n\n";
        $user_prompt .= "Please analyze this aggregated context and provide a comprehensive tree care assessment.";
        
        return $system_prompt . "\n\n" . $user_prompt;
    }
