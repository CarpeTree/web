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

function processUploadedFile($file, $quote_id, $upload_dir, $pdo) {
    // Validate file type
    if (!validateFileType($file['type'])) {
        throw new Exception('File type not allowed: ' . $file['type']);
    }
    
    // Validate file size (50MB max)
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size is 50MB.');
    }
    
    // Generate unique filename
    $unique_filename = generateUniqueFilename($file['name']);
    $file_path = $upload_dir . '/' . $unique_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Insert into media table
    $stmt = $pdo->prepare("
        INSERT INTO media (
            quote_id, filename, original_filename, file_type, 
            mime_type, file_size, upload_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $file_type = getFileTypeCategory($file['type']);
    
    $stmt->execute([
        $quote_id,
        $unique_filename,
        $file['name'],
        $file_type,
        $file['type'],
        $file['size'],
        $file_path
    ]);
    
    return [
        'filename' => $unique_filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'type' => $file_type
    ];
}
?> 