<?php
// Create a test quote directly via the live server

echo "📝 Creating Test Quote for Phil...\n";
echo "==================================\n\n";

// Test quote data
$quoteData = [
    'customer_name' => 'Phil Bajenski',
    'customer_email' => 'phil.bajenski@gmail.com',
    'customer_phone' => '778-655-3741',
    'property_address' => '123 Tree Lane, Vancouver, BC V6B 1A1',
    'project_type' => 'Tree Assessment & Pruning',
    'project_description' => 'Large maple tree assessment - concerned about dead branches near house. Need professional evaluation and potential pruning services.',
    'urgency' => 'routine',
    'preferred_contact' => 'email',
    'budget_range' => '500-1000',
    'property_type' => 'residential',
    'timeline' => 'within_month',
    'additional_notes' => 'Tree is approximately 40 feet tall, located in backyard near foundation. Some bark damage visible.',
    'latitude' => '49.2827',
    'longitude' => '-123.1207',
    'source' => 'php_resubmit_test'
];

echo "👤 Customer: {$quoteData['customer_name']}\n";
echo "📧 Email: {$quoteData['customer_email']}\n";
echo "🌲 Service: {$quoteData['project_type']}\n";
echo "📍 Location: {$quoteData['property_address']}\n\n";

// Submit to live server
echo "📤 Submitting to live server (carpetree.com)...\n";

$postData = http_build_query($quoteData);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                   "User-Agent: Carpe-Tree-Test-Script\r\n" .
                   "Content-Length: " . strlen($postData) . "\r\n",
        'content' => $postData,
        'timeout' => 30
    ]
]);

$liveEndpoints = [
    'https://carpetree.com/server/api/submitQuote.php',
    'https://carpetree.com/server/api/submitQuote-reliable.php'
];

foreach ($liveEndpoints as $endpoint) {
    echo "🎯 Trying: $endpoint\n";
    
    $result = @file_get_contents($endpoint, false, $context);
    
    if ($result !== false) {
        echo "✅ SUCCESS! Quote submitted to live server\n";
        echo "📄 Response: " . substr($result, 0, 200) . "...\n\n";
        
        // Trigger admin notification
        echo "📧 Triggering admin notification...\n";
        $adminResult = @file_get_contents('https://carpetree.com/server/api/admin-notification-simple.php', false, $context);
        
        if ($adminResult) {
            echo "✅ Admin notification sent to phil.bajenski@gmail.com\n\n";
        }
        
        echo "🎉 Test Quote Created Successfully!\n";
        echo "===================================\n";
        echo "📬 Check your Gmail for:\n";
        echo "  • Quote confirmation email\n";
        echo "  • Admin notification email\n\n";
        echo "🌐 View on admin dashboard:\n";
        echo "  https://carpetree.com/admin-dashboard.html\n\n";
        echo "✅ Quote resubmitted and project ready!\n";
        
        break;
    } else {
        echo "❌ Failed: $endpoint\n";
    }
}

if (!isset($result) || $result === false) {
    echo "\n❌ All endpoints failed. Manual submission required.\n";
    echo "🌐 Go to: https://carpetree.com/quote.html\n";
    echo "📝 Fill out form with the data above\n";
}
?>