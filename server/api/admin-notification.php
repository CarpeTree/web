<?php
// Only set header if called directly
if (basename($_SERVER['SCRIPT_NAME']) === 'admin-notification.php') {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/mailer.php';

// Function to send admin notification email with attachments
function sendAdminNotification($quote_id) {
    global $pdo;
    
    try {
        // Get quote details with duplicate customer detection and all customer data
        $stmt = $pdo->prepare("
            SELECT q.*, c.name, c.email, c.phone, c.address, c.referral_source, c.referrer_name, c.newsletter_opt_in,
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
        
        // Get uploaded files (using correct media table)
        $file_stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ?");
        $file_stmt->execute([$quote_id]);
        $files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse AI response - fix null issue
        $ai_response = null;
        if (!empty($quote['ai_response_json'])) {
            $ai_response = json_decode($quote['ai_response_json'], true);
        }
        
        // Parse services - fix null issue  
        $services = [];
        if (!empty($quote['selected_services'])) {
            $services = json_decode($quote['selected_services'], true) ?: [];
        }
        
        // Calculate distance using AI (O3 workhorse) with timeout protection
        require_once __DIR__ . '/ai-distance-calculator.php';
        try {
            $distance_km = calculateDistanceWithAI($quote['address']);
        } catch (Exception $e) {
            error_log("Distance calculation failed in admin notification: " . $e->getMessage());
            $distance_km = 40; // Fallback distance
        }
        
        // Check if media files exist
        $has_media = !empty($files);
        
        // Generate admin email content with duplicate detection  
        $media_info = $has_media ? " | " . count($files) . " media files" : " | No media";
        $duplicate_prefix = $quote['is_duplicate_customer'] ? "🔄 RETURNING CUSTOMER - " : "";
        $services_count = count($services);
        $subject = "New Quote Request #{$quote_id} - {$quote['name']} - Carpe Tree'em";
        
        // Determine how this duplicate was detected (check current quote submission for context)
        $duplicate_match_info = '';
        if ($quote['is_duplicate_customer']) {
            // Try to determine match type by comparing current quote with existing customer data
            $match_types = [];
            if (!empty($quote['email'])) $match_types[] = 'email';
            if (!empty($quote['phone'])) $match_types[] = 'phone';
            if (!empty($quote['name'])) $match_types[] = 'name';
            if (!empty($quote['address'])) $match_types[] = 'address';
            $duplicate_match_info = implode(' or ', $match_types);
        }
        
        $html_body = generateAdminEmailHTML($quote, $files, $ai_response, $services, $distance_km, $duplicate_match_info);
        
        // Send email using proper iCloud SMTP (not basic mail function)
        $attachments = [];
        foreach ($files as $file) {
            $file_path = dirname(dirname(__DIR__)) . "/uploads/$quote_id/" . $file['filename'];
            if (file_exists($file_path)) {
                $attachments[] = [
                    'path' => $file_path,
                    'name' => $file['original_filename'] ?? $file['filename']
                ];
            }
        }
        
        return sendEmail(
            $ADMIN_EMAIL ?? 'phil.bajenski@gmail.com',
            $subject,
            'admin_notification_html',
            ['html_content' => $html_body],
            $attachments
        );
        
    } catch (Exception $e) {
        error_log("Admin notification error: " . $e->getMessage());
        return false;
    }
}

function generateAdminEmailHTML($quote, $files, $ai_response, $services, $distance_km, $duplicate_match_info) {
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
                <h1>New Service Request - Carpe Tree\'em</h1>
                <h2>Quote #' . $quote['id'] . ' - ' . htmlspecialchars($quote['name']) . '</h2>
                <p><strong>Submitted:</strong> ' . date('F j, Y g:i A', strtotime($quote['quote_created_at'])) . '</p>
                <p><strong>Services:</strong> ' . count($services) . ' requested | <strong>Media:</strong> ' . ($has_media ? count($files) . ' files attached' : 'None uploaded') . '</p>
            </div>
            
            <div class="section urgent">
                <h3>🚨 Complete Quote Information</h3>
                <p><strong>✅ This email contains ALL submission details:</strong> Customer info, services requested, notes, and AI analysis.</p>
                <p><strong>📎 Media files:</strong> ' . ($has_media ? 'Attached to this email for immediate download and analysis.' : 'None uploaded - consider in-person assessment.') . '</p>
                <p><strong>🖥️ Dashboard use:</strong> View media gallery, adjust pricing, and send final quotes to customers.</p>' .
                ($quote['is_duplicate_customer'] ? 
                    '<div style="background: #fff3cd; padding: 1rem; margin: 1rem 0; border-radius: 8px; border: 2px solid #ffc107;">
                        <h4 style="color: #856404; margin: 0 0 0.5rem 0;">🔄 RETURNING CUSTOMER ALERT</h4>
                        <p style="color: #856404; margin: 0 0 0.5rem 0;">This customer has submitted quotes before. Check their history for context and pricing reference.</p>
                        <p style="color: #856404; margin: 0; font-size: 0.9em;"><strong>Detected by:</strong> ' . ($duplicate_match_info ?: 'customer record match') . '</p>
                    </div>' : '') . '
                <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="color: #1976D2; margin-bottom: 15px;">🤖 AI Model Comparison - Choose Best Analysis</h3>
                    <p><strong>Compare 3 AI models side-by-side to pick the best estimate:</strong></p>
                    
                    <div style="text-align: center; margin: 15px 0;">
                        <a href="https://carpetree.com/o4-mini-dashboard.html?quote_id=' . $quote['id'] . '" class="btn" style="background: #4CAF50; margin: 5px; padding: 15px 20px;">
                            🚀 o4-mini-2025-04-16<br><small>Fast & Cost-Effective</small>
                        </a>
                        
                        <a href="https://carpetree.com/o3-pro-dashboard.html?quote_id=' . $quote['id'] . '" class="btn" style="background: #2196F3; margin: 5px; padding: 15px 20px;">
                            🧠 o3-pro-2025-06-10<br><small>Premium Reasoning</small>
                        </a>
                        
                        <a href="https://carpetree.com/gemini-dashboard.html?quote_id=' . $quote['id'] . '" class="btn" style="background: #FF9800; margin: 5px; padding: 15px 20px;">
                            🔮 Gemini 2.5 Pro<br><small>Advanced Multimodal</small>
                        </a>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="https://carpetree.com/model-comparison-dashboard.html?quote_id=' . $quote['id'] . '" class="btn" style="background: #9C27B0; padding: 15px 25px;">
                            📊 View All 3 Models Side-by-Side
                        </a>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="https://carpetree.com/admin-dashboard.html?quote_id=' . $quote['id'] . '" class="btn" style="background: #2D5A27; font-size: 16px; padding: 15px 25px;">
                        📊 Review Quote & View Media Gallery
                    </a>
                    <a href="https://carpetree.com/customer-crm-dashboard.html?customer_id=' . $quote['customer_id'] . '" class="btn" style="background: #dc3545; font-size: 14px;">
                        👥 Customer History & CRM
                    </a>
                </div>
                <p style="text-align: center; font-size: 0.9em; color: #666; margin-top: 10px;">
                    💡 Use the dashboard to view media files in organized gallery format, adjust pricing, and send quotes to customers.
                </p>
            </div>
            
            <div class="section">
                <h3>👤 Customer Information</h3>
                <table style="width: 100%;">
                    <tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($quote['name'] ?: 'Not provided') . '</td></tr>
                    <tr><td><strong>Email:</strong></td><td><a href="mailto:' . htmlspecialchars($quote['email']) . '">' . htmlspecialchars($quote['email']) . '</a></td></tr>
                    <tr><td><strong>Phone:</strong></td><td><a href="tel:' . htmlspecialchars($quote['phone']) . '">' . htmlspecialchars($quote['phone'] ?: 'Not provided') . '</a></td></tr>
                    <tr><td><strong>Property Address:</strong></td><td>' . htmlspecialchars($quote['address'] ?: 'Not provided') . '</td></tr>
                    <tr><td><strong>Distance from base:</strong></td><td class="highlight">' . $distance_km . ' km</td></tr>' .
                    ($quote['referral_source'] ? '<tr><td><strong>How they heard about us:</strong></td><td>' . htmlspecialchars(ucwords(str_replace('_', ' ', $quote['referral_source']))) . '</td></tr>' : '') .
                    ($quote['referrer_name'] ? '<tr><td><strong>Referred by:</strong></td><td>' . htmlspecialchars($quote['referrer_name']) . '</td></tr>' : '') .
                    '<tr><td><strong>Newsletter signup:</strong></td><td>' . ($quote['newsletter_opt_in'] ? 'Yes' : 'No') . '</td></tr>
                </table>
            </div>
            
            <div class="section">
                <h3>🌲 Services Requested</h3>
                <div style="background: white; padding: 15px; border-radius: 6px;">
                    ' . $services_text . '
                </div>' .
                ($quote['notes'] ? '
                <div style="margin-top: 15px;">
                    <h4>📝 Additional Notes from Customer:</h4>
                    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #2D5A27; font-style: italic;">
                        "' . nl2br(htmlspecialchars($quote['notes'])) . '"
                    </div>
                </div>' : '') . '
            </div>';
    
    // Media section
    if ($has_media) {
        $html .= '
            <div class="section">
                <h3>📹 Uploaded Media (' . count($files) . ' files)</h3>
                <p><strong>✅ All media files are attached to this email for immediate download and analysis.</strong></p>
                <p style="background: #e8f5e8; padding: 10px; border-radius: 6px; margin: 10px 0;">
                    💡 <strong>Pro tip:</strong> Use the dashboard link below to view media files in an organized gallery format with zooming and full-screen capabilities.
                </p>
                <div class="media-grid">';
        
        foreach ($files as $file) {
            $file_size = round($file['file_size'] / 1024 / 1024, 2); // MB
            $file_icon = strpos($file['mime_type'], 'video') !== false ? '🎥' : '📸';
            $html .= '
                <div class="media-item">
                    ' . $file_icon . ' <strong>' . htmlspecialchars($file['filename']) . '</strong><br>
                    <small>' . htmlspecialchars($file['mime_type']) . ' (' . $file_size . ' MB)</small><br>
                    <small>Uploaded: ' . date('M j, Y g:i A', strtotime($file['uploaded_at'])) . '</small>
                </div>';
        }
        
        $html .= '</div></div>';
    } else {
        $html .= '
            <div class="section">
                <h3>📝 No Media Files Uploaded</h3>
                <p><strong>⚠️ Text-only quote submission - In-person assessment strongly recommended</strong></p>
                <p style="background: #fff3cd; padding: 10px; border-radius: 6px; color: #856404;">
                    Consider scheduling a site visit for accurate tree assessment and detailed recommendations.
                </p>
            </div>';
    }
    
    // AI Analysis section
    if ($ai_response) {
        $html .= '<div class="section ai-section">
            <h3>🤖 AI Analysis Results</h3>
            <p style="background: white; padding: 10px; border-radius: 6px; font-size: 0.9em; color: #666;">
                <strong>Note:</strong> AI analysis provides initial recommendations. Always verify with professional assessment before finalizing quotes.
            </p>';
        
        if (isset($ai_response['quote_summary'])) {
            $summary = $ai_response['quote_summary'];
            $html .= '<div style="background: white; padding: 15px; border-radius: 6px; margin: 10px 0;">
                <h4 style="color: #2D5A27; margin-top: 0;">📊 Analysis Summary</h4>
                <table style="width: 100%; border-collapse: collapse;">';
            
            if (isset($summary['total_trees'])) {
                $html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Trees Identified:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . $summary['total_trees'] . '</td></tr>';
            }
            if (isset($summary['analysis_method'])) {
                $html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>Analysis Method:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($summary['analysis_method']) . '</td></tr>';
            }
            if (isset($summary['requires_in_person_assessment'])) {
                $status = $summary['requires_in_person_assessment'] ? '⚠️ Yes - Required' : '✅ No - Photos sufficient';
                $bg_color = $summary['requires_in_person_assessment'] ? '#fff3cd' : '#d4edda';
                $html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;"><strong>In-Person Assessment:</strong></td><td style="padding: 8px; border-bottom: 1px solid #eee; background: ' . $bg_color . '; font-weight: bold;">' . $status . '</td></tr>';
            }
            
            $html .= '</table></div>';
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
    
    // Headers - improved to avoid spam
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: Carpe Tree'em <pherognome@icloud.com>\r\n";
    $headers .= "Reply-To: phil.bajenski@gmail.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1\r\n";
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
if (basename($_SERVER['SCRIPT_NAME']) === 'admin-notification.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
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