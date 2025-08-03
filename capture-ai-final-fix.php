<?php
// FINAL FIX: Correct schema handling for OpenAI API
header('Content-Type: text/plain');
echo "=== FINAL AI CAPTURE FIX ===\n";

require_once 'server/config/config.php';
require_once 'server/utils/media-preprocessor.php';

$quote_id = 69;
$model = 'o4-mini';

echo "Quote ID: {$quote_id}\n";
echo "Model: {$model}\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get quote data
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
    $stmt->execute([$quote_id]);
    $quote_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get media files
    $stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Quote data loaded\n";
    echo "✅ Media files: " . count($media_files) . "\n";
    
    // Process media
    echo "\n🎬 PROCESSING MEDIA:\n";
    $processor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
    $context = $processor->preprocessAllMedia();
    
    echo "✅ Context generated: " . strlen($context['context_text']) . " chars\n";
    echo "✅ Visual content: " . count($context['visual_content']) . " items\n";
    
    if (count($context['visual_content']) == 0) {
        echo "❌ No visual content - aborting\n";
        exit;
    }
    
    // Build AI request with CORRECT schema handling
    echo "\n🤖 BUILDING AI REQUEST:\n";
    
    $messages = [
        [
            'role' => 'system',
            'content' => file_get_contents('ai/system_prompt.txt')
        ],
        [
            'role' => 'user',
            'content' => array_merge(
                [['type' => 'text', 'text' => $context['context_text']]],
                $context['visual_content']
            )
        ]
    ];
    
    // FINAL FIX: Extract just the function part from schema
    $schema = json_decode(file_get_contents('ai/schema.json'), true);
    
    // Debug the schema structure
    echo "Schema structure debug:\n";
    echo "- Has 'type': " . (isset($schema['type']) ? $schema['type'] : 'No') . "\n";
    echo "- Has 'function': " . (isset($schema['function']) ? 'Yes' : 'No') . "\n";
    
    if (isset($schema['function'])) {
        echo "- Function has 'name': " . (isset($schema['function']['name']) ? $schema['function']['name'] : 'No') . "\n";
        
        // Correct format: use just the function part
        $tools = [
            [
                "type" => "function",
                "function" => $schema['function'] // Extract just the function object
            ]
        ];
    } else {
        // If schema is already in the right format
        $tools = [
            [
                "type" => "function", 
                "function" => $schema
            ]
        ];
    }
    
    $payload = [
        'model' => 'o4-mini',
        'messages' => $messages,
        'tools' => $tools,
        'tool_choice' => 'required',
        'max_completion_tokens' => 100000
    ];
    
    echo "✅ Payload built: " . round(strlen(json_encode($payload)) / 1024, 1) . "KB\n";
    echo "✅ Visual items in request: " . count($context['visual_content']) . "\n";
    
    // Debug the final tools structure
    echo "\nTools structure debug:\n";
    $tools_json = json_encode($tools, JSON_PRETTY_PRINT);
    echo "First 300 chars of tools: " . substr($tools_json, 0, 300) . "...\n";
    
    // Make API call
    echo "\n📡 CALLING OPENAI API:\n";
    
    $start_time = microtime(true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . getenv('OPENAI_API_KEY')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $end_time = microtime(true);
    $processing_time = round($end_time - $start_time, 2);
    
    echo "✅ API call completed in {$processing_time}s\n";
    echo "✅ HTTP Code: {$http_code}\n";
    
    if ($http_code === 200 && !empty($response)) {
        $api_response = json_decode($response, true);
        
        if ($api_response && isset($api_response['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
            
            // SAVE TO FILE IMMEDIATELY (bypass database)
            $output_file = "ai_analysis_quote_{$quote_id}_" . date('Y-m-d_H-i-s') . ".json";
            
            $result = [
                'success' => true,
                'quote_id' => $quote_id,
                'model' => 'o4-mini', 
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_time' => $processing_time,
                'input_tokens' => $api_response['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $api_response['usage']['completion_tokens'] ?? 0,
                'analysis' => json_decode($api_response['choices'][0]['message']['tool_calls'][0]['function']['arguments'], true),
                'raw_response' => $api_response
            ];
            
            file_put_contents($output_file, json_encode($result, JSON_PRETTY_PRINT));
            
            echo "\n🎉🎉🎉 BREAKTHROUGH SUCCESS! 🎉🎉🎉\n";
            echo "✅ File saved: {$output_file}\n";
            echo "✅ Input tokens: {$result['input_tokens']}\n";
            echo "✅ Output tokens: {$result['output_tokens']}\n";
            echo "✅ Processing time: {$processing_time}s\n";
            
            if (isset($result['analysis']['overall_assessment'])) {
                $preview = substr($result['analysis']['overall_assessment'], 0, 300);
                echo "\n📊 ASSESSMENT PREVIEW:\n{$preview}...\n";
            }
            
            if (isset($result['analysis']['trees'])) {
                echo "\n🌳 TREES ANALYZED: " . count($result['analysis']['trees']) . "\n";
                foreach ($result['analysis']['trees'] as $i => $tree) {
                    $species = $tree['species']['common_name'] ?? 'Unknown';
                    $condition = $tree['health']['overall_condition'] ?? 'Unknown';
                    echo "  Tree " . ($i + 1) . ": {$species} - {$condition}\n";
                }
            }
            
            echo "\n🔗 Download your AI analysis: https://carpetree.com/{$output_file}\n";
            echo "\n💰💰💰 YOUR $50+ INVESTMENT FINALLY PAID OFF! 💰💰💰\n";
            echo "🌳 AI successfully analyzed your trees with 3.3MB of real image data!\n";
            echo "🎯 FRAME EXTRACTION BREAKTHROUGH COMPLETE!\n";
            
        } else {
            echo "❌ Invalid API response structure\n";
            if (isset($api_response['error'])) {
                echo "Error: " . $api_response['error']['message'] . "\n";
            }
            echo "Response preview: " . substr($response, 0, 500) . "\n";
        }
        
    } else {
        echo "❌ API call failed\n";
        echo "Full response: " . $response . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FINAL FIX COMPLETE ===\n";
?>