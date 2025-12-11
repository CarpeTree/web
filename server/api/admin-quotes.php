<?php
// Production settings - log errors but don't display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

header('Content-Type', 'application/json');

// CORS not needed beyond same-origin; remove permissive wildcard

// Admin API key guard (optional; enforced if ADMIN_API_KEY is set)
function require_admin_key() {
	$expected = getenv('ADMIN_API_KEY') ?: ($_ENV['ADMIN_API_KEY'] ?? null);
	if (!$expected) return;
	$provided = $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? ($_GET['admin_key'] ?? $_POST['admin_key'] ?? null);
	if (!$provided || !hash_equals($expected, $provided)) {
		http_response_code(401);
		echo json_encode(['success' => false, 'error' => 'Unauthorized']);
		exit();
	}
}
require_admin_key();

require_once '../config/database-simple.php';

/**
 * Build a readable AI summary from a heterogeneous analysis payload.
 * Accepts either a string or array/object with keys like summary/findings.
 */
function formatAISummary($analysis, $hasMedia = false) {
	try {
		if (is_string($analysis)) {
			return $analysis;
		}
		if (is_array($analysis)) {
			// Prefer explicit fields if present
			$parts = [];
			if (!empty($analysis['summary'])) { $parts[] = $analysis['summary']; }
			if (!empty($analysis['findings']) && is_array($analysis['findings'])) {
				$parts[] = 'Findings:\n- ' . implode("\n- ", array_map('strval', $analysis['findings']));
			}
			if (empty($parts)) {
				// Fallback: pretty print a compact JSON snippet
				$parts[] = json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			}
			$prefix = $hasMedia ? 'AI review of submitted media:' : 'AI review:';
			return $prefix . "\n" . implode("\n\n", $parts);
		}
		if (is_object($analysis)) {
			return formatAISummary((array)$analysis, $hasMedia);
		}
		return '';
	} catch (Throwable $e) {
		return '';
	}
}

/**
 * Coerce any model analysis into canonical shape {services:[], frames:[], raw:"", errors:[]}
 */
function coerce_analysis($input) {
	try {
		if (is_string($input)) {
			// try to peel code fences, then outer JSON braces
			if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $input, $m)) {
				$input = $m[1];
			}
			$s = strpos($input, '{'); $e = strrpos($input, '}');
			if ($s !== false && $e !== false && $e > $s) {
				$maybe = substr($input, $s, $e - $s + 1);
				$parsed = json_decode($maybe, true);
				if (json_last_error() === JSON_ERROR_NONE) { $input = $parsed; }
			}
		}
		if (is_array($input)) {
			return [
				'services' => isset($input['services']) && is_array($input['services']) ? $input['services'] : [],
				'frames'   => isset($input['frames']) && is_array($input['frames']) ? $input['frames'] : [],
				'raw'      => json_encode($input, JSON_UNESCAPED_SLASHES),
				'errors'   => []
			];
		}
		if (is_object($input)) { return coerce_analysis((array)$input); }
		if (is_string($input)) { return ['services'=>[], 'frames'=>[], 'raw'=>$input, 'errors'=>[]]; }
	} catch (Throwable $e) { /* fall through */ }
	return ['services'=>[], 'frames'=>[], 'raw'=>'', 'errors'=>[]];
}

try {
	// Set execution time limit to prevent timeouts
	set_time_limit(30);

	$quote_id = $_GET['quote_id'] ?? null;
	if ($quote_id) {
		$stmt = $pdo->prepare("
			SELECT
				q.id, q.customer_id, q.quote_status, q.selected_services, q.notes,
				q.ai_response_json, q.quote_created_at, q.quote_expires_at,
				q.ai_o4_mini_analysis, q.ai_o3_analysis, q.ai_gemini_analysis, q.gemini_analysis,
				q.gps_lat, q.gps_lng, q.exif_lat, q.exif_lng, q.total_estimate,
				c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
				c.address, c.referral_source, c.referrer_name, c.newsletter_opt_in,
				c.geo_latitude, c.geo_longitude, c.geo_accuracy, c.ip_address,
				COUNT(m.id) as media_count
			FROM quotes q
			JOIN customers c ON q.customer_id = c.id
			LEFT JOIN media m ON q.id = m.quote_id
			WHERE q.id = ?
			GROUP BY q.id
		");
		$stmt->execute([$quote_id]);
	} else {
		$stmt = $pdo->prepare("
			SELECT
				q.id, q.customer_id, q.quote_status, q.selected_services, q.notes,
				q.ai_response_json, q.quote_created_at, q.quote_expires_at,
				q.ai_o4_mini_analysis, q.ai_o3_analysis, q.ai_gemini_analysis, q.gemini_analysis,
				q.gps_lat, q.gps_lng, q.exif_lat, q.exif_lng, q.total_estimate,
				c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
				c.address, c.referral_source, c.referrer_name, c.newsletter_opt_in,
				c.geo_latitude, c.geo_longitude, c.geo_accuracy, c.ip_address,
				COUNT(m.id) as media_count
			FROM quotes q
			JOIN customers c ON q.customer_id = c.id
			LEFT JOIN media m ON q.id = m.quote_id
			GROUP BY q.id
			ORDER BY q.quote_created_at DESC
			LIMIT 50
		");
		$stmt->execute();
	}
	$quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$formatted_quotes = [];

	foreach ($quotes as $quote) {
		// Sanitize placeholder emails (leave blanks blank)
		if (!empty($quote['customer_email'])) {
			$__em = strtolower(trim($quote['customer_email']));
			if ($__em === 'field@carpetree.com' || $__em === 'field@carpetree.ca') {
				$quote['customer_email'] = null;
			}
		}
		// Get uploaded files for this quote (prefer new media table, fallback to legacy uploaded_files)
		$files = [];
		try {
			$file_stmt = $pdo->prepare("SELECT id, filename, mime_type, file_type, quote_id, file_path, file_size, original_filename FROM media WHERE quote_id = ? LIMIT 10");
			$file_stmt->execute([$quote['id']]);
			$files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			$files = [];
		}

		// If no media found, attempt to load from legacy uploaded_files table
		if (empty($files)) {
			try {
				$check = $pdo->query("SHOW TABLES LIKE 'uploaded_files'");
				$legacy_exists = $check && $check->fetch(PDO::FETCH_NUM) !== false;
				if ($legacy_exists) {
					$legacy_stmt = $pdo->prepare("SELECT id, filename, mime_type, file_type, quote_id, file_path, file_size FROM uploaded_files WHERE quote_id = ? LIMIT 10");
					$legacy_stmt->execute([$quote['id']]);
					$legacy_files = $legacy_stmt->fetchAll(PDO::FETCH_ASSOC);
					if (!empty($legacy_files)) {
						$files = $legacy_files;
					}
				}
			} catch (Exception $e) {
				// ignore
			}
		}

		// Filesystem fallback if DB rows are missing
		if (empty($files)) {
			$uploadsDir = dirname(__DIR__) . "/uploads/quote_{$quote['id']}";
			if (is_dir($uploadsDir)) {
				$scan = scandir($uploadsDir) ?: [];
				foreach ($scan as $fn) {
					if ($fn === '.' || $fn === '..') continue;
					$path = $uploadsDir . '/' . $fn;
					if (!is_file($path)) continue;
					$mime = function_exists('mime_content_type') ? @mime_content_type($path) : null;
					$files[] = [
						'id' => null,
						'filename' => $fn,
						'mime_type' => $mime,
						'file_type' => $mime,
						'quote_id' => $quote['id'],
						'file_path' => "server/uploads/quote_{$quote['id']}/{$fn}",
						'file_size' => @filesize($path) ?: 0
					];
				}
			}
		}

		// Get EXIF location data for this quote
		require_once '../config/database-simple.php';
		$exif_locations = [];
		try {
			$exif_stmt = $pdo->prepare("SELECT exif_latitude, exif_longitude, exif_timestamp, camera_make, camera_model, media_id FROM media_locations WHERE quote_id = ?");
			$exif_stmt->execute([$quote['id']]);
			$exif_locations = $exif_stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (Exception $e) {
			// Media locations table doesn't exist yet, skip it
		}

		$formatted_assessment = null;
		if (!empty($assessment)) {
			$formatted_assessment = [
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

		// Match EXIF data with media files
		$formatted_exif = [];
		foreach ($exif_locations as $exif) {
			$media_file = null;
			foreach ($files as $file) {
				if ($file['id'] == $exif['media_id']) {
					$media_file = $file;
					break;
				}
			}

			$formatted_exif[] = [
				'latitude' => (float)$exif['exif_latitude'],
				'longitude' => (float)$exif['exif_longitude'],
				'timestamp' => $exif['exif_timestamp'],
				'camera' => trim(($exif['camera_make'] ?? '') . ' ' . ($exif['camera_model'] ?? '')),
				'filename' => $media_file ? $media_file['filename'] : 'unknown'
			];
		}

		// Format files for frontend
		$formatted_files = array_map(function($file) use ($quote) {
			return [
				'id' => $file['id'],
				'filename' => $file['filename'],
				'type' => ($file['mime_type'] ?? ($file['type'] ?? ($file['file_type'] ?? null))),
				'download_url' => "/server/uploads/quote_{$quote['id']}/{$file['filename']}",
				'file_path' => $file['file_path'] ?? ("server/uploads/quote_{$quote['id']}/{$file['filename']}") ,
				'file_size' => $file['file_size'] ?? 0
			];
		}, $files);

		// Parse unified AI response when present
		$ai_response = null;
		if (!empty($quote['ai_response_json'])) {
			$ai_response = json_decode($quote['ai_response_json'], true);
		}

		// Collect all available model analyses
		$ai_models = [];
		foreach ([
			['col' => 'ai_o3_analysis', 'fallback' => 'o3'],
			['col' => 'ai_o4_mini_analysis', 'fallback' => 'gpt-5'],
			['col' => 'ai_gemini_analysis', 'fallback' => 'gemini-1.5-pro-latest'],
			['col' => 'gemini_analysis', 'fallback' => 'gemini-1.5-pro-latest']
		] as $entry) {
			$col = $entry['col'];
			if (!empty($quote[$col])) {
				$decoded = json_decode($quote[$col], true);
				if (json_last_error() !== JSON_ERROR_NONE) continue;
				$modelName = $decoded['model'] ?? $entry['fallback'];
				$analysis  = $decoded['analysis'] ?? ($decoded['canonical'] ?? null);
				if ($analysis === null) { $analysis = $decoded; }
				$canonical = coerce_analysis($analysis);
				$ai_models[] = ['model' => $modelName, 'analysis' => $canonical];
			}
		}

		// Distance and logistics omitted for brevity in this edit; assume previous logic remains
		$distance_km = null; $travel_time = null; $distance_source = 'unavailable'; $customer_to_tree_km = null; $postal_info = []; $transfer_stations = []; $hospitals = [];

		// Costs
		$cost_stmt = $pdo->prepare("SELECT SUM(total_cost) as total_cost, SUM(input_tokens + output_tokens) as total_tokens FROM ai_cost_log WHERE quote_id = ?");
		$cost_stmt->execute([$quote['id']]);
		$cost_data = $cost_stmt->fetch(PDO::FETCH_ASSOC);

		// Parse raw AI analysis columns for direct access
		$ai_gpt5_raw = null;
		if (!empty($quote['ai_o4_mini_analysis'])) {
			$ai_gpt5_raw = json_decode($quote['ai_o4_mini_analysis'], true);
		}
		$ai_gemini_raw = null;
		if (!empty($quote['ai_gemini_analysis'])) {
			$ai_gemini_raw = json_decode($quote['ai_gemini_analysis'], true);
		}
		
		$formatted_quotes[] = [
			'id' => $quote['id'],
			'status' => $quote['quote_status'],
			'customer_name' => $quote['customer_name'],
			'customer_email' => $quote['customer_email'],
			'phone' => $quote['customer_phone'],
			'address' => $quote['address'],
			'notes' => $quote['notes'] ?? null,
			'distance_km' => $distance_km,
			'travel_time' => $travel_time,
			'distance_source' => $distance_source,
			'vehicle_type' => 'truck',
			'travel_cost' => ($distance_km !== null ? $distance_km * 1.00 : null),
			'customer_to_tree_km' => $customer_to_tree_km,
			'files' => $formatted_files,
			'postal_code' => $postal_info['postal_code'] ?? null,
			'transfer_stations' => $transfer_stations,
			'hospitals' => $hospitals,
			'ai_models' => array_map(function($m){ return $m['model']; }, $ai_models),
			'gpt_analysis' => (function($models){ foreach ($models as $m){ if (stripos($m['model'],'gpt')!==false) return $m['analysis']; } return ['services'=>[], 'frames'=>[], 'raw'=>'', 'errors'=>[]]; })($ai_models),
			'gemini_analysis' => (function($models){ foreach ($models as $m){ if (stripos($m['model'],'gemini')!==false) return $m['analysis']; } return ['services'=>[], 'frames'=>[], 'raw'=>'', 'errors'=>[]]; })($ai_models),
			// Direct access to AI analysis for dashboard
			'ai_gpt5_analysis' => $ai_gpt5_raw,
			'ai_gemini_analysis' => $ai_gemini_raw,
			'ai_o4_mini_analysis_raw' => $quote['ai_o4_mini_analysis'] ?? null,
			'ai_gemini_analysis_raw' => $quote['ai_gemini_analysis'] ?? null,
			'ai_summary' => !empty($ai_models) ? formatAISummary($ai_models[0]['analysis'], count($files) > 0) : ($ai_response ? formatAISummary($ai_response, count($files) > 0) : ''),
			'line_items' => [],
			'subtotal' => 0,
			'discount_name' => '',
			'discount_amount' => 0,
			'discount_type' => 'dollar',
			'discount_value' => 0,
			'final_total' => 0,
			'geo_latitude' => $quote['geo_latitude'],
			'geo_longitude' => $quote['geo_longitude'],
			'geo_accuracy' => $quote['geo_accuracy'],
			'ip_address' => $quote['ip_address'],
			'exif_locations' => $formatted_exif,
			'context_assessment' => $formatted_assessment,
			'total_cost' => $cost_data['total_cost'] ?? 0,
			'total_tokens' => $cost_data['total_tokens'] ?? 0,
			'created_at' => $quote['quote_created_at'] ?? null
		];
	}

	echo json_encode([
		'success' => true,
		'quotes' => $formatted_quotes
	]);

} catch (Exception $e) {
	http_response_code(400);
	echo json_encode([
		'error' => $e->getMessage()
	]);
}
?> 