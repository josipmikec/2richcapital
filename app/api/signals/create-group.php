<?php
// API: Create a new signal group (Group Creation Protocol — Phase 1)
// Always creates as status='draft'. Owner membership with role='owner' is created atomically.
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

// CSRF check (same convention as join.php)
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

// ── Phase 1 gate: only WordPress admins/editors (mentors) can create groups for now ──
// Toggle $restrict_to_staff = false once creator applications (Phase 2) are ready.
// Uses real WordPress capabilities/roles (session has no is_admin/is_mentor flags).
$user_id = (int) $_SESSION['user_id'];
$restrict_to_staff = false;
if ($restrict_to_staff) {
    $wp_user = get_userdata($user_id);
    $allowed_roles = ['administrator', 'editor', 'mentor']; // 'mentor' = custom role if you've created one
    $is_staff = false;
    if ($wp_user && !empty($wp_user->roles)) {
        $is_staff = (bool) array_intersect($allowed_roles, $wp_user->roles);
    }
    // Also allow anyone with manage_options (super admin / admin capability) as a safety net
    if (!$is_staff && $wp_user) {
        $is_staff = user_can($wp_user, 'manage_options');
    }
    if (!$is_staff) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Group creation is currently limited to approved creators.']);
        exit;
    }
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];

// DEBUG TEMP: log only to PHP error log while diagnosing create failures
error_log('[create-group] user=' . $user_id . ' method=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' csrf=' . ($incoming_csrf !== '' ? 'yes' : 'no') . ' name=' . ($_POST['name'] ?? ($body['name'] ?? '')));

$name         = trim((string) ($body['name'] ?? ''));
$team_name    = trim((string) ($body['team_name'] ?? ''));
$description  = trim((string) ($body['description'] ?? ''));
$category     = trim((string) ($body['category'] ?? ''));
$pricing_type = ($body['pricing_type'] ?? 'free') === 'paid' ? 'paid' : 'free';
$price        = $pricing_type === 'paid' ? (float) ($body['price'] ?? 0) : 0.00;
$visibility   = in_array($body['visibility'] ?? 'listed', ['listed', 'unlisted', 'private'], true) ? $body['visibility'] : 'listed';
$join_mode    = in_array($body['join_mode'] ?? 'open', ['open', 'request', 'invite'], true) ? $body['join_mode'] : 'open';
$intro_text   = trim((string) ($body['intro_text'] ?? ''));
$rules_text   = trim((string) ($body['rules_text'] ?? ''));
$accent_palette = ['#F2CA50', '#6EE7B7', '#38BDF8', '#A78BFA', '#FB7185', '#F59E0B'];
$accent_color = trim((string) ($body['accent_color'] ?? $accent_palette[0]));
if (!in_array(strtoupper($accent_color), array_map('strtoupper', $accent_palette), true)) {
    $accent_color = $accent_palette[0];
}
$requires_sl  = !empty($body['requires_stop_loss']) ? 1 : 0;
$requires_tp  = !empty($body['requires_take_profit']) ? 1 : 0;
$allowed_symbols = isset($body['allowed_symbols']) && is_array($body['allowed_symbols'])
    ? array_values(array_filter(array_map('trim', $body['allowed_symbols'])))
    : [];

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Group name is required.', 'debug' => ['stage' => 'validation', 'body' => $body]]);
    exit;
}
if ($pricing_type === 'paid' && $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Paid groups require a price greater than 0.', 'debug' => ['stage' => 'validation', 'body' => $body]]);
    exit;
}

// Slugify
function rich_slugify($str) {
    $slug = strtolower(trim($str));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

global $wpdb;
$groups_table       = $wpdb->prefix . 'rich_signal_groups';
$memberships_table  = $wpdb->prefix . 'rich_signal_memberships';
$audit_table        = $wpdb->prefix . 'rich_signal_group_audit_log';

$base_slug = rich_slugify($name);
if ($base_slug === '') {
    $base_slug = 'group-' . $user_id;
}
$slug = $base_slug;
$suffix = 1;
while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$groups_table} WHERE slug = %s", $slug))) {
    $suffix++;
    $slug = $base_slug . '-' . $suffix;
}

// Determine next display_order (append to end)
$max_order = (int) $wpdb->get_var("SELECT MAX(display_order) FROM {$groups_table}");

$insert_data = [
    'owner_user_id'          => $user_id,
    'slug'                   => $slug,
    'name'                   => $name,
    'team_name'              => $team_name !== '' ? $team_name : null,
    'description'            => $description !== '' ? $description : null,
    'pricing_type'           => $pricing_type,
    'price'                  => $price,
    'member_count'           => 1, // owner counts as first member
    'is_active'              => 0, // stays hidden from legacy is_active-based queries until published
    'display_order'          => $max_order + 1,
    'visibility'             => $visibility,
    'join_mode'              => $join_mode,
    'status'                 => 'draft',
    'category'               => $category !== '' ? $category : null,
    'intro_text'             => $intro_text !== '' ? $intro_text : null,
    'rules_text'             => $rules_text !== '' ? $rules_text : null,
    'accent_color'           => $accent_color,
    'requires_stop_loss'     => $requires_sl,
    'requires_take_profit'   => $requires_tp,
    'allowed_symbols_json'   => !empty($allowed_symbols) ? wp_json_encode($allowed_symbols) : null,
    'posted_signals_count'   => 0,
    'verification_status'    => 'none',
    'verification_note'      => null,
    'verification_requested_at' => null,
    'verified_at'            => null,
    'verified_by'            => null,
];

$wpdb->query('START TRANSACTION');

$inserted = $wpdb->insert($groups_table, $insert_data);

if ($inserted === false) {
    $wpdb->query('ROLLBACK');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create group.',
        'db_error' => $wpdb->last_error,
        'db_query' => $wpdb->last_query,
        'debug' => ['stage' => 'insert_group', 'insert_data' => $insert_data]
    ]);
    exit;
}

$group_id = (int) $wpdb->insert_id;

$member_inserted = $wpdb->insert($memberships_table, [
    'user_id'    => $user_id,
    'group_id'   => $group_id,
    'role'       => 'owner',
    'status'     => 'active',
    'access_type'=> 'free',
    'approved_by'=> $user_id,
    'approved_at'=> current_time('mysql'),
    'joined_at'  => current_time('mysql'),
]);

if ($member_inserted === false) {
    $wpdb->query('ROLLBACK');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create owner membership.',
        'db_error' => $wpdb->last_error,
        'db_query' => $wpdb->last_query,
        'debug' => ['stage' => 'insert_membership', 'group_id' => $group_id, 'user_id' => $user_id]
    ]);
    exit;
}

$wpdb->insert($audit_table, [
    'group_id'      => $group_id,
    'actor_user_id' => $user_id,
    'action'        => 'group_created',
    'meta_json'     => wp_json_encode(['name' => $name, 'visibility' => $visibility, 'join_mode' => $join_mode]),
]);

$wpdb->query('COMMIT');

echo json_encode([
    'success'  => true,
    'message'  => 'Group created as draft. Publish it once setup is complete.',
    'group_id' => $group_id,
    'slug'     => $slug,
    'status'   => 'draft',
]);
