<?php
// Enhanced Google Maps Distance Calculator with Waste Disposal Integration
require_once __DIR__ . "/../config/config.php";

class EnhancedDistanceCalculator {
    private $api_key;
    private $base_location = "4530 Blewitt Rd, Nelson, BC V1L 6X1, Canada";
    private $waste_locations;
    
    public function __construct() {
        global $GOOGLE_MAPS_API_KEY;
        $this->api_key = $GOOGLE_MAPS_API_KEY;
        
        // Load waste disposal locations
        $waste_data = json_decode(file_get_contents(__DIR__ . "/../data/waste-disposal-locations.json"), true);
        $this->waste_locations = $waste_data["transfer_stations"];
    }
    
    /**
     * Calculate distance from base to customer location
     */
    public function calculateDistanceToCustomer($customer_address, $customer_lat = null, $customer_lng = null) {
        if (empty($this->api_key)) {
            error_log("Google Maps API key not configured - using fallback");
            return $this->fallbackDistanceCalculation($customer_address);
        }
        
        $destination = $customer_lat && $customer_lng ? 
            "$customer_lat,$customer_lng" : 
            "$customer_address, BC, Canada";
        
        return $this->callDistanceMatrixAPI($this->base_location, $destination);
    }
    
    /**
     * Find nearest waste disposal location
     */
    public function findNearestWasteDisposal($customer_address, $customer_lat = null, $customer_lng = null) {
        if (empty($this->api_key)) {
            return $this->fallbackNearestWasteStation($customer_address);
        }
        
        $customer_location = $customer_lat && $customer_lng ? 
            "$customer_lat,$customer_lng" : 
            "$customer_address, BC, Canada";
        
        $nearest_station = null;
        $shortest_distance = PHP_INT_MAX;
        
        foreach ($this->waste_locations as $station) {
            $station_location = "{$station["latitude"]},{$station["longitude"]}";
            $distance_result = $this->callDistanceMatrixAPI($customer_location, $station_location);
            
            if ($distance_result && $distance_result["distance_km"] < $shortest_distance) {
                $shortest_distance = $distance_result["distance_km"];
                $nearest_station = array_merge($station, [
                    "distance_km" => $distance_result["distance_km"],
                    "duration" => $distance_result["duration"]
                ]);
            }
        }
        
        return $nearest_station;
    }
    
    /**
     * Calculate total disposal costs
     */
    public function calculateDisposalCosts($brush_weight_tonnes, $nearest_station) {
        if (!$nearest_station) {
            return [
                "disposal_cost" => 0,
                "transport_cost" => 0,
                "total_cost" => 0
            ];
        }
        
        $disposal_cost = $brush_weight_tonnes * $nearest_station["cost_per_tonne"];
        $transport_cost = $nearest_station["distance_km"] * 2.50; // $2.50/km round trip
        $total_cost = $disposal_cost + $transport_cost;
        
        return [
            "disposal_cost" => round($disposal_cost, 2),
            "transport_cost" => round($transport_cost, 2),
            "total_cost" => round($total_cost, 2),
            "cost_per_tonne" => $nearest_station["cost_per_tonne"],
            "transport_distance" => $nearest_station["distance_km"] * 2 // Round trip
        ];
    }
    
    /**
     * Estimate brush weight from tree dimensions
     */
    public function estimateBrushWeight($tree_height, $crown_spread, $service_type) {
        // Calculate crown volume (simplified ellipsoid)
        $crown_volume = (4/3) * pi() * pow($crown_spread/2, 2) * ($tree_height * 0.6);
        
        // Determine brush percentage based on service
        $brush_percentage = match($service_type) {
            "removal" => 0.4,        // 40% of tree becomes brush
            "heavy_pruning" => 0.25, // 25% of tree becomes brush
            "light_pruning" => 0.1,  // 10% of tree becomes brush
            default => 0.2           // 20% default
        };
        
        // Green brush density: 400-600 kg/m³
        $brush_density = 500; // kg/m³ average
        $brush_volume = $crown_volume * $brush_percentage;
        $brush_weight_kg = $brush_volume * $brush_density;
        $brush_weight_tonnes = $brush_weight_kg / 1000;
        
        return [
            "volume_m3" => round($brush_volume, 2),
            "weight_tonnes" => round($brush_weight_tonnes, 2),
            "truck_loads" => ceil($brush_weight_tonnes / 2), // 2 tonnes per truck load
            "service_type" => $service_type
        ];
    }
    
    /**
     * Get complete quote logistics
     */
    public function getCompleteQuoteLogistics($customer_address, $tree_height, $crown_spread, $service_type, $customer_lat = null, $customer_lng = null) {
        // Calculate distance to customer
        $customer_distance = $this->calculateDistanceToCustomer($customer_address, $customer_lat, $customer_lng);
        
        // Estimate brush weight
        $brush_estimate = $this->estimateBrushWeight($tree_height, $crown_spread, $service_type);
        
        // Find nearest waste disposal
        $nearest_station = $this->findNearestWasteDisposal($customer_address, $customer_lat, $customer_lng);
        
        // Calculate disposal costs
        $disposal_costs = $this->calculateDisposalCosts($brush_estimate["weight_tonnes"], $nearest_station);
        
        // Calculate travel costs
        $travel_cost_to_customer = $customer_distance["distance_km"] * 1.50; // $1.50/km
        
        return [
            "customer_distance" => $customer_distance,
            "brush_estimate" => $brush_estimate,
            "nearest_waste_station" => $nearest_station,
            "disposal_costs" => $disposal_costs,
            "travel_cost_to_customer" => round($travel_cost_to_customer, 2),
            "total_logistics_cost" => round($travel_cost_to_customer + $disposal_costs["total_cost"], 2)
        ];
    }
    
    private function callDistanceMatrixAPI($origin, $destination) {
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?" . http_build_query([
            "origins" => $origin,
            "destinations" => $destination,
            "mode" => "driving",
            "units" => "metric",
            "departure_time" => "now",
            "traffic_model" => "best_guess",
            "key" => $this->api_key
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
        curl_close($curl);
        
        if ($http_code !== 200 || !$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if ($data["status"] !== "OK" || empty($data["rows"][0]["elements"][0]) || $data["rows"][0]["elements"][0]["status"] !== "OK") {
            return null;
        }
        
        $element = $data["rows"][0]["elements"][0];
        
        return [
            "distance_km" => round($element["distance"]["value"] / 1000, 1),
            "duration" => $element["duration"]["text"],
            "duration_with_traffic" => $element["duration_in_traffic"]["text"] ?? $element["duration"]["text"],
            "source" => "google_maps"
        ];
    }
    
    private function fallbackDistanceCalculation($address) {
        // Simple fallback based on city/region
        $distance_estimates = [
            "vancouver" => 350,
            "richmond" => 360,
            "burnaby" => 355,
            "kamloops" => 200,
            "castlegar" => 45,
            "trail" => 50,
            "rossland" => 60,
            "grand forks" => 120,
            "nakusp" => 90,
            "kaslo" => 70
        ];
        
        $address_lower = strtolower($address);
        foreach ($distance_estimates as $city => $distance) {
            if (strpos($address_lower, $city) !== false) {
                return [
                    "distance_km" => $distance,
                    "duration" => $this->estimateDriveTime($distance),
                    "duration_with_traffic" => $this->estimateDriveTime($distance, true),
                    "source" => "fallback_estimate"
                ];
            }
        }
        
        return [
            "distance_km" => 100,
            "duration" => "1h 30m",
            "duration_with_traffic" => "1h 45m", 
            "source" => "default_fallback"
        ];
    }
    
    private function fallbackNearestWasteStation($address) {
        // Return appropriate station based on address
        $address_lower = strtolower($address);
        
        if (strpos($address_lower, "vancouver") !== false || strpos($address_lower, "richmond") !== false) {
            return $this->waste_locations[0]; // Vancouver
        } elseif (strpos($address_lower, "burnaby") !== false) {
            return $this->waste_locations[1]; // Burnaby
        } elseif (strpos($address_lower, "kamloops") !== false) {
            return $this->waste_locations[4]; // Kamloops
        } else {
            return $this->waste_locations[2]; // Castlegar (default for BC Interior)
        }
    }
    
    private function estimateDriveTime($distance_km, $with_traffic = false) {
        $avg_speed = $distance_km <= 50 ? 45 : ($distance_km <= 200 ? 65 : 80);
        $hours = $distance_km / $avg_speed;
        
        if ($with_traffic) {
            $hours *= 1.2; // 20% buffer for traffic
        }
        
        if ($hours < 1) {
            return round($hours * 60) . " mins";
        } else {
            $hours_int = floor($hours);
            $mins = round(($hours - $hours_int) * 60);
            return $hours_int . "h " . ($mins > 0 ? $mins . "m" : "");
        }
    }
}

// Standalone API endpoint for testing
if (isset($_GET["test"]) && isset($_GET["address"])) {
    header("Content-Type: application/json");
    
    $calculator = new EnhancedDistanceCalculator();
    $address = $_GET["address"];
    $height = $_GET["height"] ?? 15;
    $crown = $_GET["crown"] ?? 8;
    $service = $_GET["service"] ?? "pruning";
    
    $result = $calculator->getCompleteQuoteLogistics($address, $height, $crown, $service);
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}
?>