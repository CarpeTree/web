<?php
// Diagnostic script for quote API issues
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "🔍 QUOTE API DIAGNOSTIC - " . date('Y-m-d H:i:s') . "\n";
echo "================================================\n\n";

$quote_id = $_GET['quote_id'] ?? 75;

try {
    // 1. Check if quote exists
    echo "1️⃣ CHECKING QUOTE EXISTENCE\n";
    $stmt = $pdo->prepare("SELECT id, quote_status, quote_created_at FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();
    
    if ($quote) {
        echo "✅ Quote #$quote_id exists\n";
        echo "   Status: {$quote['quote_status']}\n";
        echo "   Created: {$quote['quote_created_at']}\n\n";
    } else {
        echo "❌ Quote #$quote_id NOT FOUND\n\n";
    }
    
    // 2. Check database schema
    echo "2️⃣ CHECKING DATABASE SCHEMA\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM quotes WHERE Field = 'quote_status'");
    $column = $stmt->fetch();
    
    if ($column) {
        echo "✅ quote_status column found\n";
        echo "   Type: {$column['Type']}\n";
        
        // Check if new ENUM values exist
        $enum_values = $column['Type'];
        $has_multi_ai_processing = strpos($enum_values, 'multi_ai_processing') !== false;
        $has_multi_ai_complete = strpos($enum_values, 'multi_ai_complete') !== false;
        
        if ($has_multi_ai_processing && $has_multi_ai_complete) {
            echo "✅ New ENUM values present\n\n";
        } else {
            echo "❌ Missing new ENUM values!\n";
            echo "   Need: multi_ai_processing, multi_ai_complete\n";
            echo "   📋 Run: apply-quote-status-update.php\n\n";
        }
    }
    
    // 3. Test admin-quotes API endpoint
    echo "3️⃣ TESTING ADMIN-QUOTES API\n";
    try {
        // Simulate the API call
        $stmt = $pdo->prepare("
            SELECT 
                q.id, q.customer_id, q.quote_status, q.selected_services, q.notes,
                q.ai_response_json, q.quote_created_at, q.quote_expires_at,
                q.ai_o4_mini_analysis, q.ai_o3_analysis, q.ai_gemini_analysis,
                q.gps_lat, q.gps_lng, q.exif_lat, q.exif_lng, q.total_estimate,
                c.name as customer_name, c.email as customer_email, c.phone as customer_phone, 
                c.address, c.referral_source, c.referrer_name, c.newsletter_opt_in,
                c.geo_latitude, c.geo_longitude, c.geo_accuracy, c.ip_address,
                COUNT(m.id) as media_count
            FROM quotes q
            JOIN customers c ON q.customer_id = c.id
            LEFT JOIN media m ON q.id = m.quote_id
            WHERE q.id = ?
            GROUP BY q.id
        ");
        $stmt->execute([$quote_id]);
        $quote_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($quote_data) {
            echo "✅ API query successful\n";
            echo "   Customer: {$quote_data['customer_name']}\n";
            echo "   Email: {$quote_data['customer_email']}\n";
            echo "   Media files: {$quote_data['media_count']}\n";
            echo "   AI Analysis columns:\n";
            echo "     - o4-mini: " . ($quote_data['ai_o4_mini_analysis'] ? 'Present' : 'NULL') . "\n";
            echo "     - o3-pro: " . ($quote_data['ai_o3_analysis'] ? 'Present' : 'NULL') . "\n";
            echo "     - Gemini: " . ($quote_data['ai_gemini_analysis'] ? 'Present' : 'NULL') . "\n\n";
        } else {
            echo "❌ API query failed - no data returned\n\n";
        }
        
    } catch (Exception $e) {
        echo "❌ API query error: " . $e->getMessage() . "\n\n";
    }
    
    // 4. Check recent quotes
    echo "4️⃣ RECENT QUOTES STATUS\n";
    $stmt = $pdo->query("
        SELECT id, quote_status, quote_created_at 
        FROM quotes 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $recent = $stmt->fetchAll();
    
    foreach ($recent as $q) {
        echo "   #{$q['id']}: {$q['quote_status']} ({$q['quote_created_at']})\n";
    }
    echo "\n";
    
    // 5. Check AI analysis columns
    echo "5️⃣ CHECKING AI ANALYSIS COLUMNS\n";
    $columns_to_check = ['ai_o4_mini_analysis', 'ai_o3_analysis', 'ai_gemini_analysis'];
    
    foreach ($columns_to_check as $col) {
        $stmt = $pdo->query("SHOW COLUMNS FROM quotes WHERE Field = '$col'");
        $column_exists = $stmt->fetch();
        
        if ($column_exists) {
            echo "✅ $col column exists\n";
        } else {
            echo "❌ $col column MISSING\n";
            echo "   📋 Run: fix-ai-dashboard-schema.sql\n";
        }
    }
    
    echo "\n🎯 SUMMARY\n";
    echo "Quote #$quote_id: " . ($quote ? "Found" : "Not Found") . "\n";
    echo "Schema: " . ($has_multi_ai_processing ?? false && $has_multi_ai_complete ?? false ? "Updated" : "Needs Update") . "\n";
    echo "API: " . (isset($quote_data) && $quote_data ? "Working" : "Issues") . "\n";
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
}

echo "\n💡 NEXT STEPS:\n";
echo "1. If schema needs update: run apply-quote-status-update.php\n";
echo "2. If AI columns missing: run fix-ai-dashboard-schema.sql\n";
echo "3. Refresh dashboard after fixes\n";
?>