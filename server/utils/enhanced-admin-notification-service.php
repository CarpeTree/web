<?php
/**
 * Enhanced Admin Notification Service
 * Generates comprehensive email notifications with 4-column AI comparison
 * Optimized for email delivery with limited media
 */

class EnhancedAdminNotificationService {
    private $db;
    private $mailer;
    private $baseUrl;
    
    public function __construct($database, $mailerInstance, $siteUrl) {
        $this->db = $database;
        $this->mailer = $mailerInstance;
        $this->baseUrl = $siteUrl;
    }
    
    /**
     * Send comprehensive admin notification for a quote
     */
    public function sendComprehensiveNotification($quoteId) {
        try {
            // Get complete quote data
            $quoteData = $this->getCompleteQuoteData($quoteId);
            if (!$quoteData) {
                throw new Exception("Quote not found: $quoteId");
            }
            
            // Get AI analysis data
            $aiData = $this->getAIAnalysisData($quoteId);
            
            // Get key images only (for email optimization)
            $keyImages = $this->getKeyImages($quoteId, 4); // Max 4 images
            
            // Calculate costs
            $costData = $this->calculateCosts($quoteData, $aiData);
            
            // Load and populate email template
            $emailHtml = $this->generateEmailContent($quoteData, $aiData, $keyImages, $costData);
            
            // Send the email
            $result = $this->mailer->sendEmail(
                ADMIN_EMAIL,
                "🌲 New Quote Analysis Ready - #{$quoteId}",
                $emailHtml,
                true // HTML format
            );
            
            // Log the notification
            $this->logNotification($quoteId, 'comprehensive_admin', $result);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Enhanced notification failed for quote $quoteId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get complete quote data from database
     */
    private function getCompleteQuoteData($quoteId) {
        $stmt = $this->db->prepare("
            SELECT q.*, 
                   DATE_FORMAT(q.created_at, '%Y-%m-%d %H:%i') as formatted_date,
                   COUNT(qf.id) as file_count
            FROM quotes q 
            LEFT JOIN quote_files qf ON q.id = qf.quote_id 
            WHERE q.id = ? 
            GROUP BY q.id
        ");
        $stmt->execute([$quoteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get AI analysis data for all models
     */
    private function getAIAnalysisData($quoteId) {
        $stmt = $this->db->prepare("
            SELECT ai_gemini_analysis, ai_o3_analysis, ai_o4_mini_analysis,
                   ai_gemini_cost, ai_o3_cost, ai_o4_mini_cost
            FROM quotes 
            WHERE id = ?
        ");
        $stmt->execute([$quoteId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Extract structured data from AI analysis
        $data['gemini_species'] = $this->extractField($data['ai_gemini_analysis'], 'species');
        $data['gemini_height'] = $this->extractField($data['ai_gemini_analysis'], 'height');
        $data['gemini_crown'] = $this->extractField($data['ai_gemini_analysis'], 'crown');
        $data['gemini_dbh'] = $this->extractField($data['ai_gemini_analysis'], 'dbh');
        $data['gemini_waste'] = $this->extractField($data['ai_gemini_analysis'], 'waste');
        
        $data['o3_species'] = $this->extractField($data['ai_o3_analysis'], 'species');
        $data['o3_height'] = $this->extractField($data['ai_o3_analysis'], 'height');
        $data['o3_crown'] = $this->extractField($data['ai_o3_analysis'], 'crown');
        $data['o3_dbh'] = $this->extractField($data['ai_o3_analysis'], 'dbh');
        $data['o3_waste'] = $this->extractField($data['ai_o3_analysis'], 'waste');
        
        $data['o4mini_species'] = $this->extractField($data['ai_o4_mini_analysis'], 'species');
        $data['o4mini_height'] = $this->extractField($data['ai_o4_mini_analysis'], 'height');
        $data['o4mini_crown'] = $this->extractField($data['ai_o4_mini_analysis'], 'crown');
        $data['o4mini_dbh'] = $this->extractField($data['ai_o4_mini_analysis'], 'dbh');
        $data['o4mini_waste'] = $this->extractField($data['ai_o4_mini_analysis'], 'waste');
        
        return $data;
    }
    
    /**
     * Get only key images for email (no video/audio)
     */
    private function getKeyImages($quoteId, $maxImages = 4) {
        $stmt = $this->db->prepare("
            SELECT file_name, file_path, file_type 
            FROM quote_files 
            WHERE quote_id = ? 
            AND file_type IN ('image/jpeg', 'image/png', 'image/webp', 'image/heic')
            ORDER BY uploaded_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$quoteId, $maxImages]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $keyImageItems = '';
        foreach ($files as $file) {
            $fileUrl = $this->baseUrl . '/uploads/' . $file['file_name'];
            $keyImageItems .= "
                <div class='media-item'>
                    <img src='{$fileUrl}' alt='{$file['file_name']}' style='max-width: 200px; height: 150px; object-fit: cover; border-radius: 6px;'>
                    <div style='font-size: 0.8em; color: #666; margin-top: 5px;'>{$file['file_name']}</div>
                </div>
            ";
        }
        
        return $keyImageItems;
    }
    
    /**
     * Calculate comprehensive costs
     */
    private function calculateCosts($quoteData, $aiData) {
        return [
            'total_ai_cost' => ($aiData['ai_gemini_cost'] ?? 0) + ($aiData['ai_o3_cost'] ?? 0) + ($aiData['ai_o4_mini_cost'] ?? 0),
            'removal_cost' => $quoteData['final_total'] * 0.4,
            'pruning_cost' => $quoteData['final_total'] * 0.2,
            'climbing_cost' => $quoteData['final_total'] * 0.15,
            'rigging_cost' => $quoteData['final_total'] * 0.1,
            'brush_removal_cost' => $quoteData['final_total'] * 0.08,
            'wood_processing_cost' => $quoteData['final_total'] * 0.05,
            'firewood_cost' => $quoteData['final_total'] * 0.02,
            'travel_cost' => 150, // Estimated
            'equipment_cost' => 75,
            'disposal_transport_cost' => 100,
            'total_min' => $quoteData['final_total'] * 0.9,
            'total_max' => $quoteData['final_total'] * 1.1
        ];
    }
    
    /**
     * Generate complete email HTML content
     */
    private function generateEmailContent($quoteData, $aiData, $keyImages, $costData) {
        $templatePath = __DIR__ . '/../templates/comprehensive_admin_notification.html';
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found: $templatePath");
        }
        
        $template = file_get_contents($templatePath);
        
        // Prepare all replacement data
        $replacements = array_merge($quoteData, $aiData, $costData, [
            'quote_id' => $quoteData['id'],
            'customer_name' => $quoteData['name'] ?? $quoteData['customer_name'],
            'customer_email' => $quoteData['email'] ?? $quoteData['customer_email'],
            'customer_phone' => $quoteData['phone'] ?? $quoteData['customer_phone'],
            'customer_address' => $quoteData['address'] ?? $quoteData['customer_address'],
            'services' => $quoteData['services_requested'] ?? 'Tree services',
            'created_at' => $quoteData['formatted_date'],
            'files_count' => $quoteData['file_count'],
            'status' => $quoteData['status'] ?? 'pending',
            'final_total' => $quoteData['final_total'] ?? 0,
            'key_image_items' => $keyImages,
            'audio_summary' => $this->getAudioSummary($quoteData['id']),
            'audio_section_display' => $keyImages ? '' : 'display:none;',
            'distance_km' => '25', // Estimated, would be calculated by Maps API
            'travel_time' => '30 min',
            'gps_coordinates' => $quoteData['gps_lat'] . ',' . $quoteData['gps_lon'],
            'current_timestamp' => date('Y-m-d H:i:s'),
            'urgency_level' => $this->determineUrgency($quoteData),
            'ai_models_complete' => $this->countCompletedAI($aiData),
            'gemini_cost' => $aiData['ai_gemini_cost'] ?? '0.00',
            'o3_cost' => $aiData['ai_o3_cost'] ?? '0.00',
            'o4_mini_cost' => $aiData['ai_o4_mini_cost'] ?? '0.00'
        ]);
        
        // Replace all placeholders
        foreach ($replacements as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Extract specific field from AI analysis text
     */
    private function extractField($analysis, $field) {
        if (!$analysis) return 'Not specified';
        
        $patterns = [
            'species' => ['/species[:\s]*([^\n\.]+)/i', '/appears to be[:\s]*([^\n\.]+)/i'],
            'height' => ['/(\d+(?:\.\d+)?)\s*(?:m|meters?|ft|feet)/i'],
            'crown' => ['/crown[^0-9]*(\d+(?:\.\d+)?)\s*(?:m|meters?)/i'],
            'dbh' => ['/dbh[^0-9]*(\d+(?:\.\d+)?)\s*(?:cm|centimeters?)/i'],
            'waste' => ['/disposal[^0-9]*(\$?\d+(?:\.\d+)?)/i']
        ];
        
        if (!isset($patterns[$field])) return 'Not specified';
        
        foreach ($patterns[$field] as $pattern) {
            if (preg_match($pattern, $analysis, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return 'Not specified';
    }
    
    /**
     * Get audio summary (truncated for email)
     */
    private function getAudioSummary($quoteId) {
        $stmt = $this->db->prepare("SELECT audio_transcription FROM quotes WHERE id = ?");
        $stmt->execute([$quoteId]);
        $transcription = $stmt->fetchColumn();
        
        if ($transcription) {
            return strlen($transcription) > 200 ? substr($transcription, 0, 200) . '...' : $transcription;
        }
        
        return 'No audio notes provided';
    }
    
    /**
     * Determine urgency level
     */
    private function determineUrgency($quoteData) {
        $services = strtolower($quoteData['services_requested'] ?? '');
        if (strpos($services, 'emergency') !== false) return 'HIGH';
        if (strpos($services, 'removal') !== false) return 'MEDIUM';
        return 'NORMAL';
    }
    
    /**
     * Count completed AI analyses
     */
    private function countCompletedAI($aiData) {
        $count = 0;
        if (!empty($aiData['ai_gemini_analysis'])) $count++;
        if (!empty($aiData['ai_o3_analysis'])) $count++;
        if (!empty($aiData['ai_o4_mini_analysis'])) $count++;
        return $count;
    }
    
    /**
     * Log notification in database
     */
    private function logNotification($quoteId, $type, $success) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notification_logs (quote_id, notification_type, sent_at, success, recipient)
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([$quoteId, $type, $success ? 1 : 0, ADMIN_EMAIL]);
        } catch (Exception $e) {
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }
}
?>