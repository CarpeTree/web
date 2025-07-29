<?php
// Manually trigger AI processing for quotes with files
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== MANUAL AI PROCESSING TRIGGER ===\n\n";

try {
    // Find quotes that have files but haven't been AI processed
    $stmt = $pdo->prepare("
        SELECT DISTINCT q.id, q.quote_status, c.email, c.name
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        LEFT JOIN uploaded_files f ON q.id = f.quote_id
        WHERE f.id IS NOT NULL 
        AND (q.quote_status = 'submitted' OR q.quote_status = '')
        AND q.ai_analysis_complete = 0
        ORDER BY q.quote_created_at DESC
    ");
    
    $stmt->execute();
    $quotes_to_process = $stmt->fetchAll();
    
    if (empty($quotes_to_process)) {
        echo "❌ No quotes found that need AI processing\n";
    } else {
        echo "✅ Found " . count($quotes_to_process) . " quotes to process:\n\n";
        
        foreach ($quotes_to_process as $quote) {
            echo "Processing Quote ID: {$quote['id']} for {$quote['name']} ({$quote['email']})\n";
            
            // Update status to ai_processing
            $update_stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'ai_processing' WHERE id = ?");
            $update_stmt->execute([$quote['id']]);
            
            // Trigger AI processing
            $ai_script = __DIR__ . '/server/api/aiQuote.php';
            $command = "cd " . __DIR__ . "/server && php api/aiQuote.php {$quote['id']} > /dev/null 2>&1 &";
            
            echo "Command: $command\n";
            exec($command, $output, $return_code);
            
            if ($return_code === 0) {
                echo "✅ AI processing triggered successfully\n";
            } else {
                echo "❌ Failed to trigger AI processing (return code: $return_code)\n";
            }
            echo "---\n\n";
        }
    }
    
    // Also check current AI processing status
    echo "=== CURRENT AI PROCESSING STATUS ===\n";
    $stmt = $pdo->prepare("
        SELECT q.id, q.quote_status, q.ai_analysis_complete, c.name
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.quote_status = 'ai_processing'
        ORDER BY q.quote_created_at DESC
        LIMIT 5
    ");
    
    $stmt->execute();
    $processing_quotes = $stmt->fetchAll();
    
    if (empty($processing_quotes)) {
        echo "❌ No quotes currently being AI processed\n";
    } else {
        foreach ($processing_quotes as $quote) {
            echo "Quote {$quote['id']} ({$quote['name']}): Status = {$quote['quote_status']}, AI Complete = " . ($quote['ai_analysis_complete'] ? 'YES' : 'NO') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 