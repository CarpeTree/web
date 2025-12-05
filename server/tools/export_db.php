<?php
// Temporary DB exporter (data-only). Remove immediately after use.
// Protected by token via GET parameter in web mode; in CLI mode token is not required.

// CONFIG
$TOKEN = 'ct_export_9b7f3a2d';
$DB_HOST = 'localhost';
$DB_NAME = 'u230128646_carpetree';
$DB_USER = 'u230128646_pherognome';
$DB_PASS = 'bupy-igfy-kaac-acfk';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    if (!isset($_GET['token']) || $_GET['token'] !== $TOKEN) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $mysqli->set_charset('utf8mb4');
} catch (Throwable $e) {
    if (!$isCli) {
        http_response_code(500);
    }
    echo 'DB connect failed';
    exit;
}

// Tables to export (data-only). Schema already present on VPS.
$tables = [
    'customers', 'quotes', 'media', 'email_log', 'system_settings',
    'trees', 'tree_work_orders', 'invoices', 'ai_cost_log', 'admin_users', 'uploaded_files'
];

if (!$isCli) {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="carpetree_data.sql"');
}

echo "SET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n";

foreach ($tables as $table) {
    // Check table exists
    try {
        $res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
        if ($res->num_rows === 0) continue;
    } catch (Throwable $e) {
        continue;
    }
    $res = $mysqli->query("SELECT * FROM `{$table}`");
    if ($res->num_rows === 0) continue;
    // Column list
    $fields = [];
    $finfo = $res->fetch_fields();
    foreach ($finfo as $fi) { $fields[] = '`' . $fi->name . '`'; }
    $colList = '(' . implode(',', $fields) . ')';
    $batch = [];
    $batchSize = 250; // tune for size
    while ($row = $res->fetch_assoc()) {
        $vals = [];
        foreach ($row as $v) {
            if ($v === null) { $vals[] = 'NULL'; continue; }
            if (is_numeric($v) && !preg_match('/^0[0-9]/', (string)$v)) {
                $vals[] = $v;
            } else {
                $vals[] = "'" . $mysqli->real_escape_string($v) . "'";
            }
        }
        $batch[] = '(' . implode(',', $vals) . ')';
        if (count($batch) >= $batchSize) {
            echo "INSERT IGNORE INTO `{$table}` {$colList} VALUES\n" . implode(",\n", $batch) . ";\n";
            $batch = [];
        }
    }
    if (!empty($batch)) {
        echo "INSERT IGNORE INTO `{$table}` {$colList} VALUES\n" . implode(",\n", $batch) . ";\n";
    }
    echo "\n";
}

echo "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n";

?>

<?php
// Temporary DB exporter (data-only). Remove immediately after use.
// Protected by token via GET parameter.

// CONFIG
$TOKEN = 'ct_export_9b7f3a2d';
$DB_HOST = 'localhost';
$DB_NAME = 'u230128646_carpetree';
$DB_USER = 'u230128646_pherognome';
$DB_PASS = 'bupy-igfy-kaac-acfk';

if (!isset($_GET['token']) || $_GET['token'] !== $TOKEN) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $mysqli->set_charset('utf8mb4');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'DB connect failed';
    exit;
}

// Tables to export (data-only). Schema already present on VPS.
$tables = [
    'customers', 'quotes', 'media', 'email_log', 'system_settings',
    'trees', 'tree_work_orders', 'invoices', 'ai_cost_log', 'admin_users', 'uploaded_files'
];

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="carpetree_data.sql"');

echo "SET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n";

foreach ($tables as $table) {
    // Check table exists
    try {
        $res = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
        if ($res->num_rows === 0) continue;
    } catch (Throwable $e) {
        continue;
    }
    $res = $mysqli->query("SELECT * FROM `{$table}`");
    if ($res->num_rows === 0) continue;
    // Column list
    $fields = [];
    $finfo = $res->fetch_fields();
    foreach ($finfo as $fi) { $fields[] = '`' . $fi->name . '`'; }
    $colList = '(' . implode(',', $fields) . ')';
    $batch = [];
    $batchSize = 250; // tune for size
    while ($row = $res->fetch_assoc()) {
        $vals = [];
        foreach ($row as $v) {
            if ($v === null) { $vals[] = 'NULL'; continue; }
            if (is_numeric($v) && !preg_match('/^0[0-9]/', (string)$v)) {
                $vals[] = $v;
            } else {
                $vals[] = "'" . $mysqli->real_escape_string($v) . "'";
            }
        }
        $batch[] = '(' . implode(',', $vals) . ')';
        if (count($batch) >= $batchSize) {
            echo "INSERT IGNORE INTO `{$table}` {$colList} VALUES\n" . implode(",\n", $batch) . ";\n";
            $batch = [];
        }
    }
    if (!empty($batch)) {
        echo "INSERT IGNORE INTO `{$table}` {$colList} VALUES\n" . implode(",\n", $batch) . ";\n";
    }
    echo "\n";
}

echo "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n";

?>


