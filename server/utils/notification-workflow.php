<?php
// Integration with quote submission workflow
require_once __DIR__ . "/../utils/enhanced-admin-notification-service.php";

function triggerComprehensiveNotifications($quote_id) {
    try {
        $notification_service = new EnhancedAdminNotificationService();
        
        // Send comprehensive admin notification
        $admin_success = $notification_service->sendComprehensiveNotification($quote_id);
        
        // Send customer confirmation (existing flow)
        $customer_success = sendCustomerConfirmation($quote_id);
        
        return [
            "admin_notification" => $admin_success,
            "customer_confirmation" => $customer_success,
            "overall_success" => $admin_success && $customer_success
        ];
        
    } catch (Exception $e) {
        error_log("Notification workflow failed for quote #{$quote_id}: " . $e->getMessage());
        return [
            "admin_notification" => false,
            "customer_confirmation" => false,
            "overall_success" => false,
            "error" => $e->getMessage()
        ];
    }
}

function sendCustomerConfirmation($quote_id) {
    // Use existing customer confirmation logic
    require_once __DIR__ . "/../utils/mailer.php";
    require_once __DIR__ . "/../config/database-simple.php";
    
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT q.*, c.name, c.email 
        FROM quotes q 
        JOIN customers c ON q.customer_id = c.id 
        WHERE q.id = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();
    
    if (!$quote) {
        return false;
    }
    
    $customer_data = [
        "customer_name" => $quote["name"],
        "quote_id" => $quote["id"],
        "services" => $quote["selected_services"],
        "files_count" => 0, // Could be calculated
        "conditional_content" => "🎯 <strong>Good news!</strong> We will review your request and get back to you as soon as possible. May take a couple of days if we're out of service, which happens regularly. We may email you for more information."
    ];
    
    return sendEmail(
        $quote["email"],
        "🌲 Quote Received - Carpe Tree'em #{$quote["id"]}",
        "quote_confirmation",
        $customer_data
    );
}
?>