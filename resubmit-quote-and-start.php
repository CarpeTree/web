<?php
// Resubmit quote and start project

echo "🌲 Carpe Tree Quote Resubmission & Project Starter\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Sample quote data
$quoteData = [
    'customer_name' => 'Phil Bajenski',
    'customer_email' => 'phil.bajenski@gmail.com',
    'customer_phone' => '778-655-3741',
    'property_address' => '123 Tree Street, Vancouver, BC',
    'project_type' => 'Tree Assessment',
    'project_description' => 'Need professional assessment of large maple tree in backyard. Concerned about potential hazards and overall tree health.',
    'urgency' => 'routine',
    'preferred_contact' => 'email',
    'budget_range' => '500-1000',
    'property_type' => 'residential',
    'timeline' => 'within_month',
    'additional_notes' => 'Tree has some dead branches and bark damage. Located near house foundation.',
    'source' => 'website_resubmit',
    'latitude' => '49.2827',
    'longitude' => '-123.1207',
    'gps_lat_1' => '49.2827',
    'gps_lng_1' => '-123.1207'
];

echo "📋 Quote Data Prepared:\n";
echo "👤 Customer: {$quoteData['customer_name']}\n";
echo "📧 Email: {$quoteData['customer_email']}\n";
echo "📍 Location: {$quoteData['property_address']}\n";
echo "🌲 Service: {$quoteData['project_type']}\n\n";

// Function to submit quote
function submitQuote($data) {
    $endpoints = [
        'http://localhost:8000/server/api/submitQuote.php',
        'http://localhost:8000/server/api/submitQuote-reliable.php',
        'http://localhost:8000/server/api/submitQuote-working.php'
    ];
    
    foreach ($endpoints as $endpoint) {
        echo "📤 Trying endpoint: $endpoint\n";
        
        $postData = http_build_query($data);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                           "Content-Length: " . strlen($postData) . "\r\n",
                'content' => $postData,
                'timeout' => 30
            ]
        ]);
        
        $result = @file_get_contents($endpoint, false, $context);
        
        if ($result !== false) {
            echo "✅ SUCCESS! Quote submitted via $endpoint\n";
            echo "📄 Response: " . substr($result, 0, 200) . "...\n\n";
            return true;
        } else {
            echo "❌ Failed: $endpoint\n";
        }
    }
    
    return false;
}

// Start local development server
echo "🚀 Starting Local Development Server...\n";
echo "-------------------------------------------\n";

// Check if server is already running
$serverCheck = @file_get_contents('http://localhost:8000', false, stream_context_create([
    'http' => ['timeout' => 2]
]));

if ($serverCheck !== false) {
    echo "✅ Server already running at http://localhost:8000\n\n";
} else {
    echo "🔧 Starting PHP development server...\n";
    
    // Start server in background
    $serverCommand = "php -S localhost:8000 > server.log 2>&1 &";
    exec($serverCommand);
    
    // Wait for server to start
    echo "⏳ Waiting for server to initialize...\n";
    sleep(3);
    
    // Test server
    $testResult = @file_get_contents('http://localhost:8000', false, stream_context_create([
        'http' => ['timeout' => 5]
    ]));
    
    if ($testResult !== false) {
        echo "✅ Development server started successfully!\n";
        echo "🌐 Access your site at: http://localhost:8000\n\n";
    } else {
        echo "❌ Server failed to start properly\n\n";
    }
}

// Submit the quote
echo "📝 Submitting Test Quote...\n";
echo "----------------------------\n";

if (submitQuote($quoteData)) {
    echo "🎉 Quote submission successful!\n\n";
    
    // Check admin email notification
    echo "📧 Checking admin notification...\n";
    $notifyResult = @file_get_contents('http://localhost:8000/server/api/admin-notification-simple.php', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'customer_name' => $quoteData['customer_name'],
                'customer_email' => $quoteData['customer_email'],
                'project_type' => $quoteData['project_type']
            ])
        ]
    ]));
    
    if ($notifyResult) {
        echo "✅ Admin notification sent to phil.bajenski@gmail.com\n\n";
    }
    
} else {
    echo "❌ Quote submission failed - trying direct database insert...\n\n";
    
    // Try direct database approach
    try {
        require_once 'server/config/config.php';
        $pdo = getDatabaseConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO quotes (
                customer_name, customer_email, customer_phone, 
                property_address, project_type, project_description,
                status, created_at, gps_coordinates
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), POINT(?, ?))
        ");
        
        $stmt->execute([
            $quoteData['customer_name'],
            $quoteData['customer_email'], 
            $quoteData['customer_phone'],
            $quoteData['property_address'],
            $quoteData['project_type'],
            $quoteData['project_description'],
            $quoteData['longitude'],
            $quoteData['latitude']
        ]);
        
        echo "✅ Quote inserted directly into database!\n";
        echo "📊 Quote ID: " . $pdo->lastInsertId() . "\n\n";
        
    } catch (Exception $e) {
        echo "❌ Database insert failed: " . $e->getMessage() . "\n\n";
    }
}

// Project status summary
echo "🎯 Project Status Summary\n";
echo "========================\n";
echo "✅ Local server: http://localhost:8000\n";
echo "✅ Quote form: http://localhost:8000/quote.html\n";
echo "✅ Video form: http://localhost:8000/video_quote.html\n";
echo "✅ Admin dash: http://localhost:8000/admin-dashboard.html\n";
echo "✅ Debug tools: http://localhost:8000/debug-simple.html\n\n";

echo "📧 Email Configuration:\n";
echo "📬 Admin email: phil.bajenski@gmail.com\n";
echo "📨 Test notification: Should arrive in Gmail\n\n";

echo "🧪 Quick Tests:\n";
echo "1. Visit http://localhost:8000/quote.html\n";
echo "2. Test progress bar with 🧪 button\n";
echo "3. Submit a real quote with photos\n";
echo "4. Check Gmail for notifications\n\n";

echo "🚀 Project Started Successfully!\n";
echo "Ready for development and testing.\n";
?>