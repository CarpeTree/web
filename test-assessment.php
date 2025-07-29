<?php
// Test the quick AI assessment
require_once 'server/api/quick-ai-assessment.php';

echo "=== TESTING QUICK AI ASSESSMENT ===\n\n";

// Test on quote #3 (the one we inserted earlier)
echo "Testing assessment on Quote #3...\n";

$result = quickAIAssessment(3);

if (isset($result['error'])) {
    echo "❌ Error: " . $result['error'] . "\n";
} else {
    echo "✅ Assessment Results:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🎯 OVERALL SCORE: {$result['sufficiency_score']}/100\n";
    echo "📊 CATEGORY: " . strtoupper($result['category']) . "\n";
    echo "🎯 CAN QUOTE ACCURATELY: " . ($result['can_quote_accurately'] ? '✅ YES' : '❌ NO') . "\n";
    echo "🔒 CONFIDENCE LEVEL: " . strtoupper($result['confidence_level']) . "\n";
    echo "⚡ PRIORITY: " . strtoupper($result['priority_level']) . "\n";
    echo "📈 ESTIMATED ACCURACY: {$result['estimated_quote_accuracy']}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "📋 DETAILED BREAKDOWN:\n";
    foreach ($result['detailed_breakdown'] as $category => $details) {
        echo "  " . strtoupper(str_replace('_', ' ', $category)) . ": {$details['score']}/{$details['max_score']}\n";
        foreach ($details['details'] as $detail) {
            echo "    • $detail\n";
        }
        echo "\n";
    }
    
    if (!empty($result['recommendations'])) {
        echo "💡 RECOMMENDATIONS:\n";
        foreach ($result['recommendations'] as $rec) {
            echo "  • $rec\n";
        }
        echo "\n";
    }
    
    if (!empty($result['action_plan'])) {
        echo "🎯 ACTION PLAN:\n";
        foreach ($result['action_plan'] as $action) {
            echo "  • $action\n";
        }
        echo "\n";
    }
}

echo "=== TEST COMPLETE ===\n";
?> 