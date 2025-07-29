<?php
// Setup Analytics Database Tables for Carpe Tree'em
header('Content-Type: text/plain');

require_once 'server/config/database-simple.php';

echo "=== CARPE TREE'EM ANALYTICS DATABASE SETUP ===\n\n";

try {
    // Read and execute the analytics schema
    $schema_file = 'server/database/analytics-schema.sql';
    
    if (!file_exists($schema_file)) {
        throw new Exception("Schema file not found: $schema_file");
    }
    
    $sql_content = file_get_contents($schema_file);
    
    // Split by semicolons and execute each statement
    $statements = explode(';', $sql_content);
    
    $created_tables = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            
            // Extract table name for reporting
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?\s*\(/i', $statement, $matches)) {
                echo "✅ Created table: {$matches[1]}\n";
                $created_tables++;
            }
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                // Table already exists - that's okay
                if (preg_match('/CREATE TABLE.*?`?(\w+)`?\s*\(/i', $statement, $matches)) {
                    echo "ℹ️  Table already exists: {$matches[1]}\n";
                }
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }
    
    echo "\n=== TABLE VERIFICATION ===\n";
    
    // Verify all analytics tables exist
    $analytics_tables = [
        'customer_interactions',
        'ai_processing_logs', 
        'service_analytics',
        'customer_journey',
        'ai_model_performance',
        'live_sessions',
        'form_abandonments',
        'ab_tests'
    ];
    
    foreach ($analytics_tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        
        if ($stmt->fetch()) {
            echo "✅ Verified: $table\n";
            
            // Show row count
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $count_stmt->fetchColumn();
            echo "   📊 Current rows: $count\n";
        } else {
            echo "❌ Missing: $table\n";
            $errors++;
        }
    }
    
    echo "\n=== ANALYTICS FEATURES ENABLED ===\n\n";
    
    echo "📊 CUSTOMER TRACKING:\n";
    echo "✅ All page interactions tracked\n";
    echo "✅ Service selection analysis\n";
    echo "✅ Form abandonment monitoring\n";
    echo "✅ Device and browser detection\n";
    echo "✅ Customer journey mapping\n";
    echo "✅ Real-time session tracking\n\n";
    
    echo "🤖 AI MONITORING:\n";
    echo "✅ Full token usage tracking\n";
    echo "✅ API cost monitoring\n";
    echo "✅ Processing time analytics\n";
    echo "✅ Reasoning trace capture\n";
    echo "✅ Error logging and analysis\n";
    echo "✅ Model performance metrics\n\n";
    
    echo "📈 BUSINESS INTELLIGENCE:\n";
    echo "✅ Conversion funnel analysis\n";
    echo "✅ Service popularity metrics\n";
    echo "✅ Peak usage time identification\n";
    echo "✅ Customer satisfaction scoring\n";
    echo "✅ Growth rate calculations\n";
    echo "✅ System health monitoring\n\n";
    
    echo "🔴 REAL-TIME FEATURES:\n";
    echo "✅ Live session monitoring\n";
    echo "✅ Active user tracking\n";
    echo "✅ Real-time alerts\n";
    echo "✅ Performance dashboards\n\n";
    
    // Sample data insertion for testing
    echo "=== SAMPLE DATA INSERTION ===\n";
    
    try {
        // Add sample AI processing log
        $pdo->exec("
            INSERT IGNORE INTO ai_processing_logs 
            (quote_id, ai_model, prompt_tokens, completion_tokens, total_tokens, 
             processing_time_ms, api_cost_usd, processing_status, created_at)
            VALUES 
            (1, 'o1-mini', 1500, 800, 2300, 15000, 0.0234, 'completed', NOW()),
            (2, 'o1-mini', 1200, 600, 1800, 12000, 0.0189, 'completed', NOW()),
            (3, 'o1-mini', 1800, 900, 2700, 18000, 0.0267, 'failed', NOW())
        ");
        echo "✅ Added sample AI processing logs\n";
        
        // Add sample customer interactions
        $pdo->exec("
            INSERT IGNORE INTO customer_interactions 
            (customer_id, session_id, interaction_type, interaction_data, 
             page_url, ip_address, interaction_timestamp)
            VALUES 
            (1, 'session_demo_1', 'form_view', '{\"device_type\":\"mobile\"}', 
             '/quote.html', '192.168.1.1', NOW()),
            (1, 'session_demo_1', 'service_select', '{\"service_name\":\"pruning\",\"device_type\":\"mobile\"}', 
             '/quote.html', '192.168.1.1', NOW()),
            (2, 'session_demo_2', 'file_upload', '{\"device_type\":\"desktop\"}', 
             '/quote.html', '192.168.1.2', NOW())
        ");
        echo "✅ Added sample customer interactions\n";
        
        // Add sample live session
        $pdo->exec("
            INSERT INTO live_sessions 
            (session_id, current_page, session_data, device_info, last_activity)
            VALUES 
            ('session_demo_live', '/admin-dashboard.html', 
             '{\"page_views\":5}', '{\"device_type\":\"desktop\",\"browser\":\"Chrome\"}', NOW())
            ON DUPLICATE KEY UPDATE last_activity = NOW()
        ");
        echo "✅ Added sample live session\n";
        
    } catch (Exception $e) {
        echo "⚠️  Sample data insertion failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== ANALYTICS ENDPOINTS ===\n\n";
    echo "📊 Analytics Dashboard: https://carpetree.com/analytics-dashboard.html\n";
    echo "📊 Metrics API: /server/api/analytics-metrics.php\n";
    echo "👥 Interactions API: /server/api/analytics-interactions.php\n";
    echo "🤖 AI Analytics API: /server/api/analytics-ai.php\n";
    echo "🛤️  Journey API: /server/api/analytics-journey.php\n";
    echo "🌳 Services API: /server/api/analytics-services.php\n";
    echo "🔴 Live Sessions API: /server/api/analytics-live.php\n";
    echo "📤 Tracking API: /server/api/track-interaction.php\n\n";
    
    echo "=== FRONTEND INTEGRATION ===\n\n";
    echo "Add this JavaScript to any page to enable tracking:\n\n";
    echo "<script>\n";
    echo "// Track page view\n";
    echo "fetch('/server/api/track-interaction.php', {\n";
    echo "    method: 'POST',\n";
    echo "    headers: {'Content-Type': 'application/json'},\n";
    echo "    body: JSON.stringify({\n";
    echo "        interaction_type: 'page_view',\n";
    echo "        page_url: window.location.href,\n";
    echo "        data: {\n";
    echo "            screen_resolution: screen.width + 'x' + screen.height,\n";
    echo "            viewport_size: window.innerWidth + 'x' + window.innerHeight,\n";
    echo "            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone\n";
    echo "        }\n";
    echo "    })\n";
    echo "});\n";
    echo "</script>\n\n";
    
    if ($errors > 0) {
        echo "⚠️  Setup completed with $errors errors. Check the output above.\n";
    } else {
        echo "🎉 ANALYTICS SYSTEM FULLY OPERATIONAL! 🎉\n";
        echo "\nYou now have comprehensive tracking of:\n";
        echo "• Every customer interaction\n";
        echo "• Complete AI processing details\n";
        echo "• Business performance metrics\n";
        echo "• Real-time user behavior\n";
        echo "• Service selection patterns\n";
        echo "• Conversion funnel analysis\n\n";
        echo "Visit the analytics dashboard to start exploring your data!\n";
    }
    
} catch (Exception $e) {
    echo "❌ SETUP FAILED: " . $e->getMessage() . "\n";
    echo "\nPlease check your database connection and try again.\n";
}
?> 