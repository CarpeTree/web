<?php
/**
 * Comprehensive Distance & Logistics Calculator
 * Calculates:
 * - Driving distance and time to customer
 * - Nearest transfer stations
 * - Round trip with disposal
 * - Total travel costs
 **/

require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../config/secure-config.php';

header('Content-Type: application/json');

// Transfer stations in BC (with actual coordinates)
$transfer_stations = [
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

function calculateRoute($origin, $destination, $waypoint = null) {
    global $GOOGLE_MAPS_API_KEY;
    
    if (empty($GOOGLE_MAPS_API_KEY)) {
        return ['error' => 'Google Maps API key not configured'];
    }
    
    // Build the URL for Distance Matrix API
    $params = [
        'origins' => $origin,
        'destinations' => $destination,
        'mode' => 'driving',
        'units' => 'metric',
        'departure_time' => 'now',
        'key' => $GOOGLE_MAPS_API_KEY
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
        return ['error' => 'API error: ' . $data['status']];
    }
    
    $element = $data['rows'][0]['elements'][0] ?? null;
    
    if (!$element || $element['status'] !== 'OK') {
        return ['error' => 'Route not found'];
    }
    
    return [
        'distance_km' => round($element['distance']['value'] / 1000, 1),
        'distance_text' => $element['distance']['text'],
        'duration_minutes' => round($element['duration']['value'] / 60),
        'duration_text' => $element['duration']['text'],
        'duration_in_traffic' => isset($element['duration_in_traffic']) 
            ? round($element['duration_in_traffic']['value'] / 60) 
            : round($element['duration']['value'] / 60)
    ];
}

function findNearestTransferStations($customer_location, $stations, $limit = 3) {
    global $GOOGLE_MAPS_API_KEY;
    
    $results = [];
    
    // Calculate distance to each transfer station
    foreach ($stations as $station) {
        $destination = $station['lat'] . ',' . $station['lng'];
        $route = calculateRoute($customer_location, $destination);
        
        if (!isset($route['error'])) {
            $results[] = array_merge($station, [
                'route_from_customer' => $route,
                'total_distance' => $route['distance_km']
            ]);
        }
    }
    
    // Sort by distance
    usort($results, function($a, $b) {
        return $a['total_distance'] <=> $b['total_distance'];
    });
    
    // Return top N closest stations
    return array_slice($results, 0, $limit);
}

function calculateComprehensiveLogistics($quote_id) {
    global $pdo, $transfer_stations;
    
    // Get quote and customer data
    $stmt = $pdo->prepare("
        SELECT q.*, c.address, c.geo_latitude, c.geo_longitude, c.name
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quote) {
        return ['error' => 'Quote not found'];
    }
    
    // Home base location
    $home = "4530 Blewitt Rd, Nelson, BC V1L 6X1";
    
    // Customer location
    $customer = $quote['address'] . ', BC, Canada';
    if ($quote['geo_latitude'] && $quote['geo_longitude']) {
        $customer = $quote['geo_latitude'] . ',' . $quote['geo_longitude'];
    }
    
    // Calculate home to customer
    $home_to_customer = calculateRoute($home, $customer);
    
    // Find nearest transfer stations
    $nearest_stations = findNearestTransferStations($customer, $transfer_stations, 3);
    
    // Calculate full route with disposal (if needed)
    $with_disposal = null;
    if (!empty($nearest_stations)) {
        $nearest = $nearest_stations[0];
        
        // Route: Home -> Customer -> Transfer Station -> Home
        $customer_to_station = $nearest['route_from_customer'];
        $station_to_home = calculateRoute(
            $nearest['lat'] . ',' . $nearest['lng'],
            $home
        );
        
        $with_disposal = [
            'station' => $nearest['name'],
            'total_distance_km' => round(
                $home_to_customer['distance_km'] + 
                $customer_to_station['distance_km'] + 
                $station_to_home['distance_km'], 1
            ),
            'total_duration_minutes' => 
                $home_to_customer['duration_minutes'] + 
                $customer_to_station['duration_minutes'] + 
                $station_to_home['duration_minutes'],
            'segments' => [
                'home_to_customer' => $home_to_customer,
                'customer_to_station' => $customer_to_station,
                'station_to_home' => $station_to_home
            ],
            'disposal_cost_per_tonne' => $nearest['cost_per_tonne']
        ];
    }
    
    // Pricing policies and toggles
    $truck_rate_per_km = 1.00; // $1/km for truck (round-trip basis per leg)
    $car_rate_per_km = 0.35;   // $0.35/km for car (informational)
    $climber_rate = 150;       // driver hourly
    $ground_rate  = 50;        // ground hourly (optional for travel)
    $dump_wait_minutes_default = 30;
    // Read optional overrides from request (safe defaults)
    $trips_required = isset($_REQUEST['trips_required']) ? max(1, (int)$_REQUEST['trips_required']) : 1;
    $include_crew_travel_labor = isset($_REQUEST['include_crew_travel_labor']) ? filter_var($_REQUEST['include_crew_travel_labor'], FILTER_VALIDATE_BOOLEAN) : false;
    // Default: labor for travel is OFF per user request
    $include_driver_travel_labor = isset($_REQUEST['include_driver_travel_labor']) ? filter_var($_REQUEST['include_driver_travel_labor'], FILTER_VALIDATE_BOOLEAN) : false;
    $dump_wait_minutes = isset($_REQUEST['dump_wait_minutes']) ? max(0, (int)$_REQUEST['dump_wait_minutes']) : $dump_wait_minutes_default;
    $ground_count = isset($_REQUEST['ground_count']) ? max(0, (int)$_REQUEST['ground_count']) : 0;
    
    $simple_round_trip = $home_to_customer['distance_km'] * 2;
    
    $result = [
        'quote_id' => $quote_id,
        'customer_name' => $quote['name'],
        'customer_address' => $quote['address'],
        'routes' => [
            'simple_round_trip' => [
                'distance_km' => $simple_round_trip,
                'duration_minutes' => $home_to_customer['duration_minutes'] * 2,
                'duration_text' => round($home_to_customer['duration_minutes'] * 2 / 60, 1) . ' hours',
                'truck_cost' => round($simple_round_trip * $truck_rate_per_km, 2),
                'car_cost' => round($simple_round_trip * $car_rate_per_km, 2)
            ],
            'one_way' => $home_to_customer,
            'with_disposal' => $with_disposal
        ],
        'nearest_transfer_stations' => $nearest_stations,
        // Travel legs as line items with driver travel labor by default
        'travel' => (function() use (
            $home_to_customer, $with_disposal, $truck_rate_per_km, $climber_rate, $ground_rate,
            $include_crew_travel_labor, $dump_wait_minutes, $trips_required
        ) {
            $legs = [];
            $km_to_mi = function($km) { return round($km * 0.621371, 1); };
            $add_leg = function($name, $distance_km, $duration_hours, $trips) use (
                $truck_rate_per_km, $climber_rate, $ground_rate, $include_driver_travel_labor, $include_crew_travel_labor, $km_to_mi
            ) {
                $distance_km = max(0, (float)$distance_km);
                $duration_hours = max(0, (float)$duration_hours);
                $trips = max(0, (int)$trips);
                $per_km_cost = round($distance_km * $truck_rate_per_km * $trips, 2);
                $driver_hours = round($duration_hours * $trips, 2);
                $driver_cost = $include_driver_travel_labor ? round($driver_hours * $climber_rate, 2) : 0.0;
                $crew_cost = $include_crew_travel_labor ? round($driver_hours * $ground_rate, 2) : 0.0;
                return [
                    'leg' => $name,
                    'trips' => $trips,
                    'distance_km' => round($distance_km, 1),
                    'distance_mi' => $km_to_mi($distance_km),
                    'duration_hours' => round($duration_hours, 2),
                    'per_km_rate_cad' => $truck_rate_per_km,
                    'per_km_cost_cad' => $per_km_cost,
                    'driver_travel_labor_hours' => $driver_hours,
                    'driver_travel_labor_cost_cad' => $driver_cost,
                    'crew_travel_labor_cost_cad' => $crew_cost,
                    'line_total_cad' => round($per_km_cost + $driver_cost + $crew_cost, 2)
                ];
            };

            // 1) Home → Job (single)
            $legs[] = $add_leg('Travel: Home → Job', $home_to_customer['distance_km'], $home_to_customer['duration_minutes'] / 60, 1);

            if ($with_disposal) {
                // Distances/durations for Job → Transfer, Transfer → Home
                $job_to_station_km = $with_disposal['segments']['customer_to_station']['distance_km'];
                $job_to_station_hr = $with_disposal['segments']['customer_to_station']['duration_minutes'] / 60;
                $station_to_home_km = $with_disposal['segments']['station_to_home']['distance_km'];
                $station_to_home_hr = $with_disposal['segments']['station_to_home']['duration_minutes'] / 60;
                // Station → Job (approx same as Job → Station)
                $station_to_job_km = $job_to_station_km;
                $station_to_job_hr = $job_to_station_hr;

                // 2) Job → Transfer (per trip)
                $legs[] = $add_leg('Travel: Job → Transfer Station', $job_to_station_km, $job_to_station_hr, $trips_required);

                // 3) Transfer wait (time only per trip)
                $wait_hours = max(0, $dump_wait_minutes) / 60;
                $wait_trips = $trips_required;
                // Build wait leg with zero distance
                $per_km_rate_cad = 0.0; // informational
                $driver_hours = round($wait_hours * $wait_trips, 2);
                $driver_cost = $include_driver_travel_labor ? round($driver_hours * $climber_rate, 2) : 0.0;
                $crew_cost = $include_crew_travel_labor ? round($driver_hours * $ground_rate, 2) : 0.0;
                $legs[] = [
                    'leg' => 'Travel: Transfer Station wait',
                    'trips' => $wait_trips,
                    'distance_km' => 0.0,
                    'distance_mi' => 0.0,
                    'duration_hours' => round($wait_hours, 2),
                    'per_km_rate_cad' => $per_km_rate_cad,
                    'per_km_cost_cad' => 0.0,
                    'driver_travel_labor_hours' => $driver_hours,
                    'driver_travel_labor_cost_cad' => $driver_cost,
                    'crew_travel_labor_cost_cad' => $crew_cost,
                    'line_total_cad' => round($driver_cost + $crew_cost, 2)
                ];

                // 4) Transfer → Job (for intermediate trips only)
                if ($trips_required > 1) {
                    $legs[] = $add_leg('Travel: Transfer Station → Job', $station_to_job_km, $station_to_job_hr, $trips_required - 1);
                }

                // 5) Transfer → Home (final)
                $legs[] = $add_leg('Travel: Transfer Station → Home', $station_to_home_km, $station_to_home_hr, 1);
            } else {
                // If no disposal, return Job → Home leg
                $legs[] = $add_leg('Travel: Job → Home', $home_to_customer['distance_km'], $home_to_customer['duration_minutes'] / 60, 1);
            }

            // Totals
            $totals = [
                'total_travel_km' => round(array_sum(array_map(function($l){ return ($l['distance_km'] ?? 0) * ($l['trips'] ?? 1); }, $legs)), 1),
                'total_travel_time_hours' => round(array_sum(array_map(function($l){ return ($l['duration_hours'] ?? 0) * ($l['trips'] ?? 1); }, $legs)), 2),
                'per_km_cost_cad' => round(array_sum(array_map(function($l){ return $l['per_km_cost_cad'] ?? 0; }, $legs)), 2),
                'driver_travel_labor_cost_cad' => round(array_sum(array_map(function($l){ return $l['driver_travel_labor_cost_cad'] ?? 0; }, $legs)), 2),
                'crew_travel_labor_cost_cad' => round(array_sum(array_map(function($l){ return $l['crew_travel_labor_cost_cad'] ?? 0; }, $legs)), 2),
            ];
            $totals['total_travel_cost_cad'] = round($totals['per_km_cost_cad'] + $totals['driver_travel_labor_cost_cad'] + $totals['crew_travel_labor_cost_cad'], 2);

            return [
                'pricing_policy' => [
                    'per_km_rate_cad' => $truck_rate_per_km,
                    'include_driver_travel_labor' => $include_driver_travel_labor,
                    'include_crew_travel_labor' => $include_crew_travel_labor,
                    'dump_wait_minutes_per_trip' => $dump_wait_minutes
                ],
                'selected_transfer_station' => $with_disposal ? [
                    'name' => $with_disposal['station'],
                    'distance_km' => $with_disposal['segments']['customer_to_station']['distance_km']
                ] : null,
                'trips_required' => $with_disposal ? $trips_required : 0,
                'legs' => $legs,
                'totals' => $totals
            ];
        })()
    ];
    
    // Update database with calculated distances
    $update_stmt = $pdo->prepare("
        UPDATE quotes 
        SET 
            distance_km = ?,
            driving_time_minutes = ?,
            nearest_transfer_station = ?,
            transfer_station_distance_km = ?
        WHERE id = ?
    ");
    
    $update_stmt->execute([
        $home_to_customer['distance_km'],
        $home_to_customer['duration_minutes'],
        $nearest_stations[0]['name'] ?? null,
        $nearest_stations[0]['total_distance'] ?? null,
        $quote_id
    ]);
    
    return $result;
}

// Handle API request
$quote_id = $_GET['quote_id'] ?? $_POST['quote_id'] ?? null;
$action = $_GET['action'] ?? 'full';

if (!$quote_id) {
    echo json_encode(['error' => 'Quote ID required']);
    exit;
}

try {
    $result = calculateComprehensiveLogistics($quote_id);
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

