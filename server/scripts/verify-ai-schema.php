<?php
require_once __DIR__ . '/../utils/ai-run-guard.php';

function validate_ai_payload(array $payload, string $label): array {
    $issues = [];
    if (empty($payload['model']) || !is_string($payload['model'])) {
        $issues[] = "$label: missing model identifier";
    }

    if (!isset($payload['services']) || !is_array($payload['services'])) {
        $issues[] = "$label: services array missing";
    } else {
        foreach ($payload['services'] as $idx => $service) {
            if (!is_array($service)) {
                $issues[] = "$label: service #$idx is not an object";
                continue;
            }
            if (empty($service['name']) || !is_string($service['name'])) {
                $issues[] = "$label: service #$idx missing name";
            }
            $priceFields = ['adjusted_price', 'line_total_cad', 'price_cad', 'cost', 'price'];
            $hasPrice = false;
            foreach ($priceFields as $field) {
                if (isset($service[$field]) && is_numeric($service[$field])) {
                    $hasPrice = true;
                    break;
                }
            }
            if (!$hasPrice) {
                $issues[] = "$label: service #$idx missing numeric price";
            }
            if (empty($service['customer_text']) && empty($service['description']) && empty($service['admin_notes'])) {
                $issues[] = "$label: service #$idx missing customer/admin text";
            }
        }
    }

    if (!isset($payload['frames']) || !is_array($payload['frames'])) {
        $issues[] = "$label: frames array missing";
    }

    if (isset($payload['global_line_items'])) {
        if (!is_array($payload['global_line_items'])) {
            $issues[] = "$label: global_line_items should be an array";
        } else {
            foreach ($payload['global_line_items'] as $idx => $item) {
                if (!is_array($item)) {
                    $issues[] = "$label: global_line_item #$idx is not an object";
                    continue;
                }
                if (empty($item['name']) || !is_string($item['name'])) {
                    $issues[] = "$label: global_line_item #$idx missing name";
                }
                $price = $item['line_total_cad'] ?? $item['price_cad'] ?? $item['amount'] ?? null;
                if (!is_null($price) && !is_numeric($price)) {
                    $issues[] = "$label: global_line_item #$idx price must be numeric";
                }
                if (!empty($item['distance_km']) && !is_numeric($item['distance_km'])) {
                    $issues[] = "$label: global_line_item #$idx distance_km must be numeric";
                }
            }
        }
    }

    return $issues;
}

$errors = [];
$fixturesDir = dirname(__DIR__) . '/fixtures/ai';
if (is_dir($fixturesDir)) {
    foreach (glob($fixturesDir . '/*/mock.json') as $fixture) {
        $data = json_decode(file_get_contents($fixture), true);
        if (!is_array($data)) {
            $errors[] = basename($fixture) . ': unable to parse JSON';
            continue;
        }
        $label = 'fixture ' . basename(dirname($fixture)) . '/' . basename($fixture);
        $errors = array_merge($errors, validate_ai_payload($data, $label));
    }
} else {
    $errors[] = 'fixtures directory missing';
}

if (empty($errors)) {
    echo "✅ AI schema verification passed (fixtures)\n";
    exit(0);
}

echo "❌ AI schema verification found issues:\n";
foreach ($errors as $err) {
    echo " - $err\n";
}
exit(1);
