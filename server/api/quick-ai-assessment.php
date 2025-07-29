<?php
// Quick AI Assessment - Check if submission is sufficient for accurate quoting
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database-simple.php';

function quickAIAssessment($quote_id) {
    global $pdo;
    
    try {
        // Get quote details
        $stmt = $pdo->prepare("
            SELECT q.*, c.name, c.email, c.phone, c.address 
            FROM quotes q 
            JOIN customers c ON q.customer_id = c.id 
            WHERE q.id = ?
        ");
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quote) {
            return ['error' => 'Quote not found'];
        }
        
        // Get uploaded files (handle missing table gracefully)
        $files = [];
        try {
            $file_stmt = $pdo->prepare("
                SELECT original_filename, file_size, mime_type, file_path 
                FROM uploaded_files 
                WHERE quote_id = ? 
                ORDER BY uploaded_at DESC
            ");
            $file_stmt->execute([$quote_id]);
            $files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table doesn't exist yet - that's okay for assessment
            if (strpos($e->getMessage(), "doesn't exist") === false) {
                throw $e; // Re-throw if it's not a "table doesn't exist" error
            }
        }
        
        // Parse services
        $services = json_decode($quote['selected_services'], true) ?: [];
        
        // Quick assessment criteria
        $assessment = [
            'quote_id' => $quote_id,
            'customer_name' => $quote['name'],
            'submission_time' => $quote['quote_created_at'],
            'assessment_time' => date('Y-m-d H:i:s'),
            'sufficiency_score' => 0,
            'max_score' => 100,
            'category' => 'insufficient', // insufficient, marginal, good, excellent
            'can_quote_accurately' => false,
            'confidence_level' => 'low',
            'detailed_breakdown' => [],
            'recommendations' => [],
            'priority_level' => 'medium',
            'estimated_quote_accuracy' => '30%'
        ];
        
        // 1. Service Information Assessment (25 points)
        $service_score = assessServiceInformation($services, $assessment);
        
        // 2. Media Quality Assessment (35 points)
        $media_score = assessMediaQuality($files, $assessment);
        
        // 3. Customer Details Assessment (20 points)
        $details_score = assessCustomerDetails($quote, $assessment);
        
        // 4. Location Information Assessment (20 points)
        $location_score = assessLocationInformation($quote, $assessment);
        
        // Calculate total score
        $total_score = $service_score + $media_score + $details_score + $location_score;
        $assessment['sufficiency_score'] = $total_score;
        
        // Determine category and confidence
        if ($total_score >= 85) {
            $assessment['category'] = 'excellent';
            $assessment['can_quote_accurately'] = true;
            $assessment['confidence_level'] = 'high';
            $assessment['priority_level'] = 'high';
            $assessment['estimated_quote_accuracy'] = '90-95%';
        } elseif ($total_score >= 70) {
            $assessment['category'] = 'good';
            $assessment['can_quote_accurately'] = true;
            $assessment['confidence_level'] = 'medium-high';
            $assessment['priority_level'] = 'high';
            $assessment['estimated_quote_accuracy'] = '80-90%';
        } elseif ($total_score >= 50) {
            $assessment['category'] = 'marginal';
            $assessment['can_quote_accurately'] = false;
            $assessment['confidence_level'] = 'medium';
            $assessment['priority_level'] = 'medium';
            $assessment['estimated_quote_accuracy'] = '60-75%';
        } else {
            $assessment['category'] = 'insufficient';
            $assessment['can_quote_accurately'] = false;
            $assessment['confidence_level'] = 'low';
            $assessment['priority_level'] = 'low';
            $assessment['estimated_quote_accuracy'] = '30-50%';
        }
        
        // Generate action recommendations
        generateActionRecommendations($assessment);
        
        // Store assessment in database
        storeAssessment($quote_id, $assessment);
        
        return $assessment;
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function assessServiceInformation($services, &$assessment) {
    $score = 0;
    $details = [];
    
    if (empty($services)) {
        $details[] = "❌ No services selected (-25 points)";
        $assessment['recommendations'][] = "Contact customer to clarify which services they need";
        return 0;
    }
    
    $service_count = count($services);
    if ($service_count >= 3) {
        $score += 25;
        $details[] = "✅ Multiple services selected (+25 points)";
    } elseif ($service_count >= 2) {
        $score += 20;
        $details[] = "✅ Two services selected (+20 points)";
    } else {
        $score += 15;
        $details[] = "⚠️ Single service selected (+15 points)";
    }
    
    // Bonus for complex services that benefit from media
    $complex_services = ['removal', 'pruning', 'cabling', 'emergency'];
    $has_complex = array_intersect($services, $complex_services);
    if (!empty($has_complex)) {
        $details[] = "ℹ️ Complex services detected: " . implode(', ', $has_complex);
    }
    
    $assessment['detailed_breakdown']['service_information'] = [
        'score' => $score,
        'max_score' => 25,
        'details' => $details
    ];
    
    return $score;
}

function assessMediaQuality($files, &$assessment) {
    $score = 0;
    $details = [];
    
    if (empty($files)) {
        $details[] = "❌ No media uploaded (-35 points)";
        $assessment['recommendations'][] = "Request photos/videos for accurate assessment";
        $assessment['recommendations'][] = "Schedule on-site visit for visual inspection";
        
        $assessment['detailed_breakdown']['media_quality'] = [
            'score' => 0,
            'max_score' => 35,
            'details' => $details
        ];
        return 0;
    }
    
    $file_count = count($files);
    $has_video = false;
    $has_images = false;
    $total_size = 0;
    
    foreach ($files as $file) {
        $total_size += $file['file_size'];
        if (strpos($file['mime_type'], 'video/') === 0) {
            $has_video = true;
        } elseif (strpos($file['mime_type'], 'image/') === 0) {
            $has_images = true;
        }
    }
    
    // File count scoring
    if ($file_count >= 5) {
        $score += 15;
        $details[] = "✅ Multiple files uploaded (+15 points)";
    } elseif ($file_count >= 3) {
        $score += 12;
        $details[] = "✅ Several files uploaded (+12 points)";
    } elseif ($file_count >= 1) {
        $score += 8;
        $details[] = "⚠️ Few files uploaded (+8 points)";
    }
    
    // Media type scoring
    if ($has_video && $has_images) {
        $score += 20;
        $details[] = "✅ Both video and images provided (+20 points)";
    } elseif ($has_video) {
        $score += 15;
        $details[] = "✅ Video provided (+15 points)";
    } elseif ($has_images) {
        $score += 10;
        $details[] = "⚠️ Images only (+10 points)";
    }
    
    // File size assessment (indicates quality)
    $avg_size_mb = ($total_size / $file_count) / (1024 * 1024);
    if ($avg_size_mb > 5) {
        $details[] = "✅ High quality files (large file sizes)";
    } elseif ($avg_size_mb > 1) {
        $details[] = "⚠️ Medium quality files";
    } else {
        $details[] = "⚠️ Small file sizes may indicate low quality";
        $assessment['recommendations'][] = "Consider requesting higher quality images/videos";
    }
    
    if ($score < 20) {
        $assessment['recommendations'][] = "Request additional photos from multiple angles";
        $assessment['recommendations'][] = "Ask for close-up shots of specific problem areas";
    }
    
    $assessment['detailed_breakdown']['media_quality'] = [
        'score' => $score,
        'max_score' => 35,
        'details' => $details,
        'file_count' => $file_count,
        'has_video' => $has_video,
        'has_images' => $has_images,
        'avg_file_size_mb' => round($avg_size_mb, 2)
    ];
    
    return $score;
}

function assessCustomerDetails($quote, &$assessment) {
    $score = 0;
    $details = [];
    
    // Contact information
    if (!empty($quote['phone'])) {
        $score += 5;
        $details[] = "✅ Phone number provided (+5 points)";
    } else {
        $details[] = "❌ No phone number (-5 points)";
        $assessment['recommendations'][] = "Request phone number for follow-up questions";
    }
    
    // Address specificity
    if (!empty($quote['address']) && strlen($quote['address']) > 10) {
        $score += 10;
        $details[] = "✅ Detailed address provided (+10 points)";
    } elseif (!empty($quote['address'])) {
        $score += 5;
        $details[] = "⚠️ Basic address provided (+5 points)";
        $assessment['recommendations'][] = "Confirm exact property location";
    } else {
        $details[] = "❌ No address provided (-10 points)";
        $assessment['recommendations'][] = "Request property address for accurate assessment";
    }
    
    // Customer notes/description
    if (!empty($quote['notes']) && strlen($quote['notes']) > 50) {
        $score += 5;
        $details[] = "✅ Detailed notes provided (+5 points)";
    } elseif (!empty($quote['notes'])) {
        $score += 3;
        $details[] = "⚠️ Basic notes provided (+3 points)";
    } else {
        $details[] = "❌ No additional notes (-5 points)";
        $assessment['recommendations'][] = "Ask customer to describe the tree work needed";
    }
    
    $assessment['detailed_breakdown']['customer_details'] = [
        'score' => $score,
        'max_score' => 20,
        'details' => $details
    ];
    
    return $score;
}

function assessLocationInformation($quote, &$assessment) {
    $score = 0;
    $details = [];
    
    // GPS coordinates
    if (!empty($quote['gps_lat']) && !empty($quote['gps_lng'])) {
        $score += 10;
        $details[] = "✅ GPS coordinates available (+10 points)";
    } else {
        $details[] = "⚠️ No GPS coordinates (-10 points)";
        $assessment['recommendations'][] = "GPS coordinates would help with travel planning";
    }
    
    // EXIF data from photos
    if (!empty($quote['exif_lat']) && !empty($quote['exif_lng'])) {
        $score += 5;
        $details[] = "✅ Photo location data available (+5 points)";
    } else {
        $details[] = "ℹ️ No photo location data";
    }
    
    // Address analysis for known areas
    if (!empty($quote['address'])) {
        $address_lower = strtolower($quote['address']);
        $known_areas = ['nelson', 'castlegar', 'trail', 'rossland', 'kaslo', 'nakusp', 'new denver', 'salmo'];
        
        foreach ($known_areas as $area) {
            if (strpos($address_lower, $area) !== false) {
                $score += 5;
                $details[] = "✅ Known service area: $area (+5 points)";
                break;
            }
        }
    }
    
    $assessment['detailed_breakdown']['location_information'] = [
        'score' => $score,
        'max_score' => 20,
        'details' => $details
    ];
    
    return $score;
}

function generateActionRecommendations(&$assessment) {
    $category = $assessment['category'];
    $score = $assessment['sufficiency_score'];
    
    switch ($category) {
        case 'excellent':
            $assessment['action_plan'] = [
                "🚀 High Priority: Process immediately",
                "✅ Generate detailed quote with confidence",
                "📧 Send professional quote email",
                "📞 Optional: Follow-up call to discuss timeline"
            ];
            break;
            
        case 'good':
            $assessment['action_plan'] = [
                "⚡ Good Priority: Process within 4 hours",
                "✅ Generate quote with minor assumptions noted",
                "📧 Send quote with clarification questions",
                "📞 Recommended: Brief call to confirm details"
            ];
            break;
            
        case 'marginal':
            $assessment['action_plan'] = [
                "⚠️ Medium Priority: Gather more info first",
                "📞 Required: Call customer for clarification",
                "📸 Request: Additional photos/videos",
                "🏠 Consider: On-site assessment if nearby"
            ];
            break;
            
        case 'insufficient':
            $assessment['action_plan'] = [
                "🔍 Low Priority: Major information gaps",
                "📞 Required: Detailed phone consultation",
                "📋 Request: Complete service requirements",
                "🏠 Recommended: Schedule on-site assessment"
            ];
            break;
    }
    
    // Add time-sensitive recommendations
    $hours_since_submission = (time() - strtotime($assessment['submission_time'])) / 3600;
    
    if ($hours_since_submission > 24) {
        $assessment['recommendations'][] = "⏰ URGENT: Quote is over 24 hours old - contact customer immediately";
        $assessment['priority_level'] = 'urgent';
    } elseif ($hours_since_submission > 12) {
        $assessment['recommendations'][] = "⏰ Follow up soon - quote is over 12 hours old";
    }
}

function storeAssessment($quote_id, $assessment) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE quotes SET 
                ai_analysis_complete = 1,
                ai_response_json = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            json_encode($assessment),
            $quote_id
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to store assessment for quote $quote_id: " . $e->getMessage());
    }
}

// Handle API calls only if called via HTTP
if (isset($_SERVER['REQUEST_METHOD'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['quote_id'])) {
        $quote_id = (int)$_GET['quote_id'];
        $result = quickAIAssessment($quote_id);
        echo json_encode($result, JSON_PRETTY_PRINT);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $quote_id = $input['quote_id'] ?? null;
        
        if ($quote_id) {
            $result = quickAIAssessment($quote_id);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Quote ID required']);
        }
    } else {
        // Show usage
        echo json_encode([
            'usage' => 'Quick AI Assessment API',
            'endpoints' => [
                'GET /quick-ai-assessment.php?quote_id=X' => 'Get assessment for quote',
                'POST /quick-ai-assessment.php' => 'Post with {"quote_id": X}'
            ],
            'example' => 'https://carpetree.com/server/api/quick-ai-assessment.php?quote_id=1'
        ]);
    }
}
?> 