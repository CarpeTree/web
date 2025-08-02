<?php
// Test video processing and AI analysis
require_once "server/config/config.php";
require_once "server/utils/media-preprocessor.php";

$pdo = getDatabaseConnection();
if (!$pdo) {
    die("Database connection failed");
}

// Test with Quote #69
$quote_id = 69;

echo "=== TESTING VIDEO PROCESSING FOR QUOTE #69 ===\n";

// Get quote data
$stmt = $pdo->prepare("SELECT q.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone FROM quotes q JOIN customers c ON q.customer_id = c.id WHERE q.id = ?");
$stmt->execute([$quote_id]);
$quote_data = $stmt->fetch();

if (!$quote_data) {
    die("Quote #69 not found");
}

// Get media files
$stmt = $pdo->prepare("SELECT * FROM media WHERE quote_id = ? ORDER BY id ASC");
$stmt->execute([$quote_id]);
$media_files = $stmt->fetchAll();

echo "Found " . count($media_files) . " media files:\n";
foreach ($media_files as $media) {
    $file_path = $media["file_path"];
    $full_path = __DIR__ . "/" . $file_path;
    $exists = file_exists($full_path) ? "✅ EXISTS" : "❌ MISSING";
    $size = file_exists($full_path) ? round(filesize($full_path) / (1024*1024), 1) . "MB" : "N/A";
    echo "- {$media["filename"]} ({$file_path}) - {$exists} ({$size})\n";
}

// Test MediaPreprocessor
echo "\n=== TESTING MEDIAPREPROCESSOR ===\n";
$preprocessor = new MediaPreprocessor($quote_id, $media_files, $quote_data);
$result = $preprocessor->preprocessAllMedia();

echo "Context text length: " . strlen($result["context_text"]) . " characters\n";
echo "Visual content items: " . count($result["visual_content"]) . "\n";
echo "Media summary items: " . count($result["media_summary"]) . "\n";
echo "Transcriptions: " . count($result["transcriptions"]) . "\n";

echo "\nMedia summaries:\n";
foreach ($result["media_summary"] as $summary) {
    echo "- {$summary}\n";
}

echo "\nFirst 500 characters of context:\n";
echo substr($result["context_text"], 0, 500) . "...\n";

if (!empty($result["visual_content"])) {
    echo "\nVisual content types:\n";
    foreach ($result["visual_content"] as $i => $content) {
        if (isset($content["type"])) {
            echo "- Item " . ($i+1) . ": " . $content["type"] . "\n";
            if ($content["type"] === "image_url" && isset($content["image_url"]["url"])) {
                $url = $content["image_url"]["url"];
                $data_prefix = substr($url, 0, 50);
                echo "  URL: {$data_prefix}...\n";
            } elseif ($content["type"] === "text") {
                echo "  Text: " . substr($content["text"], 0, 100) . "...\n";
            }
        }
    }
} else {
    echo "\n❌ No visual content generated!\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>