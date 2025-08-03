<?php
// Fix OpenAI model names to use official model IDs
echo "=== FIXING OPENAI MODEL NAMES ===\n";

// Fix o3 analysis script
$o3_file = 'server/api/openai-o3-analysis.php';
if (file_exists($o3_file)) {
    $content = file_get_contents($o3_file);
    
    // Replace 'o3' with official OpenAI model
    $old_model = "'model' => 'o3'";
    $new_model = "'model' => 'gpt-4o'"; // Using gpt-4o as closest to o3 capabilities
    
    if (strpos($content, $old_model) !== false) {
        $content = str_replace($old_model, $new_model, $content);
        file_put_contents($o3_file, $content);
        echo "✅ Fixed o3 model name to gpt-4o\n";
    } else {
        echo "⚠️ o3 model reference not found in expected format\n";
    }
}

// Fix o4-mini analysis script  
$o4_file = 'server/api/openai-o4-mini-analysis.php';
if (file_exists($o4_file)) {
    $content = file_get_contents($o4_file);
    
    // Replace 'o4-mini' with official OpenAI model
    $old_model = "'model' => 'o4-mini'";
    $new_model = "'model' => 'gpt-4o-mini'"; // Official OpenAI model name
    
    if (strpos($content, $old_model) !== false) {
        $content = str_replace($old_model, $new_model, $content);
        file_put_contents($o4_file, $content);
        echo "✅ Fixed o4-mini model name to gpt-4o-mini\n";
    } else {
        echo "⚠️ o4-mini model reference not found in expected format\n";
    }
}

echo "\n🚀 Model names fixed - ready for retry!\n";
?>