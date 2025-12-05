<?php
// Media storage configuration helper

function media_get_config(): array {
    $mode = getenv('MEDIA_MODE') ?: 'local'; // local | remote
    $localBase = getenv('MEDIA_UPLOAD_LOCAL_BASE');
    if (!$localBase) {
        // default to server/uploads under project root
        $localBase = dirname(__DIR__, 2) . '/uploads';
    }
    return [
        'mode' => $mode,
        'local_base' => rtrim($localBase, '/'),
        'remote_base_url' => rtrim(getenv('MEDIA_REMOTE_BASE_URL') ?: '', '/'),
        'remote_upload_url' => getenv('MEDIA_REMOTE_UPLOAD_URL') ?: '',
        'remote_auth_token' => getenv('MEDIA_REMOTE_AUTH_TOKEN') ?: '',
        'delete_local_after_upload' => (getenv('MEDIA_DELETE_LOCAL_AFTER_UPLOAD') ?: 'true') === 'true',
        'connect_timeout' => (int)(getenv('MEDIA_REMOTE_CONNECT_TIMEOUT_SECS') ?: '10'),
        'timeout' => (int)(getenv('MEDIA_REMOTE_TIMEOUT_SECS') ?: '120'),
    ];
}

function media_remote_enabled(): bool {
    $cfg = media_get_config();
    return $cfg['mode'] === 'remote' && !empty($cfg['remote_upload_url']) && !empty($cfg['remote_base_url']);
}

?>









