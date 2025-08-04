<?php
// Update admin email to phil.bajenski@gmail.com across all files

echo "📧 Updating admin email to phil.bajenski@gmail.com...\n";

$files_to_update = [
    'server/api/submitQuote-with-progress.php',
    'server/api/submitQuote-debug.php',
    'hostinger-config.php',
    'simple-email-test.php',
    'test-email-trigger.php'
];

$old_email = 'sapport@carpetree.com';
$new_email = 'phil.bajenski@gmail.com';

foreach ($files_to_update as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $updated_content = str_replace($old_email, $new_email, $content);
        
        if ($content !== $updated_content) {
            file_put_contents($file, $updated_content);
            echo "✅ Updated: $file\n";
        } else {
            echo "ℹ️  No changes needed: $file\n";
        }
    } else {
        echo "❌ Not found: $file\n";
    }
}

echo "\n📝 Manual .env update needed:\n";
echo "Update your .env file:\n";
echo "ADMIN_EMAIL=phil.bajenski@gmail.com\n";

echo "\n🧪 Testing new admin email...\n";

// Test email to new address
$to = $new_email;
$subject = '🎯 Admin Email Updated - ' . date('H:i:s');
$message = "
<html>
<body style='font-family: Arial, sans-serif;'>
    <h2 style='color: #2c5f2d;'>📧 Admin Email Successfully Updated!</h2>
    <p><strong>New admin email:</strong> $new_email</p>
    <p><strong>Updated at:</strong> " . date('Y-m-d H:i:s') . "</p>
    <div style='background: #f0f8f0; padding: 15px; border-left: 4px solid #2c5f2d; margin: 20px 0;'>
        <p><strong>✅ Quote notifications will now be sent to:</strong></p>
        <p style='font-size: 18px; font-weight: bold; color: #2c5f2d;'>$new_email</p>
    </div>
    <p>🌲 <strong>Carpe Tree Website System</strong></p>
</body>
</html>";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Carpe Tree System <noreply@carpetree.com>\r\n";
$headers .= "Reply-To: $new_email\r\n";

echo "📤 Sending test email to $new_email...\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ SUCCESS! Test email sent to $new_email\n";
    echo "📬 Check your Gmail inbox\n";
} else {
    echo "❌ Email sending failed\n";
}

echo "\n✅ Admin email update complete!\n";
?>