<?php
// Fix admin email configuration to use phil.bajenski@gmail.com

echo "🔧 Fixing Admin Email Configuration\n";
echo "===================================\n\n";

// Check current config
echo "📄 Checking current configuration...\n";

$config_file = 'server/config/config.php';
if (file_exists($config_file)) {
    $content = file_get_contents($config_file);
    
    // Show current admin email setting
    if (preg_match('/\$ADMIN_EMAIL\s*=\s*[\'"]([^\'"]*)[\'"]/', $content, $matches)) {
        echo "Current ADMIN_EMAIL: " . $matches[1] . "\n";
    } else {
        echo "ADMIN_EMAIL not found in config\n";
    }
    
    // Update to phil.bajenski@gmail.com
    echo "\n📧 Updating admin email to phil.bajenski@gmail.com...\n";
    
    $updated_content = preg_replace(
        '/(\$ADMIN_EMAIL\s*=\s*[\'"])([^\'"]*)([\'"]\s*;)/',
        '$1phil.bajenski@gmail.com$3',
        $content
    );
    
    if ($updated_content !== $content) {
        file_put_contents($config_file, $updated_content);
        echo "✅ Config updated successfully\n";
    } else {
        echo "ℹ️  No changes needed\n";
    }
} else {
    echo "❌ Config file not found: $config_file\n";
}

// Check .env file too
echo "\n📄 Checking .env file...\n";
$env_file = '.env';
if (file_exists($env_file)) {
    $env_content = file_get_contents($env_file);
    
    if (preg_match('/ADMIN_EMAIL\s*=\s*(.*)/', $env_content, $matches)) {
        echo "Current .env ADMIN_EMAIL: " . trim($matches[1]) . "\n";
        
        // Update .env too
        $updated_env = preg_replace(
            '/ADMIN_EMAIL\s*=\s*.*/',
            'ADMIN_EMAIL=phil.bajenski@gmail.com',
            $env_content
        );
        
        if ($updated_env !== $env_content) {
            file_put_contents($env_file, $updated_env);
            echo "✅ .env updated successfully\n";
        }
    } else {
        // Add ADMIN_EMAIL if not present
        file_put_contents($env_file, $env_content . "\nADMIN_EMAIL=phil.bajenski@gmail.com\n");
        echo "✅ Added ADMIN_EMAIL to .env\n";
    }
} else {
    echo "ℹ️  No .env file found\n";
}

// Test production admin notification
echo "\n🧪 Testing Production Admin Notification...\n";

$test_data = [
    'customer_name' => 'Email Test Customer',
    'customer_email' => 'test@example.com',
    'project_type' => 'Email System Test'
];

$postData = http_build_query($test_data);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $postData,
        'timeout' => 10
    ]
]);

echo "📤 Triggering admin notification on production...\n";

$result = @file_get_contents('https://carpetree.com/server/api/admin-notification-simple.php', false, $context);

if ($result !== false) {
    echo "✅ Admin notification API responded\n";
    echo "Response: " . substr($result, 0, 100) . "...\n";
} else {
    echo "❌ Admin notification API failed\n";
}

echo "\n📋 Summary:\n";
echo "- Admin email should now be: phil.bajenski@gmail.com\n";
echo "- Check Gmail for test notification\n";
echo "- Submit a new quote to test the full flow\n";
echo "- Monitor server logs for any email errors\n";
?>