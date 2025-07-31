<?php
// Apply quote status ENUM update for multi-AI workflow
require_once 'server/config/config.php';
require_once 'server/config/database-simple.php';

echo "🔄 Updating quote_status ENUM to include multi-AI statuses...\n";

try {
    $sql = "ALTER TABLE quotes 
            MODIFY COLUMN quote_status ENUM(
                'submitted', 
                'ai_processing', 
                'multi_ai_processing',
                'draft_ready', 
                'multi_ai_complete',
                'sent_to_client', 
                'accepted', 
                'rejected', 
                'expired'
            ) DEFAULT 'submitted'";
    
    $pdo->exec($sql);
    
    echo "✅ Quote status ENUM updated successfully!\n";
    echo "📊 New statuses added:\n";
    echo "  - multi_ai_processing (when all 3 AI models are running)\n";
    echo "  - multi_ai_complete (when all 3 AI models are finished)\n";
    echo "\n🎯 Admin emails will now be sent only after ALL AI models complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error updating quote status ENUM: " . $e->getMessage() . "\n";
    exit(1);
}
?>