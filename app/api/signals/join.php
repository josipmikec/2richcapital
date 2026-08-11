<?php
// API: Join a signal group (free groups join instantly; paid groups return a stub for now)
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// CSRF check
$incoming_csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($incoming_csrf) || !hash_equals($_SESSION['csrf_token'] ?? '', $incoming_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true);
$group_id = isset($body['group_id']) ? (int) $body['group_id'] : 0;

if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid group']);
    exit;
}

global $wpdb;
$user_id           = (int) $_SESSION['user_id'];
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';

$group = $wpdb->get_row($wpdb->prepare(
    "SELECT id, name, pricing_type, price FROM {$groups_table} WHERE id = %d AND is_active = 1",
    $group_id
), ARRAY_A);

if (!$group) {
    echo json_encode(['success' => false, 'message' => 'Group not found']);
    exit;
}

// Paid groups: billing/Stripe checkout will live on the group's dedicated page later.
// The dashboard only handles the free join loop for now.
if ($group['pricing_type'] === 'paid') {
    echo json_encode([
        'success'      => false,
        'requires_pay' => true,
        'message'      => 'This is a paid group. Subscribe from the group page to unlock the feed.'
    ]);
    exit;
}

$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$memberships_table} WHERE user_id = %d AND group_id = %d",
    $user_id, $group_id
));

if ($existing) {
    $wpdb->update(
        $memberships_table,
        ['status' => 'active', 'updated_at' => current_time('mysql')],
        ['id' => (int) $existing]
    );
} else {
    $wpdb->insert($memberships_table, [
        'user_id'    => $user_id,
        'group_id'   => $group_id,
        'status'     => 'active',
        'joined_at'  => current_time('mysql'),
    ]);
    $wpdb->query($wpdb->prepare(
        "UPDATE {$groups_table} SET member_count = member_count + 1 WHERE id = %d",
        $group_id
    ));
}

echo json_encode(['success' => true, 'message' => 'Joined ' . $group['name'], 'group_id' => $group_id]);
