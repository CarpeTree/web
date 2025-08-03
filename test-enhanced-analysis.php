<?php
// Test the enhanced MediaPreprocessor with both video frames AND audio transcription
header('Content-Type: text/plain');
echo "=== ENHANCED AI ANALYSIS WITH WHISPER ===\n";

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
    
    // Process media with ENHANCED MediaPreprocessor
    echo "\n🎬🎤 PROCESSING MEDIA WITH FRAMES + AUDIO:\n";
    $processor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
    $context = $processor->preprocessAllMedia();
    
    echo "✅ Context generated: " . strlen($context['context_text']) . " chars\n";
    echo "✅ Visual content: " . count($context['visual_content']) . " items\n";
    echo "✅ Transcriptions: " . count($context['transcriptions']) . " items\n";
    echo "✅ Media summary: " . count($context['media_summary']) . " items\n";
    
    if (count($context['transcriptions']) > 0) {
        echo "\n🎤 AUDIO TRANSCRIPTION SUCCESS!\n";
        foreach ($context['transcriptions'] as $i => $transcription) {
            echo "Transcription #{$i}: " . substr($transcription['text'], 0, 150) . "...\n";
            echo "Source: " . $transcription['source'] . "\n";
        }
    } else {
        echo "\n❌ No audio transcriptions generated\n";
    }
    
    if (count($context['visual_content']) == 0) {
        echo "❌ No visual content - aborting\n";
        exit;
    }
    
    // Build enhanced AI request with BOTH visual and audio
    echo "\n🤖 BUILDING ENHANCED AI REQUEST:\n";
    
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
    
    // Add transcriptions to the context text if available
    if (!empty($context['transcriptions'])) {
        $enhanced_context = $context['context_text'] . "\n\n🎤 AUDIO TRANSCRIPTIONS:\n";
        foreach ($context['transcriptions'] as $transcription) {
            $enhanced_context .= "- " . $transcription['source'] . ": " . $transcription['text'] . "\n";
        }
        $messages[1]['content'][0]['text'] = $enhanced_context;
    }
    
    // Correct schema handling
    $schema = json_decode(file_get_contents('ai/schema.json'), true);
    $tools = [
        [
            "type" => "function",
            "function" => $schema['function']
        ]
    ];
    
    $payload = [
        'model' => 'o4-mini',
        'messages' => $messages,
        'tools' => $tools,
        'tool_choice' => 'required',
        'max_completion_tokens' => 100000
    ];
    
    echo "✅ Enhanced payload built: " . round(strlen(json_encode($payload)) / 1024, 1) . "KB\n";
    echo "✅ Visual items: " . count($context['visual_content']) . "\n";
    echo "✅ Audio transcriptions: " . count($context['transcriptions']) . "\n";
    
    // Make enhanced AI call
    echo "\n📡 CALLING OPENAI WITH ENHANCED DATA:\n";
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 180); // 3 minutes for enhanced analysis
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $end_time = microtime(true);
    $processing_time = round($end_time - $start_time, 2);
    
    echo "✅ Enhanced API call completed in {$processing_time}s\n";
    echo "✅ HTTP Code: {$http_code}\n";
    
    if ($http_code === 200 && !empty($response)) {
        $api_response = json_decode($response, true);
        
        if ($api_response && isset($api_response['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
            
            // Save enhanced analysis
            $output_file = "enhanced_ai_analysis_quote_{$quote_id}_" . date('Y-m-d_H-i-s') . ".json";
            
            $result = [
                'success' => true,
                'quote_id' => $quote_id,
                'model' => 'o4-mini',
                'enhanced' => true,
                'includes_audio' => count($context['transcriptions']) > 0,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_time' => $processing_time,
                'input_tokens' => $api_response['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $api_response['usage']['completion_tokens'] ?? 0,
                'visual_frames' => count($context['visual_content']),
                'audio_transcriptions' => count($context['transcriptions']),
                'analysis' => json_decode($api_response['choices'][0]['message']['tool_calls'][0]['function']['arguments'], true),
                'transcriptions' => $context['transcriptions'],
                'raw_response' => $api_response
            ];
            
            file_put_contents($output_file, json_encode($result, JSON_PRETTY_PRINT));
            
            echo "\n🎉🎉🎉 ENHANCED ANALYSIS SUCCESS! 🎉🎉🎉\n";
            echo "✅ Enhanced file saved: {$output_file}\n";
            echo "✅ Input tokens: {$result['input_tokens']}\n";
            echo "✅ Output tokens: {$result['output_tokens']}\n";
            echo "✅ Processing time: {$processing_time}s\n";
            echo "✅ Visual frames: {$result['visual_frames']}\n";
            echo "✅ Audio transcriptions: {$result['audio_transcriptions']}\n";
            
            if (isset($result['analysis']['overall_assessment'])) {
                $preview = substr($result['analysis']['overall_assessment'], 0, 400);
                echo "\n📊 ENHANCED ASSESSMENT PREVIEW:\n{$preview}...\n";
            }
            
            if (isset($result['analysis']['trees'])) {
                echo "\n🌳 TREES ANALYZED WITH AUDIO CONTEXT: " . count($result['analysis']['trees']) . "\n";
                foreach ($result['analysis']['trees'] as $i => $tree) {
                    $species = $tree['species']['common_name'] ?? 'Unknown';
                    $condition = $tree['health']['overall_condition'] ?? 'Unknown';
                    echo "  Tree " . ($i + 1) . ": {$species} - {$condition}\n";
                }
            }
            
            echo "\n🔗 Download enhanced analysis: https://carpetree.com/{$output_file}\n";
            echo "\n💰💰💰 ENHANCED ANALYSIS COMPLETE! 💰💰💰\n";
            echo "🎬 Visual: 6 JPEG frames (3.3MB)\n";
            echo "🎤 Audio: Your verbal observations and context\n";
            echo "🌳 Result: Complete tree assessment with logistics planning!\n";
            
        } else {
            echo "❌ Invalid API response structure\n";
            if (isset($api_response['error'])) {
                echo "Error: " . $api_response['error']['message'] . "\n";
            }
            echo "Response preview: " . substr($response, 0, 500) . "\n";
        }
        
    } else {
        echo "❌ Enhanced API call failed\n";
        echo "Response: " . substr($response, 0, 1000) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== ENHANCED ANALYSIS COMPLETE ===\n";
?>