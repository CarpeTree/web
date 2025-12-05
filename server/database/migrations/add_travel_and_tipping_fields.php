<?php
// Migration: add travel/tipping fields to quotes
require_once __DIR__ . '/../../config/secure-config.php';
require_once __DIR__ . '/../../config/database-simple.php';

try {
    $pdo->exec("ALTER TABLE quotes 
        ADD COLUMN travel_pricing_policy JSON NULL AFTER updated_at,
        ADD COLUMN travel_legs JSON NULL AFTER travel_pricing_policy,
        ADD COLUMN travel_totals JSON NULL AFTER travel_legs,
        ADD COLUMN disposal_weight_tonnes DECIMAL(6,2) NULL AFTER travel_totals,
        ADD COLUMN disposal_tipping_fee_cad DECIMAL(10,2) NULL AFTER disposal_weight_tonnes,
        ADD COLUMN hauling_trips_required INT NULL AFTER disposal_tipping_fee_cad;");
    echo "✅ Added travel and tipping fields to quotes\n";
} catch (Throwable $e) {
    echo "⚠️ Migration notice: " . $e->getMessage() . "\n";
}
?>


