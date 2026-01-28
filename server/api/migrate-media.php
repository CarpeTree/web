<?php
/**
 * Media Migration API
 * 
 * Endpoints:
 *   POST /migrate-media.php?action=quote&quote_id=123  - Migrate a single quote
 *   POST /migrate-media.php?action=batch&limit=50     - Migrate pending quotes
 *   GET  /migrate-media.php?action=status&quote_id=123 - Get migration status
 *   GET  /migrate-media.php?action=config             - Check configuration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../utils/media_migrator.php';

// Simple admin check (you may want to add proper auth)
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    $migrator = new MediaMigrator($pdo);
    
    switch ($action) {
        case 'config':
            // Show current configuration (without sensitive values)
            $config = media_get_config();
            echo json_encode([
                'success' => true,
                'enabled' => $migrator->isEnabled(),
                'mode' => $config['mode'],
                'remote_configured' => !empty($config['remote_upload_url']),
                'delete_local_after_upload' => $config['delete_local_after_upload'],
                'remote_base_url' => $config['remote_base_url'] ? substr($config['remote_base_url'], 0, 50) . '...' : null
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'quote':
            // Migrate a single quote
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Use POST for migration']);
                break;
            }
            
            $quoteId = intval($_GET['quote_id'] ?? $_POST['quote_id'] ?? 0);
            if ($quoteId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'quote_id required']);
                break;
            }
            
            $deleteLocal = ($_GET['delete_local'] ?? $_POST['delete_local'] ?? 'true') === 'true';
            $result = $migrator->migrateQuoteMedia($quoteId, $deleteLocal);
            
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'batch':
            // Migrate multiple pending quotes
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Use POST for batch migration']);
                break;
            }
            
            $limit = intval($_GET['limit'] ?? $_POST['limit'] ?? 50);
            $limit = min(max($limit, 1), 200); // Clamp between 1-200
            
            $result = $migrator->migrateAllPending($limit);
            echo json_encode([
                'success' => empty($result['errors']),
                'result' => $result
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'status':
        default:
            // Get status for a quote or overall
            $quoteId = intval($_GET['quote_id'] ?? 0);
            
            if ($quoteId > 0) {
                $status = $migrator->getQuoteStatus($quoteId);
                echo json_encode([
                    'success' => true,
                    'quote_id' => $quoteId,
                    'status' => $status,
                    'fully_migrated' => $status['pending'] == 0 && $status['total_files'] > 0
                ], JSON_PRETTY_PRINT);
            } else {
                // Overall status
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(DISTINCT quote_id) as total_quotes_with_media,
                        COUNT(*) as total_files,
                        SUM(CASE WHEN remote_url IS NOT NULL AND remote_url != '' THEN 1 ELSE 0 END) as migrated_files,
                        SUM(CASE WHEN remote_url IS NULL OR remote_url = '' THEN 1 ELSE 0 END) as pending_files,
                        SUM(CASE WHEN remote_url IS NULL OR remote_url = '' THEN file_size ELSE 0 END) as pending_bytes
                    FROM media
                ");
                $overall = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Format bytes
                $pendingBytes = $overall['pending_bytes'] ?? 0;
                $pendingMB = round($pendingBytes / 1024 / 1024, 2);
                
                echo json_encode([
                    'success' => true,
                    'enabled' => $migrator->isEnabled(),
                    'overall' => $overall,
                    'pending_mb' => $pendingMB,
                    'fully_migrated' => ($overall['pending_files'] ?? 0) == 0
                ], JSON_PRETTY_PRINT);
            }
            break;
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
