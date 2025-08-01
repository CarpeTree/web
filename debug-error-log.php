<?php
// A simple script to execute the trigger script and capture its output

// Enable error reporting to catch any issues with this script itself
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Executing Trigger Script</h1>";

$quote_id = $_GET['quote_id'] ?? 77;
$trigger_url = "https://carpetree.com/trigger-multi-model-analysis.php?quote_id=" . $quote_id;

echo "<p>Calling: <a href='" . htmlspecialchars($trigger_url) . "'>" . htmlspecialchars($trigger_url) . "</a></p>";

echo "<h2>Output:</h2>";
echo "<pre>";

// Use curl to get the output
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $trigger_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($ch);
curl_close($ch);

echo htmlspecialchars($output);

echo "</pre>";
