<?php
header('Content-Type: application/json');
require_once '../config/database-simple.php';

try {
    $email = $_GET['email'] ?? '';
    $phone = $_GET['phone'] ?? '';
    $name = $_GET['name'] ?? '';
    $address = $_GET['address'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';

    if (!$email && !$phone && !$name && !$address && !$customer_id) {
        throw new Exception('Email, phone, name, address, or customer_id parameter required');
    }

    $customers = [];

    // Search by customer ID (direct access)
    if ($customer_id) {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(q.id) as total_quotes,
                   MAX(q.quote_created_at) as last_quote_date
            FROM customers c
            LEFT JOIN quotes q ON c.id = q.customer_id
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            $customers[] = $customer;
        }
    } else {
        // Search by email, phone, name, and/or address (with duplicate detection)
        $search_conditions = [];
        $search_params = [];

        if ($email) {
            $search_conditions[] = "c.email = ?";
            $search_params[] = $email;
        }

        if ($phone) {
            // Clean phone number for search (remove formatting)
            $clean_phone = preg_replace('/[^0-9]/', '', $phone);
            $search_conditions[] = "(REGEXP_REPLACE(c.phone, '[^0-9]', '') = ? OR c.phone = ?)";
            $search_params[] = $clean_phone;
            $search_params[] = $phone;
        }

        if ($name) {
            $search_conditions[] = "LOWER(TRIM(c.name)) LIKE LOWER(TRIM(?))";
            $search_params[] = "%{$name}%";
        }

        if ($address) {
            $search_conditions[] = "LOWER(TRIM(c.address)) LIKE LOWER(TRIM(?))";
            $search_params[] = "%{$address}%";
        }

        $where_clause = implode(' OR ', $search_conditions);

        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(q.id) as total_quotes,
                   MAX(q.quote_created_at) as last_quote_date
            FROM customers c
            LEFT JOIN quotes q ON c.id = q.customer_id
            WHERE {$where_clause}
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute($search_params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // For each customer, get their complete quote history
    foreach ($customers as &$customer) {
        // Mark as duplicate if they have more than one quote
        $customer['is_duplicate'] = (int)$customer['total_quotes'] > 1;

        // Get detailed quote history
        $quote_stmt = $pdo->prepare("
            SELECT q.*,
                   COALESCE(q.total_estimate, 0) as total_estimate
            FROM quotes q
            WHERE q.customer_id = ?
            ORDER BY q.quote_created_at DESC
        ");
        $quote_stmt->execute([$customer['id']]);
        $customer['quotes'] = $quote_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get file count for each quote
        foreach ($customer['quotes'] as &$quote) {
            $file_stmt = $pdo->prepare("
                SELECT COUNT(*) as file_count 
                FROM uploaded_files 
                WHERE quote_id = ?
            ");
            $file_stmt->execute([$quote['id']]);
            $quote['file_count'] = $file_stmt->fetchColumn();
        }
    }

    echo json_encode([
        'success' => true,
        'customers' => $customers,
        'total_found' => count($customers)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 