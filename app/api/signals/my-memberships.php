<?php
// API: Get the current user's active group memberships (drives Joined state + switcher)
// Updated for Group Creation Protocol: now returns role, access_type, and the group's
// status/visibility so Trading Floor can distinguish owner/admin/analyst/member context
// and show the correct workspace controls per group.
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

$memberships = $wpdb->get_results($wpdb->prepare(
    "SELECT g.id, g.slug, g.name, g.team_name, g.pricing_type, g.price,
            g.visibility, g.status AS group_status, g.avatar_url, g.member_count,
            m.role, m.access_type, m.billing_status, m.joined_at, m.approved_at
     FROM {$memberships_table} m
     INNER JOIN {$groups_table} g ON g.id = m.group_id
     WHERE m.user_id = %d
       AND m.status = 'active'
       AND g.status NOT IN ('archived', 'suspended')
       AND COALESCE(g.is_active, 1) = 1
     ORDER BY (m.role = 'owner') DESC, m.joined_at DESC",
    $user_id
), ARRAY_A);


$memberships = $memberships ?: [];
foreach ($memberships as &$m) {
    $m['id']            = (int) $m['id'];
    $m['price']         = (float) $m['price'];
    $m['member_count']  = (int) $m['member_count'];
    $m['role']          = $m['role'] ?: 'member';
    $m['is_owner']      = $m['role'] === 'owner';
    $m['is_staff']      = in_array($m['role'], ['owner', 'admin', 'analyst'], true);
    $m['can_post']      = in_array($m['role'], ['owner', 'admin', 'analyst'], true);
    $m['can_manage']    = in_array($m['role'], ['owner', 'admin'], true);
}
unset($m);

echo json_encode(['success' => true, 'memberships' => $memberships]);
