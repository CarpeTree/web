<?php
// debug-env-parser.php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== .env File Parser Debugger ===\n\n";

$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    // Fallback for when the .env is in the parent directory of /server etc.
    $env_path = realpath(__DIR__ . '/../') . '/.env'; 
}
if (!file_exists($env_path)) {
     // The path from the most recent user feedback
    $env_path = '/home/u230128646/domains/carpetree.com/public_html/.env';
}
if (!file_exists($env_path)) {
    die("ERROR: .env file not found in the web root. Please ensure it has been uploaded to /home/u230128646/domains/carpetree.com/public_html/.env");
}

echo "Found .env file at: " . $env_path . "\n\n";
echo "--- Parsing .env line by line ---\n";

$lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$found_openai = false;
$found_gemini = false;

foreach ($lines as $line) {
    echo "Line: " . $line . "\n";
    if (strpos(trim($line), '#') === 0) {
        echo "  -> Skipped (comment)\n\n";
        continue;
    }

    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        echo "  -> Parsed Key: '" . $key . "'\n";
        echo "  -> Parsed Value: '" . substr($value, 0, 4) . "...(hidden)'\n";

        if ($key === 'OPENAI_API_KEY') {
            $found_openai = true;
            echo "  -> MATCHED OPENAI_API_KEY\n";
        }
        if ($key === 'GOOGLE_GEMINI_API_KEY') {
            $found_gemini = true;
            echo "  -> MATCHED GOOGLE_GEMINI_API_KEY\n";
        }
        echo "\n";
    } else {
        echo "  -> Skipped (no '=' found)\n\n";
    }
}

echo "--- Summary ---\n";
echo "Found OPENAI_API_KEY line: " . ($found_openai ? 'YES' : 'NO') . "\n";
echo "Found GOOGLE_GEMINI_API_KEY line: " . ($found_gemini ? 'YES' : 'NO') . "\n\n";

echo "--- Loading config.php to see final result ---\n";
require_once __DIR__ . '/server/config/config.php';

echo "Final variable check:\n";
echo "  \$OPENAI_API_KEY is set: " . (!empty($OPENAI_API_KEY) ? 'YES' : 'NO') . "\n";
echo "  \$GOOGLE_GEMINI_API_KEY is set: " . (!empty($GOOGLE_GEMINI_API_KEY) ? 'YES' : 'NO') . "\n";

echo "\n=== Debug Complete ===\n";
?>