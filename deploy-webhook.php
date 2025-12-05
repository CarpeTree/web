<?php
/**
 * GitHub Webhook Deployment Endpoint
 * 
 * This endpoint receives webhook notifications from GitHub when code is pushed
 * and automatically pulls the latest changes to the VPS.
 * 
 * Security:
 * - Requires secret token in query parameter
 * - Only accepts POST requests
 * - Logs all deployment attempts
 * 
 * Setup:
 * 1. Set DEPLOY_WEBHOOK_SECRET in your .env file or environment
 * 2. Configure GitHub webhook to POST to: https://carpetree.com/deploy-webhook.php?secret=YOUR_SECRET
 * 3. Set content type to: application/json
 */

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// Get secret from query parameter or environment
$provided_secret = $_GET['secret'] ?? '';
$expected_secret = getenv('DEPLOY_WEBHOOK_SECRET') ?: '';

// If no secret is configured, try to load from .env file
if (empty($expected_secret)) {
    $env_file = __DIR__ . '/.env';
    if (file_exists($env_file)) {
        $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($env_lines as $line) {
            if (strpos($line, 'DEPLOY_WEBHOOK_SECRET=') === 0) {
                $expected_secret = trim(substr($line, strlen('DEPLOY_WEBHOOK_SECRET=')));
                break;
            }
        }
    }
}

// Verify secret
if (empty($expected_secret)) {
    http_response_code(500);
    echo json_encode(['error' => 'Webhook secret not configured. Set DEPLOY_WEBHOOK_SECRET in .env']);
    exit;
}

if ($provided_secret !== $expected_secret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Invalid secret.']);
    exit;
}

// Get webhook payload
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Log the deployment attempt
$log_file = __DIR__ . '/deploy-webhook.log';
$log_entry = date('Y-m-d H:i:s') . " - Deployment triggered\n";
$log_entry .= "  Branch: " . ($data['ref'] ?? 'unknown') . "\n";
$log_entry .= "  Commit: " . ($data['head_commit']['id'] ?? 'unknown') . "\n";
$log_entry .= "  Author: " . ($data['head_commit']['author']['name'] ?? 'unknown') . "\n";
$log_entry .= "  Message: " . ($data['head_commit']['message'] ?? 'unknown') . "\n";
$log_entry .= "  IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n\n";

file_put_contents($log_file, $log_entry, FILE_APPEND);

// Only deploy if push is to main branch
$ref = $data['ref'] ?? '';
if ($ref !== 'refs/heads/main' && $ref !== 'refs/heads/master') {
    echo json_encode([
        'status' => 'skipped',
        'message' => 'Not main/master branch, skipping deployment',
        'branch' => $ref
    ]);
    exit;
}

// Change to web root directory
$web_root = '/var/www/carpetree.com';
if (!is_dir($web_root)) {
    http_response_code(500);
    echo json_encode(['error' => 'Web root directory not found: ' . $web_root]);
    exit;
}

// Change directory and pull latest changes
chdir($web_root);

// Capture output from git commands
$output = [];
$return_code = 0;

// Fetch latest changes
exec('git fetch origin 2>&1', $fetch_output, $fetch_return);
$output = array_merge($output, ['fetch' => $fetch_output]);

if ($fetch_return !== 0) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Git fetch failed',
        'output' => $fetch_output
    ]);
    exit;
}

// Pull latest changes
exec('git pull origin main 2>&1', $pull_output, $pull_return);
$output = array_merge($output, ['pull' => $pull_output]);

if ($pull_return !== 0) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Git pull failed',
        'output' => $pull_output
    ]);
    exit;
}

// Log successful deployment
$log_entry = date('Y-m-d H:i:s') . " - Deployment successful\n";
$log_entry .= "  Output: " . implode("\n  ", $pull_output) . "\n\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);

// Return success response
echo json_encode([
    'status' => 'success',
    'message' => 'Deployment completed successfully',
    'commit' => $data['head_commit']['id'] ?? 'unknown',
    'output' => $pull_output
]);


