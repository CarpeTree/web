<?php
// Apply AI Dashboard Schema Fixes
header('Content-Type: text/plain');
require_once 'server/config/config.php';
require_once 'server/config/database-simple.php';

echo "🔧 Applying AI Dashboard Schema Fixes...\n";
echo "=====================================\n\n";

try {
    // SQL statements to add missing AI analysis columns
    $sql_statements = [
        "ALTER TABLE quotes ADD COLUMN ai_o4_mini_analysis TEXT NULL COMMENT 'OpenAI o4-mini analysis results'",
        "ALTER TABLE quotes ADD COLUMN ai_o3_analysis TEXT NULL COMMENT 'OpenAI o3-pro analysis results'", 
        "ALTER TABLE quotes ADD COLUMN ai_gemini_analysis TEXT NULL COMMENT 'Google Gemini 2.5 Pro analysis results'"
    ];
    
    $executed = 0;
    
    foreach ($sql_statements as $index => $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Executed statement " . ($index + 1) . "\n";
            $executed++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "ℹ️  Statement " . ($index + 1) . " - Column already exists\n";
            } else {
                echo "❌ Statement " . ($index + 1) . " failed: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n🎉 Schema fixes applied successfully!\n";
    echo "📊 Applied $executed new statements\n";
    
    // Verify the columns exist
    echo "\n🔍 Verifying columns:\n";
    $columns_to_check = ['ai_o4_mini_analysis', 'ai_o3_analysis', 'ai_gemini_analysis'];
    
    foreach ($columns_to_check as $col) {
        $stmt = $pdo->query("SHOW COLUMNS FROM quotes WHERE Field = '$col'");
        $column_exists = $stmt->fetch();
        
        if ($column_exists) {
            echo "✅ $col exists\n";
        } else {
            echo "❌ $col missing\n";
        }
    }
    
    echo "\n📈 The AI Model Comparison Dashboard should now work properly.\n";
    echo "🎯 Next: Run apply-quote-status-update.php for quote status fixes\n";
    
} catch (Exception $e) {
    echo "❌ Critical error: " . $e->getMessage() . "\n";
    exit(1);
}
?>