<?php
// Check latest quotes to find a real one to test with
require_once __DIR__ . '/server/config/database-simple.php';

echo "Checking latest quotes in database...\n";

$stmt = $pdo->prepare("SELECT q.id, q.customer_id, q.quote_created_at, c.name, c.email 
                       FROM quotes q 
                       JOIN customers c ON q.customer_id = c.id 
                       ORDER BY q.id DESC LIMIT 5");
$stmt->execute();
$quotes = $stmt->fetchAll();

if ($quotes) {
    echo "📋 Latest quotes found:\n";
    foreach ($quotes as $quote) {
        echo "- Quote #{$quote['id']}: {$quote['name']} ({$quote['email']}) - {$quote['quote_created_at']}\n";
    }
    
    $latest_quote = $quotes[0];
    echo "\n🎯 Testing iCloud SMTP with Quote #{$latest_quote['id']}...\n";
    
    require_once __DIR__ . '/server/api/admin-notification.php';
    $result = sendAdminNotification($latest_quote['id']);
    
    if ($result) {
        echo "✅ SUCCESS: iCloud SMTP admin email sent!\n";
        echo "📧 Check your Gmail inbox for:\n";
        echo "   Subject: New Quote Request #{$latest_quote['id']} - {$latest_quote['name']} - Carpe Tree'em\n";
        echo "   From: quotes@carpetree.com (authenticated via iCloud)\n";
        echo "   Content: Professional admin notification with CRM dashboard links\n";
    } else {
        echo "❌ FAILED: iCloud SMTP admin email failed\n";
    }
    
} else {
    echo "❌ No quotes found in database\n";
}
?> 