<?php
// Test AI distance calculator directly
echo "Testing AI Distance Calculator (O3 Workhorse)...\n";
echo "==============================================\n\n";

require_once __DIR__ . '/server/api/ai-distance-calculator.php';

// Test addresses
$test_addresses = [
    'Fernie, BC',
    'Cranbrook, BC', 
    'Calgary, AB',
    'Moyie Lake, BC',
    'Vancouver, BC'
];

foreach ($test_addresses as $address) {
    echo "🤖 Testing AI calculation for: $address\n";
    
    $distance = calculateDistanceWithAI($address);
    
    echo "   📍 AI Result: {$distance}km\n";
    echo "   💰 Travel Cost: \${$distance}.00\n\n";
}

echo "🎯 AI is now your geographic workhorse!\n";
echo "   - Handles ANY address dynamically\n";
echo "   - Real driving distances (not straight-line)\n";
echo "   - Considers BC mountain terrain\n";
echo "   - No more manual lookup tables!\n";
?> 