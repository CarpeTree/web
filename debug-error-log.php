<?php
// A simple script to display the contents of the error log

// Enable error reporting to catch any issues with this script itself
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error_log_path = '/home/u230128646/.logs/error_log_carpetree_com';

echo "<h1>Reading Error Log</h1>";
echo "<p>Attempting to read: " . htmlspecialchars($error_log_path) . "</p>";

if (file_exists($error_log_path)) {
    echo "<h2>Log found. Contents:</h2>";
    echo "<pre>" . htmlspecialchars(file_get_contents($error_log_path)) . "</pre>";
} else {
    echo "<h2>Log not found.</h2>";
    echo "<p>The error log file does not exist at the expected location.</p>";
}
