<?php
// Check AI processing status and results
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== AI PROCESSING DIAGNOSTIC ===\n\n";

try {
    // Check quotes with AI processing status
    echo "=== QUOTES WITH AI PROCESSING STATUS ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            q.id as quote_id,
            q.quote_status,
            q.ai_analysis_complete,
            q.ai_response_json,
            q.quote_created_at,
            c.email,
            c.name
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.quote_status = 'ai_processing' OR q.ai_analysis_complete = 1
        ORDER BY q.quote_created_at DESC
    ");
    
    $stmt->execute();
    $ai_quotes = $stmt->fetchAll();
    
    if (empty($ai_quotes)) {
        echo "❌ No quotes found with AI processing status\n\n";
    } else {
        echo "✅ Found " . count($ai_quotes) . " quotes with AI processing:\n\n";
        
        foreach ($ai_quotes as $quote) {
            echo "Quote ID: {$quote['quote_id']}\n";
            echo "Status: {$quote['quote_status']}\n";
            echo "Customer: {$quote['name']} ({$quote['email']})\n";
            echo "AI Complete: " . ($quote['ai_analysis_complete'] ? 'YES' : 'NO') . "\n";
            echo "Created: {$quote['quote_created_at']}\n";
            
            if ($quote['ai_response_json']) {
                echo "AI Response: " . substr($quote['ai_response_json'], 0, 200) . "...\n";
            } else {
                echo "AI Response: None\n";
            }
            echo "---\n\n";
        }
    }
    
    // Check uploaded files for quotes
    echo "=== UPLOADED FILES ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.quote_id,
            f.filename,
            f.file_path,
            f.file_size,
            f.mime_type,
            f.uploaded_at,
            q.quote_status
        FROM uploaded_files f
        JOIN quotes q ON f.quote_id = q.id
        ORDER BY f.uploaded_at DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $files = $stmt->fetchAll();
    
    if (empty($files)) {
        echo "❌ No uploaded files found\n\n";
    } else {
        echo "✅ Found " . count($files) . " uploaded files:\n\n";
        
        foreach ($files as $file) {
            echo "File ID: {$file['id']}\n";
            echo "Quote ID: {$file['quote_id']}\n";
            echo "Filename: {$file['filename']}\n";
            echo "Path: {$file['file_path']}\n";
            echo "Size: " . number_format($file['file_size']) . " bytes\n";
            echo "Type: {$file['mime_type']}\n";
            echo "Quote Status: {$file['quote_status']}\n";
            echo "Uploaded: {$file['uploaded_at']}\n";
            echo "---\n\n";
        }
    }
    
    // Check email log with error details
    echo "=== EMAIL LOG WITH DETAILS ===\n";
    $stmt = $pdo->prepare("SELECT * FROM email_log ORDER BY sent_at DESC LIMIT 10");
    $stmt->execute();
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo "❌ No emails logged\n\n";
    } else {
        echo "✅ Found " . count($emails) . " email log entries:\n\n";
        foreach ($emails as $email) {
            echo "To: {$email['recipient_email']}\n";
            echo "Subject: {$email['subject']}\n";
            echo "Status: {$email['status']}\n";
            echo "Template: {$email['template_used']}\n";
            echo "Quote ID: {$email['quote_id']}\n";
            echo "Sent: {$email['sent_at']}\n";
            if ($email['error_message']) {
                echo "Error: {$email['error_message']}\n";
            }
            echo "---\n\n";
        }
    }
    
    // Check for any cron job or AI processing logs
    echo "=== SYSTEM STATUS ===\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
    echo "Upload Directory Exists: " . (is_dir(dirname(__DIR__) . '/uploads') ? 'YES' : 'NO') . "\n";
    echo "AI Script Path: " . __DIR__ . '/server/api/aiQuote.php' . "\n";
    echo "AI Script Exists: " . (file_exists(__DIR__ . '/server/api/aiQuote.php') ? 'YES' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?> 