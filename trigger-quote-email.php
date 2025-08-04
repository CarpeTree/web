<?php
// Trigger a real quote submission email test
require_once 'server/config/database-simple.php';

echo "🎯 Triggering quote submission email...\n";

try {
    // Find the most recent quote to use for testing
    $stmt = $pdo->prepare("SELECT id FROM quotes ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $latest_quote = $stmt->fetch();
    
    if ($latest_quote) {
        $quote_id = $latest_quote['id'];
        echo "📋 Using quote ID: $quote_id\n";
        
        // Trigger admin notification for this quote
        echo "📤 Triggering admin notification email...\n";
        
        $notification_url = "http://localhost/server/api/admin-notification-simple.php";
        $post_data = http_build_query(['quote_id' => $quote_id]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $post_data
            ]
        ]);
        
        // Try to call the notification API
        echo "🔔 Calling notification API...\n";
        
        // Alternative: Direct file include
        $_POST['quote_id'] = $quote_id;
        ob_start();
        include 'server/api/admin-notification-simple.php';
        $result = ob_get_clean();
        
        echo "📧 Notification result:\n";
        echo $result . "\n";
        
    } else {
        echo "❌ No quotes found in database\n";
        echo "💡 Submit a quote through the website first\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "✅ Email trigger complete!\n";
echo "📬 Check sapport@carpetree.com for new emails\n";
?>