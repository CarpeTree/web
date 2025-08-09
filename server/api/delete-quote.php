<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // Disable public error display
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', __DIR__.'/../delete_error.log'); // Log errors to a file

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/error-handler.php';

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error(405, 'Method Not Allowed');
}

// Get and validate quote_id from request body
$input = json_decode(file_get_contents('php://input'), true);
$quote_id = $input['quote_id'] ?? null;

if (empty($quote_id) || !is_numeric($quote_id)) {
    send_error(400, 'Invalid or missing quote ID.');
}

$pdo = getDBConnection();
$project_root = dirname(__DIR__, 2); // Assumes script is in /server/api, goes up to project root

try {
    $pdo->beginTransaction();

    // 1. Get media file paths before deleting records
    $stmt = $pdo->prepare("SELECT file_path FROM media WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $media_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Delete related records from child tables
    $pdo->prepare("DELETE FROM media WHERE quote_id = ?")->execute([$quote_id]);
    $pdo->prepare("DELETE FROM ai_cost_log WHERE quote_id = ?")->execute([$quote_id]);
    // NOTE: Add other related tables here if necessary in the future

    // 3. Delete the main quote record
    $delete_stmt = $pdo->prepare("DELETE FROM quotes WHERE id = ?");
    $delete_stmt->execute([$quote_id]);
    $rowCount = $delete_stmt->rowCount();

    // 4. Commit transaction
    $pdo->commit();

    // 5. Delete physical files and directory after successful DB deletion
    if ($rowCount > 0) {
        $quote_dir_path = $project_root . '/uploads/quote_' . $quote_id;

        foreach ($media_files as $file) {
            // Reconstruct the full file path carefully
            // The DB stores paths like '../uploads/quote_xx/file.mov' relative to the original script.
            // We need to build the absolute path from the project root.
            $filename = basename($file['file_path']);
            $full_path = $quote_dir_path . '/' . $filename;
            
            if (file_exists($full_path)) {
                if (!unlink($full_path)) {
                     error_log("Could not delete file: " . $full_path);
                }
            } else {
                 error_log("File not found for deletion: " . $full_path);
            }
        }

        // Attempt to remove the directory if it's empty
        if (is_dir($quote_dir_path)) {
            // Check if directory is empty (scandir returns array with '.' and '..')
            if (count(scandir($quote_dir_path)) == 2) {
                if (!rmdir($quote_dir_path)) {
                    error_log("Could not delete directory: " . $quote_dir_path);
                }
            }
        }
    }

    if ($rowCount > 0) {
        echo json_encode(['success' => true, 'message' => "Quote #$quote_id and all associated files have been deleted."]);
    } else {
        send_error(404, "Quote with ID #$quote_id not found.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete Quote Error for #{$quote_id}: " . $e->getMessage());
    send_error(500, 'Database error during deletion. Check server logs.');
}
?>
