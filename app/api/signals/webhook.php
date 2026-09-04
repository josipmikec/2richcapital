<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// 1. Authenticate via Bearer Token
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$auth_header || !preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid Authorization header']);
    exit;
}

$api_key = $matches[1];

global $wpdb;
$groups_table = $wpdb->prefix . 'rich_signal_groups';
$signals_table = $wpdb->prefix . 'rich_signals';

$group = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM {$groups_table} WHERE api_key = %s AND is_active = 1", $api_key));

if (!$group) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key or inactive group']);
    exit;
}

$group_id = intval($group->id);

// 2. Parse Payload
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

$symbol = sanitize_text_field($input['symbol'] ?? '');
$direction_raw = strtolower(sanitize_text_field($input['direction'] ?? ''));
$direction = ($direction_raw === 'bearish' || $direction_raw === 'sell') ? 'SELL' : 'BUY';

$entry_price = floatval($input['entry'] ?? $input['price'] ?? 0);
$stop_loss = isset($input['stop_loss']) ? floatval($input['stop_loss']) : null;
$take_profit = isset($input['take_profit']) ? floatval($input['take_profit']) : null;
$external_id = sanitize_text_field($input['discord_message_id'] ?? '');

$alert_type = sanitize_text_field($input['alert_type'] ?? '');
$timeframe = sanitize_text_field($input['timeframe'] ?? '');
$source = sanitize_text_field($input['source'] ?? '');
$status_raw = strtolower(sanitize_text_field($input['status'] ?? ''));

if (!$symbol) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing symbol']);
    exit;
}

$notes_data = [
    'alert_type' => $alert_type,
    'timeframe' => $timeframe,
    'source' => $source
];
$notes = wp_json_encode($notes_data);

// Map status from Jake's bot ("active", "hit TP", "hit SL", "expired")
$status = 'open';
$result = 'pending';

if (strpos($status_raw, 'hit tp') !== false) {
    $status = 'closed';
    $result = 'win';
} elseif (strpos($status_raw, 'hit sl') !== false) {
    $status = 'closed';
    $result = 'loss';
} elseif (strpos($status_raw, 'expired') !== false) {
    $status = 'closed';
    $result = 'pending';
}

// 3. Upsert Logic
if ($external_id) {
    $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$signals_table} WHERE group_id = %d AND external_id = %s", $group_id, $external_id));
    
    if ($existing) {
        // Update existing signal
        $update_data = [
            'status' => $status,
            'result' => $result
        ];
        
        // Update prices only if provided in update payload
        if ($entry_price) $update_data['entry_price'] = $entry_price;
        if ($stop_loss !== null) $update_data['stop_loss'] = $stop_loss;
        if ($take_profit !== null) $update_data['take_profit'] = $take_profit;
        
        $wpdb->update(
            $signals_table,
            $update_data,
            ['id' => $existing->id],
            null, // Let wpdb infer formats
            ['%d']
        );
        
        echo json_encode(['success' => true, 'message' => 'Signal updated successfully', 'action' => 'updated', 'signal_id' => $existing->id]);
        exit;
    }
}

// Insert new signal
$insert_data = [
    'group_id' => $group_id,
    'symbol' => $symbol,
    'direction' => $direction,
    'entry_price' => $entry_price,
    'stop_loss' => $stop_loss,
    'take_profit' => $take_profit,
    'status' => $status,
    'result' => $result,
    'notes' => $notes,
    'external_id' => $external_id ?: null,
];

$wpdb->insert($signals_table, $insert_data);
$new_id = $wpdb->insert_id;

echo json_encode([
    'success' => true,
    'message' => 'Signal created successfully',
    'action' => 'created',
    'signal_id' => $new_id
]);
