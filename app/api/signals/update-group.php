<?php
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

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$user_id = (int) $_SESSION['user_id'];
$group_id = (int) ($body['group_id'] ?? 0);

if ($group_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid group_id is required.']);
    exit;
}

function rich_group_slugify($str) {
    $slug = strtolower(trim((string) $str));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

global $wpdb;
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$audit_table       = $wpdb->prefix . 'rich_signal_group_audit_log';

$membership = $wpdb->get_row($wpdb->prepare(
    "SELECT role, status FROM {$memberships_table} WHERE group_id = %d AND user_id = %d LIMIT 1",
    $group_id, $user_id
), ARRAY_A);

$allowed_roles = ['owner', 'admin'];
if (!$membership || $membership['status'] !== 'active' || !in_array($membership['role'] ?? '', $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only group owners or admins can update this group.']);
    exit;
}

$current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$groups_table} WHERE id = %d LIMIT 1", $group_id), ARRAY_A);
if (!$current) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Group not found.']);
    exit;
}

$name         = trim((string) ($body['name'] ?? $current['name'] ?? ''));
$team_name    = trim((string) ($body['team_name'] ?? $current['team_name'] ?? ''));
$description  = trim((string) ($body['description'] ?? $current['description'] ?? ''));
$category     = trim((string) ($body['category'] ?? $current['category'] ?? ''));
$pricing_type = ($body['pricing_type'] ?? $current['pricing_type'] ?? 'free') === 'paid' ? 'paid' : 'free';
$price        = $pricing_type === 'paid' ? (float) ($body['price'] ?? $current['price'] ?? 0) : 0.00;
$visibility   = in_array($body['visibility'] ?? $current['visibility'] ?? 'listed', ['listed', 'unlisted', 'private'], true) ? ($body['visibility'] ?? $current['visibility']) : 'listed';
$join_mode    = in_array($body['join_mode'] ?? $current['join_mode'] ?? 'open', ['open', 'request', 'invite'], true) ? ($body['join_mode'] ?? $current['join_mode']) : 'open';
$intro_text   = trim((string) ($body['intro_text'] ?? $current['intro_text'] ?? ''));
$rules_text   = trim((string) ($body['rules_text'] ?? $current['rules_text'] ?? ''));
$avatar_url   = trim((string) ($body['avatar_url'] ?? $current['avatar_url'] ?? ''));
$cover_url    = trim((string) ($body['cover_url'] ?? $current['cover_url'] ?? ''));
$accent_color = trim((string) ($body['accent_color'] ?? $current['accent_color'] ?? ''));
$accent_palette = ['#F2CA50', '#6EE7B7', '#38BDF8', '#A78BFA', '#FB7185', '#F59E0B'];
if (!in_array(strtoupper($accent_color), array_map('strtoupper', $accent_palette), true)) {
    $accent_color = $current['accent_color'] ?? '#F2CA50';
}
$allowed_symbols = isset($body['allowed_symbols']) && is_array($body['allowed_symbols'])
    ? array_values(array_filter(array_map('trim', $body['allowed_symbols'])))
    : (json_decode((string) ($current['allowed_symbols_json'] ?? '[]'), true) ?: []);

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Group name is required.']);
    exit;
}
if ($pricing_type === 'paid' && $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Paid groups require a price greater than 0.']);
    exit;
}

$slug = trim((string) ($current['slug'] ?? ''));
if (!empty($body['regenerate_slug']) || $slug === '') {
    $base_slug = rich_group_slugify($name);
    if ($base_slug === '') $base_slug = 'group-' . $group_id;
    $slug = $base_slug;
    $suffix = 1;
    while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$groups_table} WHERE slug = %s AND id != %d", $slug, $group_id))) {
        $suffix++;
        $slug = $base_slug . '-' . $suffix;
    }
}

$update_data = [
    'slug'                 => $slug,
    'name'                 => $name,
    'team_name'            => $team_name !== '' ? $team_name : null,
    'description'          => $description !== '' ? $description : null,
    'category'             => $category !== '' ? $category : null,
    'pricing_type'         => $pricing_type,
    'price'                => $price,
    'visibility'           => $visibility,
    'join_mode'            => $join_mode,
    'intro_text'           => $intro_text !== '' ? $intro_text : null,
    'rules_text'           => $rules_text !== '' ? $rules_text : null,
    'avatar_url'           => $avatar_url !== '' ? $avatar_url : null,
    'cover_url'            => $cover_url !== '' ? $cover_url : null,
    'requires_stop_loss'   => $requires_sl,
    'requires_take_profit' => $requires_tp,
    'allowed_symbols_json' => !empty($allowed_symbols) ? wp_json_encode($allowed_symbols) : null,
];
if (array_key_exists('accent_color', $current)) {
    $update_data['accent_color'] = $accent_color !== '' ? $accent_color : null;
}

$updated = $wpdb->update($groups_table, $update_data, ['id' => $group_id]);
if ($updated === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update group.',
        'db_error' => $wpdb->last_error,
        'db_query' => $wpdb->last_query,
        'update_fields' => array_keys($update_data)
    ]);
    exit;
}

$wpdb->insert($audit_table, [
    'group_id'      => $group_id,
    'actor_user_id' => $user_id,
    'action'        => 'group_updated',
    'meta_json'     => wp_json_encode(['fields' => array_keys($update_data)]),
]);

echo json_encode([
    'success' => true,
    'message' => 'Group updated successfully.',
    'group_id' => $group_id,
    'slug' => $slug,
]);
