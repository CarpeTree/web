<?php
header('Content-Type: application/json');

// Simple, self-contained estimator for bucking time, cut count, brush volume/weight, and consumables.
// Inputs accepted via JSON body or query params. Returns metric and imperial outputs.

/**
 * Read a parameter from JSON body, POST, or GET with a default.
 */
function param($name, $default = null) {
    static $json;
    if ($json === null) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) { $json = []; }
    }
    if (isset($_POST[$name])) return $_POST[$name];
    if (isset($_GET[$name])) return $_GET[$name];
    if (isset($json[$name])) return $json[$name];
    return $default;
}

function to_float($v, $default = null) {
    if ($v === null || $v === '') return $default;
    if (is_numeric($v)) return (float)$v;
    return $default;
}

try {
    // Units and base inputs
    $dbh_cm = to_float(param('dbh_cm'));
    if ($dbh_cm === null) {
        $dbh_in = to_float(param('dbh_in'));
        if ($dbh_in !== null) $dbh_cm = $dbh_in * 2.54;
    }

    $height_m = to_float(param('height_m'));
    if ($height_m === null) {
        $height_ft = to_float(param('height_ft'));
        if ($height_ft !== null) $height_m = $height_ft * 0.3048;
    }

    // Piece length can be provided in cm, inches, or meters
    $piece_length_m = to_float(param('piece_length_m'));
    if ($piece_length_m === null) {
        $piece_length_cm = to_float(param('piece_length_cm'));
        if ($piece_length_cm !== null) $piece_length_m = $piece_length_cm / 100.0;
    }
    if ($piece_length_m === null) {
        $piece_length_in = to_float(param('piece_length_in', 16)); // default 16" firewood
        $piece_length_m = $piece_length_in * 0.0254;
    }

    $species = trim((string)param('species', 'Unknown'));

    // Species densities (green, kg/m^3) – approximate
    $species_densities = [
        'douglas fir' => 530,
        'doug fir' => 530,
        'pseudotsuga menziesii' => 530,
        'western red cedar' => 380,
        'cedar' => 380,
        'thuja plicata' => 380,
        'spruce' => 450,
        'pine' => 500,
        'hemlock' => 545,
        'unknown' => 500
    ];
    $wood_density_kg_m3 = to_float(param('wood_density_kg_m3'));
    if ($wood_density_kg_m3 === null) {
        $key = strtolower($species);
        $wood_density_kg_m3 = $species_densities[$key] ?? $species_densities['unknown'];
    }

    // Brush/chips densities (approx)
    $brush_density_loose_kg_m3 = to_float(param('brush_density_loose_kg_m3', 150)); // loose brush
    $chip_density_kg_m3 = to_float(param('chip_density_kg_m3', 250)); // green chips

    // Geometry/ratios
    $form_factor = to_float(param('form_factor', 0.45)); // stem form factor
    $trunk_proportion = to_float(param('trunk_proportion', 0.7)); // proportion of height considered buckable stem
    $brush_to_stem_volume_ratio = to_float(param('brush_to_stem_volume_ratio', 0.6)); // loose brush volume vs stem volume
    $chip_volume_reduction_ratio = to_float(param('chip_volume_reduction_ratio', 6.0)); // loose brush to chips

    // Work multipliers
    $limb_factor = max(1.0, to_float(param('limb_factor', 1.2))); // extra cuts due to limbs/irregularity
    $rigging_multiplier = max(0.5, to_float(param('rigging_multiplier', 1.0)));
    $access_multiplier = max(0.5, to_float(param('access_multiplier', 1.0)));

    // Saw class profiles
    $saw_class = (string)param('saw_class', '60cc');
    $saw_profiles = [
        '50cc' => ['base' => 22, 'per_cm' => 0.7, 'fuel_lph' => 1.0, 'oil_lph' => 0.6],
        '60cc' => ['base' => 18, 'per_cm' => 0.5, 'fuel_lph' => 1.2, 'oil_lph' => 0.7],
        '70cc' => ['base' => 15, 'per_cm' => 0.4, 'fuel_lph' => 1.4, 'oil_lph' => 0.8],
        '80cc' => ['base' => 13, 'per_cm' => 0.35, 'fuel_lph' => 1.6, 'oil_lph' => 0.9]
    ];
    $profile = $saw_profiles[$saw_class] ?? $saw_profiles['60cc'];
    $seconds_base_per_cut = to_float(param('seconds_base_per_cut'));

    // Chipper throughput on loose brush
    $chipper_throughput_loose_m3_per_hr = max(0.1, to_float(param('chipper_throughput_loose_m3_per_hr', 6.0)));
    $chipper_access_multiplier = max(0.5, to_float(param('chipper_access_multiplier', 1.0)));

    // Validate required core inputs
    if ($dbh_cm === null || $height_m === null || $piece_length_m === null) {
        echo json_encode([
            'error' => 'Missing required inputs: dbh_cm (or dbh_in), height_m (or height_ft), piece_length (cm/in/m)'
        ]);
        exit;
    }

    // Geometry calculations
    $dbh_m = $dbh_cm / 100.0;
    $radius_m = $dbh_m / 2.0;
    $basal_area_m2 = M_PI * $radius_m * $radius_m;
    $stem_volume_m3 = $basal_area_m2 * $height_m * $form_factor; // approximate stem volume

    // Wood/brush volumes and weights
    $brush_loose_m3 = max(0.0, $stem_volume_m3 * $brush_to_stem_volume_ratio);
    $chips_m3 = $brush_loose_m3 / max(0.1, $chip_volume_reduction_ratio);

    $wood_weight_kg = $stem_volume_m3 * $wood_density_kg_m3;
    $brush_weight_kg = $brush_loose_m3 * $brush_density_loose_kg_m3;
    $chips_weight_kg = $chips_m3 * $chip_density_kg_m3;

    // Bucking plan
    $wood_length_m = $height_m * $trunk_proportion;
    $piece_count = (int)max(1, ceil($wood_length_m / $piece_length_m));

    // Diameter taper model along stem (linear taper on diameter)
    $butt_multiplier = max(0.9, to_float(param('butt_diameter_multiplier', 1.0))); // DBH → butt at base
    $top_to_base_ratio = min(1.0, max(0.1, to_float(param('top_to_base_diameter_ratio', 0.35))));
    $base_diameter_cm = $dbh_cm * $butt_multiplier;
    $top_diameter_cm = max(5.0, $base_diameter_cm * $top_to_base_ratio);

    // Build per-piece geometry and per-cut timing using saw profile
    $pieces = [];
    $remaining_m = $wood_length_m;
    $position_m = 0.0;
    $bucking_seconds = 0.0;
    $cut_diameters_cm = [];
    for ($i = 0; $i < $piece_count; $i++) {
        $len_m = ($i < $piece_count - 1) ? min($piece_length_m, $remaining_m) : $remaining_m;
        $center_m = $position_m + $len_m / 2.0;
        $t = ($wood_length_m > 0) ? ($center_m / $wood_length_m) : 1.0; // 0 at base → 1 at top
        $diam_cm = $base_diameter_cm - ($base_diameter_cm - $top_diameter_cm) * $t;
        $diam_cm = max(5.0, $diam_cm);
        $diam_m = $diam_cm / 100.0;
        $area_m2 = M_PI * pow($diam_m / 2.0, 2);
        $vol_m3 = $area_m2 * $len_m;
        $wt_kg = $vol_m3 * $wood_density_kg_m3;

        // Per-cut timing by diameter
        $sec_cut = $seconds_base_per_cut !== null
            ? $seconds_base_per_cut
            : ($profile['base'] + $profile['per_cm'] * max(8, min(120, $diam_cm)));
        $bucking_seconds += $sec_cut; // one cut per piece
        $cut_diameters_cm[] = $diam_cm;

        $pieces[] = [
            'index' => $i + 1,
            'length_m' => $len_m,
            'diameter_cm' => round($diam_cm, 1),
            'area_m2' => round($area_m2, 4),
            'volume_m3' => round($vol_m3, 4),
            'weight_kg' => round($wt_kg, 1),
            'seconds_per_cut' => round($sec_cut, 1)
        ];
        $position_m += $len_m;
        $remaining_m = max(0.0, $wood_length_m - $position_m);
    }

    // Limb cuts approximation as additional cuts with small diameter
    $bucking_cuts = $piece_count;
    $limb_cuts = (int)max(0, ceil($bucking_cuts * max(0.0, $limb_factor - 1.0)));
    $seconds_small_limb_cut = $profile['base'] + $profile['per_cm'] * 12; // ~12 cm limbs
    $limb_seconds = $limb_cuts * $seconds_small_limb_cut;
    $active_seconds = $bucking_seconds + $limb_seconds;
    $active_cut_minutes = $active_seconds / 60.0;

    // Account for positioning, clearing, stacking
    $overhead_multiplier = max(1.0, to_float(param('overhead_multiplier', 1.8)));
    $total_saw_minutes = $active_cut_minutes * $overhead_multiplier;
    $bucking_time_minutes = $total_saw_minutes * $rigging_multiplier * $access_multiplier;

    // Consumables
    $fuel_lph = $profile['fuel_lph'];
    $oil_lph = $profile['oil_lph'];
    $fuel_liters = ($total_saw_minutes / 60.0) * $fuel_lph;
    $bar_oil_liters = ($total_saw_minutes / 60.0) * $oil_lph;

    // Chipping time (loose brush basis)
    $chipper_hours = ($brush_loose_m3 / $chipper_throughput_loose_m3_per_hr) * $chipper_access_multiplier;

    // Conversions
    $m3_to_yd3 = 1.30795;
    $kg_to_lb = 2.20462;
    $l_to_gal = 0.264172;
    $m_to_ft = 3.28084;

    // Diameter binning (cm)
    $bin_edges = [15, 30, 45];
    $bin_labels = ['0-15', '15-30', '30-45', '45+'];
    $bin_counts = [0, 0, 0, 0];
    foreach ($cut_diameters_cm as $d) {
        if ($d <= $bin_edges[0]) $bin_counts[0]++;
        elseif ($d <= $bin_edges[1]) $bin_counts[1]++;
        elseif ($d <= $bin_edges[2]) $bin_counts[2]++;
        else $bin_counts[3]++;
    }

    $out = [
        'inputs' => [
            'species' => $species,
            'dbh_cm' => round($dbh_cm, 1),
            'height_m' => round($height_m, 2),
            'piece_length_m' => round($piece_length_m, 3),
            'saw_class' => $saw_class
        ],
        'estimates_metric' => [
            'stem_volume_m3' => round($stem_volume_m3, 2),
            'wood_weight_kg' => round($wood_weight_kg, 1),
            'brush_loose_m3' => round($brush_loose_m3, 2),
            'brush_weight_kg' => round($brush_weight_kg, 1),
            'chips_m3' => round($chips_m3, 2),
            'chips_weight_kg' => round($chips_weight_kg, 1),
            'piece_count' => $piece_count,
            'bucking_cuts' => $bucking_cuts,
            'limb_cuts' => $limb_cuts,
            'total_cuts' => $bucking_cuts + $limb_cuts,
            'seconds_per_cut_mean' => round($bucking_cuts ? ($bucking_seconds / $bucking_cuts) : 0.0, 1),
            'bucking_time_minutes' => round($bucking_time_minutes, 1),
            'fuel_liters' => round($fuel_liters, 2),
            'bar_oil_liters' => round($bar_oil_liters, 2),
            'chipper_hours' => round($chipper_hours, 2)
        ],
        'estimates_imperial' => [
            'stem_volume_yd3' => round($stem_volume_m3 * $m3_to_yd3, 2),
            'wood_weight_lb' => round($wood_weight_kg * $kg_to_lb, 0),
            'brush_loose_yd3' => round($brush_loose_m3 * $m3_to_yd3, 2),
            'brush_weight_lb' => round($brush_weight_kg * $kg_to_lb, 0),
            'chips_yd3' => round($chips_m3 * $m3_to_yd3, 2),
            'chips_weight_lb' => round($chips_weight_kg * $kg_to_lb, 0),
            'piece_length_in' => round($piece_length_m / 0.0254, 1),
            'height_ft' => round($height_m * $m_to_ft, 1)
        ],
        'cut_distribution' => [
            'diameter_bins_cm' => [
                ['label' => $bin_labels[0], 'count' => $bin_counts[0]],
                ['label' => $bin_labels[1], 'count' => $bin_counts[1]],
                ['label' => $bin_labels[2], 'count' => $bin_counts[2]],
                ['label' => $bin_labels[3], 'count' => $bin_counts[3]]
            ],
            'cut_diameters_cm' => array_map(function($d){ return round($d, 1); }, $cut_diameters_cm)
        ],
        'per_piece' => $pieces,
        'assumptions' => [
            'wood_density_kg_m3' => $wood_density_kg_m3,
            'brush_density_loose_kg_m3' => $brush_density_loose_kg_m3,
            'chip_density_kg_m3' => $chip_density_kg_m3,
            'form_factor' => $form_factor,
            'trunk_proportion' => $trunk_proportion,
            'brush_to_stem_volume_ratio' => $brush_to_stem_volume_ratio,
            'chip_volume_reduction_ratio' => $chip_volume_reduction_ratio,
            'limb_factor' => $limb_factor,
            'rigging_multiplier' => $rigging_multiplier,
            'access_multiplier' => $access_multiplier,
            'overhead_multiplier' => $overhead_multiplier,
            'saw_profile' => $profile,
            'butt_diameter_multiplier' => $butt_multiplier,
            'top_to_base_diameter_ratio' => $top_to_base_ratio,
            'bin_edges_cm' => $bin_edges
        ]
    ];

    echo json_encode($out, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>


