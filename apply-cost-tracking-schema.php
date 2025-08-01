<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain');

require_once __DIR__ . '/server/config/database.php';

$message = " अप कॉस्ट ट्रैकिंग स्कीमा लागू करना...\n";
$message .= "=====================================\n";

try {
    $pdo = getDB();
    if (!$pdo) {
        throw new Exception("Database connection failed.");
    }
    $message .= "✅ Database connection successful.\n";

    $sqls = [
        "
        CREATE TABLE IF NOT EXISTS `ai_model_pricing` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `model_name` varchar(255) NOT NULL,
          `provider` varchar(100) NOT NULL,
          `input_cost_per_million_tokens` decimal(10,4) NOT NULL,
          `output_cost_per_million_tokens` decimal(10,4) NOT NULL,
          `notes` TEXT,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `model_provider_unique` (`model_name`, `provider`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "
        INSERT IGNORE INTO `ai_model_pricing` (`model_name`, `provider`, `input_cost_per_million_tokens`, `output_cost_per_million_tokens`, `notes`) VALUES
        ('o4-mini-2025-04-16', 'openai', 0.1500, 0.6000, 'Replaced gpt-4o-mini'),
        ('o3', 'openai', 2.0000, 8.0000, 'Replaced gpt-4o-pro'),
        ('gemini-2.5-pro', 'google', 0.0000, 0.0000, 'Free tier, prices TBD'),
        ('claude-3-5-sonnet', 'anthropic', 3.0000, 15.0000, 'Via OpenRouter');
        ",
        "
        CREATE TABLE IF NOT EXISTS `ai_usage_logs` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `quote_id` int(11) NOT NULL,
          `model_id` int(11) NOT NULL,
          `input_tokens` int(11) DEFAULT 0,
          `output_tokens` int(11) DEFAULT 0,
          `total_cost` decimal(10,6) NOT NULL,
          `processing_time_ms` int(11) NOT NULL,
          `log_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `quote_id` (`quote_id`),
          KEY `model_id` (`model_id`),
          CONSTRAINT `ai_usage_logs_ibfk_1` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE,
          CONSTRAINT `ai_usage_logs_ibfk_2` FOREIGN KEY (`model_id`) REFERENCES `ai_model_pricing` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "
        CREATE TABLE IF NOT EXISTS `ai_daily_costs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `cost_date` DATE NOT NULL UNIQUE,
          `total_cost` DECIMAL(10, 6) NOT NULL DEFAULT 0.000000
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "
        CREATE TABLE IF NOT EXISTS `ai_cost_summary` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `model_id` INT NOT NULL,
          `total_input_tokens` BIGINT NOT NULL DEFAULT 0,
          `total_output_tokens` BIGINT NOT NULL DEFAULT 0,
          `total_cost` DECIMAL(15, 6) NOT NULL DEFAULT 0.000000,
          UNIQUE KEY `model_id` (`model_id`),
          CONSTRAINT `ai_cost_summary_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `ai_model_pricing` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        "
    ];

    $executedCount = 0;
    foreach ($sqls as $index => $sql) {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute()) {
            $message .= "✅ Executed statement " . ($index + 1) . "\n";
            $executedCount++;
        } else {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Statement " . ($index + 1) . " failed: " . $errorInfo[2]);
        }
    }

    $message .= "🎉 Schema fixes applied successfully!\n";
    $message .= "📊 Applied $executedCount new statements\n";

} catch (Exception $e) {
    http_response_code(500);
    $message .= "❌ ERROR: " . $e->getMessage() . "\n";
}

echo $message;
?>