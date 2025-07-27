<?php
// START NEW - Cron script to expire old quotes
// Run daily: 0 2 * * * /usr/bin/php /path/to/server/cron/expireQuotes.php

require_once '../config/database.php';

try {
    // Find quotes that have expired
    $stmt = $pdo->prepare("
        UPDATE quotes 
        SET quote_status = 'expired' 
        WHERE quote_status NOT IN ('accepted', 'expired', 'rejected') 
        AND quote_expires_at < NOW()
    ");
    
    $stmt->execute();
    $expired_count = $stmt->rowCount();
    
    if ($expired_count > 0) {
        echo "Expired $expired_count quotes\n";
        
        // Log the expiration
        error_log("Quote Expiration Cron: Expired $expired_count quotes");
        
        // Optionally send admin notification about expired quotes
        if ($expired_count > 5) {
            // Many quotes expired - might indicate a problem
            $admin_email = $_ENV['ADMIN_EMAIL'] ?? 'admin@carpetree.com';
            $subject = "Warning: Many quotes expired ($expired_count)";
            $message = "A large number of quotes ($expired_count) expired today. This might indicate an issue with quote processing or follow-up.";
            
            mail($admin_email, $subject, $message);
        }
    } else {
        echo "No quotes to expire\n";
    }
    
} catch (Exception $e) {
    error_log("Quote expiration cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
// END NEW
?> 