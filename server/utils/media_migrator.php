<?php
/**
 * Media Migrator - Moves processed media files to static storage (Hostinger)
 * 
 * After AI processing completes, this moves both source and processed files
 * to the static server and updates database references.
 */

require_once __DIR__ . '/../config/media.php';
require_once __DIR__ . '/media_store.php';

class MediaMigrator {
    private $pdo;
    private $config;
    private $logFile;
    
    // File extensions to migrate
    private const MEDIA_EXTENSIONS = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'],
        'videos' => ['mp4', 'mov', 'avi', 'webm', 'mkv', 'm4v', '3gp'],
        'audio'  => ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'webm']
    ];
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->config = media_get_config();
        $this->logFile = dirname(__DIR__) . '/logs/media_migration.log';
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }
    
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $message\n";
        @file_put_contents($this->logFile, $line, FILE_APPEND);
        error_log("MediaMigrator: $message");
    }
    
    /**
     * Check if remote migration is enabled and configured
     */
    public function isEnabled(): bool {
        return media_remote_enabled();
    }
    
    /**
     * Migrate all media files for a specific quote to static storage
     * Call this after AI processing completes
     */
    public function migrateQuoteMedia(int $quoteId, bool $deleteLocal = true): array {
        $results = [
            'success' => true,
            'quote_id' => $quoteId,
            'migrated' => [],
            'failed' => [],
            'skipped' => [],
            'bytes_freed' => 0
        ];
        
        if (!$this->isEnabled()) {
            $results['success'] = false;
            $results['error'] = 'Remote storage not configured. Set MEDIA_MODE=remote and configure Hostinger endpoints.';
            $this->log("Migration skipped for quote $quoteId: remote not enabled");
            return $results;
        }
        
        $this->log("Starting migration for quote $quoteId");
        
        // Get all media files from both tables
        $files = $this->getQuoteMediaFiles($quoteId);
        
        if (empty($files)) {
            $this->log("No media files found for quote $quoteId");
            $results['skipped'][] = 'No media files found';
            return $results;
        }
        
        foreach ($files as $file) {
            $localPath = $this->resolveLocalPath($file, $quoteId);
            
            if (!$localPath || !file_exists($localPath)) {
                $results['skipped'][] = [
                    'file' => $file['filename'] ?? $file['original_filename'] ?? 'unknown',
                    'reason' => 'Local file not found'
                ];
                continue;
            }
            
            // Check if already migrated (has remote URL)
            if (!empty($file['remote_url']) && strpos($file['remote_url'], 'http') === 0) {
                $results['skipped'][] = [
                    'file' => basename($localPath),
                    'reason' => 'Already migrated'
                ];
                continue;
            }
            
            // Upload to Hostinger
            $destName = $file['filename'] ?? basename($localPath);
            $remoteUrl = media_upload_remote($localPath, $quoteId, $destName);
            
            if ($remoteUrl) {
                $fileSize = filesize($localPath);
                
                // Update database with remote URL
                $this->updateFileRemoteUrl($file, $remoteUrl, $quoteId);
                
                $results['migrated'][] = [
                    'file' => $destName,
                    'size' => $fileSize,
                    'remote_url' => $remoteUrl
                ];
                
                // Delete local file if requested
                if ($deleteLocal && $this->config['delete_local_after_upload']) {
                    if (@unlink($localPath)) {
                        $results['bytes_freed'] += $fileSize;
                        $this->log("Deleted local: $localPath ({$fileSize} bytes)");
                    }
                }
                
                $this->log("Migrated: $destName -> $remoteUrl");
            } else {
                $results['failed'][] = [
                    'file' => $destName,
                    'reason' => 'Upload to remote storage failed'
                ];
                $results['success'] = false;
                $this->log("Failed to migrate: $destName");
            }
        }
        
        // Also migrate any processed files (frames, thumbnails, audio extracts)
        $processedResults = $this->migrateProcessedFiles($quoteId, $deleteLocal);
        $results['migrated'] = array_merge($results['migrated'], $processedResults['migrated']);
        $results['bytes_freed'] += $processedResults['bytes_freed'];
        
        // Clean up empty quote directory
        if ($deleteLocal) {
            $this->cleanupQuoteDirectory($quoteId);
        }
        
        $this->log("Migration complete for quote $quoteId: " . 
            count($results['migrated']) . " migrated, " . 
            count($results['failed']) . " failed, " .
            $this->formatBytes($results['bytes_freed']) . " freed");
        
        return $results;
    }
    
    /**
     * Get all media files for a quote from database
     */
    private function getQuoteMediaFiles(int $quoteId): array {
        $files = [];
        
        // Try media table first
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, filename, original_filename, file_path, file_size, mime_type, remote_url
                FROM media 
                WHERE quote_id = ?
            ");
            $stmt->execute([$quoteId]);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->log("Error querying media table: " . $e->getMessage());
        }
        
        // Also check uploaded_files table (legacy)
        try {
            $check = $this->pdo->query("SHOW TABLES LIKE 'uploaded_files'");
            if ($check && $check->fetch()) {
                $stmt = $this->pdo->prepare("
                    SELECT id, filename, file_path, file_size, mime_type, 
                           NULL as remote_url, 'uploaded_files' as source_table
                    FROM uploaded_files 
                    WHERE quote_id = ?
                ");
                $stmt->execute([$quoteId]);
                $legacyFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $files = array_merge($files, $legacyFiles);
            }
        } catch (PDOException $e) {
            // uploaded_files table might not exist
        }
        
        return $files;
    }
    
    /**
     * Resolve the actual local filesystem path for a file
     */
    private function resolveLocalPath(array $file, int $quoteId): ?string {
        $possiblePaths = [];
        
        // Try file_path field
        if (!empty($file['file_path'])) {
            $path = $file['file_path'];
            // Handle relative paths
            if (strpos($path, '/') !== 0) {
                $possiblePaths[] = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
                $possiblePaths[] = dirname(__DIR__, 2) . '/' . ltrim($path, '/');
            } else {
                $possiblePaths[] = $path;
            }
        }
        
        // Try constructing from filename
        if (!empty($file['filename'])) {
            $fn = $file['filename'];
            $possiblePaths[] = dirname(__DIR__, 2) . "/uploads/quote_{$quoteId}/{$fn}";
            $possiblePaths[] = dirname(__DIR__) . "/uploads/quote_{$quoteId}/{$fn}";
            $possiblePaths[] = $_SERVER['DOCUMENT_ROOT'] . "/server/uploads/quote_{$quoteId}/{$fn}";
        }
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    /**
     * Update database record with remote URL
     */
    private function updateFileRemoteUrl(array $file, string $remoteUrl, int $quoteId): void {
        $sourceTable = $file['source_table'] ?? 'media';
        
        try {
            if ($sourceTable === 'uploaded_files') {
                // For legacy table, we might need to add remote_url column or migrate to media table
                $stmt = $this->pdo->prepare("
                    INSERT INTO media (quote_id, filename, original_filename, file_path, file_size, mime_type, remote_url, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE remote_url = VALUES(remote_url)
                ");
                $stmt->execute([
                    $quoteId,
                    $file['filename'],
                    $file['original_filename'] ?? $file['filename'],
                    $file['file_path'],
                    $file['file_size'] ?? 0,
                    $file['mime_type'] ?? 'application/octet-stream',
                    $remoteUrl
                ]);
            } else {
                // Ensure remote_url column exists
                $this->ensureRemoteUrlColumn();
                
                $stmt = $this->pdo->prepare("UPDATE media SET remote_url = ? WHERE id = ?");
                $stmt->execute([$remoteUrl, $file['id']]);
            }
        } catch (PDOException $e) {
            $this->log("Error updating remote URL: " . $e->getMessage());
        }
    }
    
    /**
     * Ensure the remote_url column exists in media table
     */
    private function ensureRemoteUrlColumn(): void {
        static $checked = false;
        if ($checked) return;
        
        try {
            $this->pdo->exec("ALTER TABLE media ADD COLUMN IF NOT EXISTS remote_url VARCHAR(500) DEFAULT NULL");
            $checked = true;
        } catch (PDOException $e) {
            // Column might already exist or table structure issue
            $checked = true;
        }
    }
    
    /**
     * Migrate processed files (frames, thumbnails, audio extracts)
     */
    private function migrateProcessedFiles(int $quoteId, bool $deleteLocal): array {
        $results = ['migrated' => [], 'bytes_freed' => 0];
        
        $quoteDir = dirname(__DIR__) . "/uploads/quote_{$quoteId}";
        if (!is_dir($quoteDir)) {
            return $results;
        }
        
        // Patterns for processed files
        $patterns = [
            'frame_*.jpg',     // Video frames
            'thumb_*.jpg',     // Thumbnails
            'audio_*.mp3',     // Extracted audio
            'audio_*.wav',
            'processed_*.*'    // Any processed outputs
        ];
        
        foreach ($patterns as $pattern) {
            $files = glob($quoteDir . '/' . $pattern);
            foreach ($files as $localPath) {
                $filename = basename($localPath);
                $remoteUrl = media_upload_remote($localPath, $quoteId, $filename);
                
                if ($remoteUrl) {
                    $fileSize = filesize($localPath);
                    $results['migrated'][] = [
                        'file' => $filename,
                        'size' => $fileSize,
                        'remote_url' => $remoteUrl,
                        'type' => 'processed'
                    ];
                    
                    if ($deleteLocal && $this->config['delete_local_after_upload']) {
                        if (@unlink($localPath)) {
                            $results['bytes_freed'] += $fileSize;
                        }
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Clean up empty quote directory after migration
     */
    private function cleanupQuoteDirectory(int $quoteId): void {
        $dirs = [
            dirname(__DIR__) . "/uploads/quote_{$quoteId}",
            dirname(__DIR__, 2) . "/uploads/quote_{$quoteId}",
            $_SERVER['DOCUMENT_ROOT'] . "/server/uploads/quote_{$quoteId}"
        ];
        
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $remaining = glob($dir . '/*');
                if (empty($remaining)) {
                    @rmdir($dir);
                    $this->log("Removed empty directory: $dir");
                }
            }
        }
    }
    
    /**
     * Migrate all pending quotes (for batch processing)
     */
    public function migrateAllPending(int $limit = 50): array {
        $results = [
            'processed' => 0,
            'migrated_files' => 0,
            'bytes_freed' => 0,
            'errors' => []
        ];
        
        // Find quotes with local files that haven't been fully migrated
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT q.id 
            FROM quotes q
            INNER JOIN media m ON m.quote_id = q.id
            WHERE (m.remote_url IS NULL OR m.remote_url = '')
            AND m.file_path IS NOT NULL
            ORDER BY q.id DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $quotes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($quotes as $quoteId) {
            $qResult = $this->migrateQuoteMedia($quoteId);
            $results['processed']++;
            $results['migrated_files'] += count($qResult['migrated']);
            $results['bytes_freed'] += $qResult['bytes_freed'];
            
            if (!$qResult['success']) {
                $results['errors'][] = "Quote $quoteId: " . ($qResult['error'] ?? 'Unknown error');
            }
        }
        
        return $results;
    }
    
    /**
     * Get migration status for a quote
     */
    public function getQuoteStatus(int $quoteId): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_files,
                SUM(CASE WHEN remote_url IS NOT NULL AND remote_url != '' THEN 1 ELSE 0 END) as migrated,
                SUM(CASE WHEN remote_url IS NULL OR remote_url = '' THEN 1 ELSE 0 END) as pending,
                SUM(file_size) as total_size
            FROM media
            WHERE quote_id = ?
        ");
        $stmt->execute([$quoteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_files' => 0,
            'migrated' => 0,
            'pending' => 0,
            'total_size' => 0
        ];
    }
    
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

/**
 * Helper function to trigger migration after AI processing
 * Call this at the end of AI analysis endpoints
 */
function trigger_post_ai_migration(PDO $pdo, int $quoteId): void {
    // Run asynchronously if possible
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    try {
        $migrator = new MediaMigrator($pdo);
        if ($migrator->isEnabled()) {
            $migrator->migrateQuoteMedia($quoteId);
        }
    } catch (Throwable $e) {
        error_log("Post-AI migration failed for quote $quoteId: " . $e->getMessage());
    }
}

?>
