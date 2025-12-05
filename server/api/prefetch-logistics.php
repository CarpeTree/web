<?php
/**
 * Prefetch Logistics Data for AI Context
 * 
 * Returns pre-calculated Maps data to inject into AI prompts:
 * - Nearest hospital with distance and drive time
 * - Transfer stations (nearest and on-route)
 * - Municipality/jurisdiction from reverse geocoding
 * - Travel distances and times
 * - Pricing calculations
 */

require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Home base location
$HOME_BASE = "4530 Blewitt Rd, Nelson, BC V1L 6X1";
$HOME_COORDS = "49.4875,-117.2886";

// Hospitals in the region
$HOSPITALS = [
    [
        'name' => 'Kootenay Lake Hospital',
        'address' => '3 View St, Nelson, BC V1L 2V1',
        'lat' => 49.4928,
        'lng' => -117.2936,
        'emergency' => true
    ],
    [
        'name' => 'Trail Regional Hospital',
        'address' => '1200 Hospital Bench, Trail, BC V1R 4M1',
        'lat' => 49.0953,
        'lng' => -117.7117,
        'emergency' => true
    ],
    [
        'name' => 'Castlegar Health Centre',
        'address' => '709 10th St, Castlegar, BC V1N 2H7',
        'lat' => 49.3244,
        'lng' => -117.6658,
        'emergency' => false
    ],
    [
        'name' => 'Arrow Lakes Hospital',
        'address' => '97 1st Ave NW, Nakusp, BC V0G 1R0',
        'lat' => 50.2414,
        'lng' => -117.8011,
        'emergency' => true
    ]
];

// Transfer stations
$TRANSFER_STATIONS = [
    [
        'name' => 'Grohman Narrows Transfer Station',
        'address' => 'Grohman Narrows, Nelson, BC',
        'lat' => 49.5128,
        'lng' => -117.2948,
        'cost_per_tonne' => 90,
        'accepts' => ['brush', 'wood', 'yard waste']
    ],
    [
        'name' => 'Castlegar Transfer Station',
        'address' => 'Columbia Road, Castlegar, BC',
        'lat' => 49.3239,
        'lng' => -117.6621,
        'cost_per_tonne' => 85,
        'accepts' => ['brush', 'wood', 'yard waste', 'construction']
    ],
    [
        'name' => 'Salmo Transfer Station',
        'address' => 'Highway 3, Salmo, BC',
        'lat' => 49.1917,
        'lng' => -117.2783,
        'cost_per_tonne' => 80,
        'accepts' => ['brush', 'wood']
    ],
    [
        'name' => 'Trail Landfill',
        'address' => 'Waneta, Trail, BC',
        'lat' => 49.0022,
        'lng' => -117.6089,
        'cost_per_tonne' => 95,
        'accepts' => ['all waste types']
    ],
    [
        'name' => 'Nakusp Transfer Station',
        'address' => 'Highway 6, Nakusp, BC',
        'lat' => 50.2399,
        'lng' => -117.8022,
        'cost_per_tonne' => 85,
        'accepts' => ['brush', 'wood', 'yard waste']
    ]
];

// Pricing constants (from system_prompts.json)
$PRICING = [
    'climber_rate' => 150,
    'ground_rate' => 50,
    'truck_per_km' => 2.15,
    'truck_chipper_per_km' => 2.65,
    'mountain_winter_min' => 2.60,
    'mountain_winter_max' => 3.10,
    'min_mobilization' => 95,
    'first_km_included' => 30,
    'tipping_fee_per_tonne' => 60.50,
    'dump_wait_minutes' => 30,
    'max_weight_per_trip_tonnes' => 3.0
];

/**
 * Calculate distance using Google Distance Matrix API
 */
function getDistance($origin, $destination) {
    $api_key = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
    if (empty($api_key)) {
        return ['error' => 'Google Maps API key not configured'];
    }
    
    $params = [
        'origins' => $origin,
        'destinations' => $destination,
        'mode' => 'driving',
        'units' => 'metric',
        'key' => $api_key
    ];
    
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?" . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return ['error' => 'API request failed'];
    }
    
    $data = json_decode($response, true);
    
    if ($data['status'] !== 'OK') {
        return ['error' => 'API error: ' . ($data['error_message'] ?? $data['status'])];
    }
    
    $element = $data['rows'][0]['elements'][0] ?? null;
    
    if (!$element || $element['status'] !== 'OK') {
        return ['error' => 'Route not found'];
    }
    
    return [
        'distance_km' => round($element['distance']['value'] / 1000, 1),
        'distance_miles' => round($element['distance']['value'] / 1609.34, 1),
        'distance_text' => $element['distance']['text'],
        'duration_minutes' => round($element['duration']['value'] / 60),
        'duration_text' => $element['duration']['text']
    ];
}

/**
 * Reverse geocode to get municipality/jurisdiction
 */
function getMunicipality($lat, $lng) {
    $api_key = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
    if (empty($api_key)) {
        return null;
    }
    
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$api_key}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data['status'] !== 'OK' || empty($data['results'])) {
        return null;
    }
    
    $result = [
        'formatted_address' => $data['results'][0]['formatted_address'] ?? null,
        'municipality' => null,
        'regional_district' => null,
        'province' => null,
        'postal_code' => null
    ];
    
    foreach ($data['results'][0]['address_components'] as $component) {
        if (in_array('locality', $component['types'])) {
            $result['municipality'] = $component['long_name'];
        }
        if (in_array('administrative_area_level_2', $component['types'])) {
            $result['regional_district'] = $component['long_name'];
        }
        if (in_array('administrative_area_level_1', $component['types'])) {
            $result['province'] = $component['long_name'];
        }
        if (in_array('postal_code', $component['types'])) {
            $result['postal_code'] = $component['long_name'];
        }
    }
    
    return $result;
}

/**
 * Find nearest hospital from job site
 */
function findNearestHospital($job_location, $hospitals) {
    $nearest = null;
    $min_distance = PHP_INT_MAX;
    
    foreach ($hospitals as $hospital) {
        $dest = $hospital['lat'] . ',' . $hospital['lng'];
        $route = getDistance($job_location, $dest);
        
        if (!isset($route['error']) && $route['distance_km'] < $min_distance) {
            $min_distance = $route['distance_km'];
            $nearest = array_merge($hospital, [
                'distance_km' => $route['distance_km'],
                'distance_miles' => $route['distance_miles'],
                'drive_time_minutes' => $route['duration_minutes'],
                'drive_time_text' => $route['duration_text']
            ]);
        }
    }
    
    return $nearest;
}

/**
 * Find nearest and on-route transfer stations
 */
function findTransferStations($home_coords, $job_location, $stations) {
    $results = [];
    
    // Calculate distance from job to each station
    foreach ($stations as $station) {
        $dest = $station['lat'] . ',' . $station['lng'];
        $route = getDistance($job_location, $dest);
        
        if (!isset($route['error'])) {
            $results[] = array_merge($station, [
                'from_job_km' => $route['distance_km'],
                'from_job_miles' => $route['distance_miles'],
                'from_job_minutes' => $route['duration_minutes']
            ]);
        }
    }
    
    // Sort by distance from job
    usort($results, fn($a, $b) => $a['from_job_km'] <=> $b['from_job_km']);
    
    $nearest = $results[0] ?? null;
    
    // Find on-route station (between job and home)
    // Simple heuristic: station that minimizes total detour
    $on_route = null;
    $min_detour = PHP_INT_MAX;
    
    $direct_home = getDistance($job_location, $home_coords);
    $direct_distance = $direct_home['distance_km'] ?? 100;
    
    foreach ($results as $station) {
        // Calculate: job -> station -> home vs job -> home
        $station_to_home = getDistance($station['lat'] . ',' . $station['lng'], $home_coords);
        if (!isset($station_to_home['error'])) {
            $total_via_station = $station['from_job_km'] + $station_to_home['distance_km'];
            $detour = $total_via_station - $direct_distance;
            
            // Consider it "on route" if detour is less than 20km
            if ($detour < 20 && $detour < $min_detour) {
                $min_detour = $detour;
                $on_route = array_merge($station, [
                    'detour_km' => round($detour, 1),
                    'to_home_km' => $station_to_home['distance_km'],
                    'to_home_minutes' => $station_to_home['duration_minutes']
                ]);
            }
        }
    }
    
    return [
        'nearest' => $nearest,
        'on_route' => $on_route,
        'all' => array_slice($results, 0, 3)
    ];
}

/**
 * Calculate travel costs
 */
function calculateTravelCosts($distance_km, $pricing, $with_chipper = false) {
    $rate = $with_chipper ? $pricing['truck_chipper_per_km'] : $pricing['truck_per_km'];
    $round_trip_km = $distance_km * 2;
    $billable_km = max(0, $round_trip_km - $pricing['first_km_included']);
    
    return [
        'round_trip_km' => round($round_trip_km, 1),
        'billable_km' => round($billable_km, 1),
        'rate_per_km' => $rate,
        'travel_cost' => max($pricing['min_mobilization'], round($billable_km * $rate, 2)),
        'min_mobilization' => $pricing['min_mobilization'],
        'first_km_included' => $pricing['first_km_included']
    ];
}

/**
 * Calculate disposal costs
 */
function calculateDisposalCosts($tonnes, $station, $pricing) {
    $trips = ceil($tonnes / $pricing['max_weight_per_trip_tonnes']);
    $tipping_cost = round($tonnes * $pricing['tipping_fee_per_tonne'], 2);
    $wait_time_hours = ($trips * $pricing['dump_wait_minutes']) / 60;
    
    return [
        'tonnes' => $tonnes,
        'trips_required' => $trips,
        'tipping_fee_per_tonne' => $pricing['tipping_fee_per_tonne'],
        'tipping_cost' => $tipping_cost,
        'station_rate_per_tonne' => $station['cost_per_tonne'] ?? $pricing['tipping_fee_per_tonne'],
        'wait_time_hours' => round($wait_time_hours, 2),
        'wait_time_cost' => round($wait_time_hours * $pricing['climber_rate'], 2)
    ];
}

/**
 * Generate AI context string from logistics data
 */
function generateAIContext($data) {
    $lines = [];
    $lines[] = "=== PRE-VERIFIED LOCATION DATA (from Google Maps API) ===";
    $lines[] = "";
    
    // Travel
    if (isset($data['travel'])) {
        $t = $data['travel'];
        $lines[] = "TRAVEL TO JOB SITE:";
        $lines[] = "- Distance: {$t['distance_km']} km ({$t['distance_miles']} miles)";
        $lines[] = "- Drive time: {$t['duration_minutes']} minutes";
        $lines[] = "- Round trip: {$t['costs']['round_trip_km']} km";
        $lines[] = "- Billable km (after first {$t['costs']['first_km_included']} km): {$t['costs']['billable_km']} km";
        $lines[] = "- Travel cost @ \${$t['costs']['rate_per_km']}/km: \${$t['costs']['travel_cost']} CAD";
        $lines[] = "";
    }
    
    // Hospital
    if (isset($data['hospital'])) {
        $h = $data['hospital'];
        $lines[] = "NEAREST HOSPITAL:";
        $lines[] = "- Name: {$h['name']}";
        $lines[] = "- Address: {$h['address']}";
        $lines[] = "- Distance: {$h['distance_km']} km ({$h['distance_miles']} miles)";
        $lines[] = "- Drive time: {$h['drive_time_minutes']} minutes";
        $lines[] = "- Emergency: " . ($h['emergency'] ? 'Yes' : 'No');
        $lines[] = "";
    }
    
    // Municipality
    if (isset($data['municipality'])) {
        $m = $data['municipality'];
        $lines[] = "JURISDICTION:";
        $lines[] = "- Municipality: " . ($m['municipality'] ?? 'Unknown');
        $lines[] = "- Regional District: " . ($m['regional_district'] ?? 'Unknown');
        $lines[] = "- Province: " . ($m['province'] ?? 'BC');
        $lines[] = "";
    }
    
    // Transfer stations
    if (isset($data['transfer_stations'])) {
        $ts = $data['transfer_stations'];
        $lines[] = "TRANSFER STATIONS:";
        if ($ts['nearest']) {
            $n = $ts['nearest'];
            $lines[] = "- Nearest: {$n['name']} ({$n['from_job_km']} km, {$n['from_job_minutes']} min from job)";
            $lines[] = "  Tipping fee: \${$n['cost_per_tonne']}/tonne";
        }
        if ($ts['on_route']) {
            $o = $ts['on_route'];
            $lines[] = "- On-route (best for disposal): {$o['name']} (+{$o['detour_km']} km detour)";
            $lines[] = "  Tipping fee: \${$o['cost_per_tonne']}/tonne";
        }
        $lines[] = "";
    }
    
    // Pricing reference
    $lines[] = "PRICING REFERENCE:";
    $lines[] = "- Climber rate: \$150 CAD/hr";
    $lines[] = "- Ground crew rate: \$50 CAD/hr";
    $lines[] = "- Truck only: \$2.15/km";
    $lines[] = "- Truck + chipper: \$2.65/km";
    $lines[] = "- RDCK tipping fee: \$60.50/tonne";
    $lines[] = "- First 30 km included in mobilization";
    $lines[] = "- Minimum mobilization: \$95 CAD";
    $lines[] = "";
    
    return implode("\n", $lines);
}

// Main handler
$quote_id = $_GET['quote_id'] ?? $_POST['quote_id'] ?? null;

if (!$quote_id) {
    echo json_encode(['error' => 'Quote ID required']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Get quote and customer data
    $stmt = $pdo->prepare("
        SELECT q.*, c.address, c.geo_latitude, c.geo_longitude, c.name as customer_name
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quote) {
        echo json_encode(['error' => 'Quote not found']);
        exit;
    }
    
    // Determine job location
    $job_location = null;
    if ($quote['geo_latitude'] && $quote['geo_longitude']) {
        $job_location = $quote['geo_latitude'] . ',' . $quote['geo_longitude'];
    } elseif ($quote['address']) {
        $job_location = $quote['address'] . ', BC, Canada';
    }
    
    if (!$job_location) {
        echo json_encode(['error' => 'No address or coordinates for this quote']);
        exit;
    }
    
    $result = [
        'quote_id' => $quote_id,
        'customer_name' => $quote['customer_name'],
        'customer_address' => $quote['address'],
        'job_coordinates' => $quote['geo_latitude'] && $quote['geo_longitude'] 
            ? ['lat' => $quote['geo_latitude'], 'lng' => $quote['geo_longitude']] 
            : null
    ];
    
    // Calculate travel from home to job
    $travel = getDistance($HOME_BASE, $job_location);
    if (!isset($travel['error'])) {
        $result['travel'] = array_merge($travel, [
            'costs' => calculateTravelCosts($travel['distance_km'], $PRICING)
        ]);
    }
    
    // Find nearest hospital
    $result['hospital'] = findNearestHospital($job_location, $HOSPITALS);
    
    // Get municipality from reverse geocoding
    if ($quote['geo_latitude'] && $quote['geo_longitude']) {
        $result['municipality'] = getMunicipality($quote['geo_latitude'], $quote['geo_longitude']);
    }
    
    // Find transfer stations
    $result['transfer_stations'] = findTransferStations($HOME_COORDS, $job_location, $TRANSFER_STATIONS);
    
    // Calculate example disposal costs (for 2 tonnes)
    if ($result['transfer_stations']['nearest']) {
        $result['disposal_example'] = calculateDisposalCosts(2, $result['transfer_stations']['nearest'], $PRICING);
    }
    
    // Pricing constants for reference
    $result['pricing'] = $PRICING;
    
    // Generate AI context string
    $result['ai_context'] = generateAIContext($result);
    
    // Update quote with calculated data
    $update_stmt = $pdo->prepare("
        UPDATE quotes SET
            distance_km = ?,
            driving_time_minutes = ?,
            nearest_transfer_station = ?,
            transfer_station_distance_km = ?
        WHERE id = ?
    ");
    $update_stmt->execute([
        $travel['distance_km'] ?? null,
        $travel['duration_minutes'] ?? null,
        $result['transfer_stations']['nearest']['name'] ?? null,
        $result['transfer_stations']['nearest']['from_job_km'] ?? null,
        $quote_id
    ]);
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log('Prefetch logistics error: ' . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}

