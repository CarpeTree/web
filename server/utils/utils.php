<?php
// Helper functions

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . 'MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . 'KB';
    }
    return $bytes . 'B';
}

function extractVideoFrames($videoPath, $secondsInterval = 5, $maxFrames = 6) {
    $frames = [];
    $ffmpeg_paths = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/opt/homebrew/bin/ffmpeg',
        'ffmpeg'
    ];
    
    $ffmpeg_path = null;
    foreach ($ffmpeg_paths as $path) {
        if (shell_exec("which $path 2>/dev/null")) {
            $ffmpeg_path = $path;
            break;
        }
    }
    
    if (!$ffmpeg_path) {
        return $frames;
    }
    
    $tmpDir = sys_get_temp_dir() . '/frames_' . uniqid();
    mkdir($tmpDir);
    
    $cmd = sprintf('%s -hide_banner -loglevel error -i %s -vf fps=1/%d -frames:v %d %s/frame_%%03d.jpg',
        escapeshellarg($ffmpeg_path),
        escapeshellarg($videoPath),
        (int)$secondsInterval,
        (int)$maxFrames,
        escapeshellarg($tmpDir)
    );
    
    shell_exec($cmd);
    $files = glob($tmpDir . '/frame_*.jpg');
    
    foreach ($files as $frameFile) {
        $imageData = base64_encode(file_get_contents($frameFile));
        $frames[] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/jpeg;base64,' . $imageData,
                'detail' => 'high'
            ]
        ];
        unlink($frameFile);
    }
    rmdir($tmpDir);
    return $frames;
}
