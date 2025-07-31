<?php
// Quick admin email resend script
// Upload this to your live server and run: yoursite.com/resend-admin-email.php?quote_id=75

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get quote ID from URL parameter
$quote_id = $_GET['quote_id'] ?? null;

if (!$quote_id) {
    die("❌ Please provide quote_id parameter: ?quote_id=75");
}

echo "<h2>📧 Resending Admin Email for Quote #{$quote_id}</h2>";

try {
    // Load existing admin notification function
    require_once __DIR__ . '/server/api/admin-notification.php';
    
    echo "<p>🔄 Attempting to send admin email...</p>";
    
    $result = sendAdminNotification($quote_id);
    
    if ($result) {
        echo "<p style='color: green;'>✅ <strong>Admin email sent successfully!</strong></p>";
        echo "<p>📧 Email sent to: {$GLOBALS['ADMIN_EMAIL']}</p>";
        echo "<p>📋 Quote #{$quote_id} details have been resent to the admin.</p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>Failed to send admin email.</strong></p>";
        echo "<p>Please check the logs for more details.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>Usage:</strong> Add ?quote_id=XX to the URL to resend for any quote ID</p>";
echo "<p><strong>Example:</strong> yoursite.com/resend-admin-email.php?quote_id=75</p>";
?>