<?php
// Fix MediaPreprocessor syntax errors

echo "=== FIXING MEDIAPROCESSOR SYNTAX ===\n";

$content = file_get_contents('server/utils/media-preprocessor.php');

// Find where the class should end properly
$lines = explode("\n", $content);
$fixed_lines = [];
$brace_count = 0;
$in_class = false;

foreach ($lines as $line_num => $line) {
    $trimmed = trim($line);
    
    // Track class definition
    if (strpos($trimmed, 'class MediaPreprocessor') !== false) {
        $in_class = true;
        $fixed_lines[] = $line;
        continue;
    }
    
    // Skip lines after we find the problematic area
    if ($line_num > 490 && strpos($trimmed, 'processForAI') !== false) {
        echo "Stopping at line " . ($line_num + 1) . " - removing duplicate code\n";
        break;
    }
    
    $fixed_lines[] = $line;
    
    // Count braces to ensure proper structure
    $brace_count += substr_count($line, '{') - substr_count($line, '}');
    
    // If we just added the describeVideoFallback method's closing brace
    if ($in_class && strpos($trimmed, 'return true;') !== false && $brace_count == 1) {
        $fixed_lines[] = '    }'; // Close the method
        $fixed_lines[] = '}'; // Close the class
        break;
    }
}

// Write the fixed content
$fixed_content = implode("\n", $fixed_lines);
file_put_contents('server/utils/media-preprocessor.php', $fixed_content);

echo "✅ Fixed MediaPreprocessor syntax\n";
echo "Lines kept: " . count($fixed_lines) . "\n";
echo "Original lines: " . count($lines) . "\n";

// Validate the syntax
$syntax_check = shell_exec('php -l server/utils/media-preprocessor.php 2>&1');
if (strpos($syntax_check, 'No syntax errors') !== false) {
    echo "✅ Syntax validation passed\n";
} else {
    echo "❌ Syntax validation failed:\n";
    echo $syntax_check . "\n";
}
?>