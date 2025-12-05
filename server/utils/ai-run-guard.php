<?php
require_once __DIR__ . '/../config/config.php';

if (!function_exists('ai_mode')) {
    function ai_mode(): string {
        $raw = cfg_env('AI_MODE', 'live');
        $mode = strtolower(trim((string)$raw));
        $allowed = ['live', 'mock', 'replay'];
        if (!in_array($mode, $allowed, true)) {
            $mode = 'live';
        }
        return $mode;
    }
}

if (!function_exists('ai_cache_root')) {
    function ai_cache_root(): string {
        $root = dirname(__DIR__) . '/cache/ai';
        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
        }
        return $root;
    }
}

if (!function_exists('ai_cache_dir')) {
    function ai_cache_dir(string $model): string {
        $dir = ai_cache_root() . '/' . $model;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

if (!function_exists('ai_cache_path')) {
    function ai_cache_path(string $model, string $hash): string {
        return ai_cache_dir($model) . '/' . $hash . '.json';
    }
}

if (!function_exists('ai_cache_fetch')) {
    function ai_cache_fetch(string $model, string $hash): ?array {
        $path = ai_cache_path($model, $hash);
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode(file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('ai_cache_store')) {
    function ai_cache_store(string $model, string $hash, array $payload): void {
        $path = ai_cache_path($model, $hash);
        @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('ai_mock_payload')) {
    function ai_mock_payload(string $model): ?array {
        $fixture = dirname(__DIR__) . '/fixtures/ai/' . $model . '/mock.json';
        if (!is_file($fixture)) {
            return null;
        }
        $decoded = json_decode(file_get_contents($fixture), true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('ai_budget_storage_path')) {
    function ai_budget_storage_path(): string {
        return ai_cache_root() . '/budget.json';
    }
}

if (!function_exists('ai_budget_snapshot')) {
    function ai_budget_snapshot(): array {
        $today = date('Y-m-d');
        $path = ai_budget_storage_path();
        if (!is_file($path)) {
            return ['date' => $today, 'calls' => 0, 'per_model' => []];
        }
        $decoded = json_decode(file_get_contents($path), true);
        if (!is_array($decoded) || ($decoded['date'] ?? '') !== $today) {
            return ['date' => $today, 'calls' => 0, 'per_model' => []];
        }
        $decoded['calls'] = (int)($decoded['calls'] ?? 0);
        $decoded['per_model'] = is_array($decoded['per_model'] ?? null) ? $decoded['per_model'] : [];
        return $decoded;
    }
}

if (!function_exists('ai_budget_check')) {
    function ai_budget_check(string $model): array {
        $cap = (int)cfg_env('AI_DAILY_CALL_CAP', '0');
        if ($cap <= 0) {
            return ['ok' => true];
        }
        $snapshot = ai_budget_snapshot();
        $total = (int)($snapshot['calls'] ?? 0);
        if ($total >= $cap) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'AI daily call cap (%d) reached. Switch to mock/replay or increase AI_DAILY_CALL_CAP.',
                    $cap
                ),
                'snapshot' => $snapshot
            ];
        }
        return ['ok' => true, 'snapshot' => $snapshot];
    }
}

if (!function_exists('ai_budget_register')) {
    function ai_budget_register(string $model): void {
        $cap = (int)cfg_env('AI_DAILY_CALL_CAP', '0');
        if ($cap <= 0) {
            return;
        }
        $path = ai_budget_storage_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $date = date('Y-m-d');
        $fp = fopen($path, 'c+');
        if (!$fp) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            $contents = stream_get_contents($fp);
            $state = json_decode($contents ?: '[]', true);
            if (!is_array($state) || ($state['date'] ?? '') !== $date) {
                $state = ['date' => $date, 'calls' => 0, 'per_model' => []];
            }
            $state['calls'] = (int)($state['calls'] ?? 0) + 1;
            $per = $state['per_model'] ?? [];
            $per[$model] = (int)($per[$model] ?? 0) + 1;
            $state['per_model'] = $per;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}

if (!function_exists('ai_queue_acquire')) {
    function ai_queue_acquire(string $key = 'global', int $timeoutSeconds = 60): array {
        $dir = ai_cache_root() . '/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $path = $dir . '/' . $safeKey . '.lock';
        $fp = fopen($path, 'c');
        if (!$fp) {
            return ['ok' => false, 'message' => 'Unable to open queue lock file.'];
        }

        $start = microtime(true);
        $timeout = max(1, $timeoutSeconds);
        do {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                return ['ok' => true, 'handle' => $fp];
            }
            usleep(100000); // 100ms backoff
        } while ((microtime(true) - $start) < $timeout);

        fclose($fp);
        return ['ok' => false, 'message' => 'AI queue busy. Try again shortly.'];
    }
}

if (!function_exists('ai_queue_release')) {
    function ai_queue_release($handle): void {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
