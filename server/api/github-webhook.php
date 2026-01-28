<?php
/**
 * GitHub Webhook Receiver for Automated Deployments
 * 
 * This script receives push events from GitHub and triggers a deployment.
 * It validates the webhook signature to ensure requests are authentic.
 * 
 * Security: Uses HMAC signature verification - only GitHub can trigger deploys.
 */

// Configuration
$webhookSecret = getenv('GITHUB_WEBHOOK_SECRET') ?: file_get_contents('/var/www/carpetree.com/.webhook-secret');
$logFile = '/var/www/carpetree.com/server/logs/deploy.log';
$deployScript = '/var/www/carpetree.com/scripts/auto-deploy.sh';
$allowedBranch = 'main';

// Ensure log directory exists
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * Log a message with timestamp
 */
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $message\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    error_log($entry);
}

/**
 * Verify GitHub webhook signature
 */
function verifySignature($payload, $signature, $secret) {
    if (empty($signature)) {
        return false;
    }
    
    // GitHub sends signature as "sha256=..."
    $parts = explode('=', $signature, 2);
    if (count($parts) !== 2 || $parts[0] !== 'sha256') {
        return false;
    }
    
    $expected = hash_hmac('sha256', $payload, trim($secret));
    return hash_equals($expected, $parts[1]);
}

/**
 * Send JSON response
 */
function respond($status, $message, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage("REJECTED: Non-POST request from {$_SERVER['REMOTE_ADDR']}");
    respond('error', 'Method not allowed', 405);
}

// Get the raw payload
$payload = file_get_contents('php://input');
if (empty($payload)) {
    logMessage("REJECTED: Empty payload from {$_SERVER['REMOTE_ADDR']}");
    respond('error', 'Empty payload', 400);
}

// Verify the webhook secret
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$webhookSecret = trim($webhookSecret);

if (empty($webhookSecret)) {
    logMessage("ERROR: Webhook secret not configured");
    respond('error', 'Server misconfigured', 500);
}

if (!verifySignature($payload, $signature, $webhookSecret)) {
    logMessage("REJECTED: Invalid signature from {$_SERVER['REMOTE_ADDR']}");
    respond('error', 'Invalid signature', 401);
}

// Parse the payload
$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage("REJECTED: Invalid JSON payload");
    respond('error', 'Invalid JSON', 400);
}

// Check the event type
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? 'unknown';
logMessage("Received event: $event from {$_SERVER['REMOTE_ADDR']}");

// Only process push events
if ($event !== 'push') {
    logMessage("IGNORED: Non-push event ($event)");
    respond('ok', "Event '$event' ignored");
}

// Check if it's the right branch
$ref = $data['ref'] ?? '';
$branch = str_replace('refs/heads/', '', $ref);

if ($branch !== $allowedBranch) {
    logMessage("IGNORED: Push to branch '$branch' (not '$allowedBranch')");
    respond('ok', "Branch '$branch' ignored");
}

// Log commit info
$commits = $data['commits'] ?? [];
$pusher = $data['pusher']['name'] ?? 'unknown';
$commitCount = count($commits);
$headCommit = $data['head_commit']['message'] ?? 'No message';

logMessage("DEPLOY TRIGGERED: $commitCount commit(s) by $pusher");
logMessage("Head commit: " . substr($headCommit, 0, 100));

// Execute the deploy script
if (!file_exists($deployScript)) {
    logMessage("ERROR: Deploy script not found at $deployScript");
    respond('error', 'Deploy script missing', 500);
}

// Run deploy in background so we can respond quickly
$command = "nohup bash $deployScript >> $logFile 2>&1 &";
exec($command, $output, $returnCode);

logMessage("Deploy script launched (return code: $returnCode)");

respond('ok', "Deployment triggered for $commitCount commit(s)", 200);
