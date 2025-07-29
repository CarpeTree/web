<?php
// Automated reminder system - run via cron every hour
// Add to crontab: 0 * * * * /usr/bin/php /path/to/reminder-check.php

require_once dirname(__DIR__) . '/config/database-simple.php';
require_once dirname(__DIR__) . '/api/admin-notification-simple.php';

echo "=== CARPE TREE'EM REMINDER CHECK ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Find quotes older than 12 hours that are still pending
    $stmt = $pdo->prepare("
        SELECT q.id, q.quote_created_at, c.name, c.email
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.quote_status IN ('ai_processing', 'draft_ready', 'admin_review')
        AND q.quote_created_at < DATE_SUB(NOW(), INTERVAL 12 HOUR)
        AND q.id NOT IN (
            SELECT DISTINCT quote_id 
            FROM email_log 
            WHERE template_used = 'admin_reminder' 
            AND status = 'sent'
            AND quote_id IS NOT NULL
        )
        ORDER BY q.quote_created_at ASC
    ");
    
    $stmt->execute();
    $overdue_quotes = $stmt->fetchAll();
    
    if (empty($overdue_quotes)) {
        echo "✅ No overdue quotes found.\n";
        echo "All quotes are either recent (<12 hours) or already have reminders sent.\n";
    } else {
        echo "🚨 Found " . count($overdue_quotes) . " overdue quotes:\n\n";
        
        $reminders_sent = 0;
        $errors = 0;
        
        foreach ($overdue_quotes as $quote) {
            $hours_old = (time() - strtotime($quote['quote_created_at'])) / 3600;
            echo "Processing Quote #{$quote['id']}:\n";
            echo "  Customer: {$quote['name']} ({$quote['email']})\n";
            echo "  Age: " . round($hours_old, 1) . " hours\n";
            echo "  Submitted: {$quote['quote_created_at']}\n";
            
            // Send reminder email
            if (sendReminderEmail($quote['id'])) {
                echo "  ✅ Reminder sent successfully\n";
                $reminders_sent++;
            } else {
                echo "  ❌ Failed to send reminder\n";
                $errors++;
            }
            echo "  ---\n";
        }
        
        echo "\n=== SUMMARY ===\n";
        echo "Reminders sent: $reminders_sent\n";
        echo "Errors: $errors\n";
        echo "Total processed: " . count($overdue_quotes) . "\n";
    }
    
    // Also check for quotes that are VERY overdue (24+ hours)
    $urgent_stmt = $pdo->prepare("
        SELECT q.id, q.quote_created_at, c.name, c.email
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.quote_status IN ('ai_processing', 'draft_ready', 'admin_review')
        AND q.quote_created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY q.quote_created_at ASC
    ");
    
    $urgent_stmt->execute();
    $urgent_quotes = $urgent_stmt->fetchAll();
    
    if (!empty($urgent_quotes)) {
        echo "\n🚨 URGENT: " . count($urgent_quotes) . " quotes are 24+ hours old:\n";
        foreach ($urgent_quotes as $quote) {
            $hours_old = (time() - strtotime($quote['quote_created_at'])) / 3600;
            echo "  Quote #{$quote['id']}: {$quote['name']} - " . round($hours_old, 1) . " hours old\n";
        }
        echo "These need immediate attention!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    error_log("Reminder check error: " . $e->getMessage());
}

echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
?> 