<?php
require_once __DIR__ . '/../config/media.php';

/**
 * Ensures a quote directory exists and returns its absolute path (local).
 */
function media_ensure_quote_dir(int $quote_id): string {
    $cfg = media_get_config();
    $dir = $cfg['local_base'] . '/quote_' . $quote_id;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Upload a local file to remote Hostinger (or any remote HTTP uploader),
 * returning the remote URL string on success.
 */
function media_upload_remote(string $localPath, int $quote_id, string $destName): ?string {
    if (!media_remote_enabled()) return null;
    $cfg = media_get_config();
    $uploadUrl = $cfg['remote_upload_url'];
    if (!is_file($localPath)) return null;

    $c = curl_init();
    $fields = [
        'quote_id' => (string)$quote_id,
        'file' => new CURLFile($localPath, mime_content_type($localPath) ?: 'application/octet-stream', $destName),
        'filename' => $destName
    ];
    curl_setopt_array($c, [
        CURLOPT_URL => $uploadUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $cfg['connect_timeout'],
        CURLOPT_TIMEOUT => $cfg['timeout'],
        CURLOPT_HTTPHEADER => array_filter([
            $cfg['remote_auth_token'] ? 'Authorization: Bearer ' . $cfg['remote_auth_token'] : null
        ])
    ]);
    $resp = curl_exec($c);
    $err = curl_error($c);
    $status = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    curl_close($c);
    if ($err) {
        error_log('media_upload_remote curl error: ' . $err);
        return null;
    }
    if ($status < 200 || $status >= 300) {
        error_log('media_upload_remote http status: ' . $status . ' resp: ' . substr((string)$resp, 0, 300));
        return null;
    }
    $json = json_decode((string)$resp, true);
    if (is_array($json) && !empty($json['url'])) {
        return (string)$json['url'];
    }
    // Fallback: construct by convention
    $base = $cfg['remote_base_url'];
    if ($base) {
        return $base . '/quote_' . $quote_id . '/' . rawurlencode($destName);
    }
    return null;
}

/**
 * Returns a public URL for a stored media file given quote_id and filename.
 * If remote mode is enabled, returns remote URL; else returns local path guess.
 */
function media_public_url(int $quote_id, string $filename): string {
    $cfg = media_get_config();
    if (media_remote_enabled()) {
        return $cfg['remote_base_url'] . '/quote_' . $quote_id . '/' . rawurlencode($filename);
    }
    // Local: try to build a relative URL (assuming /server/uploads served)
    return '/server/uploads/quote_' . $quote_id . '/' . rawurlencode($filename);
}

?>









