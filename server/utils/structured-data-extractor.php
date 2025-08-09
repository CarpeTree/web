<?php
// Structured Data Extractor for AI Analysis
class StructuredDataExtractor {
    
    /**
     * Extract structured data from AI analysis text
     */
    public function extractAllStructuredData($ai_analysis, $ai_model = null) {
        if (empty($ai_analysis)) {
            return $this->getEmptyStructure();
        }
        
        return [
            "tree_species" => $this->extractTreeSpecies($ai_analysis),
            "tree_height" => $this->extractTreeHeight($ai_analysis),
            "crown_spread" => $this->extractCrownSpread($ai_analysis),
            "dbh" => $this->extractDBH($ai_analysis),
            "tree_health" => $this->extractTreeHealth($ai_analysis),
            "line_items" => $this->extractLineItems($ai_analysis),
            "waste_disposal" => $this->extractWasteDisposal($ai_analysis),
            "safety_concerns" => $this->extractSafetyConcerns($ai_analysis),
            "recommendations" => $this->extractRecommendations($ai_analysis),
            "estimated_costs" => $this->extractCosts($ai_analysis),
            "confidence_scores" => $this->calculateConfidenceScores($ai_analysis),
            "extracted_from" => $ai_model
        ];
    }
    
    /**
     * Extract tree species with confidence
     */
    private function extractTreeSpecies($text) {
        $species_patterns = [
            // Direct identification
            "/(?:species|identified as|appears to be|looks like)[\s:]*([^.\n]+?)(?:[.\n]|$)/i",
            "/(?:this is|this appears to be)[\s]*(?:a|an)?[\s]*([^.\n]+?)(?:[.\n]|tree)/i",
            // Common tree types
            "/(oak|maple|pine|fir|cedar|birch|aspen|poplar|spruce|hemlock|cottonwood|willow|cherry|apple|pear)(?:\s+tree)?/i",
            // Scientific names
            "/([A-Z][a-z]+\s+[a-z]+)/", // Genus species format
        ];
        
        foreach ($species_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $species = trim($matches[1]);
                $confidence = $this->calculateSpeciesConfidence($text, $species);
                
                return [
                    "species" => $species,
                    "confidence" => $confidence,
                    "source_text" => $matches[0]
                ];
            }
        }
        
        return ["species" => null, "confidence" => 0, "source_text" => null];
    }
    
    /**
     * Extract tree height
     */
    private function extractTreeHeight($text) {
        $height_patterns = [
            "/height[:\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:m|meters?|metre?s?)/i",
            "/(\d+(?:\.\d+)?)\s*(?:m|meters?|metre?s?)[\s]*(?:tall|high|in height)/i",
            "/(?:tall|high)[\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:m|meters?|metre?s?)/i",
            "/(\d+(?:\.\d+)?)\s*(?:m|meters?|metre?s?)[\s]*tall/i",
            // Imperial units (convert to metric)
            "/(\d+(?:\.\d+)?)\s*(?:ft|feet|foot)[\s]*(?:tall|high|in height)/i"
        ];
        
        foreach ($height_patterns as $i => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $height = (float)$matches[1];
                
                // Convert feet to meters if imperial
                if ($i === 4 && strpos($matches[0], "ft") !== false) {
                    $height = round($height * 0.3048, 1);
                    $units = "m (converted from ft)";
                } else {
                    $units = "m";
                }
                
                return [
                    "height" => $height,
                    "units" => $units,
                    "confidence" => $this->calculateMeasurementConfidence($text, "height"),
                    "source_text" => $matches[0]
                ];
            }
        }
        
        return ["height" => null, "units" => "m", "confidence" => 0, "source_text" => null];
    }
    
    /**
     * Extract crown spread
     */
    private function extractCrownSpread($text) {
        $crown_patterns = [
            "/crown[\s]*(?:spread|diameter|width)[:\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:m|meters?|metre?s?)/i",
            "/(?:spread|diameter|width)[\s]*(?:of)?[\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:m|meters?|metre?s?)/i",
            "/canopy[\s]*(?:spread|diameter|width)[:\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:m|meters?|metre?s?)/i",
            "/(\d+(?:\.\d+)?)\s*(?:m|meters?|metre?s?)[\s]*(?:crown|canopy|spread)/i"
        ];
        
        foreach ($crown_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $spread = (float)$matches[1];
                
                return [
                    "crown_spread" => $spread,
                    "units" => "m", 
                    "confidence" => $this->calculateMeasurementConfidence($text, "crown"),
                    "source_text" => $matches[0]
                ];
            }
        }
        
        return ["crown_spread" => null, "units" => "m", "confidence" => 0, "source_text" => null];
    }
    
    /**
     * Extract DBH (Diameter at Breast Height)
     */
    private function extractDBH($text) {
        $dbh_patterns = [
            "/dbh[:\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:cm|centimeters?|centimetres?)/i",
            "/diameter[\s]*at[\s]*breast[\s]*height[:\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:cm|centimeters?|centimetres?)/i",
            "/trunk[\s]*diameter[:\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:cm|centimeters?|centimetres?)/i",
            "/(\d+(?:\.\d+)?)\s*(?:cm|centimeters?|centimetres?)[\s]*(?:dbh|diameter)/i"
        ];
        
        foreach ($dbh_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $dbh = (float)$matches[1];
                
                return [
                    "dbh" => $dbh,
                    "units" => "cm",
                    "confidence" => $this->calculateMeasurementConfidence($text, "dbh"),
                    "source_text" => $matches[0]
                ];
            }
        }
        
        return ["dbh" => null, "units" => "cm", "confidence" => 0, "source_text" => null];
    }
    
    /**
     * Extract tree health assessment
     */
    private function extractTreeHealth($text) {
        $health_patterns = [
            "/health[:\s]*(?:appears to be|is|looks)?[\s]*(excellent|good|fair|poor|declining|dead)/i",
            "/(?:tree|condition)[\s]*(?:appears|looks|seems)?[\s]*(?:to be)?[\s]*(excellent|good|fair|poor|declining|dead)/i",
            "/(?:excellent|good|fair|poor|declining|dead)[\s]*(?:health|condition)/i"
        ];
        
        foreach ($health_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $health = strtolower($matches[1]);
                
                return [
                    "health_status" => $health,
                    "confidence" => $this->calculateHealthConfidence($text, $health),
                    "source_text" => $matches[0]
                ];
            }
        }
        
        return ["health_status" => null, "confidence" => 0, "source_text" => null];
    }
    
    /**
     * Extract line items and services
     */
    private function extractLineItems($text) {
        $line_items = [];
        
        // Service patterns
        $service_patterns = [
            "removal" => "/(?:remove|removal|take down|cut down)/i",
            "pruning" => "/(?:prune|pruning|trim|trimming)/i", 
            "crown_reduction" => "/crown[\s]*reduction/i",
            "deadwood_removal" => "/(?:deadwood|dead wood)[\s]*removal/i",
            "stump_grinding" => "/stump[\s]*(?:grinding|removal)/i",
            "cleanup" => "/(?:cleanup|clean up|debris removal)/i"
        ];
        
        foreach ($service_patterns as $service => $pattern) {
            if (preg_match($pattern, $text)) {
                $cost_estimate = $this->extractServiceCost($text, $service);
                $line_items[] = [
                    "service" => $service,
                    "detected" => true,
                    "estimated_cost" => $cost_estimate
                ];
            }
        }
        
        return $line_items;
    }
    
    /**
     * Extract waste disposal estimates
     */
    private function extractWasteDisposal($text) {
        $waste_patterns = [
            "/(?:brush|debris)[\s]*(?:volume|amount)[:\s]*(?:approximately|about|around)?[\s]*(\d+(?:\.\d+)?)\s*(?:m³|cubic meters?|cubic metres?)/i",
            "/(?:disposal|waste)[\s]*cost[:\s]*\$?(\d+(?:\.\d+)?)/i",
            "/(\d+(?:\.\d+)?)\s*(?:tonnes?|tons?)[\s]*(?:of[\s]*)?(?:brush|debris|waste)/i"
        ];
        
        $waste_data = [];
        
        foreach ($waste_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $value = (float)$matches[1];
                
                if (strpos($matches[0], "m³") !== false || strpos($matches[0], "cubic") !== false) {
                    $waste_data["volume_m3"] = $value;
                } elseif (strpos($matches[0], "cost") !== false || strpos($matches[0], "$") !== false) {
                    $waste_data["disposal_cost"] = $value;
                } elseif (strpos($matches[0], "tonne") !== false || strpos($matches[0], "ton") !== false) {
                    $waste_data["weight_tonnes"] = $value;
                }
            }
        }
        
        return $waste_data;
    }
    
    /**
     * Extract safety concerns
     */
    private function extractSafetyConcerns($text) {
        $safety_keywords = [
            "power lines", "electrical", "house", "building", "structure", "roof", 
            "danger", "hazard", "risk", "lean", "unstable", "decay", "crack"
        ];
        
        $concerns = [];
        foreach ($safety_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $concerns[] = $keyword;
            }
        }
        
        return $concerns;
    }
    
    /**
     * Extract recommendations
     */
    private function extractRecommendations($text) {
        $recommendation_patterns = [
            "/recommend[^.]*[.]/i",
            "/suggest[^.]*[.]/i", 
            "/should[^.]*[.]/i",
            "/advised[^.]*[.]/i"
        ];
        
        $recommendations = [];
        foreach ($recommendation_patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $recommendations = array_merge($recommendations, $matches[0]);
            }
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Extract cost estimates
     */
    private function extractCosts($text) {
        $cost_patterns = [
            "/\$(\d+(?:,\d{3})*(?:\.\d{2})?)/", // $1,500.00
            "/(\d+(?:,\d{3})*(?:\.\d{2})?)\s*dollars?/i", // 1500 dollars
        ];
        
        $costs = [];
        foreach ($cost_patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $cost) {
                    $costs[] = (float)str_replace(",", "", $cost);
                }
            }
        }
        
        return [
            "individual_costs" => $costs,
            "min_cost" => $costs ? min($costs) : null,
            "max_cost" => $costs ? max($costs) : null,
            "avg_cost" => $costs ? round(array_sum($costs) / count($costs), 2) : null
        ];
    }
    
    private function calculateSpeciesConfidence($text, $species) {
        $confidence_indicators = [
            "appears to be" => 0.7,
            "looks like" => 0.6,
            "seems to be" => 0.6,
            "identified as" => 0.9,
            "is a" => 0.8,
            "definitely" => 0.95,
            "possibly" => 0.4,
            "might be" => 0.3
        ];
        
        $base_confidence = 0.5;
        foreach ($confidence_indicators as $phrase => $modifier) {
            if (stripos($text, $phrase) !== false) {
                $base_confidence = $modifier;
                break;
            }
        }
        
        return round($base_confidence, 2);
    }
    
    private function calculateMeasurementConfidence($text, $measurement_type) {
        $precision_indicators = [
            "approximately" => 0.6,
            "about" => 0.6,
            "around" => 0.6,
            "roughly" => 0.5,
            "exactly" => 0.9,
            "measured" => 0.95,
            "estimated" => 0.7
        ];
        
        $base_confidence = 0.7; // Default for measurements
        foreach ($precision_indicators as $phrase => $modifier) {
            if (stripos($text, $phrase) !== false) {
                $base_confidence = $modifier;
                break;
            }
        }
        
        return round($base_confidence, 2);
    }
    
    private function calculateHealthConfidence($text, $health_status) {
        // Health assessments are generally reliable from AI
        $certainty_phrases = [
            "clearly" => 0.9,
            "obviously" => 0.9,
            "appears" => 0.7,
            "seems" => 0.6,
            "shows signs" => 0.8
        ];
        
        $base_confidence = 0.7;
        foreach ($certainty_phrases as $phrase => $modifier) {
            if (stripos($text, $phrase) !== false) {
                $base_confidence = $modifier;
                break;
            }
        }
        
        return round($base_confidence, 2);
    }
    
    private function calculateConfidenceScores($text) {
        $length_score = min(strlen($text) / 1000, 1.0); // Longer = more confidence
        $detail_score = (substr_count($text, "measurement") + substr_count($text, "diameter") + substr_count($text, "height")) / 10;
        $certainty_score = (substr_count($text, "clearly") + substr_count($text, "definitely") + substr_count($text, "measured")) / 5;
        
        return [
            "overall_confidence" => round(($length_score + $detail_score + $certainty_score) / 3, 2),
            "length_score" => round($length_score, 2),
            "detail_score" => round(min($detail_score, 1.0), 2),
            "certainty_score" => round(min($certainty_score, 1.0), 2)
        ];
    }
    
    private function extractServiceCost($text, $service) {
        // Look for costs near service mentions
        $service_text = $this->getTextAroundService($text, $service);
        $costs = $this->extractCosts($service_text);
        
        return $costs["individual_costs"] ? $costs["individual_costs"][0] : null;
    }
    
    private function getTextAroundService($text, $service) {
        $pos = stripos($text, $service);
        if ($pos === false) return "";
        
        $start = max(0, $pos - 100);
        $length = 200;
        
        return substr($text, $start, $length);
    }
    
    private function getEmptyStructure() {
        return [
            "tree_species" => ["species" => null, "confidence" => 0, "source_text" => null],
            "tree_height" => ["height" => null, "units" => "m", "confidence" => 0, "source_text" => null],
            "crown_spread" => ["crown_spread" => null, "units" => "m", "confidence" => 0, "source_text" => null],
            "dbh" => ["dbh" => null, "units" => "cm", "confidence" => 0, "source_text" => null],
            "tree_health" => ["health_status" => null, "confidence" => 0, "source_text" => null],
            "line_items" => [],
            "waste_disposal" => [],
            "safety_concerns" => [],
            "recommendations" => [],
            "estimated_costs" => ["individual_costs" => [], "min_cost" => null, "max_cost" => null, "avg_cost" => null],
            "confidence_scores" => ["overall_confidence" => 0, "length_score" => 0, "detail_score" => 0, "certainty_score" => 0],
            "extracted_from" => null
        ];
    }
}

// API endpoint for structured data extraction
if (isset($_POST["extract_data"]) || isset($_GET["extract_data"])) {
    header("Content-Type: application/json");
    
    $analysis_text = $_POST["analysis_text"] ?? $_GET["analysis_text"] ?? "";
    $ai_model = $_POST["ai_model"] ?? $_GET["ai_model"] ?? "unknown";
    
    if (empty($analysis_text)) {
        http_response_code(400);
        echo json_encode(["error" => "analysis_text required"]);
        exit;
    }
    
    $extractor = new StructuredDataExtractor();
    $structured_data = $extractor->extractAllStructuredData($analysis_text, $ai_model);
    
    echo json_encode([
        "success" => true,
        "structured_data" => $structured_data,
        "extracted_at" => date("Y-m-d H:i:s")
    ], JSON_PRETTY_PRINT);
    exit;
}
?>