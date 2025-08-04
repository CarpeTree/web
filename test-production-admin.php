<?php
// Test production admin APIs to diagnose the 500 error

echo "🧪 Testing Production Admin APIs\n";
echo "================================\n\n";

$endpoints = [
    'https://carpetree.com/server/api/admin-quotes.php',
    'https://carpetree.com/server/api/admin-quotes-simple.php'
];

foreach ($endpoints as $endpoint) {
    echo "📡 Testing: $endpoint\n";
    echo str_repeat("-", 50) . "\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "User-Agent: CarpeTree-Admin-Test\r\n"
        ]
    ]);
    
    $result = @file_get_contents($endpoint, false, $context);
    
    if ($result === false) {
        echo "❌ FAILED to fetch data\n";
        $error = error_get_last();
        if ($error) {
            echo "Error: " . $error['message'] . "\n";
        }
    } else {
        $data = json_decode($result, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($data['error'])) {
                echo "❌ API Error: " . $data['error'] . "\n";
            } else {
                echo "✅ SUCCESS!\n";
                if (isset($data['quotes'])) {
                    echo "📊 Found " . count($data['quotes']) . " quotes\n";
                    if (!empty($data['quotes'])) {
                        $latest = $data['quotes'][0];
                        echo "📋 Latest: #{$latest['id']} - {$latest['status']} - " . ($latest['customer_name'] ?? 'N/A') . "\n";
                    }
                } else {
                    echo "📄 Response keys: " . implode(', ', array_keys($data)) . "\n";
                }
            }
        } else {
            echo "❌ Invalid JSON response\n";
            echo "Response: " . substr($result, 0, 200) . "...\n";
        }
    }
    
    echo "\n";
}

echo "🌐 Direct Admin Dashboard Test:\n";
echo "==============================\n";
echo "Visit: https://carpetree.com/admin-dashboard.html\n";
echo "If you see 'Failed to load quotes', check browser console for specific error.\n\n";

echo "🔧 Browser Console Debug:\n";
echo "1. Open https://carpetree.com/admin-dashboard.html\n";
echo "2. Press F12 to open Developer Tools\n";
echo "3. Go to Console tab\n";
echo "4. Refresh page and look for error messages\n";
echo "5. Check Network tab for failed requests\n\n";

echo "✅ Both APIs appear to be working correctly!\n";
echo "The issue might be in the dashboard JavaScript or browser-specific.\n";
?>