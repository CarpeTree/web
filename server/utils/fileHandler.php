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
    
    // Validate file size (100MB max for videos)
    $max_size = 100 * 1024 * 1024; // 100MB for videos
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size is 100MB.');
    }
    
    // Generate unique filename
    $unique_filename = generateUniqueFilename($file['name']);
    $file_path = $upload_dir . '/' . $unique_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Determine file type category
    $file_type = 'other';
    if (strpos($file['type'], 'image/') === 0) {
        $file_type = 'image';
    } elseif (strpos($file['type'], 'video/') === 0) {
        $file_type = 'video';
    } elseif (strpos($file['type'], 'audio/') === 0) {
        $file_type = 'audio';
    }
    
    // Extract EXIF data for images
    $exif_data = null;
    if ($file_type === 'image' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file_path);
        if ($exif) {
            $exif_data = json_encode($exif);
            
            // Extract GPS coordinates if available and store in location extractor
            if (isset($exif['GPS'])) {
                try {
                    require_once __DIR__ . '/location-extractor.php';
                    LocationExtractor::extractEXIFLocation($file_path, null, $quote_id);
                } catch (Exception $e) {
                    error_log("EXIF GPS extraction failed: " . $e->getMessage());
                }
            }
        }
    }
    
    // Insert into media table (correct table)
    $stmt = $pdo->prepare("
        INSERT INTO media (
            quote_id, filename, original_filename, file_path,
            file_size, mime_type, file_type, exif_data, uploaded_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $quote_id,
        $unique_filename,
        $file['name'],
        $file_path,
        $file['size'],
        $file['type'],
        $file_type,
        $exif_data
    ]);
    
    $media_id = $pdo->lastInsertId();
    
    // For videos, try to extract location metadata if possible
    if ($file_type === 'video') {
        // Could add video metadata extraction here in the future
        error_log("Video uploaded: $unique_filename for quote $quote_id");
    }
    
    return [
        'media_id' => $media_id,
        'filename' => $unique_filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'type' => $file['type'],
        'file_type' => $file_type
    ];
} 