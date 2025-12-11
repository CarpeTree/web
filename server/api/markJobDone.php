<?php
// START NEW - Mark job done and generate invoice
header('Content-Type: application/json');
// CORS narrowed; only same-origin callers expected

// Admin API key guard (optional; enforced if ADMIN_API_KEY is set)
function require_admin_key() {
    $expected = getenv('ADMIN_API_KEY') ?: ($_ENV['ADMIN_API_KEY'] ?? null);
    if (!$expected) return;
    $provided = $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? ($_GET['admin_key'] ?? $_POST['admin_key'] ?? null);
    if (!$provided || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
require_admin_key();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../utils/mailer.php';
require_once '../vendor/autoload.php';

use TCPDF\TCPDF;

try {
    $quote_id = $_POST['quote_id'] ?? null;
    $duration = $_POST['duration'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    if (!$quote_id) {
        throw new Exception('Quote ID required');
    }
    
    // Get quote and work orders
    $stmt = $pdo->prepare("
        SELECT q.*, c.* 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.id = ? AND q.quote_status = 'accepted'
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();
    
    if (!$quote) {
        throw new Exception('Quote not found or not accepted');
    }
    
    // Get work orders
    $stmt = $pdo->prepare("
        SELECT tw.*, t.tree_species 
        FROM tree_work_orders tw 
        JOIN trees t ON tw.tree_id = t.id 
        WHERE tw.quote_id = ?
    ");
    $stmt->execute([$quote_id]);
    $work_orders = $stmt->fetchAll();
    
    $pdo->beginTransaction();
    
    // Update work orders status
    $stmt = $pdo->prepare("
        UPDATE tree_work_orders 
        SET status = 'completed', 
            actual_duration_minutes = ?,
            completion_notes = ?
        WHERE quote_id = ?
    ");
    $stmt->execute([$duration, $notes, $quote_id]);
    
    // Generate invoice
    $invoice_number = 'CT-' . date('Y') . '-' . str_pad($quote_id, 4, '0', STR_PAD_LEFT);
    $invoice_date = date('Y-m-d');
    $due_date = date('Y-m-d', strtotime('+30 days'));
    
    $subtotal = $quote['total_estimate'];
    $tax_rate = 5.00; // 5% GST
    $tax_amount = $subtotal * ($tax_rate / 100);
    $total_amount = $subtotal + $tax_amount;
    
    // Insert invoice record
    $stmt = $pdo->prepare("
        INSERT INTO invoices (
            quote_id, customer_id, invoice_number, invoice_date, due_date,
            subtotal, tax_rate, tax_amount, total_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $quote_id, $quote['customer_id'], $invoice_number, $invoice_date, $due_date,
        $subtotal, $tax_rate, $tax_amount, $total_amount
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    // Generate PDF invoice
    $pdf_filename = generateInvoicePDF($quote, $work_orders, [
        'invoice_number' => $invoice_number,
        'invoice_date' => $invoice_date,
        'due_date' => $due_date,
        'subtotal' => $subtotal,
        'tax_rate' => $tax_rate,
        'tax_amount' => $tax_amount,
        'total_amount' => $total_amount
    ]);
    
    // Update invoice with PDF path
    $stmt = $pdo->prepare("UPDATE invoices SET pdf_file_path = ? WHERE id = ?");
    $stmt->execute([$pdf_filename, $invoice_id]);
    
    // Handle final photos if uploaded
    if (!empty($_FILES['photos'])) {
        $photos_dir = "../../uploads/$quote_id/final";
        if (!file_exists($photos_dir)) {
            mkdir($photos_dir, 0755, true);
        }
        
        foreach ($_FILES['photos']['tmp_name'] as $index => $tmp_name) {
            if ($_FILES['photos']['error'][$index] === UPLOAD_ERR_OK) {
                $extension = pathinfo($_FILES['photos']['name'][$index], PATHINFO_EXTENSION);
                $filename = "final_" . uniqid() . ".$extension";
                move_uploaded_file($tmp_name, "$photos_dir/$filename");
                
                // Insert media record
                $stmt = $pdo->prepare("
                    INSERT INTO media (quote_id, filename, original_filename, file_path, file_type, mime_type)
                    VALUES (?, ?, ?, ?, 'image', ?)
                ");
                $stmt->execute([
                    $quote_id, $filename, $_FILES['photos']['name'][$index],
                    "$photos_dir/$filename", $_FILES['photos']['type'][$index]
                ]);
            }
        }
    }
    
    // Update quote status
    $stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'completed' WHERE id = ?");
    $stmt->execute([$quote_id]);
    
    $pdo->commit();
    
    // Send invoice to customer
    sendInvoiceEmail($quote, $invoice_number, $pdf_filename);
    
    // Clean up removal videos if any
    cleanupRemovalVideos($quote_id);
    
    echo json_encode([
        'success' => true,
        'invoice_number' => $invoice_number,
        'invoice_id' => $invoice_id,
        'message' => 'Job completed and invoice generated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function generateInvoicePDF($quote, $work_orders, $invoice_data) {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Carpe Tree\'em');
    $pdf->SetTitle('Invoice ' . $invoice_data['invoice_number']);
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Company logo and info
    $html = '<table width="100%">
        <tr>
            <td width="50%">
                <h1 style="color: #2c5f2d;">Carpe Tree\'em</h1>
                <p>Professional Tree Care Services<br>
                Phone: 778-655-3741<br>
                Email: sapport@carpetree.com</p>
            </td>
            <td width="50%" align="right">
                <h2>INVOICE</h2>
                <p><strong>Invoice #:</strong> ' . $invoice_data['invoice_number'] . '<br>
                <strong>Date:</strong> ' . date('M j, Y', strtotime($invoice_data['invoice_date'])) . '<br>
                <strong>Due Date:</strong> ' . date('M j, Y', strtotime($invoice_data['due_date'])) . '</p>
            </td>
        </tr>
    </table><hr>';
    
    // Customer info
    $html .= '<h3>Bill To:</h3>
    <p>' . htmlspecialchars($quote['name'] ?: 'Customer') . '<br>
    ' . htmlspecialchars($quote['email']) . '<br>
    ' . htmlspecialchars($quote['phone'] ?: '') . '<br>
    ' . nl2br(htmlspecialchars($quote['address'] ?: '')) . '</p>';
    
    // Work orders table
    $html .= '<h3>Services Provided:</h3>
    <table border="1" cellpadding="5">
        <tr style="background-color: #2c5f2d; color: white;">
            <th>Tree/Service</th>
            <th>Description</th>
            <th>Hours</th>
            <th>Rate</th>
            <th>Amount</th>
        </tr>';
    
    foreach ($work_orders as $order) {
        $html .= '<tr>
            <td>' . htmlspecialchars($order['tree_species']) . ' - ' . ucwords($order['service_type']) . '</td>
            <td>' . htmlspecialchars($order['service_description']) . '</td>
            <td>' . $order['estimated_hours'] . '</td>
            <td>$' . number_format($order['hourly_rate'], 2) . '</td>
            <td>$' . number_format($order['total_cost'], 2) . '</td>
        </tr>';
    }
    
    // Totals
    $html .= '</table><br>
    <table width="100%">
        <tr>
            <td width="70%"></td>
            <td width="30%">
                <table border="1" cellpadding="5">
                    <tr><td><strong>Subtotal:</strong></td><td>$' . number_format($invoice_data['subtotal'], 2) . '</td></tr>
                    <tr><td><strong>GST (' . $invoice_data['tax_rate'] . '%):</strong></td><td>$' . number_format($invoice_data['tax_amount'], 2) . '</td></tr>
                    <tr style="background-color: #2c5f2d; color: white;"><td><strong>Total:</strong></td><td><strong>$' . number_format($invoice_data['total_amount'], 2) . '</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>';
    
    // Payment terms
    $html .= '<br><h3>Payment Terms:</h3>
    <p>Payment is due within 30 days of invoice date. Thank you for choosing Carpe Tree\'em for your tree care needs!</p>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Save PDF
    $filename = "../../uploads/invoices/invoice_{$invoice_data['invoice_number']}.pdf";
    $dir = dirname($filename);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $pdf->Output($filename, 'F');
    
    return $filename;
}

function sendInvoiceEmail($quote, $invoice_number, $pdf_path) {
    $template_data = [
        'customer_name' => $quote['name'] ?: 'Customer',
        'invoice_number' => $invoice_number,
        'total_amount' => number_format($quote['total_estimate'] * 1.05, 2), // Including tax
        'due_date' => date('M j, Y', strtotime('+30 days'))
    ];
    
    $attachments = [
        ['path' => $pdf_path, 'name' => "Invoice_$invoice_number.pdf"]
    ];
    
    sendEmail(
        $quote['email'],
        "Invoice $invoice_number - Tree Care Services Completed",
        'invoice_client',
        $template_data,
        $attachments
    );
}

function cleanupRemovalVideos($quote_id) {
    // Remove any removal videos older than 30 days for privacy
    $stmt = $pdo->prepare("
        SELECT file_path FROM media 
        WHERE quote_id = ? 
        AND file_type = 'video' 
        AND uploaded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$quote_id]);
    $videos = $stmt->fetchAll();
    
    foreach ($videos as $video) {
        if (file_exists($video['file_path'])) {
            unlink($video['file_path']);
        }
    }
    
    // Mark as deleted in database
    $stmt = $pdo->prepare("
        UPDATE media 
        SET media_deleted = 1 
        WHERE quote_id = ? 
        AND file_type = 'video' 
        AND uploaded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$quote_id]);
}
// END NEW
?> 