<?php
// Secure proxy for Google Maps API
// This keeps your API key server-side only

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://carpetree.com');
header('Access-Control-Allow-Methods: GET, POST');

// Load API key from environment or config
$api_key = getenv('GOOGLE_MAPS_API_KEY');
if (!$api_key && file_exists(__DIR__ . '/../config/api-keys.php')) {
    require_once __DIR__ . '/../config/api-keys.php';
    $api_key = GOOGLE_MAPS_API_KEY ?? null;
}

if (!$api_key) {
    http_response_code(500);
    echo json_encode(['error' => 'Maps API not configured']);
    exit;
}

// Get the requested action
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'geocode':
        // Reverse geocoding
        $lat = $_GET['lat'] ?? '';
        $lng = $_GET['lng'] ?? '';
        
        if (!$lat || !$lng) {
            echo json_encode(['error' => 'Missing coordinates']);
            exit;
        }
        
        $url = "https://maps.googleapis.com/maps/api/geocode/json?" . http_build_query([
            'latlng' => "$lat,$lng",
            'key' => $api_key
        ]);
        
        $response = file_get_contents($url);
        echo $response;
        break;
        
    case 'places':
        // Places autocomplete
        $input = $_GET['input'] ?? '';
        
        if (!$input) {
            echo json_encode(['error' => 'Missing input']);
            exit;
        }
        
        $url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?" . http_build_query([
            'input' => $input,
            'key' => $api_key,
            'components' => 'country:ca'
        ]);
        
        $response = file_get_contents($url);
        echo $response;
        break;
        
    case 'distance':
        // Distance matrix
        $origins = $_GET['origins'] ?? '';
        $destinations = $_GET['destinations'] ?? '';
        
        if (!$origins || !$destinations) {
            echo json_encode(['error' => 'Missing origins or destinations']);
            exit;
        }
        
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?" . http_build_query([
            'origins' => $origins,
            'destinations' => $destinations,
            'key' => $api_key,
            'units' => 'metric'
        ]);
        
        $response = file_get_contents($url);
        echo $response;
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}











