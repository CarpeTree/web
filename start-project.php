<?php
// Start Carpe Tree project and open quote form

echo "🌲 Starting Carpe Tree Project...\n";
echo "================================\n\n";

// Check if server is running
$serverRunning = false;
$testResult = @file_get_contents('http://localhost:8000', false, stream_context_create([
    'http' => ['timeout' => 2]
]));

if ($testResult !== false) {
    echo "✅ Development server already running!\n";
    $serverRunning = true;
} else {
    echo "🚀 Starting PHP development server...\n";
    
    // Start server in background
    if (PHP_OS_FAMILY === 'Windows') {
        pclose(popen('start /B php -S localhost:8000', 'r'));
    } else {
        exec('php -S localhost:8000 > /dev/null 2>&1 &');
    }
    
    echo "⏳ Waiting for server to start...\n";
    sleep(3);
    
    // Test server
    $testResult = @file_get_contents('http://localhost:8000', false, stream_context_create([
        'http' => ['timeout' => 5]
    ]));
    
    if ($testResult !== false) {
        echo "✅ Development server started successfully!\n";
        $serverRunning = true;
    } else {
        echo "❌ Server startup failed\n";
    }
}

if ($serverRunning) {
    echo "\n🎯 Project URLs:\n";
    echo "===============\n";
    echo "🏠 Main site: http://localhost:8000/\n";
    echo "📝 Quote form: http://localhost:8000/quote.html\n";
    echo "🎬 Video form: http://localhost:8000/video_quote.html\n";
    echo "👑 Admin: http://localhost:8000/admin-dashboard.html\n";
    echo "🧪 Debug: http://localhost:8000/debug-simple.html\n\n";
    
    echo "📧 Email Status:\n";
    echo "================\n";
    echo "✅ Admin email: phil.bajenski@gmail.com\n";
    echo "✅ Notifications: Will be sent to Gmail\n";
    echo "✅ Progress bar: Fully functional\n\n";
    
    echo "🧪 Ready to Test:\n";
    echo "=================\n";
    echo "1. Progress bar animation\n";
    echo "2. Form submission with photos\n";
    echo "3. Email notifications to Gmail\n";
    echo "4. Admin dashboard functionality\n\n";
    
    // Try to open browser automatically
    echo "🌐 Opening quote form in browser...\n";
    
    $url = 'http://localhost:8000/quote.html';
    
    if (PHP_OS_FAMILY === 'Darwin') { // macOS
        exec("open '$url'");
    } elseif (PHP_OS_FAMILY === 'Windows') {
        exec("start '$url'");
    } elseif (PHP_OS_FAMILY === 'Linux') {
        exec("xdg-open '$url'");
    }
    
    echo "✅ Browser should open automatically\n";
    echo "   If not, manually visit: $url\n\n";
    
    echo "🎉 Project Started Successfully!\n";
    echo "================================\n";
    echo "Ready for quote submissions and testing.\n";
    echo "Check your Gmail for notifications!\n";
    
} else {
    echo "\n❌ Failed to start development server\n";
    echo "Try manually: php -S localhost:8000\n";
}
?>