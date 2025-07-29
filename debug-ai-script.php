<?php
// Debug AI processing directly
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== AI PROCESSING DEBUG ===\n\n";

require_once 'server/config/database-simple.php';

try {
    // Check quotes waiting for AI processing
    $stmt = $pdo->prepare("
        SELECT q.*, c.email, c.name 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.quote_status = 'ai_processing' AND q.ai_analysis_complete = 0
        ORDER BY q.quote_created_at ASC
    ");
    $stmt->execute();
    $quotes = $stmt->fetchAll();
    
    foreach ($quotes as $quote) {
        echo "=== PROCESSING QUOTE {$quote['id']} ===\n";
        echo "Customer: {$quote['name']} ({$quote['email']})\n";
        echo "Services: {$quote['selected_services']}\n";
        echo "Notes: {$quote['notes']}\n";
        echo "Created: {$quote['quote_created_at']}\n\n";
        
        // Check for uploaded files
        $file_stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = ?");
        $file_stmt->execute([$quote['id']]);
        $files = $file_stmt->fetchAll();
        
        if (empty($files)) {
            echo "❌ No files found for this quote\n";
            echo "📁 Checking uploads directory...\n";
            
            $upload_dir = dirname(__DIR__) . "/uploads/{$quote['id']}";
            echo "Upload path: $upload_dir\n";
            
            if (is_dir($upload_dir)) {
                $dir_files = scandir($upload_dir);
                $actual_files = array_filter($dir_files, function($f) { return !in_array($f, ['.', '..']); });
                
                if (!empty($actual_files)) {
                    echo "✅ Found files in directory: " . implode(', ', $actual_files) . "\n";
                    echo "🔧 Files exist but not tracked in database - this could be the issue!\n";
                } else {
                    echo "❌ Upload directory exists but is empty\n";
                }
            } else {
                echo "❌ Upload directory doesn't exist\n";
            }
        } else {
            echo "✅ Found " . count($files) . " tracked files:\n";
            foreach ($files as $file) {
                echo "  - {$file['filename']} ({$file['mime_type']}, " . number_format($file['file_size']) . " bytes)\n";
                echo "    Path: {$file['file_path']}\n";
                if (file_exists($file['file_path'])) {
                    echo "    ✅ File exists on disk\n";
                } else {
                    echo "    ❌ File missing on disk\n";
                }
            }
        }
        
        echo "\n🤖 Attempting to run AI processing manually...\n";
        
        // Try to include and run the AI script directly
        $ai_script_path = __DIR__ . '/server/api/aiQuote.php';
        echo "AI script path: $ai_script_path\n";
        echo "AI script exists: " . (file_exists($ai_script_path) ? 'YES' : 'NO') . "\n";
        
        if (file_exists($ai_script_path)) {
            echo "🚀 Running AI processing for quote {$quote['id']}...\n";
            
            // Capture output from AI script
            ob_start();
            $old_argv = $_SERVER['argv'] ?? [];
            $_SERVER['argv'] = ['aiQuote.php', $quote['id']];
            
            try {
                include $ai_script_path;
                $ai_output = ob_get_contents();
                echo "AI Script Output:\n" . $ai_output . "\n";
            } catch (Exception $e) {
                echo "❌ AI Script Error: " . $e->getMessage() . "\n";
            } finally {
                ob_end_clean();
                $_SERVER['argv'] = $old_argv;
            }
        }
        
        echo "\n" . str_repeat("=", 50) . "\n\n";
    }
    
    // Check if AI script has any requirements
    echo "=== SYSTEM CHECK ===\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "Memory Limit: " . ini_get('memory_limit') . "\n";
    echo "Max Execution Time: " . ini_get('max_execution_time') . "\n";
    echo "OpenAI Extension: " . (extension_loaded('curl') ? 'curl available' : 'curl NOT available') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 