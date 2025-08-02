<?php
// Final fix for environment variables
echo "=== FINAL ENVIRONMENT VARIABLE FIX ===\n\n";

// 1. Copy .env file to the correct location (web root)
$source_env = '/home/u230128646/domains/carpetree.com/public_html/server/config/.env';
$target_env = '/home/u230128646/domains/carpetree.com/public_html/.env';

// Check if .env exists in config directory
if (file_exists($source_env)) {
    copy($source_env, $target_env);
    echo "1. ✅ Copied .env from config directory to web root\n";
} else {
    echo "1. ❌ .env file not found in config directory\n";
    echo "   You need to manually copy your .env file to the web root\n";
}

// 2. Update debug script to use config variables instead of getenv()
$debug_file = '/home/u230128646/domains/carpetree.com/public_html/debug-analysis-direct.php';
if (file_exists($debug_file)) {
    $content = file_get_contents($debug_file);
    
    // Replace getenv() calls with config variables
    $old_env_check = 'echo "   - OPENAI_API_KEY: " . (getenv(\'OPENAI_API_KEY\') ? \'SET\' : \'NOT SET\') . "\\n";
echo "   - GOOGLE_GEMINI_API_KEY: " . (getenv(\'GOOGLE_GEMINI_API_KEY\') ? \'SET\' : \'NOT SET\') . "\\n\\n";';

    $new_env_check = 'require_once __DIR__ . \'/server/config/config.php\';
echo "   - OPENAI_API_KEY: " . (!empty($OPENAI_API_KEY) ? \'SET\' : \'NOT SET\') . "\\n";
echo "   - GOOGLE_GEMINI_API_KEY: " . (!empty($GOOGLE_GEMINI_API_KEY) ? \'SET\' : \'NOT SET\') . "\\n\\n";';

    if (strpos($content, 'getenv(\'OPENAI_API_KEY\')') !== false) {
        $content = str_replace($old_env_check, $new_env_check, $content);
        file_put_contents($debug_file, $content);
        echo "2. ✅ Updated debug script to use config variables\n";
    } else {
        echo "2. ✅ Debug script already updated\n";
    }
}

// 3. Test final environment loading
echo "3. Testing final environment loading...\n";
require_once '/home/u230128646/domains/carpetree.com/public_html/server/config/config.php';

if (!empty($OPENAI_API_KEY)) {
    echo "   ✅ OPENAI_API_KEY: SET (" . strlen($OPENAI_API_KEY) . " chars)\n";
} else {
    echo "   ❌ OPENAI_API_KEY: NOT SET\n";
}

if (!empty($GOOGLE_GEMINI_API_KEY)) {
    echo "   ✅ GOOGLE_GEMINI_API_KEY: SET (" . strlen($GOOGLE_GEMINI_API_KEY) . " chars)\n";
} else {
    echo "   ❌ GOOGLE_GEMINI_API_KEY: NOT SET\n";
}

echo "\n=== FINAL FIX COMPLETE ===\n";
echo "Now test the debug script and trigger analyses!\n";
?>