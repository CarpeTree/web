<?php
// Simple admin notifications - no attachments, just alerts
header('Content-Type: application/json');
require_once '../config/database-simple.php';
require_once '../config/config.php';

// Function to send simple admin alert (no attachments)
function sendSimpleAdminAlert($quote_id) {
    global $pdo;
    
    try {
        // Get quote details
        $stmt = $pdo->prepare("
            SELECT q.*, c.name, c.email, c.phone, c.address 
            FROM quotes q 
            JOIN customers c ON q.customer_id = c.id 
            WHERE q.id = ?
        ");
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quote) {
            throw new Exception("Quote not found");
        }
        
        // Count uploaded files
        $file_stmt = $pdo->prepare("SELECT COUNT(*) as file_count FROM uploaded_files WHERE quote_id = ?");
        $file_stmt->execute([$quote_id]);
        $file_count = $file_stmt->fetchColumn();
        
        // Parse services
        $services = json_decode($quote['selected_services'], true) ?: [];
        $services_text = implode(', ', array_map('ucfirst', $services));
        
        // Calculate distance
        $distance_km = calculateDistanceFromNelson($quote['address']);
        
        // Generate simple email content
        $subject = "🌳 New Quote #{$quote_id} Needs Review - Carpe Tree'em";
        
        $html_body = generateSimpleAdminEmail($quote, $file_count, $services_text, $distance_km);
        
        // Send lightweight email
        return sendSimpleEmail(
            $ADMIN_EMAIL ?? 'phil.bajenski@gmail.com',
            $subject,
            $html_body,
            $quote_id
        );
        
    } catch (Exception $e) {
        error_log("Simple admin notification error: " . $e->getMessage());
        return false;
    }
}

function generateSimpleAdminEmail($quote, $file_count, $services_text, $distance_km) {
    $has_media = $file_count > 0;
    $urgency_class = $has_media ? 'urgent' : 'normal';
    $urgency_text = $has_media ? '🎬 VIDEO QUOTE' : '📝 TEXT QUOTE';
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #2D5A27, #4a7c59); color: white; padding: 20px; text-align: center; }
            .urgent { background: linear-gradient(135deg, #d32f2f, #f57c00) !important; }
            .content { padding: 20px; }
            .alert-box { background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin: 15px 0; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0; }
            .info-item { background: #f8f9fa; padding: 10px; border-radius: 4px; }
            .info-label { font-weight: 600; color: #2D5A27; font-size: 0.9rem; }
            .btn { display: inline-block; padding: 12px 24px; background: #2D5A27; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
            .btn-primary { background: #2D5A27; }
            .btn-secondary { background: #6c757d; }
            .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 0.9rem; color: #666; }
            .priority { font-size: 1.1rem; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header $urgency_class'>
                <h1>🌳 Quote Review Alert</h1>
                <div class='priority'>$urgency_text - Quote #{$quote['id']}</div>
                <p>Submitted: " . date('M j, Y g:i A', strtotime($quote['quote_created_at'])) . "</p>
            </div>
            
            <div class='content'>
                <div class='alert-box'>
                    <h3>🚨 Action Required</h3>
                    <p><strong>New quote needs your review in the admin dashboard.</strong></p>
                    " . ($has_media ? "<p>📹 <strong>$file_count media file(s) uploaded</strong> - customer provided photos/videos for analysis.</p>" : "<p>📝 <strong>Text-only submission</strong> - in-person assessment likely needed.</p>") . "
                </div>
                
                <h3>👤 Customer Information</h3>
                <div class='info-grid'>
                    <div class='info-item'>
                        <div class='info-label'>Customer</div>
                        <div>" . htmlspecialchars($quote['name']) . "</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Email</div>
                        <div><a href='mailto:" . htmlspecialchars($quote['email']) . "'>" . htmlspecialchars($quote['email']) . "</a></div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Phone</div>
                        <div><a href='tel:" . htmlspecialchars($quote['phone']) . "'>" . htmlspecialchars($quote['phone']) . "</a></div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Distance</div>
                        <div>🗺️ {$distance_km} km from Nelson</div>
                    </div>
                </div>
                
                <div class='info-item' style='margin: 15px 0;'>
                    <div class='info-label'>Services Requested</div>
                    <div>$services_text</div>
                </div>
                
                " . (!empty($quote['notes']) ? "<div class='info-item' style='margin: 15px 0;'>
                    <div class='info-label'>Customer Notes</div>
                    <div><em>\"" . htmlspecialchars($quote['notes']) . "\"</em></div>
                </div>" : "") . "
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://carpetree.com/admin-dashboard.html' class='btn btn-primary'>📊 Review in Dashboard</a>
                    <a href='mailto:" . htmlspecialchars($quote['email']) . "' class='btn btn-secondary'>📧 Contact Customer</a>
                </div>
                
                <div class='alert-box' style='background: #e8f5e8; border-color: #c3e6cb;'>
                    <p><strong>💡 Next Steps:</strong></p>
                    <ol>
                        <li>Review quote details in dashboard</li>
                        <li>Edit pricing with Ed Gilman prescriptions</li>
                        <li>Send interactive quote to customer</li>
                        <li>Schedule on-site work</li>
                    </ol>
                </div>
            </div>
            
            <div class='footer'>
                <p><strong>Carpe Tree'em Admin System</strong></p>
                <p>Based in the Kootenays on unceded Sinixt əmxʷúlaʔx territory</p>
                <p>This is an automated notification. You'll receive a reminder in 12 hours if not processed.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}

function sendSimpleEmail($to, $subject, $html_body, $quote_id) {
    // Simple email headers (no attachments)
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: Carpe Tree'em System <quotes@carpetree.com>\r\n";
    $headers .= "Reply-To: phil.bajenski@gmail.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Priority: 1\r\n";
    $headers .= "X-MSMail-Priority: High\r\n";
    
    // Send email
    $result = mail($to, $subject, $html_body, $headers);
    
    // Log the email attempt
    global $pdo;
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO email_log (recipient_email, subject, template_used, status, quote_id, sent_at, error_message)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        $log_stmt->execute([
            $to,
            $subject,
            'admin_alert_simple',
            $result ? 'sent' : 'failed',
            $quote_id,
            $result ? null : 'Mail function returned false'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log simple admin email: " . $e->getMessage());
    }
    
    return $result;
}

function calculateDistanceFromNelson($address) {
    $address_lower = strtolower($address);
    
    if (strpos($address_lower, 'nelson') !== false) {
        return rand(5, 15);
    } elseif (strpos($address_lower, 'castlegar') !== false) {
        return 25;
    } elseif (strpos($address_lower, 'trail') !== false) {
        return 45;
    } elseif (strpos($address_lower, 'rossland') !== false) {
        return 35;
    } elseif (strpos($address_lower, 'salmo') !== false) {
        return 55;
    } elseif (strpos($address_lower, 'kaslo') !== false) {
        return 75;
    } elseif (strpos($address_lower, 'new denver') !== false) {
        return 85;
    } elseif (strpos($address_lower, 'nakusp') !== false) {
        return 120;
    } else {
        return rand(20, 60);
    }
}

// Function to send 12-hour reminder
function sendReminderEmail($quote_id) {
    global $pdo;
    
    try {
        // Check if quote is still pending
        $stmt = $pdo->prepare("
            SELECT q.*, c.name, c.email 
            FROM quotes q 
            JOIN customers c ON q.customer_id = c.id 
            WHERE q.id = ? AND q.quote_status IN ('ai_processing', 'draft_ready', 'admin_review')
        ");
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quote) {
            return false; // Quote no longer pending
        }
        
        // Check if reminder already sent
        $reminder_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM email_log 
            WHERE quote_id = ? AND template_used = 'admin_reminder' AND status = 'sent'
        ");
        $reminder_stmt->execute([$quote_id]);
        $reminder_count = $reminder_stmt->fetchColumn();
        
        if ($reminder_count > 0) {
            return false; // Reminder already sent
        }
        
        $subject = "⏰ REMINDER: Quote #{$quote_id} still pending - Carpe Tree'em";
        
        $html_body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #ff9800; color: white; padding: 20px; border-radius: 8px; text-align: center;'>
                <h1>⏰ Quote Reminder</h1>
                <h2>Quote #{$quote_id} - Still Pending</h2>
            </div>
            
            <div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 20px; margin: 20px 0;'>
                <h3>🚨 12-Hour Reminder</h3>
                <p><strong>Quote #{$quote_id} for {$quote['name']} ({$quote['email']}) has been waiting for 12+ hours.</strong></p>
                <p>Customer is likely expecting a response soon.</p>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://carpetree.com/admin-dashboard.html' style='display: inline-block; padding: 15px 30px; background: #2D5A27; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>📊 Review Now</a>
            </div>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 6px; font-size: 0.9rem; color: #666;'>
                <p><strong>Carpe Tree'em Admin Reminder</strong></p>
                <p>This reminder is sent automatically for quotes pending over 12 hours.</p>
            </div>
        </div>";
        
        return sendSimpleEmail(
            $ADMIN_EMAIL ?? 'phil.bajenski@gmail.com',
            $subject,
            $html_body,
            $quote_id
        );
        
    } catch (Exception $e) {
        error_log("Reminder email error: " . $e->getMessage());
        return false;
    }
}

// If called directly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $quote_id = $input['quote_id'] ?? $_GET['quote_id'] ?? null;
    $action = $input['action'] ?? $_GET['action'] ?? 'alert';
    
    if ($quote_id) {
        if ($action === 'reminder') {
            $result = sendReminderEmail($quote_id);
        } else {
            $result = sendSimpleAdminAlert($quote_id);
        }
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Notification sent' : 'Failed to send notification'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Quote ID required']);
    }
}
?> 