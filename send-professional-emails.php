<?php
// Send professional HTML emails using PHP mail()
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== SENDING PROFESSIONAL HTML EMAILS ===\n\n";

function loadHtmlTemplate($template_name, $data) {
    $template_path = __DIR__ . "/server/templates/$template_name.html";
    
    if (!file_exists($template_path)) {
        throw new Exception("Template not found: $template_name");
    }
    
    $html = file_get_contents($template_path);
    
    // Replace simple variables
    foreach ($data as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            $html = str_replace("{{$key}}", htmlspecialchars($value), $html);
        }
    }
    
    // Handle cost items array
    if (isset($data['cost_items']) && is_array($data['cost_items'])) {
        $cost_items_html = '';
        foreach ($data['cost_items'] as $item) {
            $cost_items_html .= '<div class="cost-item">';
            $cost_items_html .= '<span>' . htmlspecialchars($item['service_name']) . '</span>';
            $cost_items_html .= '<span>$' . number_format($item['cost'], 2) . '</span>';
            $cost_items_html .= '</div>';
        }
        $html = str_replace('{{#cost_items}}', '', $html);
        $html = str_replace('{{/cost_items}}', $cost_items_html, $html);
    }
    
    // Handle recommendations
    if (isset($data['recommendations']) && is_array($data['recommendations'])) {
        $rec_html = '';
        foreach ($data['recommendations'] as $rec) {
            $rec_html .= '<li>' . htmlspecialchars($rec['description']) . '</li>';
        }
        $html = str_replace('{{#has_recommendations}}', '', $html);
        $html = str_replace('{{/has_recommendations}}', '', $html);
        $html = str_replace('{{#recommendations}}', '', $html);
        $html = str_replace('{{/recommendations}}', $rec_html, $html);
    }
    
    // Handle conditional sections
    $html = str_replace('{{#requires_assessment}}', '', $html);
    $html = str_replace('{{/requires_assessment}}', '', $html);
    $html = str_replace('{{^requires_assessment}}', '<!--', $html);
    $html = str_replace('{{/^requires_assessment}}', '-->', $html);
    $html = str_replace('{{#has_files}}', '', $html);
    $html = str_replace('{{/has_files}}', '', $html);
    
    return $html;
}

try {
    // Get quotes ready for professional emails
    $stmt = $pdo->prepare("
        SELECT q.*, c.email, c.name, c.address
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.quote_status = 'draft_ready' 
        AND q.ai_analysis_complete = 1
        AND c.email = 'phil.bajenski@gmail.com'
        ORDER BY q.quote_created_at DESC
    ");
    $stmt->execute();
    $quotes = $stmt->fetchAll();
    
    if (empty($quotes)) {
        echo "❌ No quotes ready for professional emails\n";
        exit;
    }
    
    echo "✅ Found " . count($quotes) . " quotes ready for professional emails:\n\n";
    
    foreach ($quotes as $quote) {
        echo "Processing Quote #{$quote['id']}...\n";
        
        // Parse quote data
        $ai_response = json_decode($quote['ai_response_json'], true);
        $services = json_decode($quote['selected_services'], true) ?: [];
        $services_text = implode(', ', array_map('ucfirst', $services));
        
        // Prepare template data
        $template_data = [
            'quote_id' => $quote['id'],
            'customer_name' => $quote['name'],
            'services_list' => $services_text,
            'address' => $quote['address'] ?: 'Location provided',
            'analysis_method' => $ai_response['quote_summary']['analysis_method'] ?? 'Text-based analysis',
            'total_estimate' => number_format($quote['total_estimate'], 2),
            'files_count' => 0,
            'cost_items' => [],
            'recommendations' => $ai_response['recommendations'] ?? []
        ];
        
        // Build cost breakdown
        if (isset($ai_response['cost_breakdown'])) {
            foreach ($ai_response['cost_breakdown'] as $item) {
                $template_data['cost_items'][] = [
                    'service_name' => ucfirst($item['service']),
                    'cost' => $item['estimated_cost']
                ];
            }
        }
        
        echo "Template data prepared\n";
        
        // Load and process HTML template
        try {
            $html_body = loadHtmlTemplate('quote_ready_professional', $template_data);
            echo "✅ HTML template loaded\n";
        } catch (Exception $e) {
            echo "⚠️ Could not load HTML template, using simple format\n";
            
            // Fallback to simple HTML
            $html_body = "
            <html>
            <body style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #2D5A27;'>🌳 Your Tree Service Quote #{$quote['id']}</h2>
                <p>Dear {$quote['name']},</p>
                <p>Thank you for your tree service request! Here's your quote:</p>
                <div style='background: #f8f9fa; padding: 20px; border-left: 4px solid #2D5A27;'>
                    <h3>Quote Summary</h3>
                    <p><strong>Services:</strong> $services_text</p>
                    <p><strong>Total Estimate:</strong> $" . number_format($quote['total_estimate'], 2) . "</p>
                </div>
                <p>Contact us at 778-655-3741 or sapport@carpetree.com</p>
                <p>Best regards,<br>The Carpe Tree'em Team</p>
            </body>
            </html>";
        }
        
        // Email headers for HTML
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Carpe Tree'em <quotes@carpetree.com>\r\n";
        $headers .= "Reply-To: sapport@carpetree.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        $subject = "🌳 Your Professional Tree Service Quote #{$quote['id']} - Carpe Tree'em";
        
        echo "Sending professional HTML email...\n";
        echo "To: {$quote['email']}\n";
        echo "Subject: $subject\n";
        
        // Send HTML email
        $mail_sent = mail($quote['email'], $subject, $html_body, $headers);
        
        if ($mail_sent) {
            echo "✅ Professional HTML email sent successfully!\n";
            
            // Log email
            $log_stmt = $pdo->prepare("
                INSERT INTO email_log (recipient_email, subject, template_used, status, quote_id, sent_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $quote['email'],
                $subject,
                'quote_ready_professional',
                'sent',
                $quote['id']
            ]);
            
            // Update quote status
            $update_stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'sent_to_client' WHERE id = ?");
            $update_stmt->execute([$quote['id']]);
            
            echo "✅ Quote status updated to 'sent_to_client'\n";
            
        } else {
            echo "❌ Failed to send email\n";
        }
        
        echo "---\n\n";
    }
    
    echo "=== EMAIL SENDING COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 