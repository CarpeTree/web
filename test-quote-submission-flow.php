<?php
// Test the complete quote submission flow to verify email notifications

echo "🧪 Testing Complete Quote Submission Flow\n";
echo "=========================================\n\n";

// Test data for quote submission
$testQuote = [
    'name' => 'Email Test Customer',
    'email' => 'phil.bajenski@gmail.com',  // Use your email for testing
    'phone' => '778-555-EMAIL',
    'address' => 'Test Address, Vancouver, BC',
    'services' => json_encode(['tree_assessment', 'pruning']),
    'notes' => 'Testing email notification system - ' . date('Y-m-d H:i:s'),
    'referralSource' => 'website_test',
    'newsletterOptIn' => false
];

echo "📋 Test Quote Data:\n";
foreach (['name', 'email', 'phone', 'address'] as $field) {
    echo "  $field: {$testQuote[$field]}\n";
}
echo "\n";

// Submit to production
echo "📤 Submitting test quote to production...\n";

$postData = http_build_query($testQuote);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                   "User-Agent: EmailTest-Script\r\n",
        'content' => $postData,
        'timeout' => 30
    ]
]);

$endpoints = [
    'https://carpetree.com/server/api/submitQuote.php',
    'https://carpetree.com/server/api/submitQuote-reliable.php'
];

$success = false;
foreach ($endpoints as $endpoint) {
    echo "🎯 Trying: $endpoint\n";
    
    $result = @file_get_contents($endpoint, false, $context);
    
    if ($result !== false) {
        $data = json_decode($result, true);
        
        if ($data && isset($data['success']) && $data['success']) {
            echo "✅ SUCCESS! Quote submitted\n";
            echo "📊 Quote ID: " . ($data['quote_id'] ?? 'unknown') . "\n";
            echo "📧 Email sent: " . ($data['email_sent'] ? 'Yes' : 'No') . "\n";
            $success = true;
            break;
        } else {
            echo "❌ Failed: " . (isset($data['error']) ? $data['error'] : 'Unknown error') . "\n";
        }
    } else {
        echo "❌ Request failed\n";
    }
}

if ($success) {
    echo "\n🎉 Quote Submission Successful!\n";
    echo "===============================\n";
    echo "✅ Quote submitted to production database\n";
    echo "✅ Customer confirmation email should be sent\n";
    echo "✅ Admin notification should be sent to phil.bajenski@gmail.com\n\n";
    
    echo "📬 Check your Gmail for:\n";
    echo "1. Customer confirmation email (from quote submission)\n";
    echo "2. Admin notification email (new quote alert)\n\n";
    
    echo "🔍 If emails don't arrive:\n";
    echo "1. Check Gmail spam folder\n";
    echo "2. Wait 2-3 minutes for delivery\n";
    echo "3. Check admin dashboard for the new quote\n";
    echo "4. Verify .env ADMIN_EMAIL=phil.bajenski@gmail.com\n\n";
    
    echo "🌐 View the quote in admin dashboard:\n";
    echo "https://carpetree.com/admin-dashboard.html\n";
    
} else {
    echo "\n❌ Quote Submission Failed\n";
    echo "==========================\n";
    echo "All endpoints failed. Check server configuration.\n";
}

echo "\n📧 Email Configuration Status:\n";
echo "==============================\n";
echo "✅ .env file updated with phil.bajenski@gmail.com\n";
echo "✅ Admin dashboard updated (no more AI references)\n";
echo "✅ Quote form messaging updated (human-centered)\n";
echo "⏳ Testing quote submission and email flow...\n";
?>