<?php
// START NEW - PHPMailer utility wrapper
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $template, $data = [], $attachments = []) {
    global $pdo, $SMTP_HOST, $SMTP_USER, $SMTP_PASS, $SMTP_PORT, $SMTP_FROM;
    
    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST ?? 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER ?? '';
        $mail->Password   = $SMTP_PASS ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_PORT ?? 587;

        // Recipients
        $mail->setFrom($SMTP_FROM ?? 'noreply@carpetree.com', 'Carpe Tree\'em');
        $mail->addAddress($to);

        // Content - ensure proper HTML rendering
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = $subject;
        
        // Load and process template
        $html_body = loadEmailTemplate($template, $data);
        $mail->Body = $html_body;
        
        // Plain text alternative
        $mail->AltBody = strip_tags($html_body);

        // Add attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment['path'])) {
                $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
            }
        }

        $mail->send();
        
        // Log successful email
        logEmail($to, $subject, $template, 'sent', '', $data);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        
        // Log failed email
        logEmail($to, $subject, $template, 'failed', $e->getMessage(), $data);
        
        return false;
    }
}

function loadEmailTemplate($template, $data) {
    $template_path = __DIR__ . "/../templates/{$template}.html";
    
    if (!file_exists($template_path)) {
        throw new Exception("Email template not found: $template");
    }
    
    $html = file_get_contents($template_path);
    
            // Replace placeholders with data - no htmlspecialchars for html_content
        foreach ($data as $key => $value) {
            if ($key === 'html_content') {
                // Don't escape HTML content - it's already properly formatted
                $html = str_replace("{{$key}}", (string)$value, $html);
            } else {
                // Handle arrays and objects by converting to string
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                } elseif ($value === null) {
                    $value = '';
                }
                $html = str_replace("{{$key}}", htmlspecialchars((string)$value), $html);
            }
        }
    
    // Add default values
    $html = str_replace('{company_name}', 'Carpe Tree\'em', $html);
    $html = str_replace('{company_phone}', '778-655-3741', $html);
    $html = str_replace('{company_email}', 'sapport@carpetree.com', $html);
    global $SITE_URL;
    $html = str_replace('{logo_url}', $SITE_URL . '/images/carpeclear.png', $html);
    
    return $html;
}

function logEmail($recipient, $subject, $template, $status, $error = '', $data = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_log (
                recipient_email, subject, template_used, status, error_message,
                quote_id, invoice_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Handle quote_id - only save if it's numeric and exists in database
        $quote_id = null;
        if (isset($data['quote_id']) && is_numeric($data['quote_id'])) {
            $check_quote_id = (int)$data['quote_id'];
            // Check if quote exists (unless it's a test)
            if (isset($data['test_mode']) || $check_quote_id <= 0) {
                $quote_id = null; // Don't reference non-existent quotes
            } else {
                try {
                    $check_stmt = $pdo->prepare("SELECT id FROM quotes WHERE id = ?");
                    $check_stmt->execute([$check_quote_id]);
                    if ($check_stmt->fetch()) {
                        $quote_id = $check_quote_id;
                    }
                } catch (Exception $e) {
                    // If check fails, don't reference the quote
                    $quote_id = null;
                }
            }
        }
        
        $invoice_id = null;
        if (isset($data['invoice_id']) && is_numeric($data['invoice_id'])) {
            $invoice_id = (int)$data['invoice_id'];
        }
        
        $stmt->execute([
            $recipient,
            $subject,
            $template,
            $status,
            $error,
            $quote_id,
            $invoice_id
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
    }
}

function sendAdminAlert($type, $data) {
    $admin_email = $_ENV['ADMIN_EMAIL'] ?? 'admin@carpetree.com';
    
    switch ($type) {
        case 'new_quote':
            return sendEmail(
                $admin_email,
                "New Quote Submitted - #{$data['quote_id']}",
                'quote_alert_admin',
                $data
            );
            
        case 'quote_accepted':
            return sendEmail(
                $admin_email,
                "Quote Accepted - #{$data['quote_id']}",
                'quote_accepted_admin',
                $data
            );
            
        default:
            return false;
    }
}

function sendEmailDirect($to, $subject, $html_body, $quote_id = null) {
    global $pdo, $SMTP_HOST, $SMTP_USER, $SMTP_PASS, $SMTP_PORT, $SMTP_FROM;
    
    try {
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST ?? 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER ?? '';
        $mail->Password   = $SMTP_PASS ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_PORT ?? 587;

        // Recipients
        $mail->setFrom($SMTP_FROM ?? 'noreply@carpetree.com', 'Carpe Tree\'em');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = strip_tags($html_body);

        $mail->send();
        
        // Log successful email (simpler version)
        logEmailSimple($to, $subject, 'direct', 'sent', '', $quote_id);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        
        // Log failed email
        logEmailSimple($to, $subject, 'direct', 'failed', $e->getMessage(), $quote_id);
        
        return false;
    }
}

function logEmailSimple($recipient, $subject, $template, $status, $error = '', $quote_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_logs (
                recipient, subject, template_used, status, error_message, quote_id
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $recipient,
            $subject,
            $template,
            $status,
            $error ?: null,
            $quote_id
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
    }
}
// END NEW 