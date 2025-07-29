<?php
// Enhanced Location Extraction and Distance Calculation
require_once __DIR__ . '/../config/database-simple.php';

class LocationExtractor {
    
    public static function extractEXIFLocation($image_path, $media_id, $quote_id) {
        global $pdo;
        
        if (!file_exists($image_path) || !function_exists('exif_read_data')) {
            return false;
        }
        
        try {
            $exif = exif_read_data($image_path, 0, true);
            
            if (!isset($exif['GPS'])) {
                error_log("No GPS data found in EXIF for: $image_path");
                return false;
            }
            
            $gps = $exif['GPS'];
            
            // Extract GPS coordinates
            $latitude = self::convertGPSCoordinate($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
            $longitude = self::convertGPSCoordinate($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
            $altitude = isset($gps['GPSAltitude']) ? self::convertGPSAltitude($gps['GPSAltitude']) : null;
            
            // Extract timestamp
            $timestamp = null;
            if (isset($exif['EXIF']['DateTimeOriginal'])) {
                $timestamp = date('Y-m-d H:i:s', strtotime($exif['EXIF']['DateTimeOriginal']));
            }
            
            // Extract camera info
            $camera_make = $exif['IFD0']['Make'] ?? null;
            $camera_model = $exif['IFD0']['Model'] ?? null;
            
            // Store EXIF location data
            $stmt = $pdo->prepare("
                INSERT INTO media_locations 
                (media_id, quote_id, exif_latitude, exif_longitude, exif_altitude, exif_timestamp, camera_make, camera_model) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $media_id, $quote_id, $latitude, $longitude, $altitude, $timestamp, $camera_make, $camera_model
            ]);
            
            error_log("Extracted EXIF location: $latitude, $longitude for media $media_id");
            return ['latitude' => $latitude, 'longitude' => $longitude, 'altitude' => $altitude];
            
        } catch (Exception $e) {
            error_log("EXIF extraction error: " . $e->getMessage());
            return false;
        }
    }
    
    private static function convertGPSCoordinate($coordinate, $hemisphere) {
        if (!is_array($coordinate) || count($coordinate) < 3) {
            return null;
        }
        
        $degrees = self::convertFraction($coordinate[0]);
        $minutes = self::convertFraction($coordinate[1]);
        $seconds = self::convertFraction($coordinate[2]);
        
        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        
        if ($hemisphere === 'S' || $hemisphere === 'W') {
            $decimal = -$decimal;
        }
        
        return $decimal;
    }
    
    private static function convertFraction($fraction) {
        if (strpos($fraction, '/') !== false) {
            $parts = explode('/', $fraction);
            return $parts[1] > 0 ? $parts[0] / $parts[1] : 0;
        }
        return floatval($fraction);
    }
    
    private static function convertGPSAltitude($altitude) {
        return self::convertFraction($altitude);
    }
    
    public static function calculateMultiSourceDistances($quote_id) {
        global $pdo;
        
        // Base location: 4530 Blewitt Rd., Nelson BC
        $base_lat = 49.4928;
        $base_lng = -117.2948;
        
        // Get quote and customer data
        $stmt = $pdo->prepare("
            SELECT q.*, c.address, c.geo_latitude, c.geo_longitude, c.ip_address 
            FROM quotes q 
            JOIN customers c ON q.customer_id = c.id 
            WHERE q.id = ?
        ");
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quote) {
            return false;
        }
        
        $distances = [];
        
        // 1. Address-based distance (using AI)
        if (!empty($quote['address'])) {
            try {
                require_once __DIR__ . '/../api/ai-distance-calculator.php';
                $address_distance = calculateDistanceWithAI($quote['address'], 3);
                
                self::storeDistanceCalculation($quote_id, 'address', null, null, $address_distance, 'ai_calculation', 'medium');
                $distances['address'] = $address_distance;
                
            } catch (Exception $e) {
                error_log("Address distance calculation failed: " . $e->getMessage());
            }
        }
        
        // 2. Geolocation-based distance  
        if ($quote['geo_latitude'] && $quote['geo_longitude']) {
            $geo_distance = self::haversineDistance($base_lat, $base_lng, $quote['geo_latitude'], $quote['geo_longitude']);
            
            self::storeDistanceCalculation($quote_id, 'geolocation', $quote['geo_latitude'], $quote['geo_longitude'], $geo_distance, 'haversine', 'high');
            $distances['geolocation'] = $geo_distance;
        }
        
        // 3. EXIF-based distance
        $stmt = $pdo->prepare("SELECT * FROM media_locations WHERE quote_id = ? AND exif_latitude IS NOT NULL");
        $stmt->execute([$quote_id]);
        $exif_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($exif_locations as $location) {
            $exif_distance = self::haversineDistance($base_lat, $base_lng, $location['exif_latitude'], $location['exif_longitude']);
            
            self::storeDistanceCalculation($quote_id, 'exif', $location['exif_latitude'], $location['exif_longitude'], $exif_distance, 'haversine', 'high');
            $distances['exif'][] = $exif_distance;
        }
        
        // 4. IP-based location (rough estimate)
        if (!empty($quote['ip_address']) && $quote['ip_address'] !== 'Unknown') {
            try {
                $ip_location = self::getIPLocation($quote['ip_address']);
                if ($ip_location) {
                    $ip_distance = self::haversineDistance($base_lat, $base_lng, $ip_location['lat'], $ip_location['lng']);
                    
                    self::storeDistanceCalculation($quote_id, 'ip_lookup', $ip_location['lat'], $ip_location['lng'], $ip_distance, 'ip_geolocation', 'low');
                    $distances['ip'] = $ip_distance;
                }
            } catch (Exception $e) {
                error_log("IP location lookup failed: " . $e->getMessage());
            }
        }
        
        return $distances;
    }
    
    private static function storeDistanceCalculation($quote_id, $source_type, $latitude, $longitude, $distance_km, $method, $accuracy) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            INSERT INTO distance_calculations 
            (quote_id, source_type, latitude, longitude, distance_km, calculation_method, accuracy_rating) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$quote_id, $source_type, $latitude, $longitude, $distance_km, $method, $accuracy]);
    }
    
    private static function haversineDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return round($earthRadius * $c, 2);
    }
    
    private static function getIPLocation($ip) {
        // Free IP geolocation service (you may want to use a paid service for better accuracy)
        $url = "http://ip-api.com/json/$ip";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'CarpeTreeQuoteSystem/1.0'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        if (!$response) {
            return false;
        }
        
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'success') {
            return [
                'lat' => $data['lat'],
                'lng' => $data['lon'],
                'city' => $data['city'],
                'region' => $data['regionName'],
                'country' => $data['country']
            ];
        }
        
        return false;
    }
    
    public static function getBestDistanceEstimate($quote_id) {
        global $pdo;
        
        // Get all distance calculations for this quote, ordered by accuracy
        $stmt = $pdo->prepare("
            SELECT * FROM distance_calculations 
            WHERE quote_id = ? 
            ORDER BY 
                CASE accuracy_rating 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                calculated_at DESC
        ");
        $stmt->execute([$quote_id]);
        $calculations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($calculations)) {
            return 40; // Default fallback
        }
        
        // Return the most accurate distance available
        return $calculations[0]['distance_km'];
    }
}
?> 