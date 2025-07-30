<?php
// Basic file handler utility

function validateFileType($mimeType) {
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic',
        'video/mp4', 'video/quicktime', 'video/x-msvideo',
        'audio/mpeg', 'audio/mp4', 'audio/wav',
        'application/octet-stream' // Allow generic binary type (browser fallback)
    ];
    
    return in_array($mimeType, $allowed_types);
}

function getFileTypeCategory($mimeType, $filename = '') {
    if (strpos($mimeType, 'image/') === 0) {
        return 'image';
    } elseif (strpos($mimeType, 'video/') === 0) {
        return 'video';
    } elseif (strpos($mimeType, 'audio/') === 0) {
        return 'audio';
    } elseif ($mimeType === 'application/octet-stream' && $filename) {
        // For generic binary type, check file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'heic'])) {
            return 'image';
        } elseif (in_array($extension, ['mp4', 'mov', 'avi'])) {
            return 'video';
        } elseif (in_array($extension, ['mp3', 'm4a', 'wav'])) {
            return 'audio';
        }
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
    
    // Validate file size (1 GB max)
    $max_size = 1024 * 1024 * 1024; // 1 GB
    if ($file['size'] > $max_size) {
        throw new Exception('File too large. Maximum size is 1GB.');
    }
    
    // Generate unique filename
    $unique_filename = generateUniqueFilename($file['name']);
    $file_path = $upload_dir . '/' . $unique_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Determine file type category
    $file_type = getFileTypeCategory($file['type'], $file['name']);
    if ($file_type === 'unknown') {
        $file_type = 'other';
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