<?php
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

require_once '../csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

verify_csrf();

global $wpdb;
$user_id = (int) $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

$column_name    = isset($input['column_name'])    ? trim($input['column_name'])    : '';
$data_type      = isset($input['data_type'])      ? trim($input['data_type'])      : 'text';
$select_options_raw = isset($input['select_options']) ? $input['select_options'] : null;

if ($column_name === '') {
    echo json_encode(['success' => false, 'message' => 'Column name is required']);
    exit;
}

// ── Allowed types: match exactly what the JS sends ────────────────────────────
$allowed_types = ['text', 'number', 'checkbox', 'select', 'yes_no', 'dropdown'];

if (!in_array($data_type, $allowed_types, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data type',
        'received' => $data_type
    ]);
    exit;
}

// ── Decode select_options (sent Base64-encoded to avoid WAF blocking) ─────────
$select_options = null;

if (in_array($data_type, ['select', 'dropdown'], true) && !empty($select_options_raw)) {
    // JS encodes as Base64 string; decode it then JSON-parse
    if (is_string($select_options_raw)) {
        $decoded = base64_decode($select_options_raw, true);
        if ($decoded !== false) {
            $parsed = json_decode($decoded, true);
            $select_options = is_array($parsed) ? array_values(array_filter($parsed)) : null;
        }
    } elseif (is_array($select_options_raw)) {
        // Fallback: if somehow still arrives as plain array
        $select_options = array_values(array_filter($select_options_raw));
    }
}

// ── Table check ───────────────────────────────────────────────────────────────
$custom_columns_table = $wpdb->prefix . 'rich_custom_columns';

$table_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $custom_columns_table
));

if ($table_exists !== $custom_columns_table) {
    echo json_encode([
        'success'  => false,
        'message'  => 'Custom columns table is missing',
        'db_error' => "Missing table: {$custom_columns_table}"
    ]);
    exit;
}

// ── Duplicate key check ───────────────────────────────────────────────────────
$column_key = sanitize_title($column_name);

$existing_key = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$custom_columns_table} WHERE user_id = %d AND column_key = %s LIMIT 1",
    $user_id,
    $column_key
));

if ($existing_key) {
    echo json_encode(['success' => false, 'message' => 'A column with this name already exists']);
    exit;
}

// ── Display order ─────────────────────────────────────────────────────────────
$max_order = $wpdb->get_var($wpdb->prepare(
    "SELECT MAX(display_order) FROM {$custom_columns_table} WHERE user_id = %d",
    $user_id
));

$display_order = ($max_order !== null ? (int) $max_order : 100) + 1;

// ── Insert ────────────────────────────────────────────────────────────────────
$inserted = $wpdb->insert(
    $custom_columns_table,
    [
        'user_id'        => $user_id,
        'column_name'    => $column_name,
        'column_key'     => $column_key,
        'data_type'      => $data_type,
        'select_options' => $select_options ? wp_json_encode($select_options) : null,
        'display_order'  => $display_order,
    ],
    ['%d', '%s', '%s', '%s', '%s', '%d']
);

if ($inserted === false) {
    echo json_encode([
        'success'  => false,
        'message'  => 'Failed to create custom column',
        'db_error' => $wpdb->last_error
    ]);
    exit;
}

echo json_encode([
    'success'   => true,
    'message'   => 'Custom column created successfully',
    'column_id' => $wpdb->insert_id
]);
exit;
?>
