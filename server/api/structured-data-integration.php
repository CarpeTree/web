<?php
// Admin Dashboard Integration API for Structured Data
header("Content-Type: application/json");
require_once "../utils/structured-data-extractor.php";
require_once "../config/database-simple.php";

try {
    $action = $_GET["action"] ?? $_POST["action"] ?? "extract";
    
    switch ($action) {
        case "extract_quote_data":
            if (!isset($_GET["quote_id"])) {
                throw new Exception("quote_id required");
            }
            
            $quote_id = $_GET["quote_id"];
            
            // Get quote with AI analysis
            $stmt = $pdo->prepare("
                SELECT 
                    id, ai_gemini_analysis, ai_o3_analysis, ai_o4_mini_analysis
                FROM quotes 
                WHERE id = ?
            ");
            $stmt->execute([$quote_id]);
            $quote = $stmt->fetch();
            
            if (!$quote) {
                throw new Exception("Quote not found");
            }
            
            $extractor = new StructuredDataExtractor();
            $extracted_data = [];
            
            // Extract from each AI model
            if ($quote["ai_gemini_analysis"]) {
                $extracted_data["gemini"] = $extractor->extractAllStructuredData($quote["ai_gemini_analysis"], "gemini");
            }
            
            if ($quote["ai_o3_analysis"]) {
                $extracted_data["o3"] = $extractor->extractAllStructuredData($quote["ai_o3_analysis"], "o3");
            }
            
            if ($quote["ai_o4_mini_analysis"]) {
                $extracted_data["o4_mini"] = $extractor->extractAllStructuredData($quote["ai_o4_mini_analysis"], "o4_mini");
            }
            
            $result = [
                "quote_id" => $quote_id,
                "extracted_data" => $extracted_data,
                "comparison" => $extractor->compareAIOutputs($extracted_data)
            ];
            break;
            
        case "save_selection":
            if (!isset($_POST["quote_id"]) || !isset($_POST["selections"])) {
                throw new Exception("quote_id and selections required");
            }
            
            $quote_id = $_POST["quote_id"];
            $selections = json_decode($_POST["selections"], true);
            
            // Save admin selections to database
            $stmt = $pdo->prepare("
                INSERT INTO admin_selections (quote_id, field_name, selected_ai, selected_value, confidence_override, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                selected_ai = VALUES(selected_ai),
                selected_value = VALUES(selected_value),
                confidence_override = VALUES(confidence_override),
                updated_at = NOW()
            ");
            
            foreach ($selections as $field => $selection) {
                $stmt->execute([
                    $quote_id,
                    $field,
                    $selection["ai_model"] ?? null,
                    $selection["value"] ?? null,
                    $selection["confidence"] ?? null
                ]);
            }
            
            $result = ["success" => true, "message" => "Selections saved"];
            break;
            
        default:
            throw new Exception("Invalid action");
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>