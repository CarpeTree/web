<?php
// AI-Powered Context Assessment for Accurate Estimates
require_once __DIR__ . '/../config/database-simple.php';
require_once __DIR__ . '/../utils/openai-client.php';

class ContextAssessor {
    
    public static function assessSubmissionContext($quote_id) {
        global $pdo;
        
        try {
            // Get quote and customer data
            $stmt = $pdo->prepare("
                SELECT q.*, c.name, c.email, c.phone, c.address, c.geo_latitude, c.geo_longitude,
                       c.geo_accuracy, c.ip_address
                FROM quotes q 
                JOIN customers c ON q.customer_id = c.id 
                WHERE q.id = ?
            ");
            $stmt->execute([$quote_id]);
            $quote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$quote) {
                throw new Exception("Quote not found");
            }
            
            // Get media files
            $media_stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ?");
            $media_stmt->execute([$quote_id]);
            $media_files = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get EXIF location data
            $exif_stmt = $pdo->prepare("SELECT * FROM media_locations WHERE quote_id = ?");
            $exif_stmt->execute([$quote_id]);
            $exif_locations = $exif_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Analyze context completeness
            $assessment = self::performAIContextAssessment($quote, $media_files, $exif_locations);
            
            // Store assessment results
            self::storeAssessmentResults($quote_id, $assessment);
            
            return $assessment;
            
        } catch (Exception $e) {
            error_log("Context assessment error for quote $quote_id: " . $e->getMessage());
            return self::getDefaultAssessment('error', $e->getMessage());
        }
    }
    
    private static function performAIContextAssessment($quote, $media_files, $exif_locations) {
        // Prepare submission data for AI analysis
        $submission_data = self::prepareSubmissionData($quote, $media_files, $exif_locations);
        
        // Build AI prompt for context assessment
        $prompt = self::buildContextAssessmentPrompt($submission_data);
        
        try {
            // Call OpenAI for context assessment
            $response = callOpenAI([
                'model' => 'gpt-4o',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert tree service estimator evaluating whether a customer submission contains sufficient information to provide an accurate quote. Respond in JSON format only.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.1
            ]);
            
            if ($response && isset($response['choices'][0]['message']['content'])) {
                $content = trim($response['choices'][0]['message']['content']);
                
                // Extract JSON from response
                if (preg_match('/\{.*\}/s', $content, $matches)) {
                    $assessment_data = json_decode($matches[0], true);
                    if ($assessment_data) {
                        return self::formatAssessmentResponse($assessment_data, $submission_data);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("AI context assessment failed: " . $e->getMessage());
        }
        
        // Fallback: rule-based assessment
        return self::performRuleBasedAssessment($submission_data);
    }
    
    private static function prepareSubmissionData($quote, $media_files, $exif_locations) {
        // Parse services
        $services = [];
        if (!empty($quote['selected_services'])) {
            $services = json_decode($quote['selected_services'], true) ?: [];
        }
        
        // Categorize media
        $media_summary = [
            'images' => 0,
            'videos' => 0,
            'has_close_ups' => false,
            'has_wide_shots' => false,
            'has_location_data' => false
        ];
        
        foreach ($media_files as $media) {
            if (strpos($media['mime_type'] ?? '', 'image/') === 0) {
                $media_summary['images']++;
            } elseif (strpos($media['mime_type'] ?? '', 'video/') === 0) {
                $media_summary['videos']++;
            }
        }
        
        $media_summary['has_location_data'] = !empty($exif_locations) || 
                                            !empty($quote['geo_latitude']) ||
                                            !empty($quote['address']);
        
        return [
            'services' => $services,
            'property_details' => $quote['property_details'] ?? '',
            'urgency' => $quote['urgency'] ?? '',
            'budget_range' => $quote['budget_range'] ?? '',
            'address' => $quote['address'] ?? '',
            'has_gps' => !empty($quote['geo_latitude']),
            'gps_accuracy' => $quote['geo_accuracy'] ?? null,
            'media_summary' => $media_summary,
            'total_media' => count($media_files),
            'exif_locations' => count($exif_locations),
            'customer_notes' => $quote['notes'] ?? ''
        ];
    }
    
    private static function buildContextAssessmentPrompt($data) {
        $services_text = implode(', ', $data['services']);
        
        return "Assess whether this tree service submission has sufficient context for an accurate estimate:

**SERVICES REQUESTED:** {$services_text}

**PROPERTY DETAILS:** {$data['property_details']}

**CUSTOMER NOTES:** {$data['customer_notes']}

**LOCATION DATA:**
- Address: {$data['address']}
- GPS Available: " . ($data['has_gps'] ? 'Yes' : 'No') . "
- GPS Accuracy: {$data['gps_accuracy']}m
- Photo Locations: {$data['exif_locations']}

**MEDIA PROVIDED:**
- Images: {$data['media_summary']['images']}
- Videos: {$data['media_summary']['videos']}
- Total Files: {$data['total_media']}

**ASSESSMENT CRITERIA:**
1. **Visual Documentation** (40%): Are there enough photos/videos to assess tree condition, size, location, accessibility?
2. **Property Context** (25%): Can you determine property layout, obstacles, proximity to structures?
3. **Service Specificity** (20%): Are the requested services clearly defined and detailed?
4. **Access Assessment** (15%): Can you evaluate equipment access, terrain, safety considerations?

Respond with JSON:
```json
{
  \"confidence_score\": 85,
  \"overall_rating\": \"sufficient|insufficient|excellent\",
  \"estimate_accuracy\": \"high|medium|low\",
  \"missing_elements\": [\"element1\", \"element2\"],
  \"strengths\": [\"strength1\", \"strength2\"],
  \"recommendations\": [\"action1\", \"action2\"],
  \"can_provide_accurate_estimate\": true,
  \"reasoning\": \"Brief explanation of assessment\"
}
```";
    }
    
    private static function performRuleBasedAssessment($data) {
        $score = 0;
        $missing = [];
        $strengths = [];
        $recommendations = [];
        
        // Visual documentation (40 points max)
        if ($data['media_summary']['images'] >= 3) {
            $score += 30;
            $strengths[] = "Multiple photos provided";
        } elseif ($data['media_summary']['images'] >= 1) {
            $score += 15;
            $missing[] = "More photos needed for complete assessment";
        } else {
            $missing[] = "Photos required for visual assessment";
        }
        
        if ($data['media_summary']['videos'] >= 1) {
            $score += 10;
            $strengths[] = "Video provides dynamic context";
        }
        
        // Location context (25 points max)
        if ($data['has_gps'] && $data['gps_accuracy'] < 20) {
            $score += 25;
            $strengths[] = "Precise GPS location available";
        } elseif (!empty($data['address'])) {
            $score += 15;
            $strengths[] = "Address provided for location context";
        } else {
            $score += 5;
            $missing[] = "Location information incomplete";
        }
        
        // Service specificity (20 points max)
        if (count($data['services']) >= 1 && !empty($data['property_details'])) {
            $score += 20;
            $strengths[] = "Clear service requirements and details";
        } elseif (count($data['services']) >= 1) {
            $score += 10;
            $missing[] = "More details about property/trees needed";
        } else {
            $missing[] = "Service requirements unclear";
        }
        
        // Access assessment (15 points max)
        if (!empty($data['customer_notes']) || $data['total_media'] >= 2) {
            $score += 15;
            $strengths[] = "Sufficient context for access evaluation";
        } else {
            $score += 5;
            $missing[] = "Access and safety considerations unclear";
        }
        
        // Generate recommendations
        if ($data['total_media'] < 3) {
            $recommendations[] = "Request additional photos from multiple angles";
        }
        if (empty($data['property_details'])) {
            $recommendations[] = "Ask for detailed property description";
        }
        if (!$data['has_gps'] && empty($data['address'])) {
            $recommendations[] = "Obtain precise location information";
        }
        
        $rating = $score >= 70 ? 'sufficient' : ($score >= 90 ? 'excellent' : 'insufficient');
        $accuracy = $score >= 75 ? 'high' : ($score >= 50 ? 'medium' : 'low');
        
        return [
            'confidence_score' => $score,
            'overall_rating' => $rating,
            'estimate_accuracy' => $accuracy,
            'missing_elements' => $missing,
            'strengths' => $strengths,
            'recommendations' => $recommendations,
            'can_provide_accurate_estimate' => $score >= 60,
            'reasoning' => "Rule-based assessment: {$score}/100 points based on media, location, service details, and access factors"
        ];
    }
    
    private static function formatAssessmentResponse($ai_data, $submission_data) {
        // Ensure all required fields are present
        $defaults = [
            'confidence_score' => 50,
            'overall_rating' => 'insufficient',
            'estimate_accuracy' => 'low',
            'missing_elements' => [],
            'strengths' => [],
            'recommendations' => [],
            'can_provide_accurate_estimate' => false,
            'reasoning' => 'Assessment incomplete'
        ];
        
        $assessment = array_merge($defaults, $ai_data);
        
        // Add submission context
        $assessment['submission_summary'] = [
            'total_media' => $submission_data['total_media'],
            'services_count' => count($submission_data['services']),
            'has_location' => $submission_data['has_gps'] || !empty($submission_data['address']),
            'has_details' => !empty($submission_data['property_details'])
        ];
        
        return $assessment;
    }
    
    private static function storeAssessmentResults($quote_id, $assessment) {
        global $pdo;
        
        try {
            // Create context_assessments table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS context_assessments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    quote_id INT NOT NULL,
                    confidence_score INT NOT NULL,
                    overall_rating ENUM('excellent', 'sufficient', 'insufficient') NOT NULL,
                    estimate_accuracy ENUM('high', 'medium', 'low') NOT NULL,
                    can_provide_accurate_estimate BOOLEAN NOT NULL,
                    missing_elements JSON DEFAULT NULL,
                    strengths JSON DEFAULT NULL,
                    recommendations JSON DEFAULT NULL,
                    reasoning TEXT DEFAULT NULL,
                    assessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
                )
            ");
            
            // Insert assessment results
            $stmt = $pdo->prepare("
                INSERT INTO context_assessments 
                (quote_id, confidence_score, overall_rating, estimate_accuracy, can_provide_accurate_estimate,
                 missing_elements, strengths, recommendations, reasoning)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                confidence_score = VALUES(confidence_score),
                overall_rating = VALUES(overall_rating),
                estimate_accuracy = VALUES(estimate_accuracy),
                can_provide_accurate_estimate = VALUES(can_provide_accurate_estimate),
                missing_elements = VALUES(missing_elements),
                strengths = VALUES(strengths),
                recommendations = VALUES(recommendations),
                reasoning = VALUES(reasoning),
                assessed_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $quote_id,
                $assessment['confidence_score'],
                $assessment['overall_rating'],
                $assessment['estimate_accuracy'],
                $assessment['can_provide_accurate_estimate'] ? 1 : 0,
                json_encode($assessment['missing_elements']),
                json_encode($assessment['strengths']),
                json_encode($assessment['recommendations']),
                $assessment['reasoning']
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to store assessment results: " . $e->getMessage());
        }
    }
    
    private static function getDefaultAssessment($type = 'insufficient', $message = '') {
        return [
            'confidence_score' => 0,
            'overall_rating' => $type,
            'estimate_accuracy' => 'low',
            'missing_elements' => ['Unable to assess - system error'],
            'strengths' => [],
            'recommendations' => ['Manual review required'],
            'can_provide_accurate_estimate' => false,
            'reasoning' => $message ?: 'Default assessment due to processing error'
        ];
    }
    
    public static function getAssessmentForQuote($quote_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM context_assessments WHERE quote_id = ? ORDER BY assessed_at DESC LIMIT 1");
            $stmt->execute([$quote_id]);
            $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($assessment) {
                return [
                    'confidence_score' => (int)$assessment['confidence_score'],
                    'overall_rating' => $assessment['overall_rating'],
                    'estimate_accuracy' => $assessment['estimate_accuracy'],
                    'can_provide_accurate_estimate' => (bool)$assessment['can_provide_accurate_estimate'],
                    'missing_elements' => json_decode($assessment['missing_elements'], true) ?: [],
                    'strengths' => json_decode($assessment['strengths'], true) ?: [],
                    'recommendations' => json_decode($assessment['recommendations'], true) ?: [],
                    'reasoning' => $assessment['reasoning'],
                    'assessed_at' => $assessment['assessed_at']
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Failed to get assessment: " . $e->getMessage());
            return null;
        }
    }
}
?> 