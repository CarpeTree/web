<?php
// Create a comprehensive admin notification with all AI data, costs, and media

echo "📧 Creating Comprehensive Admin Notification\n";
echo "============================================\n\n";

// Mock quote data for testing the comprehensive template
$quote_data = [
    'quote_id' => '123',
    'customer_name' => 'Phil Bajenski',
    'customer_email' => 'phil.bajenski@gmail.com',
    'customer_phone' => '778-655-3741',
    'customer_address' => '404 Kildare St, New Denver, BC',
    'customer_notes' => 'Large maple tree assessment - concerned about dead branches near house.',
    'services' => 'Tree Assessment, Pruning',
    'submission_time' => date('Y-m-d H:i:s'),
    'distance_km' => '45',
    'travel_time' => '1h 15m',
    'travel_cost' => '90',
    'gps_coordinates' => '49.3243, -117.3678',
    'urgency_level' => 'Routine',
    'quote_status' => 'ai_processing',
    'media_count' => '4',
    'customer_id' => '789'
];

// Mock AI analysis data
$ai_data = [
    'gemini_species' => 'Oak (85% confidence)',
    'openai_species' => 'Maple (92% confidence)',
    'claude_species' => 'Birch (78% confidence)',
    'gemini_height' => '18',
    'openai_height' => '20',
    'claude_height' => '17',
    'gemini_crown' => '12',
    'openai_crown' => '14',
    'claude_crown' => '11',
    'gemini_dbh' => '45',
    'openai_dbh' => '52',
    'claude_dbh' => '48',
    'gemini_analysis' => 'Large mature oak tree showing good overall health. Minor deadwood in crown requires removal. Tree is well-positioned and structurally sound. Recommended maintenance pruning to remove deadwood and improve air circulation.',
    'openai_analysis' => 'This appears to be a mature maple tree with significant size and good structural integrity. Some concern about proximity to structures. Tree shows signs of stress in certain areas but overall healthy.',
    'claude_analysis' => 'Birch tree displaying characteristic bark and growth pattern. Tree appears to be in decline phase with some dieback visible in upper crown. Structural assessment needed to determine safety.',
    'gemini_cost' => '0.15',
    'openai_cost' => '0.23',
    'claude_cost' => '0.18',
    'total_ai_cost' => '0.56',
    'gemini_total' => '1125',
    'openai_total' => '1450',
    'claude_total' => '1050'
];

// Mock cost breakdown
$cost_data = [
    'removal_cost' => '1200-1800',
    'pruning_cost' => '600-900',
    'climbing_cost' => '300-450',
    'rigging_cost' => '200-400',
    'brush_removal_cost' => '150-250',
    'wood_processing_cost' => '100-200',
    'firewood_cost' => '150-300',
    'transfer_station_cost' => '85-120',
    'equipment_cost' => '75-150',
    'disposal_transport_cost' => '50-100',
    'total_min' => '900',
    'total_max' => '2300'
];

// Mock media items
$media_items = '
<div class="media-item">
    <img src="/uploads/tree_front_view.jpg" alt="Front view of tree">
    <div>tree_front_view.jpg</div>
    <a href="/uploads/tree_front_view.jpg" class="btn">📷 View Full Size</a>
</div>
<div class="media-item">
    <img src="/uploads/tree_side_angle.jpg" alt="Side angle">
    <div>tree_side_angle.jpg</div>
    <a href="/uploads/tree_side_angle.jpg" class="btn">📷 View Full Size</a>
</div>
<div class="media-item">
    <img src="/uploads/tree_base_damage.jpg" alt="Base damage detail">
    <div>tree_base_damage.jpg</div>
    <a href="/uploads/tree_base_damage.jpg" class="btn">📷 View Full Size</a>
</div>
<div class="media-item">
    <div style="background: #f8f9fa; height: 120px; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
        📹 tree_assessment.mp4
    </div>
    <div>tree_assessment.mp4</div>
    <a href="/uploads/tree_assessment.mp4" class="btn">🎬 Play Video</a>
</div>';

// Mock line items for each AI
$gemini_line_items = '
<div class="line-item">
    <div class="line-item-header">Tree Pruning - Deadwood Removal</div>
    <div class="line-item-price">$450 - $650 (climbing access required)</div>
</div>
<div class="line-item">
    <div class="line-item-header">Crown Thinning</div>
    <div class="line-item-price">$300 - $500 (improve air circulation)</div>
</div>
<div class="line-item">
    <div class="line-item-header">Brush Removal</div>
    <div class="line-item-price">$150 - $200 (estimated 2m³ of brush)</div>
</div>';

$openai_line_items = '
<div class="line-item">
    <div class="line-item-header">Tree Removal (if required)</div>
    <div class="line-item-price">$1,200 - $1,800 (complex rigging needed)</div>
</div>
<div class="line-item">
    <div class="line-item-header">Alternative: Heavy Pruning</div>
    <div class="line-item-price">$600 - $900 (reduce crown by 30%)</div>
</div>
<div class="line-item">
    <div class="line-item-header">Stump Grinding</div>
    <div class="line-item-price">$350 - $500 (if removal chosen)</div>
</div>';

$claude_line_items = '
<div class="line-item">
    <div class="line-item-header">Tree Health Assessment</div>
    <div class="line-item-price">$200 - $300 (professional evaluation)</div>
</div>
<div class="line-item">
    <div class="line-item-header">Crown Reduction</div>
    <div class="line-item-price">$400 - $700 (if tree is salvageable)</div>
</div>
<div class="line-item">
    <div class="line-item-header">Complete Removal</div>
    <div class="line-item-price">$800 - $1,200 (if assessment shows decay)</div>
</div>';

// Mock waste disposal data
$waste_data = [
    'nearest_transfer_station' => 'Castlegar Regional Landfill',
    'transfer_station_distance' => '35',
    'transfer_station_rate' => '85',
    'estimated_waste_weight' => '2.5',
    'estimated_brush_volume' => '15',
    'estimated_brush_weight' => '2.2',
    'estimated_truck_loads' => '2',
    'total_disposal_cost' => '220',
    'audio_section_display' => 'display: none;',
    'audio_transcription' => '',
    'current_timestamp' => date('Y-m-d H:i:s')
];

// Combine all data
$template_data = array_merge($quote_data, $ai_data, $cost_data, $waste_data);
$template_data['media_items'] = $media_items;
$template_data['gemini_line_items'] = $gemini_line_items;
$template_data['openai_line_items'] = $openai_line_items;
$template_data['claude_line_items'] = $claude_line_items;

echo "📋 Template Data Prepared:\n";
echo "- Quote ID: {$template_data['quote_id']}\n";
echo "- Customer: {$template_data['customer_name']}\n";
echo "- AI Models: 3 (Gemini, OpenAI, Claude)\n";
echo "- Media Files: {$template_data['media_count']}\n";
echo "- Total AI Cost: \${$template_data['total_ai_cost']}\n\n";

// Load and process the comprehensive template
if (file_exists('server/templates/comprehensive_admin_notification.html')) {
    $template_content = file_get_contents('server/templates/comprehensive_admin_notification.html');
    
    // Replace all placeholders
    foreach ($template_data as $key => $value) {
        $template_content = str_replace('{' . $key . '}', $value, $template_content);
    }
    
    // Save the processed email for review
    file_put_contents('comprehensive_admin_email_preview.html', $template_content);
    
    echo "✅ Comprehensive admin email template processed!\n";
    echo "📄 Preview saved as: comprehensive_admin_email_preview.html\n";
    echo "🌐 Open this file in your browser to see the full email layout\n\n";
    
    // Check for remaining placeholders
    if (preg_match_all('/\{([^}]+)\}/', $template_content, $matches)) {
        $remaining = array_unique($matches[1]);
        echo "⚠️  Remaining placeholders to implement:\n";
        foreach ($remaining as $placeholder) {
            echo "  - {" . $placeholder . "}\n";
        }
    } else {
        echo "✅ All placeholders processed successfully!\n";
    }
    
    echo "\n📧 Email Features Included:\n";
    echo "✅ 4-column analysis comparison\n";
    echo "✅ Checkbox selection system\n";
    echo "✅ All AI costs and analysis\n";
    echo "✅ Media file links and previews\n";
    echo "✅ Itemized cost breakdown\n";
    echo "✅ Waste disposal calculations\n";
    echo "✅ Usage logging for data analysis\n";
    echo "✅ Re-trigger analysis functionality\n";
    echo "✅ Google Maps integration\n";
    echo "✅ Action buttons for admin tasks\n";
    
} else {
    echo "❌ Template file not found: server/templates/comprehensive_admin_notification.html\n";
}

echo "\n🚀 Next Steps:\n";
echo "1. Review comprehensive_admin_email_preview.html\n";
echo "2. Test the 4-column dashboard: carpetree.com/redesign-admin-dashboard-4column.html\n";
echo "3. Submit a test quote to trigger the new email system\n";
echo "4. Configure Google Maps API key for distance calculations\n";
?>