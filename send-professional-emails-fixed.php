<?php
// Send professional HTML emails with proper template processing
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== SENDING PROFESSIONAL HTML EMAILS (FIXED) ===\n\n";

function loadHtmlTemplate($template_name, $data) {
    $template_path = __DIR__ . "/server/templates/$template_name.html";
    
    if (!file_exists($template_path)) {
        throw new Exception("Template not found: $template_name");
    }
    
    $html = file_get_contents($template_path);
    
    // Replace simple variables with proper formatting
    foreach ($data as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            $html = str_replace("{{" . $key . "}}", $value, $html);
        }
    }
    
    // Handle cost items array - process the template section
    if (isset($data['cost_items']) && is_array($data['cost_items'])) {
        $cost_items_html = '';
        foreach ($data['cost_items'] as $item) {
            $cost_items_html .= '
                <div class="cost-item">
                    <span>' . htmlspecialchars($item['service_name']) . '</span>
                    <span>$' . number_format($item['cost'], 2) . '</span>
                </div>';
        }
        
        // Replace the template section with actual items
        $pattern = '/{{#cost_items}}.*?{{\/cost_items}}/s';
        $html = preg_replace($pattern, $cost_items_html, $html);
    }
    
    // Handle recommendations
    if (isset($data['recommendations']) && is_array($data['recommendations'])) {
        $rec_html = '';
        foreach ($data['recommendations'] as $rec) {
            $rec_html .= '<li>' . htmlspecialchars($rec['description']) . '</li>';
        }
        
        // Show recommendations section
        $html = str_replace('{{#has_recommendations}}', '', $html);
        $html = str_replace('{{/has_recommendations}}', '', $html);
        
        // Replace recommendations list
        $pattern = '/{{#recommendations}}.*?{{\/recommendations}}/s';
        $html = preg_replace($pattern, $rec_html, $html);
    } else {
        // Remove recommendations section entirely
        $pattern = '/{{#has_recommendations}}.*?{{\/has_recommendations}}/s';
        $html = preg_replace($pattern, '', $html);
    }
    
    // Handle conditional sections for assessment
    if (isset($data['requires_assessment']) && $data['requires_assessment']) {
        $html = str_replace('{{#requires_assessment}}', '', $html);
        $html = str_replace('{{/requires_assessment}}', '', $html);
        $html = str_replace('{{^requires_assessment}}', '<!--', $html);
        $html = str_replace('{{/^requires_assessment}}', '-->', $html);
    } else {
        $html = str_replace('{{^requires_assessment}}', '', $html);
        $html = str_replace('{{/^requires_assessment}}', '', $html);
        $html = str_replace('{{#requires_assessment}}', '<!--', $html);
        $html = str_replace('{{/requires_assessment}}', '-->', $html);
    }
    
    // Handle files section
    if (isset($data['has_files']) && $data['has_files']) {
        $html = str_replace('{{#has_files}}', '', $html);
        $html = str_replace('{{/has_files}}', '', $html);
    } else {
        $pattern = '/{{#has_files}}.*?{{\/has_files}}/s';
        $html = preg_replace($pattern, '', $html);
    }
    
    return $html;
}

try {
    // Get quotes ready for professional emails (targeting the specific quote)
    $stmt = $pdo->prepare("
        SELECT q.*, c.email, c.name, c.address
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.quote_status = 'draft_ready'
        AND q.ai_analysis_complete = 1
        AND q.id = 3
        ORDER BY q.quote_created_at DESC
    ");
    $stmt->execute();
    $quotes = $stmt->fetchAll();

    if (empty($quotes)) {
        echo "❌ No quotes ready for professional emails\n";
        
        // Let's check what quotes we have
        echo "\n=== CHECKING AVAILABLE QUOTES ===\n";
        $check_stmt = $pdo->prepare("
            SELECT q.id, q.quote_status, q.ai_analysis_complete, c.name, c.email
            FROM quotes q
            JOIN customers c ON q.customer_id = c.id
            ORDER BY q.quote_created_at DESC
            LIMIT 5
        ");
        $check_stmt->execute();
        $all_quotes = $check_stmt->fetchAll();
        
        foreach ($all_quotes as $quote) {
            echo "Quote #{$quote['id']}: {$quote['name']} - Status: {$quote['quote_status']} - AI: " . ($quote['ai_analysis_complete'] ? 'YES' : 'NO') . "\n";
        }
        exit;
    }

    echo "✅ Found " . count($quotes) . " quotes ready for professional emails:\n\n";

    foreach ($quotes as $quote) {
        echo "Processing Quote #{$quote['id']}...\n";

        // Parse quote data
        $ai_response = json_decode($quote['ai_response_json'], true);
        $services = json_decode($quote['selected_services'], true) ?: [];
        $services_text = implode(', ', array_map('ucfirst', $services));

        // Prepare template data with proper values
        $template_data = [
            'quote_id' => $quote['id'],
            'customer_name' => $quote['name'],
            'services_list' => $services_text,
            'address' => $quote['address'] ?: 'Location to be confirmed',
            'analysis_method' => ($ai_response['quote_summary']['analysis_method'] ?? 'Text-based analysis'),
            'total_estimate' => number_format($quote['total_estimate'], 2),
            'files_count' => 0,
            'has_files' => false,
            'requires_assessment' => true,
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

        echo "Template data prepared:\n";
        echo "- Customer: {$template_data['customer_name']}\n";
        echo "- Services: {$template_data['services_list']}\n";
        echo "- Total: \${$template_data['total_estimate']}\n";
        echo "- Cost items: " . count($template_data['cost_items']) . "\n";
        echo "- Recommendations: " . count($template_data['recommendations']) . "\n";

        // Load and process HTML template
        try {
            $html_body = loadHtmlTemplate('quote_ready_professional', $template_data);
            echo "✅ HTML template processed successfully\n";
        } catch (Exception $e) {
            echo "⚠️ Template error: " . $e->getMessage() . "\n";
            echo "Using fallback HTML...\n";

            // Fallback to simple HTML
            $html_body = generateFallbackHTML($template_data);
        }

        // Email headers for HTML
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Carpe Tree'em <quotes@carpetree.com>\r\n";
        $headers .= "Reply-To: sapport@carpetree.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        $subject = "🌳 Your Professional Tree Service Quote #{$quote['id']} - Carpe Tree'em";

        echo "Sending corrected professional HTML email...\n";
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
                'quote_ready_professional_fixed',
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

function generateFallbackHTML($data) {
    $cost_items_html = '';
    foreach ($data['cost_items'] as $item) {
        $cost_items_html .= '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee;">
            <span>' . htmlspecialchars($item['service_name']) . '</span>
            <span>$' . number_format($item['cost'], 2) . '</span>
        </div>';
    }
    
    $recommendations_html = '';
    foreach ($data['recommendations'] as $rec) {
        $recommendations_html .= '<li>' . htmlspecialchars($rec['description']) . '</li>';
    }

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #2D5A27 0%, #4A7C59 100%); color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px; }
            .section { background: #f8f9fa; border-left: 4px solid #2D5A27; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .cost-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
            .total { font-weight: bold; background: #e8f5e8; margin: 10px -10px 0; padding: 15px 10px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .btn { display: inline-block; padding: 12px 25px; background: #2D5A27; color: white; text-decoration: none; border-radius: 25px; margin: 10px 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🌳 Carpe Tree'em</h1>
                <h2>Your Tree Service Quote</h2>
                <div style='background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; display: inline-block;'>
                    Quote #{$data['quote_id']}
                </div>
            </div>
            
            <div class='content'>
                <h2>Dear {$data['customer_name']},</h2>
                <p>Thank you for choosing Carpe Tree'em! I'm based in the Kootenays on unceded Sinixt əmxʷúlaʔx territory and travel with my equipment to serve communities in need. I've completed analysis of your request.</p>
                
                <div class='section'>
                    <h3>📋 Quote Summary</h3>
                    <p><strong>Services Requested:</strong> {$data['services_list']}</p>
                    <p><strong>Property Location:</strong> {$data['address']}</p>
                    <p><strong>Analysis Method:</strong> {$data['analysis_method']}</p>
                </div>
                
                <div class='section'>
                    <h3>💰 Cost Breakdown</h3>
                    $cost_items_html
                    <div class='total cost-item'>
                        <span><strong>Total Estimate</strong></span>
                        <span><strong>\${$data['total_estimate']}</strong></span>
                    </div>
                </div>
                
                <div class='section' style='background: #fff3cd;'>
                    <h3>📹 Video Quote Limitations</h3>
                    <p><strong>Text-Only Analysis:</strong> This preliminary quote is based on your description. Please note:</p>
                    <ul>
                        <li>🔍 <strong>Visibility Limitations:</strong> Not all tree conditions are visible from the ground or in media</li>
                        <li>⚠️ <strong>Price Changes:</strong> If I discover conditions not visible in photos/videos, the price may change</li>
                        <li>💬 <strong>Communication:</strong> I will communicate any price changes before beginning work when possible</li>
                        <li>🌳 <strong>Tree Nature:</strong> Trees can have hidden defects that only become apparent during work</li>
                    </ul>
                    <p><strong>At this point, I will come to your property to complete the work.</strong> This quote represents my best assessment based on available information.</p>
                </div>
            </div>
            
            <div style='background: #2D5A27; color: white; padding: 20px; text-align: center;'>
                <h4>Ready to Move Forward?</h4>
                <p>Contact me to schedule your service or ask any questions:</p>
                <a href='tel:778-655-3741' class='btn'>📞 Call Now</a>
                <a href='mailto:sapport@carpetree.com' class='btn'>📧 Email Me</a>
            </div>
            
            <div class='footer'>
                <p><strong>Carpe Tree'em - Professional Tree Care Services</strong></p>
                <p>📞 778-655-3741 | 📧 sapport@carpetree.com</p>
                <p>Based in the Kootenays on unceded Sinixt əmxʷúlaʔx territory</p>
                <p>Serving communities throughout the region with professional tree care</p>
                <br>
                <p>This quote is valid for 30 days. Final pricing confirmed on-site before work begins.</p>
            </div>
        </div>
    </body>
    </html>";
}
?> 