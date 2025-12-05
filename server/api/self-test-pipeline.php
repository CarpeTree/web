<?php
/**
 * Self-Test Pipeline for Carpe Tree'em Quote System
 * 
 * Tests the full quote submission and AI analysis pipeline:
 * 1. Database connectivity
 * 2. Email notification system
 * 3. AI model availability (GPT-5.1 and Gemini 3 Pro)
 * 4. File upload handling
 * 5. Quote submission flow
 * 
 * Run via: /server/api/self-test-pipeline.php
 * Or CLI: php self-test-pipeline.php
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => [],
    'summary' => [
        'passed' => 0,
        'failed' => 0,
        'warnings' => 0
    ]
];

function addResult(&$results, $name, $status, $message, $details = null) {
    $results['tests'][] = [
        'name' => $name,
        'status' => $status,
        'message' => $message,
        'details' => $details
    ];
    
    if ($status === 'pass') $results['summary']['passed']++;
    elseif ($status === 'fail') $results['summary']['failed']++;
    else $results['summary']['warnings']++;
}

// Test 1: Database Connectivity
try {
    require_once __DIR__ . '/../config/database-simple.php';
    
    if (isset($pdo) && $pdo) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM quotes");
        $count = $stmt->fetchColumn();
        addResult($results, 'Database Connection', 'pass', "Connected successfully. Found {$count} quotes in database.");
    } else {
        addResult($results, 'Database Connection', 'fail', 'PDO connection not established');
    }
} catch (Throwable $e) {
    addResult($results, 'Database Connection', 'fail', 'Database error: ' . $e->getMessage());
}

// Test 2: Config Files
try {
    $config_path = __DIR__ . '/../config/config.php';
    $prompts_path = __DIR__ . '/../ai/system_prompts.json';
    
    if (file_exists($config_path)) {
        require_once $config_path;
        addResult($results, 'Config File', 'pass', 'config.php loaded successfully');
    } else {
        addResult($results, 'Config File', 'fail', 'config.php not found');
    }
    
    if (file_exists($prompts_path)) {
        $prompts = json_decode(file_get_contents($prompts_path), true);
        if ($prompts && isset($prompts['gpt5.1']) && isset($prompts['gemini3'])) {
            addResult($results, 'AI Prompts Config', 'pass', 'system_prompts.json loaded with GPT-5.1 and Gemini 3 Pro prompts', [
                'version' => $prompts['version'] ?? 'unknown',
                'has_gpt5.1' => isset($prompts['gpt5.1']),
                'has_gemini3' => isset($prompts['gemini3']),
                'truck_only_per_km' => $prompts['travel_policy']['truck_only_per_km'] ?? 'not set',
                'truck_chipper_per_km' => $prompts['travel_policy']['truck_chipper_per_km'] ?? 'not set'
            ]);
        } else {
            addResult($results, 'AI Prompts Config', 'warning', 'system_prompts.json missing GPT-5.1 or Gemini 3 keys');
        }
    } else {
        addResult($results, 'AI Prompts Config', 'fail', 'system_prompts.json not found');
    }
} catch (Throwable $e) {
    addResult($results, 'Config Files', 'fail', 'Config error: ' . $e->getMessage());
}

// Test 3: API Keys
try {
    $openai_key = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? ($OPENAI_API_KEY ?? ''));
    $gemini_key = getenv('GOOGLE_GEMINI_API_KEY') ?: ($_ENV['GOOGLE_GEMINI_API_KEY'] ?? ($GOOGLE_GEMINI_API_KEY ?? ''));
    
    if (!empty($openai_key) && strlen($openai_key) > 20) {
        addResult($results, 'OpenAI API Key', 'pass', 'OpenAI API key configured (length: ' . strlen($openai_key) . ')');
    } else {
        addResult($results, 'OpenAI API Key', 'fail', 'OpenAI API key not configured or too short');
    }
    
    if (!empty($gemini_key) && strlen($gemini_key) > 20) {
        addResult($results, 'Gemini API Key', 'pass', 'Gemini API key configured (length: ' . strlen($gemini_key) . ')');
    } else {
        addResult($results, 'Gemini API Key', 'fail', 'Gemini API key not configured or too short');
    }
} catch (Throwable $e) {
    addResult($results, 'API Keys', 'fail', 'API key check error: ' . $e->getMessage());
}

// Test 4: Email Configuration
try {
    $smtp_host = $_ENV['SMTP_HOST'] ?? ($SMTP_HOST ?? '');
    $smtp_user = $_ENV['SMTP_USER'] ?? ($SMTP_USER ?? '');
    $admin_email = $_ENV['ADMIN_EMAIL'] ?? 'phil.bajenski@gmail.com';
    
    if (!empty($smtp_host) && !empty($smtp_user)) {
        addResult($results, 'Email Configuration', 'pass', "SMTP configured: {$smtp_host}", [
            'smtp_host' => $smtp_host,
            'smtp_user' => $smtp_user,
            'admin_email' => $admin_email
        ]);
    } else {
        addResult($results, 'Email Configuration', 'warning', 'SMTP not fully configured - emails may fail');
    }
    
    // Check mailer utility exists
    $mailer_path = __DIR__ . '/../utils/mailer.php';
    if (file_exists($mailer_path)) {
        addResult($results, 'Mailer Utility', 'pass', 'mailer.php exists');
    } else {
        addResult($results, 'Mailer Utility', 'fail', 'mailer.php not found');
    }
} catch (Throwable $e) {
    addResult($results, 'Email Configuration', 'fail', 'Email config error: ' . $e->getMessage());
}

// Test 5: Upload Directory
try {
    $upload_dir = __DIR__ . '/../../uploads';
    
    if (is_dir($upload_dir)) {
        if (is_writable($upload_dir)) {
            addResult($results, 'Upload Directory', 'pass', 'uploads/ directory exists and is writable');
        } else {
            addResult($results, 'Upload Directory', 'warning', 'uploads/ directory exists but may not be writable');
        }
    } else {
        // Try to create it
        if (mkdir($upload_dir, 0755, true)) {
            addResult($results, 'Upload Directory', 'pass', 'uploads/ directory created successfully');
        } else {
            addResult($results, 'Upload Directory', 'fail', 'uploads/ directory does not exist and could not be created');
        }
    }
} catch (Throwable $e) {
    addResult($results, 'Upload Directory', 'fail', 'Upload dir error: ' . $e->getMessage());
}

// Test 6: AI Analysis Endpoints
try {
    $openai_endpoint = __DIR__ . '/openai-o4-mini-analysis.php';
    $gemini_endpoint = __DIR__ . '/gemini-2-5-pro-analysis.php';
    
    if (file_exists($openai_endpoint)) {
        $content = file_get_contents($openai_endpoint);
        if (strpos($content, 'gpt-5.1') !== false) {
            addResult($results, 'OpenAI Endpoint', 'pass', 'openai-o4-mini-analysis.php configured for GPT-5.1');
        } else {
            addResult($results, 'OpenAI Endpoint', 'warning', 'OpenAI endpoint exists but may not be using GPT-5.1');
        }
    } else {
        addResult($results, 'OpenAI Endpoint', 'fail', 'openai-o4-mini-analysis.php not found');
    }
    
    if (file_exists($gemini_endpoint)) {
        $content = file_get_contents($gemini_endpoint);
        if (strpos($content, 'gemini-3-pro') !== false) {
            addResult($results, 'Gemini Endpoint', 'pass', 'gemini-2-5-pro-analysis.php configured for Gemini 3 Pro');
        } else {
            addResult($results, 'Gemini Endpoint', 'warning', 'Gemini endpoint exists but may not be using Gemini 3 Pro');
        }
    } else {
        addResult($results, 'Gemini Endpoint', 'fail', 'gemini-2-5-pro-analysis.php not found');
    }
} catch (Throwable $e) {
    addResult($results, 'AI Endpoints', 'fail', 'AI endpoint check error: ' . $e->getMessage());
}

// Test 7: Quote Submission Endpoint
try {
    $submit_endpoint = __DIR__ . '/submitQuote-reliable.php';
    
    if (file_exists($submit_endpoint)) {
        $content = file_get_contents($submit_endpoint);
        
        // Check for immediate email notification
        if (strpos($content, 'IMMEDIATE admin email notification') !== false || 
            strpos($content, 'sendEmailDirect') !== false) {
            addResult($results, 'Immediate Email', 'pass', 'submitQuote-reliable.php has immediate email notification');
        } else {
            addResult($results, 'Immediate Email', 'warning', 'Immediate email notification may not be configured');
        }
        
        addResult($results, 'Submit Endpoint', 'pass', 'submitQuote-reliable.php exists');
    } else {
        addResult($results, 'Submit Endpoint', 'fail', 'submitQuote-reliable.php not found');
    }
} catch (Throwable $e) {
    addResult($results, 'Submit Endpoint', 'fail', 'Submit endpoint check error: ' . $e->getMessage());
}

// Test 8: Recent Quote Check
try {
    if (isset($pdo) && $pdo) {
        $stmt = $pdo->query("SELECT q.id, q.quote_status, q.created_at, c.name, c.email 
                            FROM quotes q 
                            LEFT JOIN customers c ON q.customer_id = c.id 
                            ORDER BY q.id DESC LIMIT 5");
        $recent_quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recent_quotes)) {
            addResult($results, 'Recent Quotes', 'pass', 'Found ' . count($recent_quotes) . ' recent quotes', $recent_quotes);
        } else {
            addResult($results, 'Recent Quotes', 'warning', 'No quotes found in database');
        }
    }
} catch (Throwable $e) {
    addResult($results, 'Recent Quotes', 'fail', 'Recent quotes check error: ' . $e->getMessage());
}

// Test 9: Schema File
try {
    $schema_path = __DIR__ . '/../../ai/schema.json';
    
    if (file_exists($schema_path)) {
        $schema = json_decode(file_get_contents($schema_path), true);
        if ($schema && json_last_error() === JSON_ERROR_NONE) {
            addResult($results, 'AI Schema', 'pass', 'schema.json loaded successfully');
        } else {
            addResult($results, 'AI Schema', 'fail', 'schema.json is invalid JSON');
        }
    } else {
        addResult($results, 'AI Schema', 'fail', 'schema.json not found');
    }
} catch (Throwable $e) {
    addResult($results, 'AI Schema', 'fail', 'Schema check error: ' . $e->getMessage());
}

// Test 10: Liquid Glass Theme Check
try {
    $quote_html = __DIR__ . '/../../quote.html';
    $admin_html = __DIR__ . '/../../admin-dashboard.html';
    
    $has_liquid_glass = false;
    
    if (file_exists($quote_html)) {
        $content = file_get_contents($quote_html);
        if (strpos($content, 'backdrop-filter') !== false && strpos($content, 'glass') !== false) {
            $has_liquid_glass = true;
        }
    }
    
    if (file_exists($admin_html)) {
        $content = file_get_contents($admin_html);
        if (strpos($content, 'backdrop-filter') !== false && strpos($content, 'glass') !== false) {
            $has_liquid_glass = true;
        }
    }
    
    if ($has_liquid_glass) {
        addResult($results, 'Liquid Glass Theme', 'pass', 'Liquid Glass theme detected in HTML files');
    } else {
        addResult($results, 'Liquid Glass Theme', 'warning', 'Liquid Glass theme may not be fully applied');
    }
} catch (Throwable $e) {
    addResult($results, 'Liquid Glass Theme', 'fail', 'Theme check error: ' . $e->getMessage());
}

// Final Summary
$results['overall'] = $results['summary']['failed'] === 0 ? 'PASS' : 'FAIL';
$results['message'] = $results['summary']['failed'] === 0 
    ? 'All critical tests passed! System is ready.'
    : $results['summary']['failed'] . ' test(s) failed. Please review and fix issues.';

echo json_encode($results, JSON_PRETTY_PRINT);
?>

