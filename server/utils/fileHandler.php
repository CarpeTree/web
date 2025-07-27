<?php
// Basic file handler utility

function validateFileType($mimeType) {
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic',
        'video/mp4', 'video/quicktime', 'video/x-msvideo',
        'audio/mpeg', 'audio/mp4', 'audio/wav'
    ];
    
    return in_array($mimeType, $allowed_types);
}

function getFileTypeCategory($mimeType) {
    if (strpos($mimeType, 'image/') === 0) {
        return 'image';
    } elseif (strpos($mimeType, 'video/') === 0) {
        return 'video';
    } elseif (strpos($mimeType, 'audio/') === 0) {
        return 'audio';
    }
    return 'unknown';
}

function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '.' . $extension;
}
?> 