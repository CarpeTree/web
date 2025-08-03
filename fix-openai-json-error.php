<?php
// Fix the JSON decode error in OpenAI scripts
echo "=== FIXING OPENAI JSON ERROR ===\n";

$files_to_fix = [
    'server/api/openai-o3-analysis.php',
    'server/api/openai-o4-mini-analysis.php'
];

foreach ($files_to_fix as $file) {
    if (!file_exists($file)) {
        echo "❌ File not found: {$file}\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Fix json_decode with null check
    $old_pattern = '/\$ai_response = json_decode\(\$response, true\);/';
    $new_code = '$ai_response = null;
    if ($response && trim($response) !== "") {
        $ai_response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from OpenAI: " . json_last_error_msg() . ". Response: " . substr($response, 0, 200));
        }
    } else {
        throw new Exception("Empty or null response from OpenAI API. Check API key and request.");
    }';
    
    if (preg_match($old_pattern, $content)) {
        $content = preg_replace($old_pattern, $new_code, $content);
        echo "✅ Fixed JSON handling in " . basename($file) . "\n";
    } else {
        echo "⚠️ Pattern not found in " . basename($file) . "\n";
    }
    
    // Also add API key validation
    $api_key_check = '// Validate API key
    if (empty($OPENAI_API_KEY)) {
        throw new Exception("OpenAI API key not configured or empty");
    }
    
    // Log API request for debugging
    error_log("OpenAI API request for quote #{$quote_id}: " . json_encode([
        "model" => $openai_request["model"],
        "message_count" => count($openai_request["messages"]),
        "has_tools" => !empty($openai_request["tools"])
    ]));';
    
    // Insert before the curl setup
    if (strpos($content, '// 6. EXECUTE API CALL') !== false) {
        $content = str_replace('// 6. EXECUTE API CALL', $api_key_check . "\n\n    // 6. EXECUTE API CALL", $content);
        echo "✅ Added API key validation to " . basename($file) . "\n";
    }
    
    file_put_contents($file, $content);
}

echo "\n=== TESTING API KEY ===\n";
require_once 'server/config/config.php';
echo "OpenAI API Key: " . (empty($OPENAI_API_KEY) ? "❌ EMPTY" : "✅ Present (" . substr($OPENAI_API_KEY, 0, 10) . "...)") . "\n";
echo "Gemini API Key: " . (empty($GOOGLE_GEMINI_API_KEY) ? "❌ EMPTY" : "✅ Present (" . substr($GOOGLE_GEMINI_API_KEY, 0, 10) . "...)") . "\n";

echo "\n🚀 Fixed JSON handling and added debugging!\n";
?>