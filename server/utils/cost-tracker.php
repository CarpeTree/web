<?php
// Utility for tracking AI API costs

class CostTracker {
    private $pdo;
    private $cost_rates;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        // All costs are in USD per 1,000,000 tokens
        $this->cost_rates = [
            'openai' => [
                'o4-mini' => ['input' => 0.15, 'output' => 0.60],
                'o3' => ['input' => 5.00, 'output' => 15.00],
                'whisper-1' => ['input' => 0.006, 'output' => 0] // Priced per minute, approx this per 1M tokens
            ],
            'google' => [
                'gemini-2.5-pro' => ['input' => 3.50, 'output' => 10.50]
            ]
        ];
    }

    public function trackUsage($data) {
        $provider = $data['provider'];
        $model = $data['model_name'];
        $input_tokens = $data['input_tokens'];
        $output_tokens = $data['output_tokens'];
        
        $input_cost = ($this->cost_rates[$provider][$model]['input'] / 1000000) * $input_tokens;
        $output_cost = ($this->cost_rates[$provider][$model]['output'] / 1000000) * $output_tokens;
        $total_cost = $input_cost + $output_cost;

        $stmt = $this->pdo->prepare("
            INSERT INTO ai_cost_log (quote_id, model_name, provider, input_tokens, output_tokens, total_cost, processing_time_ms)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['quote_id'],
            $model,
            $provider,
            $input_tokens,
            $output_tokens,
            $total_cost,
            $data['processing_time_ms']
        ]);

        return [
            'total_cost' => $total_cost,
            'input_cost' => $input_cost,
            'output_cost' => $output_cost
        ];
    }
}
?>