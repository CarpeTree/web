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
                'gpt-5' => ['input' => 10.00, 'output' => 30.00],
                // Keep legacy mapping for historical rows still labeled as o4-mini
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

        // Handle database connection recovery
        try {
            // Ensure connection is alive
            if (!$this->pdo || !$this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)) {
                $this->pdo = $this->reconnectDatabase();
            }
            
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
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'server has gone away') !== false) {
                // Reconnect and retry
                $this->pdo = getDatabaseConnection();
                if ($this->pdo) {
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
                } else {
                    error_log("CostTracker: Database reconnection failed: " . $e->getMessage());
                    // Continue without saving cost - don't fail the entire analysis
                }
            } else {
                error_log("CostTracker: Database error: " . $e->getMessage());
                // Continue without saving cost - don't fail the entire analysis
            }
        }

        return [
            'total_cost' => $total_cost,
            'input_cost' => $input_cost,
            'output_cost' => $output_cost
        ];
    }
}
?>