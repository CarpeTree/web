<?php
// Google Places and Geocoding helpers
require_once __DIR__ . '/../config/config.php';

/**
 * Perform a Places Nearby search with one or more keywords
 * @param float $lat
 * @param float $lng
 * @param array $keywords e.g. ['yard waste disposal','transfer station','green waste']
 * @param string|null $type optional Google Places type (e.g., 'point_of_interest')
 * @param int $radius meters
 * @return array normalized results: [ [name, address, lat, lng, place_id] ]
 */
function placesNearbySearch(float $lat, float $lng, array $keywords, ?string $type = null, int $radius = 30000): array {
    global $GOOGLE_MAPS_API_KEY;
    if (empty($GOOGLE_MAPS_API_KEY)) {
        return [];
    }

    $results = [];
    foreach ($keywords as $kw) {
        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?' . http_build_query([
            'location' => $lat . ',' . $lng,
            'radius' => $radius,
            'keyword' => $kw,
            'type' => $type ?: 'point_of_interest',
            'key' => $GOOGLE_MAPS_API_KEY,
        ]);
        $resp = httpGetJson($url);
        if (!empty($resp['results'])) {
            foreach ($resp['results'] as $r) {
                $results[] = [
                    'name' => $r['name'] ?? 'Unknown',
                    'address' => $r['vicinity'] ?? ($r['formatted_address'] ?? ''),
                    'lat' => $r['geometry']['location']['lat'] ?? null,
                    'lng' => $r['geometry']['location']['lng'] ?? null,
                    'place_id' => $r['place_id'] ?? null,
                ];
            }
        }
        // If we already have some results, stop trying more keywords
        if (!empty($results)) break;
    }
    return dedupePlaces($results);
}

/**
 * Reverse geocode to get postal code and formatted address
 */
function reverseGeocodePostalCode(float $lat, float $lng): array {
    global $GOOGLE_MAPS_API_KEY;
    if (empty($GOOGLE_MAPS_API_KEY)) return [];
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'latlng' => $lat . ',' . $lng,
        'result_type' => 'postal_code',
        'key' => $GOOGLE_MAPS_API_KEY,
    ]);
    $resp = httpGetJson($url);
    if (empty($resp['results'][0])) return [];
    $res = $resp['results'][0];
    $postal = '';
    foreach ($res['address_components'] as $comp) {
        if (in_array('postal_code', $comp['types'])) {
            $postal = $comp['long_name'];
            break;
        }
    }
    return [
        'postal_code' => $postal,
        'formatted_address' => $res['formatted_address'] ?? ''
    ];
}

/** Helper: simple HTTP GET JSON */
function httpGetJson(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!$body) return [];
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

/** Deduplicate places by place_id/name */
function dedupePlaces(array $places): array {
    $seen = [];
    $out = [];
    foreach ($places as $p) {
        $k = $p['place_id'] ?: (strtolower($p['name']) . '|' . strtolower($p['address']));
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $p;
    }
    return $out;
}

/** Compute perpendicular distance from point C to segment AB (in meters, approximate) */
function distanceToSegment(float $ax, float $ay, float $bx, float $by, float $cx, float $cy): float {
    // Convert to radians and project roughly; for short distances we can treat as planar
    $A = ['x' => $ax, 'y' => $ay];
    $B = ['x' => $bx, 'y' => $by];
    $C = ['x' => $cx, 'y' => $cy];
    $ABx = $B['x'] - $A['x'];
    $ABy = $B['y'] - $A['y'];
    $AB2 = $ABx*$ABx + $ABy*$ABy;
    if ($AB2 == 0) return haversine($ax, $ay, $cx, $cy);
    $t = (($C['x'] - $A['x'])*$ABx + ($C['y'] - $A['y'])*$ABy) / $AB2;
    $t = max(0.0, min(1.0, $t));
    $Dx = $A['x'] + $t*$ABx;
    $Dy = $A['y'] + $t*$ABy;
    return haversine($cx, $cy, $Dx, $Dy);
}

/** Haversine meters */
function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)*sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

?>


