<?php
/**
 * Send Estimate to Customer API
 * Generates and emails customer-facing estimate
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['quote_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Quote ID required']);
    exit;
}

$quote_id = (int)$input['quote_id'];
$items = $input['items'] ?? [];
$totals = $input['totals'] ?? [];
$customer_email = $input['customer_email'] ?? null;

try {
    // Get quote and customer info
    $stmt = $pdo->prepare("
        SELECT q.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quote) {
        throw new Exception('Quote not found');
    }
    
    $customer_email = $customer_email ?: $quote['customer_email'];
    
    if (!$customer_email) {
        throw new Exception('Customer email not available');
    }
    
    // Generate estimate URL
    $estimate_token = bin2hex(random_bytes(16));
    $estimate_url = "https://carpetree.com/view-estimate.html?token=" . $estimate_token;
    
    // Save estimate
    $estimate_data = json_encode([
        'items' => $items,
        'totals' => $totals,
        'sent_at' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $pdo->prepare("SELECT id FROM quote_estimates WHERE quote_id = ?");
    $stmt->execute([$quote_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE quote_estimates SET 
            estimate_data = ?,
            estimate_url = ?,
            estimate_token = ?,
            status = 'sent',
            sent_at = NOW(),
            updated_at = NOW()
            WHERE quote_id = ?");
        $stmt->execute([$estimate_data, $estimate_url, $estimate_token, $quote_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO quote_estimates 
            (quote_id, estimate_data, estimate_url, estimate_token, status, sent_at, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 'sent', NOW(), NOW(), NOW())");
        $stmt->execute([$quote_id, $estimate_data, $estimate_url, $estimate_token]);
    }
    
    // Update quote status
    $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'sent_to_client' WHERE id = ?");
    $stmt->execute([$quote_id]);
    
    // Build email HTML
    $items_html = '';
    $grouped_items = [];
    foreach ($items as $item) {
        $tree = $item['tree'] ?? 'General';
        if (!isset($grouped_items[$tree])) {
            $grouped_items[$tree] = [];
        }
        $grouped_items[$tree][] = $item;
    }
    
    foreach ($grouped_items as $tree => $tree_items) {
        $items_html .= "<h3 style='color: #2D5A27; margin-top: 20px;'>{$tree}</h3>";
        $items_html .= "<table style='width: 100%; border-collapse: collapse;'>";
        foreach ($tree_items as $item) {
            $price = number_format($item['price'] ?? 0, 2);
            $items_html .= "<tr style='border-bottom: 1px solid #eee;'>
                <td style='padding: 10px 0;'>{$item['name']}</td>
                <td style='padding: 10px 0; text-align: right; font-weight: bold;'>\${$price}</td>
            </tr>";
        }
        $items_html .= "</table>";
    }
    
    $total = number_format($totals['services'] ?? 0, 2);
    
    $email_html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #2D5A27; color: white; padding: 20px; text-align: center;'>
            <h1 style='margin: 0;'>Carpe Tree'em</h1>
            <p style='margin: 5px 0 0 0; opacity: 0.9;'>Your Tree Care Estimate</p>
        </div>
        
        <div style='padding: 30px; background: #f8f8f8;'>
            <p>Dear {$quote['customer_name']},</p>
            <p>Thank you for considering Carpe Tree'em for your tree care needs. Below is your personalized estimate:</p>
            
            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h2 style='color: #2D5A27; margin-top: 0;'>Estimate #CT-{$quote_id}</h2>
                <p><strong>Property:</strong> {$quote['address']}</p>
                
                {$items_html}
                
                <div style='margin-top: 20px; padding-top: 20px; border-top: 2px solid #2D5A27;'>
                    <table style='width: 100%;'>
                        <tr>
                            <td style='font-size: 18px; font-weight: bold;'>Total</td>
                            <td style='font-size: 24px; font-weight: bold; text-align: right; color: #2D5A27;'>\${$total}</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <p style='text-align: center;'>
                <a href='{$estimate_url}' style='display: inline-block; background: #2D5A27; color: white; padding: 15px 30px; border-radius: 8px; text-decoration: none; font-weight: bold;'>View Full Estimate</a>
            </p>
            
            <p style='font-size: 14px; color: #666; margin-top: 30px;'>
                This estimate is valid for 90 days. If you have any questions, please don't hesitate to reach out.
            </p>
            
            <p>Best regards,<br><strong>Carpe Tree'em</strong><br>778-655-3741<br>sapport@carpetree.com</p>
        </div>
        
        <div style='background: #333; color: white; padding: 15px; text-align: center; font-size: 12px;'>
            Serving communities on unceded Sinixt territory
        </div>
    </div>
    ";
    
    // Send email
    $subject = "Your Tree Care Estimate from Carpe Tree'em - #CT-{$quote_id}";
    sendEmailDirect($customer_email, $subject, $email_html, $quote_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Estimate sent successfully',
        'quote_id' => $quote_id,
        'estimate_url' => $estimate_url,
        'sent_to' => $customer_email
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

