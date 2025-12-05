<?php
header('Content-Type: application/json');

// Load database config first
$db_config = __DIR__ . '/../config/db-config.php';
if (file_exists($db_config)) {
    require_once $db_config;
}

require_once __DIR__ . '/../config/database-simple.php';

// Load other configs if they exist
$config_file = __DIR__ . '/../config/config.php';
if (file_exists($config_file)) {
    require_once $config_file;
}

$secure_config = __DIR__ . '/../config/secure-config.php';
if (file_exists($secure_config)) {
    require_once $secure_config;
}

// File handler
$file_handler = __DIR__ . '/../utils/fileHandler.php';
if (file_exists($file_handler)) {
    require_once $file_handler;
}

function logErr($msg, $data = []) {
  error_log("FieldCapture: $msg " . json_encode($data));
}

try {
  // Field capture can work with minimal data
  $email = $_POST['email'] ?? 'field@carpetree.com';
  
  // Start transaction
  $pdo->beginTransaction();
  
  // Find or create customer
  $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
  $stmt->execute([$email]);
  $customer = $stmt->fetch();
  
  if (!$customer) {
    $stmt = $pdo->prepare("INSERT INTO customers (email, name, phone, address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$email, $_POST['name'] ?? null, $_POST['phone'] ?? null, $_POST['address'] ?? null]);
    $customer_id = $pdo->lastInsertId();
  } else {
    $customer_id = $customer['id'];
  }
  
  // Extract and save GPS location
  $address = null; $postal = null; $lat = null; $lng = null; $acc = null;
  if (isset($_POST['gpsLat']) && isset($_POST['gpsLng'])) {
    $lat = (float)$_POST['gpsLat'];
    $lng = (float)$_POST['gpsLng'];
    $acc = isset($_POST['gpsAccuracy']) ? (float)$_POST['gpsAccuracy'] : null;
    
    // Reverse geocode with Google if key configured
    global $GOOGLE_MAPS_API_KEY;
    if (!empty($GOOGLE_MAPS_API_KEY)) {
      $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(['latlng'=>"{$lat},{$lng}",'key'=>$GOOGLE_MAPS_API_KEY]);
      $resp = @file_get_contents($url);
      if ($resp) {
        $data = json_decode($resp, true);
        if (!empty($data['results'][0])) {
          $address = $data['results'][0]['formatted_address'] ?? null;
          foreach (($data['results'][0]['address_components']??[]) as $comp) {
            if (in_array('postal_code', $comp['types']??[])) { $postal = $comp['long_name']; break; }
          }
        }
      }
    }
    
    // Save GPS on customer as best-effort
    try {
      $pdo->prepare("UPDATE customers SET geo_latitude=?, geo_longitude=?, geo_accuracy=? WHERE id=?")->execute([$lat,$lng,$acc,$customer_id]);
    } catch (Throwable $t) { logErr('geo save failed',['e'=>$t->getMessage()]); }
  }
  
  // Create quote (placeholder status)
  $notes = $_POST['notes'] ?? '';
  $stmt = $pdo->prepare("INSERT INTO quotes (customer_id, notes, quote_status, created_at) VALUES (?, ?, 'submitted', NOW())");
  $stmt->execute([$customer_id, $notes]);
  $quote_id = (int)$pdo->lastInsertId();
  
  // Attach address to customer if resolved
  if ($address) {
    $pdo->prepare("UPDATE customers SET address=? WHERE id=?")->execute([$address, $customer_id]);
  }
  
  // Save media under /server/uploads/quote_{id}/
  $file_count = 0;
  if (!empty($_FILES) && isset($_FILES['files'])) {
    $upload_dir = __DIR__ . '/../uploads/quote_' . $quote_id;
    if (!is_dir($upload_dir)) {
      mkdir($upload_dir, 0755, true);
    }
    
    $files = $_FILES['files'];
    $count = is_array($files['name']) ? count($files['name']) : 1;
    
    for ($i = 0; $i < $count; $i++) {
      if (is_array($files['error'])) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $name = $files['name'][$i];
        $tmp = $files['tmp_name'][$i];
      } else {
        if ($files['error'] !== UPLOAD_ERR_OK) continue;
        $name = $files['name'];
        $tmp = $files['tmp_name'];
      }
      
      $safe_name = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $name);
      $safe_name = time() . '_' . $safe_name;
      $target = $upload_dir . '/' . $safe_name;
      
      if (move_uploaded_file($tmp, $target)) {
        $file_count++;
      }
    }
  }
  
  // Save additional field data
  $field_data = [
    'priority' => $_POST['priority'] ?? null,
    'estimatedCost' => $_POST['estimatedCost'] ?? null,
    'selectedServices' => $_POST['selectedServices'] ?? '[]',
    'fieldCapture' => true,
    'gps' => $lat && $lng ? ['lat' => $lat, 'lng' => $lng, 'accuracy' => $acc] : null,
    'address' => $address,
    'postal' => $postal,
    'files' => $file_count
  ];
  
  // Store field data as JSON in notes or a new column
  $combined_notes = $notes;
  if (!empty($field_data)) {
    $combined_notes .= "\n\n[Field Data]: " . json_encode($field_data);
  }
  
  $pdo->prepare("UPDATE quotes SET notes=?, selected_services=? WHERE id=?")->execute([$combined_notes, $_POST['selectedServices'] ?? '[]', $quote_id]);
  
  $pdo->commit();
  
  echo json_encode([
    'success' => true,
    'quote_id' => $quote_id,
    'customer_id' => $customer_id,
    'location' => $lat && $lng ? ['lat' => $lat, 'lng' => $lng] : null,
    'address' => $address,
    'files_uploaded' => $file_count
  ]);
  
} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  
  logErr('Field capture error', ['error' => $e->getMessage()]);
  
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}