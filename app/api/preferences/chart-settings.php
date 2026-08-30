<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
ob_end_clean();

require_once '../csrf.php';
header('Content-Type: application/json');
$debug_enabled = isset($_GET['debug']) && $_GET['debug'] === '1';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

global $wpdb;
$user_id = intval($_SESSION['user_id']);
$table   = $wpdb->prefix . 'rich_user_preferences';
$key     = 'market_data_tv_settings';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT pref_value FROM {$table} WHERE user_id = %d AND pref_key = %s LIMIT 1",
        $user_id,
        $key
    ));

    $decoded = [];
    if ($row && isset($row->pref_value) && $row->pref_value !== '') {
        $decoded = json_decode((string)$row->pref_value, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
    }

    $response = [
        'success' => true,
        'settings' => $decoded,
    ];
    if ($debug_enabled) {
        $response['debug'] = [
            'user_id' => $user_id,
            'pref_key' => $key,
            'row_found' => (bool)$row,
            'stored_bytes' => $row ? strlen((string)$row->pref_value) : 0,
            'stored_keys' => array_keys($decoded),
        ];
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    verify_csrf();
    $wpdb->delete(
        $table,
        [
            'user_id' => $user_id,
            'pref_key' => $key,
        ],
        ['%d', '%s']
    );

    if ($wpdb->last_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $wpdb->last_error]);
        exit;
    }

    echo json_encode(['success' => true, 'reset' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

verify_csrf();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$reset = !empty($input['reset']);
if ($reset) {
    $wpdb->delete(
        $table,
        [
            'user_id' => $user_id,
            'pref_key' => $key,
        ],
        ['%d', '%s']
    );

    if ($wpdb->last_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $wpdb->last_error]);
        exit;
    }

    echo json_encode(['success' => true, 'reset' => true]);
    exit;
}

$settings = $input['settings'] ?? null;
if (!is_array($settings)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Settings payload must be an object']);
    exit;
}

$allowed_prefixes = [
    'chart',
    'linetool',
    'study',
    'trading',
    'symbol',
    'pane',
    'scale',
    'mainseries',
    'session',
    'editor',
    'drawing',
    'drawings',
    'favorite',
    'favourite',
    'legend',
    'priceaxis',
    'time',
    'timezone',
    'watchlist',
    'background',
    'grid',
];

$allowed_exact = [
    'interval',
    'timeframe',
    'symbol',
    'timezone',
    'favorites',
    'favourites',
];

$sanitized = [];
$debug_rejected = [];
foreach ($settings as $setting_key => $value) {
    $setting_key = trim((string)$setting_key);
    if ($setting_key === '') continue;

    $normalized = strtolower($setting_key);
    $normalized_compact = preg_replace('/[^a-z0-9]+/', '', $normalized);
    $allowed = in_array($normalized, $allowed_exact, true) || in_array($normalized_compact, $allowed_exact, true);
    if (!$allowed) {
        foreach ($allowed_prefixes as $prefix) {
            if (strpos($normalized, $prefix) === 0 || strpos($normalized_compact, $prefix) === 0) {
                $allowed = true;
                break;
            }
        }
    }
    if (!$allowed) {
        if ($debug_enabled) {
            $debug_rejected[] = [
                'original' => $setting_key,
                'normalized' => $normalized,
                'compact' => $normalized_compact,
                'value_type' => gettype($value),
            ];
        }
        continue;
    }

    if (is_array($value) || is_object($value)) {
        $encoded = wp_json_encode($value);
        if ($encoded === false) continue;
        $sanitized[$setting_key] = $encoded;
        continue;
    }

    if (is_bool($value)) {
        $sanitized[$setting_key] = $value ? 'true' : 'false';
        continue;
    }

    if ($value === null) {
        $sanitized[$setting_key] = '';
        continue;
    }

    $sanitized[$setting_key] = (string)$value;
}

$current_row = $wpdb->get_row($wpdb->prepare(
    "SELECT id FROM {$table} WHERE user_id = %d AND pref_key = %s LIMIT 1",
    $user_id,
    $key
));

$payload = wp_json_encode($sanitized);
if ($payload === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to encode settings']);
    exit;
}

if ($current_row) {
    $wpdb->update(
        $table,
        ['pref_value' => $payload, 'updated_at' => current_time('mysql')],
        ['id' => intval($current_row->id)],
        ['%s', '%s'],
        ['%d']
    );
} else {
    $wpdb->insert(
        $table,
        [
            'user_id' => $user_id,
            'pref_key' => $key,
            'pref_value' => $payload,
            'updated_at' => current_time('mysql'),
        ],
        ['%d', '%s', '%s', '%s']
    );
}

if ($wpdb->last_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $wpdb->last_error]);
    exit;
}

$response = ['success' => true, 'settings' => $sanitized];
if ($debug_enabled) {
    $response['debug'] = [
        'user_id' => $user_id,
        'pref_key' => $key,
        'saved_keys' => array_keys($sanitized),
        'saved_bytes' => strlen($payload),
        'rejected' => $debug_rejected,
    ];
}
echo json_encode($response);
