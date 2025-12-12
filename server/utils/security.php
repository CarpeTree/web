<?php
// Shared security utilities for public endpoints
// - CORS allowlist
// - Optional bearer token guard
// - Simple IP rate limiting

require_once __DIR__ . '/../config/config.php';

function sec_allowed_origins(): array {
    $env = cfg_env('ALLOWED_ORIGINS', '');
    if (!empty($env)) {
        return array_filter(array_map('trim', explode(',', $env)));
    }
    // Sensible defaults: production + localhost for development
    return [
        'https://carpetree.com',
        'https://www.carpetree.com',
        'http://localhost:3000',
        'http://localhost:5173'
    ];
}

function sec_enforce_cors(array $methods = ['POST']): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = sec_allowed_origins();
    if ($origin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } else {
        // Hard fail if origin is present and not allowed
        if ($origin !== '') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'origin_not_allowed']);
            exit();
        }
    }
    header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

function sec_require_public_token(): void {
    $expected = cfg_env('PUBLIC_API_TOKEN', '');
    if ($expected === '') {
        return; // optional: no token required when not configured
    }
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($auth, 'bearer ') === 0) {
        $token = trim(substr($auth, 7));
        if (hash_equals($expected, $token)) {
            return;
        }
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'auth_required']);
    exit();
}

/**
 * Basic IP rate limiting using a file-based counter.
 * @param string $bucket   Logical bucket name (e.g., 'upload', 'submit_quote')
 * @param int    $limit    Max requests in the window
 * @param int    $window   Window seconds
 */
function sec_rate_limit_ip(string $bucket, int $limit, int $window = 600): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = sys_get_temp_dir() . '/ct_security';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $path = $dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $bucket) . '.json';

    $now = time();
    $data = ['samples' => []];

    $fp = fopen($path, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            $contents = stream_get_contents($fp);
            $decoded = json_decode($contents ?: '{}', true);
            if (is_array($decoded) && isset($decoded['samples']) && is_array($decoded['samples'])) {
                $data = $decoded;
            }
            // prune old entries
            $data['samples'] = array_filter(
                $data['samples'],
                fn($entry) => ($entry['ts'] ?? 0) >= ($now - $window)
            );

            // add current request
            $data['samples'][] = ['ip' => $ip, 'ts' => $now];
            $recent = array_filter($data['samples'], fn($entry) => ($entry['ip'] ?? '') === $ip);

            // write back
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);

            if (count($recent) > $limit) {
                http_response_code(429);
                echo json_encode(['success' => false, 'error' => 'rate_limited']);
                exit();
            }
        }
        fclose($fp);
    }
}

