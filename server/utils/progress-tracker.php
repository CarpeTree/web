<?php

function getProgressDirectory() {
    $dir = __DIR__ . '/../logs/progress';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function initializeProgressId($providedId = null) {
    if (is_string($providedId) && $providedId !== '') {
        return $providedId;
    }
    try {
        $random = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $random = substr(str_shuffle('abcdef0123456789'), 0, 8);
    }
    return 'p_' . time() . '_' . $random;
}

function writeProgressUpdate($progressId, $stage, $percent, $extra = []) {
    $dir = getProgressDirectory();
    $path = $dir . '/' . basename($progressId) . '.json';
    $payload = [
        'progress_id' => $progressId,
        'stage' => (string)$stage,
        'percent' => max(0, min(100, (int)$percent)),
        'time' => time(),
        'extra' => is_array($extra) ? $extra : [],
    ];
    @file_put_contents($path, json_encode($payload), LOCK_EX);
    @file_put_contents($dir . '/latest.json', json_encode($payload), LOCK_EX);
}

function readProgress($progressId) {
    $path = getProgressDirectory() . '/' . basename($progressId) . '.json';
    if (!is_file($path)) {
        return null;
    }
    $json = @file_get_contents($path);
    if ($json === false) {
        return null;
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

?>


