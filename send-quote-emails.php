<?php
// Send quote notification emails using basic PHP mail()
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== SENDING QUOTE EMAILS ===\n\n";

try {
    // Get completed quotes that need email notifications
    $stmt = $pdo->prepare("
        SELECT q.*, c.email, c.name 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.quote_status = 'draft_ready' 
        AND q.ai_analysis_complete = 1
        AND c.email = 'phil.bajenski@gmail.com'
        ORDER BY q.quote_created_at DESC
    ");
    $stmt->execute();
    $quotes = $stmt->fetchAll();
    
    if (empty($quotes)) {
        echo "❌ No completed quotes found for phil.bajenski@gmail.com\n";
        exit;
    }
    
    echo "✅ Found " . count($quotes) . " quotes to send:\n\n";
    
    foreach ($quotes as $quote) {
        echo "Processing Quote #{$quote['id']}...\n";
        
        // Parse AI response
        $ai_response = json_decode($quote['ai_response_json'], true);
        $services = json_decode($quote['selected_services'], true) ?: [];
        $services_text = implode(', ', $services);
        
        // Create email content
        $subject = "Your Tree Service Quote #" . $quote['id'] . " - Carpe Tree'em";
        
        $message = "Dear " . $quote['name'] . ",\n\n";
        $message .= "Thank you for your tree service request! Here's your personalized quote:\n\n";
        $message .= "QUOTE #" . $quote['id'] . "\n";
        $message .= "Services Requested: " . $services_text . "\n";
        $message .= "Estimated Total: $" . number_format($quote['total_estimate'], 2) . "\n\n";
        
        if ($ai_response && isset($ai_response['cost_breakdown'])) {
            $message .= "COST BREAKDOWN:\n";
            foreach ($ai_response['cost_breakdown'] as $item) {
                $message .= "- " . ucfirst($item['service']) . ": $" . number_format($item['estimated_cost'], 2) . "\n";
                if (isset($item['notes'])) {
                    $message .= "  " . $item['notes'] . "\n";
                }
            }
            $message .= "\n";
        }
        
        if ($ai_response && isset($ai_response['recommendations'])) {
            $message .= "RECOMMENDATIONS:\n";
            foreach ($ai_response['recommendations'] as $rec) {
                $message .= "- " . $rec['description'] . "\n";
            }
            $message .= "\n";
        }
        
        $message .= "NEXT STEPS:\n";
        $message .= "This is a preliminary estimate based on the information provided.\n";
        $message .= "We recommend scheduling an in-person assessment for a final quote.\n\n";
        
        $message .= "To proceed or ask questions:\n";
        $message .= "📞 Phone: 778-655-3741\n";
        $message .= "📧 Email: sapport@carpetree.com\n\n";
        
        $message .= "Thank you for choosing Carpe Tree'em!\n\n";
        $message .= "Best regards,\n";
        $message .= "The Carpe Tree'em Team\n";
        $message .= "Professional Tree Care Services";
        
        // Send email
        $headers = "From: quotes@carpetree.com\r\n";
        $headers .= "Reply-To: sapport@carpetree.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        echo "Sending to: {$quote['email']}\n";
        echo "Subject: $subject\n";
        
        $mail_sent = mail($quote['email'], $subject, $message, $headers);
        
        if ($mail_sent) {
            echo "✅ Email sent successfully!\n";
            
            // Log email in database
            $log_stmt = $pdo->prepare("
                INSERT INTO email_log (recipient_email, subject, template_used, status, quote_id, sent_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $quote['email'],
                $subject,
                'quote_ready_basic',
                'sent',
                $quote['id']
            ]);
            
            // Update quote status
            $update_stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'sent_to_client' WHERE id = ?");
            $update_stmt->execute([$quote['id']]);
            
            echo "✅ Database updated\n";
            
        } else {
            echo "❌ Email failed to send\n";
            
            // Log failure
            $log_stmt = $pdo->prepare("
                INSERT INTO email_log (recipient_email, subject, template_used, status, error_message, quote_id, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $quote['email'],
                $subject,
                'quote_ready_basic',
                'failed',
                'PHP mail() function failed',
                $quote['id']
            ]);
        }
        
        echo "---\n\n";
    }
    
    // Check final email log
    echo "=== EMAIL LOG SUMMARY ===\n";
    $log_stmt = $pdo->prepare("SELECT * FROM email_log ORDER BY sent_at DESC LIMIT 5");
    $log_stmt->execute();
    $logs = $log_stmt->fetchAll();
    
    foreach ($logs as $log) {
        echo "To: {$log['recipient_email']} | Status: {$log['status']} | Quote: {$log['quote_id']} | Time: {$log['sent_at']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 