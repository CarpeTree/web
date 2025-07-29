<?php
// Check recent quote submissions
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== RECENT QUOTE SUBMISSIONS ===\n\n";

try {
    // Get recent quotes with customer info
    $stmt = $pdo->prepare("
        SELECT 
            q.id as quote_id,
            q.quote_status,
            q.selected_services,
            q.notes,
            q.quote_created_at,
            c.email,
            c.name,
            c.phone,
            c.address
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        ORDER BY q.quote_created_at DESC 
        LIMIT 10
    ");
    
    $stmt->execute();
    $quotes = $stmt->fetchAll();
    
    if (empty($quotes)) {
        echo "❌ No quotes found in database\n";
    } else {
        echo "✅ Found " . count($quotes) . " recent quotes:\n\n";
        
        foreach ($quotes as $quote) {
            echo "Quote ID: {$quote['quote_id']}\n";
            echo "Status: {$quote['quote_status']}\n";
            echo "Customer: {$quote['name']} ({$quote['email']})\n";
            echo "Phone: {$quote['phone']}\n";
            echo "Address: {$quote['address']}\n";
            echo "Services: {$quote['selected_services']}\n";
            echo "Notes: {$quote['notes']}\n";
            echo "Created: {$quote['quote_created_at']}\n";
            echo "---\n\n";
        }
    }
    
    // Check if there are any email logs
    echo "=== EMAIL LOG ===\n";
    $stmt = $pdo->prepare("SELECT * FROM email_log ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo "❌ No emails logged\n";
    } else {
        echo "✅ Found " . count($emails) . " recent email attempts:\n\n";
        foreach ($emails as $email) {
            echo "To: {$email['recipient_email']}\n";
            echo "Subject: {$email['subject']}\n";
            echo "Status: {$email['status']}\n";
            echo "Template: {$email['template_used']}\n";
            echo "Quote ID: {$email['quote_id']}\n";
            echo "Created: {$email['created_at']}\n";
            if ($email['error_message']) {
                echo "Error: {$email['error_message']}\n";
            }
            echo "---\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?> 