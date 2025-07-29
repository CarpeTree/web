<?php
// Check and fix quote statuses for testing
header('Content-Type: text/plain');
require_once 'server/config/database-simple.php';

echo "=== QUOTE STATUS CHECK & FIX ===\n\n";

try {
    // Check all quotes
    $stmt = $pdo->prepare("
        SELECT q.id, q.quote_status, q.ai_analysis_complete, c.name, c.email, c.address, q.selected_services
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        ORDER BY q.quote_created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $quotes = $stmt->fetchAll();

    echo "Current Quotes:\n";
    foreach ($quotes as $quote) {
        $services = json_decode($quote['selected_services'], true) ?: [];
        $services_text = implode(', ', $services);
        
        echo "Quote #{$quote['id']}: {$quote['name']} ({$quote['email']})\n";
        echo "  Status: {$quote['quote_status']}\n";
        echo "  AI Complete: " . ($quote['ai_analysis_complete'] ? 'YES' : 'NO') . "\n";
        echo "  Services: $services_text\n";
        echo "  Address: {$quote['address']}\n";
        echo "---\n";
    }

    // Reset quote #3 to draft_ready for testing
    echo "\n🔄 Resetting Quote #3 to draft_ready for testing...\n";
    $reset_stmt = $pdo->prepare("UPDATE quotes SET quote_status = 'draft_ready' WHERE id = 3");
    $reset_result = $reset_stmt->execute();

    if ($reset_result) {
        echo "✅ Quote #3 reset to draft_ready\n";
    } else {
        echo "❌ Failed to reset quote #3\n";
    }

    // Check again
    echo "\n=== UPDATED STATUS ===\n";
    $check_stmt = $pdo->prepare("
        SELECT id, quote_status, ai_analysis_complete 
        FROM quotes 
        WHERE id IN (1, 2, 3)
        ORDER BY id
    ");
    $check_stmt->execute();
    $updated_quotes = $check_stmt->fetchAll();

    foreach ($updated_quotes as $quote) {
        echo "Quote #{$quote['id']}: {$quote['quote_status']} (AI: " . ($quote['ai_analysis_complete'] ? 'YES' : 'NO') . ")\n";
    }

    echo "\n📧 Email Sending Instructions:\n";
    echo "1. Run: php send-professional-emails-fixed.php\n";
    echo "2. Check your email for the corrected format\n";
    echo "3. The new email will have:\n";
    echo "   ✅ Proper customer name (no {brackets})\n";
    echo "   ✅ Kootenays/Sinixt territory location\n";
    echo "   ✅ Video quote disclaimers\n";
    echo "   ✅ Correct cost breakdown\n";
    echo "   ✅ Updated business information\n\n";

    echo "📱 To fix 'Mail cannot verify' issue:\n";
    echo "1. Add quotes@carpetree.com to your contacts\n";
    echo "2. Or check your spam folder\n";
    echo "3. The email should show as verified once you interact with it\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 