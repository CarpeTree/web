<?php
// Simple email test without database dependencies
echo "📧 Testing phil.bajenski@gmail.com email delivery...\n";

$to = 'phil.bajenski@gmail.com';
$subject = '🎯 Quote System Test - ' . date('H:i:s');
$message = "
<html>
<body style='font-family: Arial, sans-serif;'>
    <h2 style='color: #2c5f2d;'>🌲 Carpe Tree Email Test</h2>
    <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
    <p><strong>Test Type:</strong> Email delivery verification</p>
    <div style='background: #f0f8f0; padding: 15px; border-left: 4px solid #2c5f2d; margin: 20px 0;'>
        <p><strong>✅ If you receive this email:</strong></p>
        <ul>
            <li>Email system is working correctly</li>
            <li>phil.bajenski@gmail.com is receiving emails</li>
            <li>Quote notifications should work</li>
        </ul>
    </div>
    <p>📱 <strong>Phone:</strong> 778-655-3741</p>
    <p>🌐 <strong>Website:</strong> carpetree.com</p>
</body>
</html>";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Carpe Tree System <noreply@carpetree.com>\r\n";
$headers .= "Reply-To: phil.bajenski@gmail.com\r\n";

echo "📤 Sending email to phil.bajenski@gmail.com...\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ SUCCESS! Email sent to phil.bajenski@gmail.com\n";
    echo "📬 Check your email inbox (including spam folder)\n";
    echo "⏰ Email should arrive within 1-2 minutes\n";
} else {
    echo "❌ Email sending failed\n";
}

echo "\n🧪 To test the full system:\n";
echo "1. Check your phil.bajenski@gmail.com email\n";
echo "2. Submit a test quote on carpetree.com/quote.html\n";
echo "3. Watch for quote notification emails\n";
?>