<?php
header('Content-Type: application/json');
require_once '../config/database-simple.php';

try {
    $email = $_GET['email'] ?? '';
    $phone = $_GET['phone'] ?? '';
    $name = $_GET['name'] ?? '';
    $address = $_GET['address'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $show_all = isset($_GET['all']) && ($_GET['all'] === '1' || strtolower((string)$_GET['all']) === 'true');
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    if (!$email && !$phone && !$name && !$address && !$customer_id && !$show_all) {
        throw new Exception('Provide email, phone, name, address, customer_id, or set all=1');
    }

    $customers = [];

    // Show all with pagination
    if ($show_all) {
        // Total customers for pagination metadata
        $total_stmt = $pdo->query("SELECT COUNT(*) FROM customers");
        $total_customers = (int)$total_stmt->fetchColumn();

        // Pull page of customers, with aggregate quote info
        $sql = "
            SELECT c.*, 
                   COUNT(q.id) AS total_quotes,
                   MAX(q.quote_created_at) AS last_quote_date
            FROM customers c
            LEFT JOIN quotes q ON c.id = q.customer_id
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->query($sql);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each customer, attach quotes (recent first)
        foreach ($customers as &$customer) {
            $customer['is_duplicate'] = (int)$customer['total_quotes'] > 1;
            $quote_stmt = $pdo->prepare("SELECT q.*, COALESCE(q.total_estimate, 0) as total_estimate FROM quotes q WHERE q.customer_id = ? ORDER BY q.quote_created_at DESC");
            $quote_stmt->execute([$customer['id']]);
            $customer['quotes'] = $quote_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($customer['quotes'] as &$quote) {
                $file_stmt = $pdo->prepare("SELECT COUNT(*) as file_count FROM uploaded_files WHERE quote_id = ?");
                $file_stmt->execute([$quote['id']]);
                $quote['file_count'] = $file_stmt->fetchColumn();
            }
        }

        $has_more = ($offset + count($customers)) < $total_customers;

        echo json_encode([
            'success' => true,
            'customers' => $customers,
            'total_found' => count($customers),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'has_more' => $has_more,
                'total_customers' => $total_customers
            ]
        ]);
        exit;

    // Search by customer ID (direct access)
    } elseif ($customer_id) {
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
            $search_conditions[] = "(REPLACE(REPLACE(REPLACE(REPLACE(c.phone, '-', ''), ' ', ''), '(', ''), ')', '') = ? OR c.phone = ?)";
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