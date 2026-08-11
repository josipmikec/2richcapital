<?php
// API: List discoverable signal groups for Discovery, with each group's membership state for this user.
// Updated for Group Creation Protocol: only shows status='live' + visibility='listed' groups
// (plus backward-compat is_active=1), and returns branding/category fields for richer cards.
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

global $wpdb;
$user_id           = (int) $_SESSION['user_id'];
$groups_table      = $wpdb->prefix . 'rich_signal_groups';
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';

$groups = $wpdb->get_results(
    "SELECT id, slug, name, team_name, description, pricing_type, price, member_count,
            owner_user_id, visibility, join_mode, status, category, avatar_url, cover_url,
            intro_text, requires_stop_loss, requires_take_profit, posted_signals_count, last_signal_at,
            verification_status, verified_at, accent_color
     FROM {$groups_table}
     WHERE is_active = 1
       AND status = 'live'
       AND visibility = 'listed'
     ORDER BY display_order ASC, id ASC",
    ARRAY_A
);

$groups = $groups ?: [];

// Membership rows for this user (status + role), keyed by group_id for fast lookup
$my_memberships = $wpdb->get_results($wpdb->prepare(
    "SELECT group_id, role, status, access_type FROM {$memberships_table} WHERE user_id = %d",
    $user_id
), ARRAY_A);

$my_membership_map = [];
foreach ($my_memberships ?: [] as $m) {
    $my_membership_map[(int) $m['group_id']] = $m;
}

foreach ($groups as &$g) {
    $g['id']                     = (int) $g['id'];
    $g['price']                  = (float) $g['price'];
    $g['member_count']           = (int) $g['member_count'];
    $g['owner_user_id']          = $g['owner_user_id'] !== null ? (int) $g['owner_user_id'] : null;
    $g['requires_stop_loss']     = (bool) $g['requires_stop_loss'];
    $g['requires_take_profit']   = (bool) $g['requires_take_profit'];
    $g['posted_signals_count']   = (int) $g['posted_signals_count'];
    $g['signal_count']           = $g['posted_signals_count'];
    $g['is_verified']            = ($g['verification_status'] ?? 'none') === 'verified';

    $membership = $my_membership_map[$g['id']] ?? null;
    $g['is_joined']   = $membership !== null && $membership['status'] === 'active';
    $g['my_role']     = $membership['role'] ?? null;
    $g['is_owner']    = ($membership['role'] ?? null) === 'owner';
}
unset($g);

// Also return the current user's own draft/pending groups so "My Groups" can show them
// even though they're excluded from public Discovery above.
$my_owned_drafts = $wpdb->get_results($wpdb->prepare(
    "SELECT id, slug, name, team_name, description, pricing_type, price, member_count,
            visibility, join_mode, status, category, avatar_url, cover_url, verification_status,
            verification_note, verification_requested_at, verified_at, intro_text, rules_text
     FROM {$groups_table}
     WHERE owner_user_id = %d AND status IN ('draft', 'pending_review', 'suspended')
     ORDER BY id DESC",
    $user_id
), ARRAY_A);

foreach ($my_owned_drafts as &$d) {
    $d['id']           = (int) $d['id'];
    $d['price']        = (float) $d['price'];
    $d['member_count'] = (int) $d['member_count'];
    $d['is_owner']     = true;
    $d['is_verified']  = ($d['verification_status'] ?? 'none') === 'verified';
}
unset($d);

echo json_encode([
    'success'     => true,
    'groups'      => $groups,
    'my_drafts'   => $my_owned_drafts ?: [],
]);
