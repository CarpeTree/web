<?php
header('Content-Type: application/json');
require_once '../config/database-simple.php';
require_once '../config/config.php';

// Function to send admin notification email with attachments
function sendAdminNotification($quote_id) {
    global $pdo;
    
    try {
        // Get quote details with duplicate customer detection
        $stmt = $pdo->prepare("
            SELECT q.*, c.name, c.email, c.phone, c.address,
                   COUNT(q2.id) as total_customer_quotes
            FROM quotes q 
            JOIN customers c ON q.customer_id = c.id 
            LEFT JOIN quotes q2 ON c.id = q2.customer_id
            WHERE q.id = ?
            GROUP BY q.id, c.id
        ");
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quote) {
            throw new Exception("Quote not found");
        }
        
        // Mark if this is a duplicate/returning customer
        $quote['is_duplicate_customer'] = (int)$quote['total_customer_quotes'] > 1;
        
        // Get uploaded files
        $file_stmt = $pdo->prepare("SELECT * FROM uploaded_files WHERE quote_id = ?");
        $file_stmt->execute([$quote_id]);
        $files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse AI response
        $ai_response = json_decode($quote['ai_response_json'], true);
        $services = json_decode($quote['selected_services'], true) ?: [];
        
        // Calculate distance from Nelson
        $distance_km = calculateDistanceFromNelson($quote['address']);
        
        // Generate admin email content with duplicate detection
        $duplicate_prefix = $quote['is_duplicate_customer'] ? "🔄 RETURNING CUSTOMER - " : "";
        $subject = "🌳 {$duplicate_prefix}New Quote Ready for Review - Quote #{$quote_id}";
        
        // Determine how this duplicate was detected (check current quote submission for context)
        $duplicate_match_info = '';
        if ($quote['is_duplicate_customer']) {
            // Try to determine match type by comparing current quote with existing customer data
            $match_types = [];
            if (!empty($quote['email'])) $match_types[] = 'email';
            if (!empty($quote['phone'])) $match_types[] = 'phone';
            if (!empty($quote['name'])) $match_types[] = 'name';
            if (!empty($quote['address'])) $match_types[] = 'address';
            $duplicate_match_info = implode('/or ', $match_types);
        }
        
        $html_body = generateAdminEmailHTML($quote, $files, $ai_response, $services, $distance_km);
        
        // Send email with attachments
        return sendEmailWithAttachments(
            $ADMIN_EMAIL ?? 'sapport@carpetree.com',
            $subject,
            $html_body,
            $files,
            $quote_id
        );
        
    } catch (Exception $e) {
        error_log("Admin notification error: " . $e->getMessage());
        return false;
    }
}

function generateAdminEmailHTML($quote, $files, $ai_response, $services, $distance_km) {
    $services_text = implode(', ', array_map('ucfirst', $services));
    $has_media = !empty($files);
    
    $estimated_total = $quote['total_estimate'] ?? 0;
    if ($ai_response && isset($ai_response['quote_summary']['estimated_total_cost'])) {
        $estimated_total = $ai_response['quote_summary']['estimated_total_cost'];
    }
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { background: #2D5A27; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
            .section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #2D5A27; }
            .urgent { background: #fff3cd; border-left-color: #856404; }
            .ai-section { background: #e8f5e8; border-left-color: #2D5A27; }
            .media-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 10px 0; }
            .media-item { background: white; padding: 10px; border-radius: 6px; text-align: center; }
            .pricing-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            .pricing-table th, .pricing-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .pricing-table th { background: #2D5A27; color: white; }
            .total-row { background: #e8f5e8; font-weight: bold; }
            .action-buttons { text-align: center; margin: 20px 0; }
            .btn { display: inline-block; padding: 12px 24px; background: #2D5A27; color: white; text-decoration: none; border-radius: 6px; margin: 5px; }
            .highlight { background: #fff3cd; padding: 2px 6px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🌳 New Quote Ready for Your Review</h1>
                <h2>Quote #' . $quote['id'] . '</h2>
                <p>Submitted: ' . date('F j, Y g:i A', strtotime($quote['quote_created_at'])) . '</p>
            </div>
            
            <div class="section urgent">
                <h3>🚨 Action Required</h3>
                <p><strong>This quote needs your personal review and pricing adjustment.</strong></p>
                <p>Media files are attached to this email for your analysis.</p>' .
                ($quote['is_duplicate_customer'] ? 
                    '<div style="background: #fff3cd; padding: 1rem; margin: 1rem 0; border-radius: 8px; border: 2px solid #ffc107;">
                        <h4 style="color: #856404; margin: 0 0 0.5rem 0;">🔄 RETURNING CUSTOMER ALERT</h4>
                        <p style="color: #856404; margin: 0 0 0.5rem 0;">This customer has submitted quotes before. Check their history for context and pricing reference.</p>
                        <p style="color: #856404; margin: 0; font-size: 0.9em;"><strong>Detected by:</strong> ' . ($duplicate_match_info ?: 'customer record match') . '</p>
                    </div>' : '') . '
                <div class="action-buttons">
                    <a href="https://carpetree.com/admin-dashboard.html?quote_id=' . $quote['id'] . '" class="btn">📊 Review This Quote</a>
                    <a href="https://carpetree.com/customer-crm-dashboard.html?customer_id=' . $quote['customer_id'] . '" class="btn" style="background: #dc3545;">👥 Customer CRM Dashboard</a>
                </div>
            </div>
            
            <div class="section">
                <h3>👤 Customer Information</h3>
                <table style="width: 100%;">
                    <tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($quote['name']) . '</td></tr>
                    <tr><td><strong>Email:</strong></td><td><a href="mailto:' . htmlspecialchars($quote['email']) . '">' . htmlspecialchars($quote['email']) . '</a></td></tr>
                    <tr><td><strong>Phone:</strong></td><td><a href="tel:' . htmlspecialchars($quote['phone']) . '">' . htmlspecialchars($quote['phone']) . '</a></td></tr>
                    <tr><td><strong>Address:</strong></td><td>' . htmlspecialchars($quote['address']) . '</td></tr>
                    <tr><td><strong>Distance:</strong></td><td class="highlight">' . $distance_km . ' km from Nelson</td></tr>
                    <tr><td><strong>Services:</strong></td><td>' . $services_text . '</td></tr>
                </table>
            </div>';
    
    // Media section
    if ($has_media) {
        $html .= '
            <div class="section">
                <h3>📹 Uploaded Media (' . count($files) . ' files)</h3>
                <p><strong>All media files are attached to this email for download.</strong></p>
                <div class="media-grid">';
        
        foreach ($files as $file) {
            $file_size = round($file['file_size'] / 1024 / 1024, 2); // MB
            $html .= '
                <div class="media-item">
                    <strong>' . htmlspecialchars($file['filename']) . '</strong><br>
                    <small>' . htmlspecialchars($file['mime_type']) . ' (' . $file_size . ' MB)</small><br>
                    <small>Uploaded: ' . date('M j, Y g:i A', strtotime($file['uploaded_at'])) . '</small>
                </div>';
        }
        
        $html .= '</div></div>';
    } else {
        $html .= '
            <div class="section">
                <h3>📝 No Media Files</h3>
                <p><strong>Text-only quote - In-person assessment recommended</strong></p>
            </div>';
    }
    
    // AI Analysis section
    if ($ai_response) {
        $html .= '<div class="section ai-section"><h3>🤖 AI Analysis Results</h3>';
        
        if (isset($ai_response['quote_summary'])) {
            $summary = $ai_response['quote_summary'];
            $html .= '<h4>Analysis Summary</h4><ul>';
            
            if (isset($summary['total_trees'])) {
                $html .= '<li><strong>Trees Identified:</strong> ' . $summary['total_trees'] . '</li>';
            }
            if (isset($summary['analysis_method'])) {
                $html .= '<li><strong>Analysis Method:</strong> ' . htmlspecialchars($summary['analysis_method']) . '</li>';
            }
            if (isset($summary['requires_in_person_assessment'])) {
                $status = $summary['requires_in_person_assessment'] ? 'Yes' : 'No';
                $html .= '<li><strong>In-Person Assessment Required:</strong> ' . $status . '</li>';
            }
            
            $html .= '</ul>';
        }
        
        // Cost breakdown
        if (isset($ai_response['cost_breakdown']) && is_array($ai_response['cost_breakdown'])) {
            $html .= '<h4>AI Cost Estimation</h4>
                <table class="pricing-table">
                    <tr><th>Service</th><th>AI Estimate</th><th>Notes</th></tr>';
            
            $total = 0;
            foreach ($ai_response['cost_breakdown'] as $item) {
                $cost = $item['estimated_cost'] ?? 0;
                $total += $cost;
                $html .= '<tr>
                    <td>' . htmlspecialchars(ucfirst($item['service'])) . '</td>
                    <td>$' . number_format($cost, 2) . '</td>
                    <td>' . htmlspecialchars($item['notes'] ?? '') . '</td>
                </tr>';
            }
            
            $html .= '<tr class="total-row">
                <td><strong>AI Total Estimate</strong></td>
                <td><strong>$' . number_format($total, 2) . '</strong></td>
                <td>Preliminary - requires your adjustment</td>
            </tr></table>';
        }
        
        // Recommendations
        if (isset($ai_response['recommendations']) && is_array($ai_response['recommendations'])) {
            $html .= '<h4>AI Recommendations</h4><ul>';
            foreach ($ai_response['recommendations'] as $rec) {
                if (isset($rec['description'])) {
                    $priority = isset($rec['priority']) ? strtoupper($rec['priority']) : '';
                    $html .= '<li><strong>[' . $priority . ']</strong> ' . htmlspecialchars($rec['description']) . '</li>';
                }
            }
            $html .= '</ul>';
        }
        
        $html .= '</div>';
    }
    
    // Customer notes
    if (!empty($quote['notes'])) {
        $html .= '
            <div class="section">
                <h3>💬 Customer Notes</h3>
                <p><em>"' . htmlspecialchars($quote['notes']) . '"</em></p>
            </div>';
    }
    
    // Travel cost calculation
    $truck_cost = $distance_km * 1.00;
    $car_cost = $distance_km * 0.35;
    
    $html .= '
            <div class="section">
                <h3>🚗 Travel Cost Calculation</h3>
                <table class="pricing-table">
                    <tr><th>Vehicle</th><th>Rate</th><th>Distance</th><th>Cost</th></tr>
                    <tr><td>🚛 Truck</td><td>$1.00/km</td><td>' . $distance_km . ' km</td><td>$' . number_format($truck_cost, 2) . '</td></tr>
                    <tr><td>🚗 Car</td><td>$0.35/km</td><td>' . $distance_km . ' km</td><td>$' . number_format($car_cost, 2) . '</td></tr>
                </table>
            </div>
            
            <div class="action-buttons">
                <a href="https://carpetree.com/admin-dashboard.html?quote_id=' . $quote['id'] . '" class="btn">📊 Review & Edit Quote</a>
                <a href="https://carpetree.com/customer-crm-dashboard.html?customer_id=' . $quote['customer_id'] . '" class="btn" style="background: #dc3545;">👥 Customer History</a>
                <a href="mailto:' . htmlspecialchars($quote['email']) . '" class="btn">📧 Contact Customer</a>
            </div>
            
            <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #666;">
                <p>Carpe Tree\'em Admin Notification System</p>
                <p>Based in the Kootenays on unceded Sinixt əmxʷúlaʔx territory</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

function sendEmailWithAttachments($to, $subject, $html_body, $files, $quote_id) {
    $boundary = md5(uniqid(time()));
    
    // Headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: Carpe Tree'em System <quotes@carpetree.com>\r\n";
    $headers .= "Reply-To: sapport@carpetree.com\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    
    // Email body
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $html_body . "\r\n\r\n";
    
    // Add file attachments
    foreach ($files as $file) {
        $file_path = dirname(dirname(__DIR__)) . "/uploads/$quote_id/" . $file['filename'];
        
        if (file_exists($file_path)) {
            $file_content = file_get_contents($file_path);
            $file_content_base64 = chunk_split(base64_encode($file_content));
            
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: " . $file['mime_type'] . "; name=\"" . $file['filename'] . "\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . $file['filename'] . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $file_content_base64 . "\r\n";
        }
    }
    
    $body .= "--$boundary--\r\n";
    
    // Send email
    $result = mail($to, $subject, $body, $headers);
    
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
            'admin_notification',
            $result ? 'sent' : 'failed',
            $quote_id,
            $result ? null : 'Mail function returned false'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log admin email: " . $e->getMessage());
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

// If called directly, send notification for a specific quote
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $quote_id = $input['quote_id'] ?? $_GET['quote_id'] ?? null;
    
    if ($quote_id) {
        $result = sendAdminNotification($quote_id);
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Admin notification sent' : 'Failed to send notification'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Quote ID required']);
    }
}
?> 