<?php
// Google Maps Distance Matrix API Calculator
require_once __DIR__ . '/../config/config.php';

function calculateDistanceWithGoogleMaps($customer_address, $customer_lat = null, $customer_lng = null, $origin_lat = null, $origin_lng = null) {
    global $GOOGLE_MAPS_API_KEY, $HOME_LAT, $HOME_LNG;
    
    if (empty($GOOGLE_MAPS_API_KEY)) {
        error_log("Google Maps API key not configured - falling back to AI distance");
        return null;
    }
    
    // Base location: Carpe Tree'em office (prefer fixed coordinates if provided)
    // Allow dynamic base camp via JSON config
    $camp_file = dirname(__DIR__) . '/data/base-camps.json';
    $current_camp = null;
    if (file_exists($camp_file)) {
        $camp_json = json_decode(file_get_contents($camp_file), true);
        if (!empty($camp_json['current']['latitude']) && !empty($camp_json['current']['longitude'])) {
            $current_camp = [
                'lat' => (float)$camp_json['current']['latitude'],
                'lng' => (float)$camp_json['current']['longitude']
            ];
        } elseif (!empty($camp_json['base_camps'])) {
            foreach ($camp_json['base_camps'] as $bc) {
                if (!empty($bc['default'])) {
                    $current_camp = [ 'lat' => (float)$bc['latitude'], 'lng' => (float)$bc['longitude'] ];
                    break;
                }
            }
        }
    }

    if ($origin_lat && $origin_lng) {
        $origin = "$origin_lat,$origin_lng";
    } elseif ($current_camp) {
        $origin = $current_camp['lat'] . ',' . $current_camp['lng'];
    } elseif (!empty($HOME_LAT) && !empty($HOME_LNG)) {
        $origin = "$HOME_LAT,$HOME_LNG";
    } else {
        $origin = "4530 Blewitt Rd, Nelson, BC V1L 6X1, Canada";
    }
    
    // Determine destination
    if ($customer_lat && $customer_lng) {
        // Use GPS coordinates if available (more accurate)
        $destination = "$customer_lat,$customer_lng";
        error_log("Using GPS coordinates for distance calculation: $customer_lat,$customer_lng");
    } else {
        // Use address geocoding
        $destination = $customer_address . ", BC, Canada";
        error_log("Using address for distance calculation: $destination");
    }
    
    // Google Maps Distance Matrix API endpoint
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?" . http_build_query([
        'origins' => $origin,
        'destinations' => $destination,
        'mode' => 'driving',
        'units' => 'metric',
        'departure_time' => 'now', // Current traffic conditions
        'traffic_model' => 'best_guess',
        'key' => $GOOGLE_MAPS_API_KEY
    ]);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($http_code !== 200 || !$response || $curl_error) {
        error_log("Google Maps API request failed: HTTP $http_code, Error: $curl_error");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || $data['status'] !== 'OK') {
        error_log("Google Maps API error: " . ($data['status'] ?? 'Unknown error') . " - " . ($data['error_message'] ?? ''));
        return null;
    }
    
    $element = $data['rows'][0]['elements'][0] ?? null;
    
    if (!$element || $element['status'] !== 'OK') {
        error_log("Google Maps route calculation failed: " . ($element['status'] ?? 'Unknown status'));
        return null;
    }
    
    // Extract distance and duration
    $distance_km = round($element['distance']['value'] / 1000, 1); // Convert meters to km
    $duration_text = $element['duration']['text'] ?? 'Unknown';
    $duration_traffic_text = $element['duration_in_traffic']['text'] ?? $duration_text;
    
    // Log successful calculation
    error_log("Google Maps calculated distance: {$distance_km}km, Duration: $duration_text (with traffic: $duration_traffic_text)");
    
    return [
        'distance_km' => $distance_km,
        'duration' => $duration_text,
        'duration_with_traffic' => $duration_traffic_text,
        'source' => 'google_maps'
    ];
}



function estimateDriveTime($distance_km, $with_traffic = false) {
    // Estimate drive time based on BC mountain/highway driving
    // Assumptions: Mix of highway (80-100 km/h) and mountain roads (40-60 km/h)
    
    if ($distance_km <= 20) {
        // Local/city driving
        $avg_speed = 35; // km/h (lots of stops, mountain roads)
    } elseif ($distance_km <= 100) {
        // Regional driving (mix of highway and mountain roads)
        $avg_speed = 55; // km/h
    } else {
        // Long distance (mostly highway)
        $avg_speed = 70; // km/h (accounting for mountain passes)
    }
    
    $hours = $distance_km / $avg_speed;
    
    // Add traffic buffer for current conditions
    if ($with_traffic) {
        $hours *= 1.2; // 20% buffer for traffic/construction
    }
    
    if ($hours < 1) {
        return round($hours * 60) . " mins";
    } else {
        $hours_int = floor($hours);
        $mins = round(($hours - $hours_int) * 60);
        return $hours_int . "h " . ($mins > 0 ? $mins . "m" : "");
    }
}

// Standalone endpoint for testing
if (isset($_GET['test']) && isset($_GET['address'])) {
    header('Content-Type: application/json');
    
    $address = $_GET['address'];
    $lat = $_GET['lat'] ?? null;
    $lng = $_GET['lng'] ?? null;
    
    $result = calculateDistanceMultiSource($address, $lat, $lng);
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}
?>