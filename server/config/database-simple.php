<?php
// Simplified database configuration without Composer dependencies

// Database configuration (updated for your setup)
$db_host = 'localhost';
$db_name = 'carpe_tree_quotes';
$db_user = 'root';  // MySQL username
$db_pass = '';      // No password set (default for Homebrew MySQL)
$db_charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "✅ Database connection successful!<br>";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    die();
}
?> 