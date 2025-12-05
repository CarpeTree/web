<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

try {
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : (isset($_GET['lat']) ? (float)$_GET['lat'] : null);
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : (isset($_GET['lng']) ? (float)$_GET['lng'] : null);
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    $name = $_POST['name'] ?? $_GET['name'] ?? null;
    $token = $_POST['token'] ?? $_GET['token'] ?? '';

    // Optional simple token gate if configured
    global $ADMIN_TOKEN;
    if (!empty($ADMIN_TOKEN) && $token !== $ADMIN_TOKEN) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    if ($lat === null || $lng === null) {
        throw new Exception('lat and lng are required');
    }

    $file = dirname(__DIR__) . '/data/base-camps.json';
    $data = [ 'base_camps' => [], 'current' => [ 'id' => $id ?: 'custom', 'latitude' => $lat, 'longitude' => $lng ] ];
    if (file_exists($file)) {
        $decoded = json_decode(file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    // Update current
    $data['current'] = [ 'id' => $id ?: 'custom', 'latitude' => $lat, 'longitude' => $lng ];

    // If an id/name provided and not present in base_camps, add it
    if ($id && $name) {
        $exists = false;
        if (!isset($data['base_camps']) || !is_array($data['base_camps'])) {
            $data['base_camps'] = [];
        }
        foreach ($data['base_camps'] as $bc) {
            if (!empty($bc['id']) && $bc['id'] === $id) { $exists = true; break; }
        }
        if (!$exists) {
            $data['base_camps'][] = [ 'id' => $id, 'name' => $name, 'latitude' => $lat, 'longitude' => $lng, 'region' => '', 'default' => false ];
        }
    }

    // Write file atomically
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT));
    rename($tmp, $file);

    echo json_encode(['success' => true, 'current' => $data['current']]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>


