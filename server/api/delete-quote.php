<?php
header('Content-Type', 'application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/../delete_error.log');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/error-handler.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') { http_response_code(200); exit; }
if (!in_array($method, ['POST','DELETE'])) { send_error(405, 'Method Not Allowed'); }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$pattern = $_GET['pattern'] ?? $input['pattern'] ?? null;

$pdo = getDBConnection();
$project_root = dirname(__DIR__, 2);

// Bulk delete of test data when pattern is provided
if ($pattern && strtolower($pattern) === 'test') {
    try {
        $pdo->beginTransaction();
        // Find quotes that look like tests (by customer name/email/address/notes)
        $like = '%test%';
        $stmt = $pdo->prepare("SELECT id FROM quotes q JOIN customers c ON q.customer_id=c.id WHERE LOWER(c.name) LIKE ? OR LOWER(c.email) LIKE ? OR LOWER(q.notes) LIKE ? OR LOWER(c.address) LIKE ?");
        $stmt->execute([$like,$like,$like,$like]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Also include quotes with typical test emails (o3.test@example.com, admin.email.test, e2e)
        $stmt2 = $pdo->query("SELECT id FROM quotes q JOIN customers c ON q.customer_id=c.id WHERE c.email LIKE '%test%' OR c.email LIKE '%example.com%' OR c.email LIKE '%e2e%' ");
        $ids2 = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        $allIds = array_values(array_unique(array_merge($ids, $ids2)));

        // Collect media file paths
        $media = [];
        if (!empty($allIds)) {
            $in = implode(',', array_fill(0, count($allIds), '?'));
            $mstmt = $pdo->prepare("SELECT quote_id, file_path FROM media WHERE quote_id IN ($in)");
            $mstmt->execute($allIds);
            $media = $mstmt->fetchAll(PDO::FETCH_ASSOC);

            $pdo->prepare("DELETE FROM media WHERE quote_id IN ($in)")->execute($allIds);
            $pdo->prepare("DELETE FROM ai_cost_log WHERE quote_id IN ($in)")->execute($allIds);
            $pdo->prepare("DELETE FROM quotes WHERE id IN ($in)")->execute($allIds);
        }
        $pdo->commit();

        // Remove files/directories
        foreach ($allIds as $qid) {
            $dir = $project_root . '/uploads/quote_' . $qid;
            if (is_dir($dir)) {
                foreach (glob($dir.'/*') as $f) { @unlink($f); }
                @rmdir($dir);
            }
        }
        echo json_encode(['success'=>true,'deleted_ids'=>$allIds]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Bulk delete error: '.$e->getMessage());
        send_error(500,'Bulk delete failed');
    }
    exit;
}

// Single delete path
$quote_id = $input['quote_id'] ?? null;
if (empty($quote_id) || !is_numeric($quote_id)) { send_error(400, 'Invalid or missing quote ID.'); }

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT file_path FROM media WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->prepare("DELETE FROM media WHERE quote_id = ?")->execute([$quote_id]);
    $pdo->prepare("DELETE FROM ai_cost_log WHERE quote_id = ?")->execute([$quote_id]);
    $delete_stmt = $pdo->prepare("DELETE FROM quotes WHERE id = ?");
    $delete_stmt->execute([$quote_id]);
    $rowCount = $delete_stmt->rowCount();

    $pdo->commit();

    if ($rowCount > 0) {
        $quote_dir_path = $project_root . '/uploads/quote_' . $quote_id;
        foreach ($media_files as $file) {
            $filename = basename($file['file_path']);
            $full_path = $quote_dir_path . '/' . $filename;
            if (file_exists($full_path)) { @unlink($full_path); }
        }
        if (is_dir($quote_dir_path) && count(scandir($quote_dir_path)) == 2) { @rmdir($quote_dir_path); }
        echo json_encode(['success'=>true,'message'=>"Quote #$quote_id deleted"]);    
    } else {
        send_error(404, "Quote with ID #$quote_id not found.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Delete Quote Error for #{$quote_id}: " . $e->getMessage());
    send_error(500, 'Database error during deletion. Check server logs.');
}
?>
