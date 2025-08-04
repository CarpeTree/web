<?php
// Fix Google Maps API configuration and test functionality

echo "🗺️ Google Maps API Configuration & Testing\n";
echo "==========================================\n\n";

// Check current config
require_once 'server/config/config.php';

echo "📊 Current Configuration:\n";
echo "GOOGLE_MAPS_API_KEY: " . (isset($GOOGLE_MAPS_API_KEY) && !empty($GOOGLE_MAPS_API_KEY) ? "✅ Set" : "❌ Not set") . "\n";

if (!isset($GOOGLE_MAPS_API_KEY) || empty($GOOGLE_MAPS_API_KEY)) {
    echo "\n🔧 Setting up Google Maps API...\n";
    echo "You need to:\n";
    echo "1. Go to https://console.cloud.google.com/\n";
    echo "2. Enable Distance Matrix API and Places API\n";
    echo "3. Create an API key\n";
    echo "4. Add it to your .env file as GOOGLE_MAPS_API_KEY=your_key_here\n\n";
    
    // For now, let's test with a dummy implementation
    echo "⚠️  Using fallback distance calculation for testing...\n";
} else {
    echo "✅ Google Maps API key is configured\n\n";
}

// Test distance calculation
echo "🧪 Testing Distance Calculation...\n";

require_once 'server/api/google-distance-calculator.php';

$test_addresses = [
    "404 Kildare St, New Denver, BC",
    "123 Main St, Vancouver, BC",
    "789 Oak St, Victoria, BC"
];

foreach ($test_addresses as $address) {
    echo "📍 Testing: $address\n";
    
    try {
        $result = calculateDistanceWithGoogleMaps($address);
        
        if ($result) {
            echo "  ✅ Distance: {$result['distance_km']}km\n";
            echo "  🕒 Duration: {$result['duration']}\n";
            echo "  🚗 With traffic: {$result['duration_with_traffic']}\n";
        } else {
            echo "  ❌ Calculation failed - using fallback\n";
            
            // Test fallback calculation
            $fallback_distance = estimateDistanceFromNelson($address);
            echo "  📊 Fallback distance: {$fallback_distance}km\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Test waste disposal location finding
echo "🗑️ Testing Waste Disposal Location Finding...\n";

function findNearestTransferStations($customer_address) {
    // BC Transfer Stations Database (example data)
    $transfer_stations = [
        [
            'name' => 'Greater Vancouver Transfer Station',
            'address' => '5000 Miller Rd, Richmond, BC',
            'cost_per_tonne' => 120,
            'accepts_brush' => true,
            'coordinates' => [49.166592, -123.133569]
        ],
        [
            'name' => 'Burnaby Transfer Station', 
            'address' => '7975 6th St, Burnaby, BC',
            'cost_per_tonne' => 110,
            'accepts_brush' => true,
            'coordinates' => [49.217621, -122.959786]
        ],
        [
            'name' => 'Castlegar Regional Landfill',
            'address' => '1018 Ootischenia Rd, Castlegar, BC',
            'cost_per_tonne' => 85,
            'accepts_brush' => true,
            'coordinates' => [49.323308, -117.686097]
        ]
    ];
    
    // For now, return closest based on region
    if (strpos($customer_address, 'Vancouver') !== false || strpos($customer_address, 'Richmond') !== false) {
        return $transfer_stations[0];
    } elseif (strpos($customer_address, 'Burnaby') !== false) {
        return $transfer_stations[1];
    } else {
        return $transfer_stations[2]; // Castlegar for rural BC
    }
}

foreach ($test_addresses as $address) {
    echo "📍 Address: $address\n";
    
    $nearest_station = findNearestTransferStations($address);
    echo "  🗑️ Nearest Station: {$nearest_station['name']}\n";
    echo "  📍 Location: {$nearest_station['address']}\n";
    echo "  💰 Cost: \${$nearest_station['cost_per_tonne']}/tonne\n";
    echo "  ✅ Accepts Brush: " . ($nearest_station['accepts_brush'] ? 'Yes' : 'No') . "\n\n";
}

// Estimate brush weight
echo "⚖️ Brush Weight Estimation Examples...\n";

function estimateBrushWeight($tree_height, $crown_spread, $service_type) {
    // Rough estimation based on tree dimensions
    $crown_volume = (4/3) * pi() * pow($crown_spread/2, 2) * ($tree_height * 0.6); // Approximate crown volume
    
    if ($service_type === 'removal') {
        // Full tree = more brush
        $brush_percentage = 0.3; // 30% of tree becomes brush
    } else {
        // Pruning = less brush
        $brush_percentage = 0.1; // 10% of tree becomes brush
    }
    
    // Green brush is roughly 400-600 kg/m³
    $brush_density = 500; // kg/m³
    $brush_volume = $crown_volume * $brush_percentage;
    $brush_weight_kg = $brush_volume * $brush_density;
    $brush_weight_tonnes = $brush_weight_kg / 1000;
    
    return [
        'volume_m3' => round($brush_volume, 2),
        'weight_tonnes' => round($brush_weight_tonnes, 2),
        'truck_loads' => ceil($brush_weight_tonnes / 2), // 2 tonnes per truck load
        'disposal_cost' => round($brush_weight_tonnes * 100, 2) // Average $100/tonne
    ];
}

$examples = [
    ['height' => 15, 'crown' => 8, 'type' => 'removal'],
    ['height' => 20, 'crown' => 12, 'type' => 'pruning'],
    ['height' => 25, 'crown' => 15, 'type' => 'removal']
];

foreach ($examples as $example) {
    $estimate = estimateBrushWeight($example['height'], $example['crown'], $example['type']);
    
    echo "🌲 {$example['height']}m tall, {$example['crown']}m crown, {$example['type']}:\n";
    echo "  📦 Brush Volume: {$estimate['volume_m3']}m³\n";
    echo "  ⚖️ Weight: {$estimate['weight_tonnes']} tonnes\n";
    echo "  🚛 Truck Loads: {$estimate['truck_loads']}\n";
    echo "  💰 Disposal Cost: \${$estimate['disposal_cost']}\n\n";
}

echo "✅ Google Maps API testing complete!\n";
echo "Next steps:\n";
echo "1. Configure your Google Maps API key in .env\n";
echo "2. Test distance calculations with real quotes\n";
echo "3. Implement transfer station database\n";
echo "4. Integrate into admin dashboard\n";
?>