<?php
require_once '../auth/session-config.php';
require_once '../auth/feature-flags.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    header('Location: https://app.2rich.capital/login/');
    exit;
}

rich_feature_guard('trading-floor', 'Trading Floor');
$user_name  = $_SESSION['user_name']  ?? 'Member';
$user_email = $_SESSION['user_email'] ?? '';
$user_id    = (int) ($_SESSION['user_id'] ?? 0);
$view_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : $user_id;
if ($view_user_id <= 0) {
    $view_user_id = $user_id;
}
$is_own_profile = $view_user_id === $user_id;

if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}
require_once dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$profile_table = $wpdb->prefix . 'rich_user_profiles';
$profile_row = $wpdb->get_row($wpdb->prepare(
    "SELECT display_name, trading_handle, bio, primary_market, trading_style FROM {$profile_table} WHERE user_id = %d LIMIT 1",
    $view_user_id
), ARRAY_A) ?: [];
$viewed_user = get_userdata($view_user_id);
$profile_display_name = $profile_row['display_name'] ?? ($viewed_user ? $viewed_user->display_name : $user_name);
$profile_handle = trim((string)($profile_row['trading_handle'] ?? ''));
$profile_handle = $profile_handle !== '' ? ltrim($profile_handle, '@') : strtolower(str_replace(' ', '', $profile_display_name));
$profile_bio = trim((string)($profile_row['bio'] ?? ''));
$profile_primary_market = trim((string)($profile_row['primary_market'] ?? ''));
$profile_trading_style = trim((string)($profile_row['trading_style'] ?? ''));
$trader_results = $wpdb->get_results(
    "SELECT u.ID AS user_id,
            COALESCE(NULLIF(p.display_name, ''), NULLIF(u.display_name, ''), NULLIF(u.user_nicename, ''), NULLIF(u.user_login, ''), CONCAT('User #', u.ID)) AS display_name,
            NULLIF(p.primary_market, '') AS primary_market,
            NULLIF(p.trading_style, '') AS trading_style
     FROM {$wpdb->users} u
     LEFT JOIN {$profile_table} p ON p.user_id = u.ID
     WHERE u.ID > 0
     ORDER BY display_name ASC
     LIMIT 100",
    ARRAY_A
) ?: [];

// Debug output is restricted to staff. Keep these variables defined for every request
// so production/member views never emit PHP undefined-variable warnings.
$rich_debug_row = null;
$rich_debug_roles = [];
$rich_debug_user_id = (int) $user_id;
$rich_debug_is_staff = false;

$total_trades = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d", $view_user_id
));
$wins = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d AND outcome = 'WIN'", $user_id
));
$losses = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d AND outcome = 'LOSS'", $user_id
));
$avg_pnl = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT AVG(profit_loss_pct) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d AND profit_loss_pct IS NOT NULL", $view_user_id
));
$best_trade = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT MAX(profit_loss_pct) FROM {$wpdb->prefix}rich_trades WHERE user_id = %d", $view_user_id
));
$win_rate = $total_trades > 0 ? round(($wins / $total_trades) * 100, 1) : 0;
$recent_trades = $wpdb->get_results($wpdb->prepare(
    "SELECT symbol, direction, outcome, profit_loss_pct, entry_date
     FROM {$wpdb->prefix}rich_trades
     WHERE user_id = %d
     ORDER BY entry_date DESC LIMIT 6",
    $view_user_id
));
$profile_stats = [
    ['Trades', $total_trades],
    ['Win Rate', $win_rate . '%'],
    ['Followers', '—'],
    ['Following', '—'],
];
$profile_post_count = 0;
$profile_followers_count = 0;
$profile_following_count = 0;
$profile_follow_state = false;
$social_table = $wpdb->prefix . 'rich_user_follows';
$post_table = $wpdb->prefix . 'rich_social_posts';
$wpdb->query("CREATE TABLE IF NOT EXISTS {$social_table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, follower_id BIGINT UNSIGNED NOT NULL, following_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY follower_following (follower_id, following_id), KEY following_idx (following_id), KEY follower_idx (follower_id)) {$wpdb->get_charset_collate()}");
$wpdb->query("CREATE TABLE IF NOT EXISTS {$post_table} (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, post_type VARCHAR(24) NOT NULL DEFAULT 'trade', symbol VARCHAR(32) NULL, direction VARCHAR(16) NULL, pnl_value DECIMAL(10,2) NULL, rr_value VARCHAR(32) NULL, caption TEXT NULL, image_url TEXT NULL, image_path TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY user_created_idx (user_id, created_at), KEY created_idx (created_at), KEY post_type_idx (post_type)) {$wpdb->get_charset_collate()}");
$profile_followers_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$social_table} WHERE following_id = %d", $view_user_id));
$profile_following_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$social_table} WHERE follower_id = %d", $view_user_id));
if (!$is_own_profile) {
    $profile_follow_state = (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$social_table} WHERE follower_id = %d AND following_id = %d LIMIT 1", $user_id, $view_user_id));
}
$profile_visibility_label = $is_own_profile ? 'Public profile preview' : 'Public trader profile';

$profile_section_note = $is_own_profile ? 'This is your public Trading Floor profile.' : "You are viewing this trader's public profile.";

if (!function_exists('tf_format_social_post')) {
    function tf_format_social_post($row, $fallback_name = 'Trader') {
        $display_name = trim((string) ($row->display_name ?? ''));
        if ($display_name === '') {
            $display_name = trim((string) ($row->user_nicename ?? ''));
        }
        if ($display_name === '') {
            $display_name = trim((string) ($row->user_login ?? ''));
        }
        if ($display_name === '') {
            $display_name = $fallback_name;
        }

        $avatar_url = '';
        if (!empty($row->user_id)) {
            $avatar_url = get_avatar_url((int) $row->user_id, ['size' => 96]);
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'user_id' => (int) ($row->user_id ?? 0),
            'author_name' => $display_name,
            'author_avatar' => $avatar_url ?: '',
            'post_type' => sanitize_key($row->post_type ?? 'trade'),
            'symbol' => strtoupper(trim((string) ($row->symbol ?? ''))),
            'direction' => strtoupper(trim((string) ($row->direction ?? ''))),
            'pnl_value' => isset($row->pnl_value) && $row->pnl_value !== null ? (float) $row->pnl_value : null,
            'rr_value' => trim((string) ($row->rr_value ?? '')),
            'caption' => trim((string) ($row->caption ?? '')),
            'image_url' => trim((string) ($row->image_url ?? '')),
            'created_at' => mysql2date('c', (string) ($row->created_at ?? current_time('mysql')), false),
            'created_label' => human_time_diff(strtotime((string) ($row->created_at ?? current_time('mysql'))), current_time('timestamp')) . ' ago',
        ];
    }
}

$profile_post_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT p.*, u.display_name, u.user_nicename, u.user_login
     FROM {$post_table} p
     LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
     WHERE p.user_id = %d
     ORDER BY p.created_at DESC
     LIMIT 24",
    $view_user_id
));
$profile_post_count = is_array($profile_post_rows) ? count($profile_post_rows) : 0;
$profile_posts = array_map(static function ($row) {
    return tf_format_social_post($row);
}, $profile_post_rows ?: []);

$feed_post_rows = $wpdb->get_results(
    "SELECT p.*, u.display_name, u.user_nicename, u.user_login
     FROM {$post_table} p
     LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
     ORDER BY p.created_at DESC
     LIMIT 30"
);
$home_feed_posts = array_map(static function ($row) {
    return tf_format_social_post($row);
}, $feed_post_rows ?: []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Floor - 2RICH CAPITAL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* Topbar action buttons */
        .tf-topbar-btn {
            display: flex; align-items: center; gap: 6px;
            background: none; border: none; cursor: pointer;
            color: #555; padding: 6px 8px; border-radius: 8px;
            font-family: "Montserrat", sans-serif;
            font-size: 11px; font-weight: 700; letter-spacing: 0.04em;
            transition: color 0.2s, background 0.2s;
            position: relative;
        }
        .tf-topbar-btn:hover { color: #ccc; background: rgba(255,255,255,0.04); }
        .tf-topbar-badge {
            position: absolute; top: 4px; right: 4px;
            width: 7px; height: 7px; background: #F2CA50;
            border-radius: 50%; border: 1.5px solid #0E0E0E;
            box-shadow: 0 0 6px rgba(242,202,80,0.5);
        }
        .tf-topbar-create {
            color: #F2CA50;
            border: 1px solid rgba(242,202,80,0.25);
            background: rgba(242,202,80,0.06);
            padding: 6px 14px;
        }
        .tf-topbar-create:hover { background: rgba(242,202,80,0.12); color: #F2CA50; }
        .tf-topbar-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #F2CA50, #FFDB70);
            color: #0E0E0E; font-size: 13px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: 2px solid transparent;
            transition: border-color 0.2s;
        }
        .tf-topbar-avatar:hover { border-color: #F2CA50; }

        .floor-section { flex: 1; display: block; padding: 32px 24px 32px 32px; max-width: 640px; overflow-y: auto; }
        .floor-section[hidden] { display: none !important; }
        .floor-section-card { background: #151515; border: 1px solid #1e1e1e; border-radius: 16px; padding: 20px; box-shadow: 0 24px 64px rgba(0,0,0,0.35); }
        .floor-section-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .floor-kicker { font-size: 9px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #777; margin-bottom: 6px; }
        .floor-profile-grid { display: grid; grid-template-columns: 72px 1fr; gap: 18px; align-items: start; }
        .floor-profile-avatar { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #F2CA50, #FFDB70); color: #0E0E0E; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; }
        .floor-profile-name { font-size: 24px; font-weight: 800; color: #f2f2f2; margin-bottom: 4px; }
        .floor-profile-email { font-size: 12px; color: #999; margin-bottom: 12px; }
        .floor-profile-note { font-size: 13px; color: #9ea3ad; line-height: 1.6; max-width: 560px; }
        .profile-hero {
            background: transparent;
            border: 0;
            border-radius: 0;
            padding: 8px 0 0;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 18px;
            position: relative;
            overflow: visible;
        }
        .profile-avatar-wrap { position: relative; flex-shrink: 0; }
        .profile-avatar-large {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #F2CA50, #FFDB70);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 800;
            color: #0E0E0E;
            border: 3px solid rgba(242,202,80,0.3);
        }
        .profile-avatar-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 26px;
            height: 26px;
            background: #1e1e1e;
            border: 2px solid #0E0E0E;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            color: #666;
        }
        .profile-avatar-edit:hover { background: #2a2a2a; color: #aaa; }
        .profile-info { flex: 1; }
        .profile-handle-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .profile-name { font-size: 14px; font-weight: 500; color: #8b9098; letter-spacing: 0.01em; margin-top: 0; }
        .profile-handle { font-size: 30px; font-weight: 800; color: #f1f1f1; margin: 0; letter-spacing: -0.01em; line-height: 1; }
        .profile-member-badge { position:relative; display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:999px; background:rgba(242,202,80,0.18); color:#ffe082; border:1px solid rgba(255,220,120,0.42); box-shadow: inset 0 1px 0 rgba(255,255,255,0.2), 0 4px 12px rgba(0,0,0,0.16); overflow:hidden; transition: width 0.2s ease, padding 0.2s ease, background 0.18s ease, border-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease; vertical-align:middle; white-space:nowrap; backdrop-filter: blur(6px); }
        .profile-member-badge:hover { width:120px; padding:0 10px; justify-content:flex-start; background:rgba(242,202,80,0.24); border-color:rgba(255,228,138,0.54); box-shadow: inset 0 1px 0 rgba(255,255,255,0.24), 0 8px 18px rgba(0,0,0,0.22); }
        .profile-member-badge-icon { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; flex:0 0 20px; font-size:11px; font-weight:800; text-shadow: 0 1px 0 rgba(0,0,0,0.16); }
        .profile-member-badge-text { opacity:0; max-width:0; overflow:hidden; font-size:8px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#ffe082; text-shadow: 0 1px 0 rgba(0,0,0,0.16); transition: opacity 0.16s ease, max-width 0.2s ease, margin-left 0.2s ease; }
        .profile-member-badge:hover .profile-member-badge-text { opacity:1; max-width:92px; margin-left:3px; }
        .profile-bio-text { font-size: 14px; font-weight: 500; color: #8b9098; margin-top: 12px; line-height: 1.55; max-width: 460px; }
        .profile-bio-line { display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-top:12px; max-width:560px; }
        .profile-bio-copy { font-size: 14px; font-weight: 500; color: #8b9098; line-height: 1.55; }
        .profile-bio-separator { color: rgba(255,255,255,0.22); font-weight: 700; }
        .profile-identity-badges { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .profile-identity-badge { display:inline-flex; align-items:center; gap:6px; min-height:24px; padding:4px 10px; border:1px solid rgba(242,202,80,0.28); border-radius:999px; background:linear-gradient(180deg, rgba(242,202,80,0.16), rgba(242,202,80,0.07)); color:#ffe082; box-shadow:inset 0 1px 0 rgba(255,255,255,0.12), 0 4px 12px rgba(0,0,0,0.14); font-size:10px; font-weight:750; letter-spacing:.04em; text-transform:uppercase; }
        .profile-identity-badge::before { content:'◆'; font-size:7px; color:#f2ca50; }
        .profile-identity-badge--style { border-color:rgba(151,185,255,0.28); background:linear-gradient(180deg, rgba(151,185,255,0.15), rgba(151,185,255,0.06)); color:#c8d9ff; }
        .profile-identity-badge--style::before { color:#97b9ff; }
        .profile-identity-grid { display:grid; gap:16px; margin-top:18px; }
        .profile-section-card { background: rgba(18,18,18,0.82); border: 1px solid #1a1a1a; border-radius: 14px; padding: 18px; }
        .profile-section-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .profile-section-head h3 { margin:0; font-size:15px; font-weight:800; color:#f1f1f1; letter-spacing:0.01em; }
        .profile-section-head p { margin:4px 0 0; font-size:12px; line-height:1.5; color:#7b8088; max-width:420px; }
        .profile-social-strip { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; margin-top:18px; }
        .profile-social-box { background:#101010; border:1px solid rgba(255,255,255,0.06); border-radius:14px; padding:14px 12px; }
        .profile-social-box-value { font-size:22px; font-weight:800; color:#f3f3f3; line-height:1; }
        .profile-social-box-label { margin-top:6px; font-size:10px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#666b73; }
        .profile-highlights { display:flex; gap:12px; margin-top:18px; overflow-x:auto; padding-bottom:2px; scrollbar-width:none; }
        .profile-profile-actions { display:flex; gap:10px; margin-top:16px; flex-wrap:nowrap; width:100%; max-width:360px; margin-left:0; margin-right:0; justify-content:flex-start; }
        .profile-action-btn { flex:1 1 0; display:inline-flex; align-items:center; justify-content:center; min-width:0; padding:9px 16px; border-radius:999px; border:1px solid rgba(255,255,255,0.05); background:#131313; color:#bfc3ca; font-size:10px; font-weight:700; letter-spacing:0.01em; text-transform:none; transition:background 0.2s,border-color 0.2s,color 0.2s,transform 0.2s; }
        .profile-action-btn:hover { background:#171717; border-color:rgba(255,255,255,0.10); color:#e8e8e8; transform:translateY(-1px); }
        .profile-action-btn.secondary { background:#141414; border-color:rgba(255,255,255,0.06); color:#c8ccd2; }
        .profile-action-btn.secondary:hover { background:#181818; border-color:rgba(255,255,255,0.10); color:#ededed; }
        .profile-highlights::-webkit-scrollbar { display:none; }
        .profile-highlight { min-width:70px; display:grid; justify-items:center; gap:7px; }
        .profile-highlight-ring { width:63px; height:63px; border-radius:50%; border:1px solid rgba(255,255,255,0.08); background:#101010; display:grid; place-items:center; }
        .profile-highlight-ring span { width:51px; height:51px; border-radius:50%; border:2px solid rgba(255,255,255,0.18); display:grid; place-items:center; color:#f3f3f3; font-size:19px; font-weight:700; }
        .profile-highlight-label { font-size:10px; font-weight:800; letter-spacing:0.08em; text-transform:uppercase; color:#c3c7cf; text-align:center; }
        .profile-tabs { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:18px; padding-top:16px; border-top:1px solid rgba(255,255,255,0.06); }
        .profile-tab-list { display:flex; gap:8px; flex-wrap:wrap; }
        .profile-tab { display:inline-flex; align-items:center; justify-content:center; min-height:34px; padding:0 12px; border-radius:999px; border:1px solid rgba(255,255,255,0.08); background:#101010; color:#b7bcc5; font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; }
        .profile-tab.active { background:rgba(242,202,80,0.12); color:#F2CA50; border-color:rgba(242,202,80,0.22); }
        .profile-counts-inline { display:flex; gap:10px; flex-wrap:wrap; align-items:center; color:#f3f3f3; font-size:12px; font-weight:700; }
        .profile-counts-inline span { color:#80858d; font-weight:600; }
        .profile-feed-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px; margin-top:18px; }
        .profile-tab-panel { display:none; margin-top:18px; }
        .profile-tab-panel.active { display:block; }
        .profile-archive-mode .profile-hero,
        .profile-archive-mode .profile-profile-actions,
        .profile-archive-mode .profile-highlights,
        .profile-archive-mode .profile-tabs,
        .dashboard-container.profile-mode #floor-profile-panel.profile-archive-mode .profile-hero,
        .dashboard-container.profile-mode #floor-profile-panel.profile-archive-mode .profile-profile-actions,
        .dashboard-container.profile-mode #floor-profile-panel.profile-archive-mode .profile-highlights,
        .dashboard-container.profile-mode #floor-profile-panel.profile-archive-mode .profile-tabs {
            display:none !important;
        }
        .profile-trades-panel { display:grid; gap:18px; }
        .profile-stats-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px; }
        .profile-stat-card { background:#121212; border:1px solid rgba(255,255,255,0.06); border-radius:16px; padding:18px; }
        .profile-stat-card-label { font-size:10px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#6e737c; }
        .profile-stat-card-value { margin-top:10px; font-size:28px; font-weight:800; color:#f3f3f3; line-height:1; }
        .profile-stat-card-value.gold { color:#F2CA50; }
        .profile-stat-card-value.positive { color:#4ade80; }
        .profile-stat-card-value.negative { color:#f87171; }
        .profile-stat-card-sub { margin-top:8px; font-size:12px; color:#7b8088; line-height:1.5; }
        .profile-activity-card { background:#121212; border:1px solid rgba(255,255,255,0.06); border-radius:16px; overflow:hidden; }
        .profile-activity-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 18px 14px; border-bottom:1px solid rgba(255,255,255,0.06); }
        .profile-activity-head h3 { margin:0; font-size:15px; font-weight:800; color:#f3f3f3; }
        .profile-activity-head p { margin:4px 0 0; font-size:12px; color:#7b8088; }
        .profile-activity-link { color:#F2CA50; font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; text-decoration:none; }
        .profile-activity-empty { padding:40px; text-align:center; color:#666b73; font-size:12px; font-weight:600; }
        .profile-activity-row { display:grid; grid-template-columns:minmax(0, 1fr) auto auto auto auto; gap:12px; align-items:center; padding:14px 18px; border-top:1px solid rgba(255,255,255,0.04); }
        .profile-activity-symbol { font-size:13px; font-weight:800; color:#f3f3f3; }
        .profile-activity-dir,
        .profile-activity-outcome { font-size:10px; font-weight:800; letter-spacing:0.08em; text-transform:uppercase; padding:6px 8px; border-radius:999px; }
        .profile-activity-dir.long, .profile-activity-dir.buy { background:rgba(74,222,128,0.12); color:#4ade80; }
        .profile-activity-dir.short, .profile-activity-dir.sell { background:rgba(248,113,113,0.12); color:#f87171; }
        .profile-activity-outcome.win { background:rgba(74,222,128,0.12); color:#4ade80; }
        .profile-activity-outcome.loss { background:rgba(248,113,113,0.12); color:#f87171; }
        .profile-activity-outcome.be { background:rgba(255,255,255,0.08); color:#d1d5db; }
        .profile-activity-pnl { font-size:13px; font-weight:800; }
        .profile-activity-pnl.pos { color:#4ade80; }
        .profile-activity-pnl.neg { color:#f87171; }
        .profile-activity-date { font-size:11px; color:#6e737c; text-align:right; }
        .profile-post-thumb { position:relative; aspect-ratio:1 / 1; border-radius:16px; overflow:hidden; background:#101010; border:1px solid rgba(255,255,255,0.06); display:grid; place-items:center; }
        .profile-post-thumb .post-meta-badge { position:absolute; top:10px; right:10px; z-index:1; }
        .profile-inline-list { display:grid; gap:8px; }
        .profile-inline-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:12px; background:#101010; border:1px solid rgba(255,255,255,0.06); }
        .profile-inline-copy strong { display:block; font-size:12px; color:#ececec; }
        .profile-inline-copy span { display:block; margin-top:2px; font-size:11px; color:#767b84; }
        @media (max-width: 720px) {
            .profile-social-strip, .profile-feed-grid { grid-template-columns:1fr; }
            .profile-tabs { flex-direction:column; align-items:flex-start; }
            .profile-hero { padding:22px; gap:18px; flex-direction:column; align-items:flex-start; }
        }
        .profile-stats-row { display:flex; gap:18px; margin-top:10px; margin-bottom:10px; flex-wrap:wrap; align-items:center; }
        .profile-stat { display:inline-flex; align-items:baseline; gap:6px; text-align:left; border:0; padding:0; margin:0; background:transparent; font:inherit; color:inherit; appearance:none; -webkit-appearance:none; box-shadow:none; }
        .profile-stat-value { font-size: 14px; font-weight: 800; color: #F2CA50; line-height:1; }
        .profile-stat-label { font-size: 14px; font-weight: 500; color: #8b9098; letter-spacing: 0; text-transform: none; margin-top: 0; line-height:1.2; }
        .profile-stat:hover .profile-stat-value,
        .profile-stat:focus-visible .profile-stat-value { color:#f7d86d; }

        .social-list-modal { position: fixed; inset: 0; z-index: 1000; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.68); padding: 20px; }
        .social-list-modal.open { display: flex; }
        .social-list-dialog { width: min(420px, 100%); max-height: 80vh; overflow: auto; background: #171717; border: 1px solid rgba(255,255,255,.12); border-radius: 14px; padding: 20px; }
        .social-list-head { display:flex; align-items:center; justify-content:space-between; gap: 12px; margin-bottom: 16px; }
        .social-list-title { font-size: 18px; font-weight: 800; color: #f5f5f5; }
        .social-list-close { color:#aaa; font-size:22px; cursor:pointer; }
        .social-list-row { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,.08); }
        .social-list-avatar { width:34px; height:34px; border-radius:50%; display:grid; place-items:center; color:#111; font-size:12px; font-weight:800; }
        .social-list-name { color:#f5f5f5; font-weight:700; text-decoration:none; }
        .social-list-empty { color:#8d929b; padding:18px 0; }
        .profile-stat-label { font-size: 14px; font-weight: 500; color: #8b9098; letter-spacing: 0; text-transform: none; margin-top: 0; line-height:1.2; }
        .profile-badge-row { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
        .profile-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .profile-badge.gold { background: rgba(242,202,80,0.12); color: #F2CA50; border: 1px solid rgba(242,202,80,0.2); }
        .profile-badge.green { background: rgba(74,222,128,0.1); color: #4ade80; border: 1px solid rgba(74,222,128,0.15); }
        .profile-badge.blue { background: rgba(96,165,250,0.1); color: #60a5fa; border: 1px solid rgba(96,165,250,0.15); }
        .group-feed-stack { display: flex; flex-direction: column; gap: 12px; padding-bottom: 28px; }
        .group-pinned-header { font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; color: #f2ca50; font-weight: 800; margin: 4px 0 0; }
        .group-feed-card { background: rgba(20,20,20,0.9); border: 1px solid rgba(242,202,80,0.12); border-radius: 18px; padding: 15px 16px; box-shadow: 0 12px 26px rgba(0,0,0,0.18); }
        .group-feed-card.joined { border-color: rgba(242,202,80,0.28); background: linear-gradient(180deg, rgba(242,202,80,0.08), rgba(20,20,20,0.94)); }
        .group-feed-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .group-feed-title { font-size: 20px; line-height: 1.12; font-weight: 800; color: #f5f5f5; letter-spacing: -0.02em; }
        .group-feed-meta { margin-top: 5px; font-size: 12px; color: #a8a8a8; letter-spacing: 0.01em; }
        .group-feed-body { margin-top: 10px; font-size: 13px; line-height: 1.55; color: #d3d3d3; }
        .group-joined-pill { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border-radius: 999px; background: rgba(242,202,80,0.14); border: 1px solid rgba(242,202,80,0.3); color: #f2ca50; font-size: 10px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; white-space: nowrap; }
        .group-feed-actions { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
        .tf-groups-workspace { background: transparent; border: none; box-shadow: none; }
        .tf-groups-shell { display: flex; flex-direction: column; gap: 14px; }
        .tf-groups-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 4px; padding-bottom: 0; border-bottom: none; }
        .tf-groups-header h2 { font-size: 19px; line-height: 1.1; font-weight: 800; letter-spacing: 0.01em; color: #f3f3f3; margin: 0; }
        .tf-groups-header p { max-width: 720px; font-size: 12px; line-height: 1.45; color: #8a909b; margin: 4px 0 0; }
        .tf-groups-toolbar { display:flex; flex-wrap:wrap; gap:0; padding:0; margin:0 0 28px 0; align-items:center; justify-content:flex-start; border-bottom:1px solid rgba(255,255,255,0.06); }
        .tf-groups-toolbar .group-pill-btn { padding:0 14px 10px; font-size:11px; font-weight:700; letter-spacing:0.08em; color:#5f6064; background:none; border:none; border-bottom:2px solid transparent; border-radius:0; cursor:pointer; font-family:"Montserrat",sans-serif; line-height:1; text-transform:uppercase; transition:color 0.2s ease, border-color 0.2s ease; box-shadow:none; min-height:auto; }
        .tf-groups-toolbar .group-pill-btn:hover { color:#b9b9bd; background:none; transform:none; filter:none; }
        .tf-groups-toolbar .group-pill-btn.is-active { color:#F2CA50; border-bottom-color:#F2CA50; background:none; }
        .group-pill-btn, .group-ghost-btn { border: none; border-radius: 999px; padding: 7px 12px; min-height: auto; font-weight: 700; font-size: 10px; letter-spacing: .08em; text-transform: uppercase; line-height: 1; transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease, opacity 0.18s ease; }
        .group-pill-btn { background: #f2ca50; color: #111827; box-shadow: inset 0 1px 0 rgba(255,255,255,0.18); }
        .group-pill-btn:hover { transform: none; filter: none; }
        .group-pill-btn.is-active { background: #f2ca50; color: #111827; }
        .group-ghost-btn { background: rgba(255,255,255,0.015); color: #c6cad3; border: 1px solid rgba(255,255,255,0.10); }
        .group-ghost-btn:hover { background: transparent; border-color: rgba(255,255,255,0.10); }
        .group-color-palette { display:flex; flex-wrap:wrap; gap:8px; }
        .group-color-swatch { width:34px; height:34px; border-radius:999px; border:2px solid rgba(255,255,255,0.12); box-shadow: inset 0 1px 0 rgba(255,255,255,0.1); cursor:pointer; padding:0; position:relative; }
        .group-color-swatch.is-selected { outline:2px solid #f2ca50; outline-offset:2px; }
        .group-color-swatch::after { content:''; position:absolute; inset:8px; border-radius:999px; background:rgba(0,0,0,0.12); opacity:0; transition:opacity 0.18s ease; }
        .group-color-swatch:hover::after { opacity:1; }
        .group-ghost-btn:hover { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.14); color: #eceff4; }
        .group-ghost-btn:disabled { opacity: 0.55; cursor: default; }
        .tf-group-workspace { display:flex; flex-direction:column; gap:20px; }
        .group-workspace-hero { display:grid; grid-template-columns:minmax(0, 1.2fr) auto; align-items:start; gap:20px; padding:10px 0 4px; }
        .group-workspace-copy { min-width:0; max-width:620px; display:grid; gap:8px; }
        .group-workspace-copy .section-kicker { margin-bottom:0; }
        .group-workspace-copy h2 { font-size:38px; line-height:0.98; font-weight:800; color:#f5f5f5; margin:0; letter-spacing:-0.04em; max-width:11ch; }
        .group-workspace-copy p { font-size:14px; line-height:1.5; color:#d5d7dc; margin:0; max-width:30ch; }
        .group-workspace-copy .group-card-meta { display:flex; flex-wrap:wrap; gap:6px 12px; font-size:11px; color:#8f95a0; }
        .group-workspace-copy .group-card-meta span { white-space:nowrap; }
        .group-workspace-hero .group-card-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; align-items:center; gap:8px; flex:0 0 auto; width:auto; max-width:100%; padding-top:2px; }
        .group-workspace-hero .group-card-actions .group-pill-btn,
        .group-workspace-hero .group-card-actions .group-ghost-btn { min-height:34px; padding:0 14px; }
        .group-workspace-signal-head { display:flex; align-items:center; justify-content:flex-start; gap:8px; margin-bottom:2px; }
        .group-workspace-signal-actions { display:flex; align-items:center; justify-content:center; gap:0; margin-top:-6px; }
        .group-workspace-tooltip { position:relative; display:inline-flex; align-items:center; gap:0; width:max-content; }
        .group-workspace-tooltip-copy { position:absolute; left:0; top:calc(100% + 10px); width:min(320px, 78vw); padding:12px 14px; border-radius:14px; background:rgba(14,14,14,0.96); border:1px solid rgba(242,202,80,0.18); box-shadow:0 18px 44px rgba(0,0,0,0.32); color:#d8dbe1; font-size:13px; line-height:1.5; opacity:0; pointer-events:none; transform:translateY(4px); transition:opacity .18s ease, transform .18s ease; z-index:8; }
        .group-workspace-tooltip:hover .group-workspace-tooltip-copy,
        .group-workspace-tooltip:focus-within .group-workspace-tooltip-copy { opacity:1; transform:translateY(0); }
        .group-workspace-signal-action { display:inline-flex; align-items:center; justify-content:center; gap:4px; min-height:26px; padding:0 6px; border-radius:999px; border:none; background:transparent; color:#f2ca50; font-size:9px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; overflow:hidden; transition:width .18s ease, padding .18s ease, background-color .18s ease, color .18s ease; }
        .group-workspace-signal-action svg { width:10px; height:10px; display:block; flex:0 0 auto; }
        .group-workspace-signal-action span { max-width:0; opacity:0; transform:translateX(-4px); white-space:nowrap; overflow:hidden; transition:max-width .18s ease, opacity .18s ease, transform .18s ease; }
        .group-workspace-signal-action:hover,
        .group-workspace-signal-action:focus-visible { width:auto; padding:0 9px; background:rgba(242,202,80,0.12); color:#f6d56b; }
        .group-workspace-signal-action:hover span,
        .group-workspace-signal-action:focus-visible span { max-width:70px; opacity:1; transform:translateX(0); }
        .group-workspace-signal-action--mt5 { color:#f2ca50; background:transparent; }
        .group-workspace-signal-action--mt5:hover,
        .group-workspace-signal-action--mt5:focus-visible { background:rgba(242,202,80,0.12) !important; color:#f6d56b; }
        .group-workspace-signal-action--mt5 span { max-width:0; }
        .group-workspace-tabs { display:flex; flex-wrap:wrap; gap:0; padding:0; margin:0 0 4px 0; align-items:center; justify-content:flex-start; border-bottom:1px solid rgba(255,255,255,0.06); }
        .group-workspace-tabs .group-pill-btn,
        .group-workspace-tabs .group-ghost-btn { padding:0 14px 10px; font-size:11px; font-weight:700; letter-spacing:0.08em; color:#5f6064; background:none; border:none; border-bottom:2px solid transparent; border-radius:0; cursor:pointer; font-family:"Montserrat",sans-serif; line-height:1; text-transform:uppercase; transition:color 0.2s ease, border-color 0.2s ease; box-shadow:none; min-height:auto; }
        .group-workspace-tabs .group-pill-btn:hover,
        .group-workspace-tabs .group-ghost-btn:hover { color:#b9b9bd; background:none; transform:none; filter:none; }
        .group-workspace-tabs .group-pill-btn.is-active,
        .group-workspace-tabs .group-ghost-btn.is-active { color:#F2CA50; border-bottom-color:#F2CA50; background:none; }
        .group-workspace-grid { display:grid; grid-template-columns:minmax(0, 1.52fr) minmax(220px, 0.72fr); gap:18px; align-items:start; }
        .group-workspace-panel { display:flex; flex-direction:column; gap:8px; min-width:0; }
        .group-workspace-panel h3 { font-size:15px; line-height:1.12; font-weight:800; color:#f5f5f5; margin:0; letter-spacing:-0.02em; max-width:14ch; }
        .group-workspace-panel p { font-size:12px; line-height:1.55; color:#c8ced7; margin:0; max-width:28ch; }
        .group-workspace-panel .group-feed-card { min-height:104px; display:flex; align-items:flex-start; }
        .group-workspace-panel--signals .group-feed-card { min-height:0; }
        .group-workspace-tooltip { position:relative; display:inline-flex; align-items:center; gap:8px; width:max-content; }
        .group-workspace-tooltip-copy { position:absolute; left:0; top:calc(100% + 10px); width:min(320px, 78vw); padding:12px 14px; border-radius:14px; background:rgba(14,14,14,0.96); border:1px solid rgba(242,202,80,0.18); box-shadow:0 18px 44px rgba(0,0,0,0.32); color:#d8dbe1; font-size:13px; line-height:1.5; opacity:0; pointer-events:none; transform:translateY(4px); transition:opacity .18s ease, transform .18s ease; z-index:8; }
        .group-workspace-tooltip:hover .group-workspace-tooltip-copy,
        .group-workspace-tooltip:focus-within .group-workspace-tooltip-copy { opacity:1; transform:translateY(0); }
        .group-signal-list { display:flex; flex-direction:column; gap:6px; }
        .group-signal-card { background:rgba(17,17,17,0.84); border:1px solid rgba(255,255,255,0.06); border-radius:14px; padding:10px 12px; box-shadow:none; overflow:hidden; }
        .group-signal-card-header { display:grid; grid-template-columns:minmax(0, 0.9fr) minmax(0, 1fr) minmax(74px, auto); gap:10px; align-items:start; }
        .group-signal-card-header > div { min-width:0; }
        .group-signal-card-symbol { font-size:10px; letter-spacing:0.12em; text-transform:uppercase; color:#9ca3af; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .group-signal-card-title { font-size:16px; line-height:1.02; font-weight:800; color:#f5f5f5; letter-spacing:-0.02em; margin-top:3px; word-break:break-word; overflow-wrap:anywhere; }
        .group-signal-card-status { font-size:10px; color:#8f96a3; letter-spacing:0.12em; text-transform:uppercase; line-height:1.3; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .group-signal-card-levels { font-size:12px; line-height:1.45; color:#eceef2; word-break:break-word; overflow-wrap:anywhere; }
        .group-signal-card-time { font-size:10px; color:#7d8592; text-align:right; line-height:1.35; white-space:normal; word-break:break-word; overflow-wrap:anywhere; max-width:84px; justify-self:end; }
        .group-signal-card-notes { margin-top:6px; font-size:12px; line-height:1.45; color:#d8dbe1; word-break:break-word; overflow-wrap:anywhere; }
        .tf-group-workspace > .group-feed-card { margin-top:2px; }
        @media (max-width: 1240px) {
            .group-workspace-hero { grid-template-columns:minmax(0, 1fr); }
            .group-workspace-hero .group-card-actions { width:100%; flex-basis:auto; justify-content:flex-start; }
        }
        @media (max-width: 1180px) { .group-workspace-grid { grid-template-columns:1fr; } }
        @media (max-width: 960px) {
            .group-workspace-copy h2 { font-size:34px; max-width:none; }
            .group-workspace-copy p { max-width:none; }
            .group-workspace-panel p { max-width:none; }
        }
        @media (max-width: 720px) {
            .group-workspace-tabs { gap:0; }
            .group-workspace-tabs .group-pill-btn,
            .group-workspace-tabs .group-ghost-btn,
            .group-workspace-hero .group-card-actions .group-pill-btn,
            .group-workspace-hero .group-card-actions .group-ghost-btn { width:100%; justify-content:center; }
        }
        .group-signal-modal{position:fixed;inset:0;background:rgba(0,0,0,0.72);display:none;align-items:center;justify-content:center;z-index:12000;padding:24px}
        .group-signal-modal.active{display:flex}
        .group-signal-dialog{width:min(760px,100%);max-height:min(88vh,920px);overflow:auto;background:linear-gradient(180deg,#151515 0%,#101010 100%);border:1px solid rgba(242,202,80,0.14);border-radius:22px;box-shadow:0 30px 80px rgba(0,0,0,0.45)}
        .group-signal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px 16px;border-bottom:1px solid rgba(255,255,255,0.06)}
        .group-signal-head h3{font-size:24px;line-height:1.05;font-weight:800;color:#f6f6f6;margin:4px 0 6px;letter-spacing:-0.03em}
        .group-signal-head p{font-size:13px;line-height:1.5;color:#9aa0aa;margin:0;max-width:52ch}
        .group-signal-body{padding:22px 24px 24px;display:flex;flex-direction:column;gap:18px}
        .group-signal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .group-signal-field{display:flex;flex-direction:column;gap:7px;min-width:0}
        .group-signal-field.full{grid-column:1 / -1}
        .group-signal-label{font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#8f949d}
        .group-signal-input,.group-signal-select,.group-signal-textarea{width:100%;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:14px;color:#f4f5f7;padding:12px 14px;font-size:14px;outline:none;transition:border-color .18s ease,box-shadow .18s ease,background-color .18s ease}
        .group-signal-input:focus,.group-signal-select:focus,.group-signal-textarea:focus{border-color:rgba(242,202,80,0.45);box-shadow:0 0 0 3px rgba(242,202,80,0.10);background:rgba(255,255,255,0.045)}
        .group-signal-select{appearance:none}
        .group-signal-textarea{min-height:120px;resize:vertical}
        .group-signal-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-top:4px}
        .group-signal-note{font-size:12px;line-height:1.5;color:#8f949d;max-width:48ch}
        .group-signal-status{font-size:13px;line-height:1.45;color:#f2ca50;min-height:20px}
        @media (max-width: 720px){.group-signal-modal{padding:14px}.group-signal-head,.group-signal-body{padding:18px}.group-signal-grid{grid-template-columns:1fr}.group-signal-actions{flex-direction:column;align-items:stretch}.group-signal-actions .group-pill-btn,.group-signal-actions .group-ghost-btn{width:100%;justify-content:center}}
        .group-feature-card, .group-card, .group-row-card { background: linear-gradient(180deg, rgba(255,255,255,0.022) 0%, rgba(255,255,255,0.012) 100%); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.03); }
        .group-feature-card { padding: 18px 20px; margin-top: 4px; }
        .group-feature-card h3, .group-card h3, .group-row-card h3 { color: #f4f4f5; letter-spacing: 0; margin: 0 0 9px; }
        .group-feature-card p, .group-card p, .group-row-card p { color: #bcc1ca; margin: 9px 0 0; line-height: 1.55; max-width: 46ch; }
        .group-card-kicker, .section-kicker { color: #9da3b0; font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 6px; display: inline-block; }
        .group-grid, .group-list { display: flex; flex-direction: column; gap: 10px; }
        .group-card, .group-row-card { padding: 14px 16px; }
        .group-card { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(260px,0.95fr); gap:20px; align-items:start; }
        .group-card-main { min-width:0; display:flex; flex-direction:column; }
        .group-card-side { min-width:0; display:flex; flex-direction:column; align-items:flex-end; justify-content:flex-end; gap:16px; }
        .group-card-head { display:flex; align-items:flex-start; gap:12px; }
        .group-card-avatar { width:52px; height:52px; border-radius:50%; flex:0 0 52px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:linear-gradient(180deg, rgba(242,202,80,0.2), rgba(242,202,80,0.08)); border:1px solid rgba(242,202,80,0.18); color:#f2ca50; font-size:18px; font-weight:800; letter-spacing:0.02em; }
        .group-card-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
        .group-card-copy { min-width:0; }
        .group-card-copy p { max-width:34ch; margin-top: 12px; }
        .group-card-copy .group-card-meta { margin-top: 8px; gap: 8px 14px; }
        .group-card-side .group-card-badge { justify-content:flex-end; flex-wrap:wrap; }
        .group-card-side .group-card-meta { justify-content:flex-end; text-align:right; max-width:360px; }
        .group-card-side .group-card-actions { justify-content:flex-end; width:100%; }

        .group-card-top, .group-row-actions, .group-card-actions { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .group-card-meta { display: flex; flex-wrap: wrap; gap: 10px 16px; margin-top: 14px; color: #7f8794; font-size: 11px; }
        .group-card-meta span { display: inline-flex; align-items: center; min-height: auto; padding: 0; border-radius: 0; background: transparent; border: none; color: inherit; font-size: inherit; white-space: nowrap; }
        .group-card-badge { display: inline-flex; align-items: center; gap: 8px; color: #f2ca50; font-size: 9px; letter-spacing: 0.12em; text-transform: uppercase; margin-top: 2px; }
        .group-verified-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border-radius:999px; background:rgba(110,231,183,0.10); border:1px solid rgba(110,231,183,0.22); color:#8ef0c1; font-size:9px; font-weight:800; letter-spacing:0.08em; text-transform:uppercase; white-space:nowrap; box-shadow: inset 0 1px 0 rgba(255,255,255,0.08); }
        .group-create-form { display: flex; flex-direction: column; gap: 10px; padding: 0; border-radius: 0; background: transparent; border: none; }
        .group-create-form label { display: flex; flex-direction: column; gap: 5px; color: #c9ced7; font-size: 11px; font-weight: 700; letter-spacing: 0.02em; }
        .group-create-form input, .group-create-form select, .group-create-form textarea { width: 100%; border-radius: 10px; border: 1px solid rgba(255,255,255,0.07); background: rgba(255,255,255,0.022); color: #f4f4f5; padding: 10px 11px; font-family: "Montserrat", sans-serif; font-size: 12px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.025); }
        .group-create-form select { appearance: none; }
        .group-create-form textarea { resize: vertical; min-height: 92px; }
        .group-create-form input::placeholder, .group-create-form textarea::placeholder { color: #7d8594; }
        .group-create-form input:focus, .group-create-form select:focus, .group-create-form textarea:focus { outline: none; border-color: rgba(242,202,80,0.24); background: rgba(255,255,255,0.04); }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px; }
        .checkbox { flex-direction: row !important; align-items: center; gap: 8px !important; padding: 12px 14px; border-radius: 12px; background: rgba(255,255,255,0.025); border: 1px solid rgba(255,255,255,0.06); color: #cfd5df; }
        .checkbox input { width: 15px; height: 15px; accent-color: #f2ca50; }
        .group-create-form .group-row-actions { padding-top: 4px; justify-content: flex-start; }
        @media (max-width: 900px) { .tf-groups-header { flex-direction: column; align-items: flex-start; } .form-grid { grid-template-columns: 1fr; } }
        .group-manager-shell { display: flex; flex-direction: column; gap: 14px; padding-bottom: 28px; }
        .group-manager-toolbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 18px 0; }
        .group-manager-heading { font-size: 18px; font-weight: 800; color: #f3f3f3; }
        .group-manager-subheading { margin-top: 6px; font-size: 12px; line-height: 1.55; color: #a0a0a0; max-width: 420px; }
        .group-manager-list { display: flex; flex-direction: column; gap: 12px; }
        .group-manager-card { background: rgba(20,20,20,0.92); border: 1px solid rgba(242,202,80,0.12); border-radius: 18px; padding: 16px; box-shadow: 0 12px 26px rgba(0,0,0,0.18); }
        .group-manager-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .group-manager-name { font-size: 16px; font-weight: 800; color: #f5f5f5; }
        .group-manager-meta { margin-top: 4px; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #8e8e8e; }
        .group-manager-note { margin-top: 10px; font-size: 12px; color: #b7b7b7; }
        .group-manager-status { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border-radius: 999px; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; border: 1px solid rgba(242,202,80,0.18); color: #f2ca50; background: rgba(242,202,80,0.08); }
        .group-manager-status.paused { color: #ffb85c; border-color: rgba(255,184,92,0.24); background: rgba(255,184,92,0.08); }
        .group-manager-actions { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 8px; }

        .tf-groups-layout{display:block}
        .tf-groups-main{min-width:0}
        .tf-groups-toolbar{display:flex;align-items:center;justify-content:flex-start;gap:0;padding:0;margin:0 0 18px 0;border-bottom:1px solid rgba(255,255,255,0.06);flex-direction:row;flex-wrap:wrap}
        .tf-groups-toolbar-copy{display:grid;gap:4px}
        .tf-groups-eyebrow{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:rgba(242,202,80,.72);font-weight:700}
        .tf-groups-toolbar-copy h3{margin:0;font-size:20px;line-height:1.05;color:#f5f5f5}
        .tf-groups-toolbar-copy p{margin:0;color:#989ca6;font-size:13px;max-width:680px}
        .group-top-tabs{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-start}
        .group-top-tab,.group-pill-btn,.group-ghost-btn{border:none;border-radius:999px;padding:8px 12px;font-weight:700;font-size:11px;letter-spacing:.08em;text-transform:uppercase}
        .group-top-tab{background:rgba(255,255,255,0.04);color:#c6cad3}
        .group-top-tab.active,.group-pill-btn{background:#f2ca50;color:#111827}
        .group-ghost-btn{background:transparent;color:#c6cad3;border:1px solid rgba(255,255,255,0.10)}
        .group-ghost-btn:disabled{opacity:.5;cursor:default}
        .tf-groups-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
        .tf-groups-stat{background:rgba(255,255,255,0.025);border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:12px 14px}
        .tf-groups-stat strong{display:block;color:#f5f5f5;font-size:18px;line-height:1.1}
        .tf-groups-stat span{display:block;color:#8f95a1;font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin-top:4px}
        .tf-groups-stat b{display:block;color:#d4d7dd;font-size:12px;font-weight:500;margin-top:8px}
        .tf-groups-list{display:grid;gap:10px}
        .tf-groups-empty{padding:18px 0;color:#959aa5;font-size:13px}
        .group-card,.group-row-card{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;background:rgba(255,255,255,0.025);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:14px 16px;position:relative;overflow:hidden}
        .group-card::before,.group-row-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2px;background:var(--group-accent,#f2ca50);opacity:.85}
        .group-card-top{display:grid;gap:6px;min-width:0}
        .group-card-kicker{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#9da3b0;font-weight:700}
        .group-card h3,.group-row-card h3{margin:0;color:#f4f4f5;font-size:20px;line-height:1.05}
        .group-card p,.group-row-card p{margin:0;color:#bcc1ca;line-height:1.45;font-size:14px;max-width:60ch}
        .group-card-meta{display:flex;flex-wrap:wrap;gap:10px;color:#9098ab;font-size:12px}
        .group-card-badge{justify-self:end;font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#f2ca50;font-weight:700}
        .group-card-actions,.group-row-actions{display:flex;gap:8px;justify-content:flex-end;align-items:center}
        .tf-groups-form-shell{background:rgba(255,255,255,0.022);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:16px}
        .tf-groups-form-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding-bottom:12px;margin-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.05)}
        .tf-groups-form-head h3{margin:0;color:#f5f5f5;font-size:20px}
        .tf-groups-form-head p{margin:4px 0 0;color:#989ca6;font-size:13px;max-width:520px}
        .tf-group-create-form{display:grid;gap:10px}
        .tf-group-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .tf-field{display:grid;gap:6px}
        .tf-field span,.tf-toggle{color:#b6bbc6;font-size:12px}
        .tf-field input,.tf-field select,.tf-field textarea{width:100%;border-radius:12px;border:1px solid rgba(255,255,255,0.09);background:rgba(0,0,0,0.22);color:#fff;padding:11px 12px}
        .tf-toggle{display:flex;gap:8px;align-items:center}
        .tf-form-message{min-height:18px;color:#f2ca50;font-size:12px}
        .tf-inline-banner{margin:0 0 12px;background:rgba(242,202,80,.10);border:1px solid rgba(242,202,80,.22);color:#f6e6ae;padding:10px 12px;border-radius:12px;font-size:12px}
        .tf-inline-banner-error{background:rgba(248,113,113,.10);border-color:rgba(248,113,113,.22);color:#fecaca}
        @media (max-width: 960px){.tf-groups-strip{grid-template-columns:repeat(2,minmax(0,1fr))}.tf-groups-form-head,.group-row-card{grid-template-columns:1fr;display:grid}.group-card{grid-template-columns:1fr;gap:14px}.group-card-side{align-items:flex-start}.group-card-side .group-card-badge,.group-card-side .group-card-meta,.group-card-side .group-card-actions{justify-content:flex-start;text-align:left}.group-card-actions,.group-row-actions{justify-content:flex-start}.group-card-badge{justify-self:start}.tf-groups-toolbar{display:flex;flex-wrap:wrap;justify-content:flex-start;align-items:center;gap:0}}
        @media (max-width: 760px){.tf-group-grid-2,.tf-groups-strip{grid-template-columns:1fr}.tf-groups-toolbar{display:flex;flex-wrap:wrap;justify-content:flex-start;align-items:center;gap:0}}
        .group-action-btn, .group-create-btn, .group-wizard-close { border: 1px solid rgba(242,202,80,0.18); background: rgba(242,202,80,0.08); color: #f2ca50; border-radius: 999px; padding: 9px 14px; font-size: 10px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; cursor: pointer; font-family: "Montserrat", sans-serif; }
        .group-action-btn.subtle { background: rgba(255,255,255,0.04); color: #cfcfcf; border-color: rgba(255,255,255,0.1); }
        .group-action-btn.danger { background: rgba(255,91,91,0.08); color: #ff8d8d; border-color: rgba(255,91,91,0.18); }
        .group-create-btn:hover, .group-action-btn:hover, .group-wizard-close:hover { filter: brightness(1.08); }
        .group-wizard { margin-top: 2px; background: #141414; border: 1px solid rgba(242,202,80,0.14); border-radius: 20px; padding: 18px; box-shadow: 0 18px 40px rgba(0,0,0,0.28); }
        .group-wizard[hidden] { display: none !important; }
        .group-wizard-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .group-wizard-steps { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .group-wizard-step { padding: 6px 10px; border-radius: 999px; background: rgba(255,255,255,0.05); color: #8f8f8f; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .group-wizard-step.active { background: rgba(242,202,80,0.12); color: #f2ca50; border: 1px solid rgba(242,202,80,0.2); }
        .group-wizard-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .group-wizard-field { display: flex; flex-direction: column; gap: 7px; }
        .group-wizard-field.full { grid-column: 1 / -1; }
        .group-wizard-field span { font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #8f8f8f; }
        .group-wizard-field input, .group-wizard-field textarea { width: 100%; border-radius: 14px; border: 1px solid rgba(255,255,255,0.08); background: #101010; color: #f4f4f4; padding: 12px 13px; font: 600 12px "Montserrat", sans-serif; outline: none; }
        .group-wizard-field input:focus, .group-wizard-field textarea:focus { border-color: rgba(242,202,80,0.36); box-shadow: 0 0 0 3px rgba(242,202,80,0.08); }
        .group-wizard-actions { margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px; }
        @media (max-width: 760px) { .group-manager-toolbar, .group-wizard-head { flex-direction: column; } .group-wizard-grid { grid-template-columns: 1fr; } }
        .group-top-tabs { display: flex; gap: 8px; margin: 8px 0 12px; }
        .group-top-tab { padding: 8px 12px; border-radius: 999px; border: 1px solid rgba(242,202,80,0.14); background: rgba(255,255,255,0.03); color: #9a9a9a; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; font-family: "Montserrat", sans-serif; cursor: pointer; }
        .group-top-tab.active { color: #F2CA50; border-color: rgba(242,202,80,0.28); background: rgba(242,202,80,0.08); }
        .group-joined-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .group-joined-switcher { flex: 1; background: #141414; border: 1px solid #232323; color: #e8e8e8; border-radius: 12px; padding: 10px 12px; font-family: "Montserrat", sans-serif; font-size: 12px; }
        .group-joined-count { font-size: 11px; color: #8a8a8a; white-space: nowrap; }

        .group-feed-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 32px; padding: 0 12px; border-radius: 999px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.04); color: #f3f3f3; font-size: 10px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; cursor: pointer; transition: all 0.18s ease; }
        .group-feed-btn:hover { border-color: rgba(242,202,80,0.38); color: #f2ca50; background: rgba(242,202,80,0.08); }
        .group-feed-btn.primary { background: #f2ca50; color: #111; border-color: #f2ca50; }
        .group-feed-btn.primary:hover { background: #f6d774; border-color: #f6d774; color: #111; }
        .group-feed-tags { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px; }
        .group-feed-tag { display: inline-flex; align-items: center; padding: 5px 9px; border-radius: 999px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); color: #cfcfcf; font-size: 10px; font-weight: 700; letter-spacing: 0.06em; }
        @media (max-width: 1100px) { .group-feed-title { font-size: 18px; } }
        @media (max-width: 720px) { .group-feed-card { padding: 14px; border-radius: 16px; } .group-feed-top { flex-direction: column; } .group-feed-title { font-size: 17px; } .group-joined-pill, .group-feed-btn { width: 100%; } }
        .right-panel-card { background: rgba(18,18,18,0.76); border: 1px solid rgba(255,255,255,0.06); border-radius: 18px; padding: 18px; }
        .right-panel-title { font-size: 11px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: #f2ca50; margin-bottom: 14px; }
        .right-panel-list { display: flex; flex-direction: column; gap: 10px; }
        .right-rail-group-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .right-rail-group-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .right-rail-group-main { min-width: 0; }
        .right-rail-group-name { font-size: 14px; font-weight: 700; color: #f3f3f3; line-height: 1.2; }
        .right-rail-group-meta { margin-top: 4px; font-size: 12px; color: #989898; }
        .right-rail-group-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 30px; padding: 0 12px; border-radius: 999px; background: #f2ca50; border: 1px solid #f2ca50; color: #111; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .right-rail-status { display: inline-flex; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 999px; border: 1px solid rgba(242,202,80,0.28); background: rgba(242,202,80,0.12); color: #f2ca50; font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; white-space: nowrap; }

        /* ── Middle feed ── */
        .tf-feed-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            padding: 32px 24px 32px 32px;
            max-width: 640px;
            scrollbar-width: thin;
            scrollbar-color: #1a1a1a transparent;
        }
        .dashboard-container.profile-mode .tf-feed-col,
        .dashboard-container.profile-mode .floor-section {
            max-width: 960px;
            margin-left: 0;
            margin-right: auto;
            padding-right: 32px;
        }
        .dashboard-container.profile-mode #floor-profile-panel {
            margin-left: 0;
            margin-right: auto;
        }
        .dashboard-container.profile-mode .profile-hero,
        .dashboard-container.profile-mode .profile-highlights,
        .dashboard-container.profile-mode .profile-social-strip,
        .dashboard-container.profile-mode .profile-profile-actions {
            width: 100%;
            max-width: 820px;
            margin-left: 0;
            margin-right: auto;
        }
        .dashboard-container.profile-mode .profile-tabs {
            justify-content: flex-start;
            text-align: left;
        }
        .dashboard-container.profile-mode .profile-hero {
            flex-direction: row;
            align-items: center;
            justify-content: center;
            padding-left: 72px;
        }
        .dashboard-container.profile-mode .profile-avatar-wrap {
            margin-left: auto;
        }
        .dashboard-container.profile-mode .profile-info {
            flex: 0 1 560px;
            margin-left: 0;
            margin-right: auto;
            text-align: left;
        }
        .dashboard-container.profile-mode .profile-highlights {
            justify-content: center;
        }
        .dashboard-container.profile-mode .profile-profile-actions {
            justify-content: center;
            padding-left: 72px;
        }
        .dashboard-container.profile-mode .profile-stats-row,
        .dashboard-container.profile-mode .profile-social-strip,
        .dashboard-container.profile-mode .profile-tab-list {
            justify-content: flex-start;
        }
        .tf-feed-col::-webkit-scrollbar { width: 4px; }
        .tf-feed-col::-webkit-scrollbar-thumb { background: #1a1a1a; border-radius: 4px; }

        /* Stories */
        .stories-row {
            display: flex;
            gap: 16px;
            padding-bottom: 24px;
            border-bottom: 1px solid #1a1a1a;
            margin-bottom: 32px;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .stories-row::-webkit-scrollbar { display: none; }
        .story-item { display: flex; flex-direction: column; align-items: center; gap: 6px; cursor: pointer; flex-shrink: 0; }
        .story-ring {
            width: 64px; height: 64px;
            border-radius: 50%;
            padding: 2.5px;
            transition: transform 0.2s ease;
            position: relative;
        }
        .story-ring.unseen { background: linear-gradient(135deg, #F2CA50, #FFDB70); }
        .story-ring.seen { background: #2a2a2a; }
        .story-ring.add-story { background: #1a1a1a; }
        .story-ring:hover { transform: scale(1.06); }
        .story-avatar {
            width: 100%; height: 100%;
            border-radius: 50%;
            background: #151515;
            border: 2px solid #0E0E0E;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 700; color: #F2CA50;
        }
        .story-add-btn {
            position: absolute; bottom: 0; right: 0;
            width: 20px; height: 20px;
            background: #F2CA50;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #0E0E0E;
        }
        .story-add-btn svg { color: #0E0E0E; }
        .story-timer {
            position: absolute; bottom: -2px; left: 50%;
            transform: translateX(-50%);
            font-size: 7px; font-weight: 700; color: #F2CA50;
            background: rgba(14,14,14,0.9);
            padding: 1px 4px; border-radius: 4px;
            white-space: nowrap; letter-spacing: 0.04em;
        }
        .story-name {
            font-size: 10px; font-weight: 600; color: #888;
            max-width: 64px; text-align: center;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }

        /* Post cards */
        .post-card {
            background: rgba(18,18,18,0.8);
            border: 1px solid #1a1a1a;
            border-radius: 12px;
            margin-bottom: 24px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .post-card:hover { border-color: #252525; }
        .post-header { display: flex; align-items: center; gap: 12px; padding: 16px 18px 14px; }
        .post-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700; color: #0E0E0E;
            flex-shrink: 0; cursor: pointer;
        }
        .post-meta { flex: 1; }
        .post-author { font-size: 13px; font-weight: 700; color: #e0e0e0; cursor: pointer; }
        .post-author:hover { color: #F2CA50; }
        .post-subtitle { font-size: 10px; font-weight: 500; color: #555; letter-spacing: 0.04em; margin-top: 1px; }
        .post-time { font-size: 10px; font-weight: 500; color: #444; }
        .post-more-btn { color: #555; cursor: pointer; padding: 4px; border-radius: 4px; transition: color 0.2s; background: none; border: none; }
        .post-more-btn:hover { color: #aaa; }

        /* Trade card inside post */
        .post-trade-card { margin: 0 18px; border-radius: 10px; overflow: hidden; border: 1px solid #1e1e1e; }
        .trade-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px 12px;
            background: rgba(14,14,14,0.6);
            border-bottom: 1px solid #1e1e1e;
        }
        .trade-symbol { font-size: 18px; font-weight: 800; letter-spacing: 0.04em; color: #f4f4f4; }
        .trade-direction { display: flex; align-items: center; gap: 6px; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
        .trade-direction.long { color: #4ade80; }
        .trade-direction.short { color: #f87171; }
        .trade-direction-dot { width: 7px; height: 7px; border-radius: 50%; }
        .long .trade-direction-dot { background: #4ade80; }
        .short .trade-direction-dot { background: #f87171; }
        .trade-pnl-badge { font-size: 14px; font-weight: 800; padding: 4px 12px; border-radius: 6px; }
        .trade-pnl-badge.win { color: #4ade80; background: rgba(74,222,128,0.1); }
        .trade-pnl-badge.loss { color: #f87171; background: rgba(248,113,113,0.1); }
        .trade-stats-row { display: grid; grid-template-columns: repeat(4, 1fr); border-bottom: 1px solid #1a1a1a; }
        .trade-stat { padding: 12px 14px; border-right: 1px solid #1a1a1a; background: rgba(12,12,12,0.4); }
        .trade-stat:last-child { border-right: none; }
        .trade-stat-label { font-size: 8px; font-weight: 700; letter-spacing: 0.1em; color: #444; text-transform: uppercase; margin-bottom: 4px; }
        .trade-stat-value { font-size: 13px; font-weight: 600; color: #ccc; }
        .trade-chart-area { height: 120px; background: rgba(10,10,10,0.6); position: relative; overflow: hidden; }
        .trade-chart-area canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .trade-chart-label { position: absolute; bottom: 8px; right: 10px; font-size: 8px; font-weight: 600; color: #333; letter-spacing: 0.08em; text-transform: uppercase; }

        .post-body { padding: 14px 18px 0; }
        .post-caption { font-size: 13px; font-weight: 500; color: #aaa; line-height: 1.7; }
        .post-caption strong { color: #e0e0e0; font-weight: 600; }
        .post-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .post-tag { font-size: 11px; font-weight: 600; color: #F2CA50; cursor: pointer; opacity: 0.8; }
        .post-tag:hover { opacity: 1; }
        .post-actions { display: flex; align-items: center; gap: 20px; padding: 14px 18px 16px; }
        .post-action-btn {
            display: flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 600; color: #555;
            cursor: pointer; transition: color 0.2s;
            background: none; border: none;
            font-family: "Montserrat", sans-serif; letter-spacing: 0.04em; padding: 0;
        }
        .post-action-btn:hover { color: #ccc; }
        .post-action-btn.liked { color: #f87171; }
        .post-action-btn.liked svg { fill: #f87171; }
        .post-action-btn.bookmarked { color: #F2CA50; }
        .post-action-spacer { flex: 1; }

        /* ── Right sidebar ── */
        .tf-right-col {
            width: 320px; flex-shrink: 0;
            padding: 32px 32px 32px 24px;
            overflow-y: auto; scrollbar-width: none;
        }
        .dashboard-container.profile-mode .tf-right-col { display: none; }
        .tf-right-col::-webkit-scrollbar { display: none; }
        .right-user-card { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #1a1a1a; }
        .dashboard-container.profile-mode .right-user-card { display: none; }
        .right-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #F2CA50, #FFDB70); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; color: #0E0E0E; flex-shrink: 0; }
        .right-user-info { flex: 1; }
        .right-user-name { font-size: 13px; font-weight: 700; color: #e0e0e0; }
        .right-user-email { font-size: 11px; font-weight: 500; color: #555; margin-top: 2px; }
        .right-switch-btn { font-size: 10px; font-weight: 700; color: #F2CA50; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; background: none; border: none; font-family: "Montserrat", sans-serif; padding: 0; }
        .right-switch-btn:hover { color: #FFDB70; }
        .right-section-label { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #444; text-transform: uppercase; margin-bottom: 16px; }
        .suggested-list { display: flex; flex-direction: column; gap: 4px; margin-bottom: 32px; }
        .suggested-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
        .suggested-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #0E0E0E; flex-shrink: 0; }
        .suggested-info { flex: 1; min-width: 0; }
        .suggested-name { font-size: 12px; font-weight: 700; color: #ccc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .suggested-reason { font-size: 10px; font-weight: 500; color: #444; margin-top: 1px; }
        .follow-btn { font-size: 10px; font-weight: 700; color: #F2CA50; letter-spacing: 0.06em; text-transform: uppercase; cursor: pointer; background: none; border: none; font-family: "Montserrat", sans-serif; padding: 0; flex-shrink: 0; transition: color 0.2s; }
        .follow-btn:hover { color: #FFDB70; }
        .follow-btn.following { color: #555; }
        .right-footer-links { display: flex; flex-wrap: wrap; gap: 6px 10px; margin-top: 8px; }
        .right-footer-link { font-size: 9px; font-weight: 500; color: #333; letter-spacing: 0.04em; cursor: pointer; text-transform: uppercase; transition: color 0.2s; }
        .right-footer-link:hover { color: #555; }

        /* ── Story viewer overlay ── */
        .story-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.92); z-index: 1000; align-items: center; justify-content: center; }
        .story-overlay.active { display: flex; }
        .story-viewer { width: 360px; max-height: 85vh; background: #111; border-radius: 16px; overflow: hidden; position: relative; box-shadow: 0 24px 64px rgba(0,0,0,0.6); }
        .story-progress-bar { display: flex; gap: 3px; padding: 12px 12px 6px; position: absolute; top: 0; left: 0; right: 0; z-index: 2; }
        .story-progress-segment { flex: 1; height: 2px; background: rgba(255,255,255,0.25); border-radius: 2px; overflow: hidden; }
        .story-progress-fill { height: 100%; background: #fff; width: 0%; }
        .story-progress-fill.done { width: 100%; }
        .story-progress-fill.active { width: 100%; transition: width 5s linear; }
        .story-close-btn { position: absolute; top: 12px; right: 12px; z-index: 3; color: rgba(255,255,255,0.7); cursor: pointer; background: none; border: none; padding: 4px; }
        .story-content {
            width: 100%; aspect-ratio: 9/16;
            background: linear-gradient(160deg, #1a1a1a, #111);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 64px 24px 24px;
            position: relative;
        }
        .story-user-row { position: absolute; top: 36px; left: 14px; display: flex; align-items: center; gap: 8px; }
        .story-user-mini-avatar { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #F2CA50, #FFDB70); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #0E0E0E; }
        .story-user-mini-name { font-size: 12px; font-weight: 700; color: #fff; }
        .story-user-mini-time { font-size: 9px; color: rgba(255,255,255,0.5); margin-top: 1px; }
        .story-trade-symbol { font-size: 56px; font-weight: 800; letter-spacing: -0.02em; color: #F2CA50; filter: drop-shadow(0 0 24px rgba(242,202,80,0.3)); margin-bottom: 8px; }
        .story-trade-pnl { font-size: 28px; font-weight: 700; margin-bottom: 16px; }
        .story-trade-pnl.win { color: #4ade80; }
        .story-trade-pnl.loss { color: #f87171; }
        .story-trade-detail { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.4); letter-spacing: 0.08em; text-transform: uppercase; }

        /* ── Create modal ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 500; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .create-modal { background: #151515; border: 1px solid #1e1e1e; border-radius: 16px; width: 540px; max-height: 85vh; overflow-y: auto; box-shadow: 0 24px 64px rgba(0,0,0,0.5); }
        .create-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #1e1e1e; }
        .create-modal-title { font-size: 14px; font-weight: 700; color: #e0e0e0; letter-spacing: 0.04em; text-transform: uppercase; }
        .modal-close-btn { color: #555; cursor: pointer; background: none; border: none; padding: 4px; transition: color 0.2s; }
        .modal-close-btn:hover { color: #aaa; }
        .create-modal-body { padding: 24px; }
        .create-tabs { display: flex; gap: 4px; margin-bottom: 20px; background: rgba(255,255,255,0.03); border-radius: 8px; padding: 4px; }
        .create-tab { flex: 1; padding: 8px; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #555; border-radius: 6px; cursor: pointer; transition: all 0.2s; background: none; border: none; font-family: "Montserrat", sans-serif; text-align: center; }
        .create-tab.active { background: rgba(242,202,80,0.1); color: #F2CA50; }
        .create-form-field { margin-bottom: 16px; }
        .create-form-label { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #555; text-transform: uppercase; margin-bottom: 8px; display: block; }
        .create-form-input, .create-form-textarea, .create-form-select { width: 100%; background: #0E0E0E; border: 1px solid #1e1e1e; border-radius: 8px; padding: 11px 14px; font-size: 13px; font-weight: 500; color: #ccc; font-family: "Montserrat", sans-serif; transition: border-color 0.2s; }
        .create-form-input:focus, .create-form-textarea:focus, .create-form-select:focus { outline: none; border-color: rgba(242,202,80,0.3); }
        .create-form-textarea { resize: vertical; min-height: 100px; }
        .create-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .create-submit-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #F2CA50, #FFDB70); color: #0E0E0E; font-size: 11px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; border: none; border-radius: 8px; cursor: pointer; font-family: "Montserrat", sans-serif; transition: all 0.2s; margin-top: 8px; }
        .create-submit-btn:hover { box-shadow: 0 6px 20px rgba(242,202,80,0.35); transform: translateY(-1px); }

        /* ── DM panel ── */
        .dm-panel { position: fixed; bottom: 0; right: 80px; width: 300px; background: #141414; border: 1px solid #1e1e1e; border-bottom: none; border-radius: 12px 12px 0 0; z-index: 200; transform: translateY(calc(100% - 48px)); transition: transform 0.3s ease; box-shadow: 0 -8px 32px rgba(0,0,0,0.4); }
        .dm-panel.open { transform: translateY(0); }
        .dm-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; cursor: pointer; border-bottom: 1px solid transparent; transition: border-color 0.2s; }
        .dm-panel.open .dm-panel-header { border-bottom-color: #1e1e1e; }
        .dm-panel-title { font-size: 12px; font-weight: 700; color: #ccc; display: flex; align-items: center; gap: 8px; }
        .dm-unread-dot { width: 8px; height: 8px; background: #F2CA50; border-radius: 50%; animation: pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.6;transform:scale(0.95)} }
        .dm-list { max-height: 280px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #1a1a1a transparent; }
        .dm-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; cursor: pointer; transition: background 0.15s; border-bottom: 1px solid #0f0f0f; }
        .dm-item:hover { background: rgba(255,255,255,0.02); }
        .dm-item-avatar { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #0E0E0E; flex-shrink: 0; }
        .dm-item-info { flex: 1; min-width: 0; }
        .dm-item-name { font-size: 12px; font-weight: 600; color: #666; }
        .dm-item-preview { font-size: 10px; color: #444; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
        .dm-item-time { font-size: 9px; color: #333; flex-shrink: 0; }
        .dm-item.unread .dm-item-name { color: #e0e0e0; }
        .dm-item.unread .dm-item-preview { color: #888; }
        .dm-compose-row { padding: 12px 16px; border-top: 1px solid #1e1e1e; display: flex; gap: 8px; }
        .dm-input { flex: 1; background: rgba(255,255,255,0.04); border: 1px solid #1e1e1e; border-radius: 16px; padding: 8px 12px; font-size: 12px; font-weight: 500; color: #aaa; font-family: "Montserrat", sans-serif; }
        .dm-input:focus { outline: none; border-color: #333; }

        /* ── Search overlay ── */
        .search-overlay { display: none; position: fixed; top: 0; left: 72px; width: 340px; height: 100vh; background: #0f0f0f; border-right: 1px solid #1a1a1a; z-index: 300; padding: 24px 20px; overflow-y: auto; }
        .search-overlay.active { display: block; }
        .search-input-wrapper { position: relative; margin-bottom: 24px; }
        .search-input-wrapper svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #444; }
        .search-input { width: 100%; background: rgba(255,255,255,0.05); border: 1px solid #1e1e1e; border-radius: 10px; padding: 11px 14px 11px 38px; font-size: 13px; font-weight: 500; color: #ccc; font-family: "Montserrat", sans-serif; }
        .search-input:focus { outline: none; border-color: rgba(242,202,80,0.3); }
        .search-section-label { font-size: 9px; font-weight: 700; letter-spacing: 0.1em; color: #333; text-transform: uppercase; margin-bottom: 12px; }
        .search-result-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; cursor: pointer; border-bottom: 1px solid #0f0f0f; }
        .search-result-item:hover .search-result-name { color: #F2CA50; }
        .search-result-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #0E0E0E; flex-shrink: 0; }
        .search-result-name { font-size: 13px; font-weight: 600; color: #ccc; transition: color 0.15s; }
        .search-result-sub { font-size: 10px; color: #444; margin-top: 2px; }

        /* ── Responsive ── */
        @media (max-width: 1100px) { .tf-right-col { display: none; } .tf-feed-col { max-width: 100%; padding-right: 32px; } }
        /* ── Left links column ── */
		.tf-left-links-col {
		    width: 210px;
		    flex-shrink: 0;
		    padding: 0 0 32px 0;
		    display: flex;
		    flex-direction: column;
		    gap: 2px;
		    position: sticky;
		    top: 64px;
		    height: calc(100vh - 64px);
		    justify-content: center;
		    align-self: flex-start;
		    align-items: left;
		}
		.tf-left-links-col:hover {
		    align-items: flex-start;
		}
		.tf-left-link {
		    display: flex;
		    align-items: center;
		    gap: 12px;
		    padding: 11px 13px;
		    border-radius: 10px;
		    color: rgba(224,224,224,0.67);
		    text-decoration: none;
		    cursor: pointer;
		    overflow: hidden;
		    white-space: nowrap;
		    width: 46px;
		    transition: color 0.2s ease, background 0.2s ease, width 0.25s cubic-bezier(0.16,1,0.3,1), box-shadow 0.2s ease;
		}
		.tf-left-link svg { flex-shrink: 0; }
		.tf-left-link span {
		    font-size: 11px;
		    font-weight: 700;
		    letter-spacing: 0.06em;
		    text-transform: uppercase;
		    font-family: "Montserrat", sans-serif;
		    opacity: 0;
		    transition: opacity 0.15s ease 0.05s;
		}
		/* Hovering the column expands ALL links */
		.tf-left-links-col:hover .tf-left-link {
		    color: #e0e0e0;
		    background: rgba(255,255,255,0.00);
		    width: 228px;
		}
		.tf-left-links-col:hover .tf-left-link span {
		    opacity: 1;
		}
		.tf-left-link.active {
		    color: #F2CA50 !important;
		    background: rgba(242,202,80,0.10) !important;
		    box-shadow: inset 0 0 0 1px rgba(242,202,80,0.16);
		}
		/* Directly hovered item gets gold accent */
		.tf-left-link:hover {
		    color: #F2CA50 !important;
		    background: rgba(242,202,80,0.06) !important;
		}


        @media (max-width: 920px) { .tf-left-links-col { display: none; } }
        @media (max-width: 768px) {
            .tf-sidebar { width: 100%; flex-direction: row; height: 56px; padding: 0 16px; border-right: none; border-top: 1px solid #1a1a1a; position: fixed; bottom: 0; left: 0; z-index: 100; }
            .tf-brand { display: none; }
            .tf-nav { flex-direction: row; padding: 0; justify-content: space-around; width: 100%; }
            .tf-nav-item { padding: 8px 0; }
            .tf-nav-label { display: none; }
            .tf-user-avatar { margin: 0; width: 28px; height: 28px; font-size: 11px; }
            .dashboard-container { flex-direction: column; }
            .tf-feed-col { padding: 16px 16px 72px; max-width: 100%; }
            .dm-panel { right: 16px; }
        }
    </style>
</head>
<body>


    <div class="dashboard-background"></div>

    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <h1>2RICH CAPITAL</h1>
                <span class="nav-tagline">INSTITUTIONAL GRADE TRADING</span>
            </div>
            <div class="nav-right">
                <div class="tf-topbar-avatar" onclick="window.location.href='/account'" title="Account">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <a href="../auth/logout.php" class="logout-btn">LOGOUT</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="dashboard-container">

        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item" onclick="window.location.href='/dashboard'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Dashboard</span>
                </li>
                <li class="menu-item" onclick="window.location.href='/journal'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span>Trading Journal</span>
                </li>
                <li class="menu-item active" onclick="window.location.href='/trading-floor'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <span>Trading Floor</span>
                </li>
                <li class="menu-item" onclick="window.location.href='/market-data'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>Market Data</span>
                </li>
                <li class="menu-item" onclick="window.location.href='/account'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Account</span>
                </li>
            </ul>
        </aside>

        <aside class="tf-left-links-col">
            <a class="tf-left-link active" data-floor-nav="home" onclick="openFloorSection('home'); return false;" title="Home">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path></svg>
                <span>Home</span>
            </a>
            <a class="tf-left-link" onclick="openCreateModal('post')" title="Create">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                <span>Create</span>
            </a>
            <a class="tf-left-link" data-floor-nav="groups" id="groupsNavLink" href="javascript:void(0)" onclick="openFloorSection('groups'); return false;" title="Groups">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>GROUPS</span>
            </a>
            <a class="tf-left-link" title="Notifications"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span>Notifications</span></a>
            <a class="tf-left-link" onclick="document.getElementById('dmPanel').classList.add('open')" title="Messages"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></a>
            <a class="tf-left-link" title="Saved"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg><span>Saved</span></a>
            <a class="tf-left-link" data-floor-nav="profile" onclick="openFloorSection('profile')" title="Profile"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Profile</span></a>
        </aside>
        <!-- Search Overlay -->
        <div class="search-overlay" id="searchOverlay">
            <div class="search-input-wrapper">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" class="search-input" placeholder="Search traders, symbols..." id="searchInput">
            </div>
            <p class="search-section-label">All Traders</p>
            <div id="searchResults">
                <?php foreach ($trader_results as $t):
                    $profile_user_id = (int) ($t['user_id'] ?? 0);
                    if ($profile_user_id <= 0) { continue; }
                    $display_name = trim((string) ($t['display_name'] ?? 'Trader'));
                    $avatar_initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $display_name), 0, 2));
                    if ($avatar_initials === '') { $avatar_initials = 'TR'; }
                    $market = trim((string) ($t['primary_market'] ?? ''));
                    $style = trim((string) ($t['trading_style'] ?? ''));
                    $reason_parts = array_values(array_filter([$market, $style]));
                    $reason = !empty($reason_parts) ? implode(' · ', $reason_parts) : 'View public trader profile';
                    $profile_href = 'https://app.2rich.capital/trading-floor/?user_id=' . $profile_user_id;
                    $color_seed = substr(md5((string) $profile_user_id), 0, 6);
                ?>
                <a class="search-result-item" href="<?= htmlspecialchars($profile_href) ?>" style="text-decoration:none; color:inherit;">
                    <div class="search-result-avatar" style="background:#<?= htmlspecialchars($color_seed) ?>"><?= htmlspecialchars($avatar_initials) ?></div>
                    <div>
                        <div class="search-result-name"><?= htmlspecialchars($display_name) ?></div>
                        <div class="search-result-sub"><?= htmlspecialchars($reason) ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Center Workspace Panels -->
        <section class="floor-section" id="floor-profile-panel" hidden>
                <div class="profile-hero">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar-large"><?php echo strtoupper(substr($profile_display_name ?: $user_name,0,1)); ?></div>
                        <div class="profile-avatar-edit">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="profile-info">
                        <div class="profile-handle-row">
                            <div class="profile-handle">@<?php echo htmlspecialchars($profile_handle); ?></div>
                            <span class="profile-member-badge" title="2RICH VERIFIED" aria-label="2RICH VERIFIED">
                                <span class="profile-member-badge-icon">✓</span>
                                <span class="profile-member-badge-text">2RICH VERIFIED</span>
                            </span>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($profile_display_name); ?></div>
                        <div class="profile-stats-row profile-stats-inline">
                            <div class="profile-stat"><div class="profile-stat-value"><?php echo (int)$total_trades; ?></div><div class="profile-stat-label">Trades</div></div>
                            <div class="profile-stat"><div class="profile-stat-value"><?php echo htmlspecialchars((string)$win_rate); ?>%</div><div class="profile-stat-label">Win Rate</div></div>
                            <button type="button" class="profile-stat" data-social-list="followers" data-user-id="<?php echo (int)$view_user_id; ?>"><div class="profile-stat-value" data-followers-count><?php echo htmlspecialchars((string)$profile_followers_count); ?></div><div class="profile-stat-label">Followers</div></button>
                            <button type="button" class="profile-stat" data-social-list="following" data-user-id="<?php echo (int)$view_user_id; ?>"><div class="profile-stat-value" data-following-count data-own-following-count="<?php echo $is_own_profile ? '1' : '0'; ?>"><?php echo htmlspecialchars((string)$profile_following_count); ?></div><div class="profile-stat-label">Following</div></button>
                        </div>
                        <div class="profile-bio-line" id="bioPreview">
                            <span class="profile-bio-copy"><?php echo htmlspecialchars($profile_bio !== '' ? $profile_bio : 'Trader. No bio yet — add one below.'); ?></span>
                            <?php if ($profile_primary_market !== '' || $profile_trading_style !== ''): ?>
                            <span class="profile-bio-separator">•</span>
                            <span class="profile-identity-badges">
                                <?php if ($profile_primary_market !== ''): ?><span class="profile-identity-badge"><?php echo htmlspecialchars($profile_primary_market); ?></span><?php endif; ?>
                                <?php if ($profile_trading_style !== ''): ?><span class="profile-identity-badge profile-identity-badge--style"><?php echo htmlspecialchars($profile_trading_style); ?></span><?php endif; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="profile-profile-actions">
                    <?php if ($is_own_profile): ?>
                        <a class="profile-action-btn secondary" href="https://app.2rich.capital/account/" style="text-decoration:none;">Edit Profile</a>
                        <button class="profile-action-btn" type="button" data-profile-action="view-archive">View Archive</button>
                    <?php else: ?>
                        <button class="profile-action-btn secondary<?php echo $profile_follow_state ? ' following' : ''; ?>" type="button" data-profile-action="follow" data-following="<?php echo $profile_follow_state ? '1' : '0'; ?>"><?php echo $profile_follow_state ? 'Following' : 'Follow'; ?></button>
                        <button class="profile-action-btn" type="button" data-profile-action="message" data-profile-name="<?php echo htmlspecialchars($profile_display_name, ENT_QUOTES); ?>">Message</button>
                    <?php endif; ?>
                </div>

                <div class="profile-highlights">
                    <div class="profile-highlight"><div class="profile-highlight-ring"><span>↗</span></div><div class="profile-highlight-label">Trades</div></div>
                    <div class="profile-highlight"><div class="profile-highlight-ring"><span>⚠</span></div><div class="profile-highlight-label">Beware</div></div>
                    <div class="profile-highlight"><div class="profile-highlight-ring"><span>“</span></div><div class="profile-highlight-label">Quotes</div></div>
                    <div class="profile-highlight"><div class="profile-highlight-ring"><span>$</span></div><div class="profile-highlight-label">Funded</div></div>
                    <div class="profile-highlight"><div class="profile-highlight-ring"><span>✦</span></div><div class="profile-highlight-label">You</div></div>
                    <div class="profile-highlight"><div class="profile-highlight-ring"><span>+</span></div><div class="profile-highlight-label">New</div></div>
                </div>

                <div class="profile-tabs">
                    <div class="profile-tab-list">
                        <button type="button" class="profile-tab active" data-profile-tab="posts">Posts</button>
                        <button type="button" class="profile-tab" data-profile-tab="trades">Trades</button>
                        <?php if ($is_own_profile): ?>
                            <button type="button" class="profile-tab" data-profile-tab="saved">Saved</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-tab-panel active" data-profile-panel="posts">
                    <div class="profile-feed-grid">
                        <?php if (!$is_own_profile): ?>
                            <div class="profile-activity-empty" style="grid-column: 1 / -1;">Public posts from this trader will appear here.</div>
                        <?php else: ?>
                            <div class="profile-post-thumb"><span class="post-meta-badge">Play</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#1a1c22,#0f1116 45%,#2b3038);display:grid;place-items:center;color:#fff;font-size:28px;font-weight:800;">2</div></div>
                            <div class="profile-post-thumb"><span class="post-meta-badge">Play</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#30201f,#191416 45%,#4b2b25);display:grid;place-items:center;color:#fff;font-size:28px;font-weight:800;">R</div></div>
                            <div class="profile-post-thumb"><span class="post-meta-badge">Play</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#191a20,#101114 45%,#3a3d45);display:grid;place-items:center;color:#fff;font-size:28px;font-weight:800;">T</div></div>
                            <div class="profile-post-thumb"><span class="post-meta-badge">Play</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#2a2416,#15120f 45%,#4a3a1c);display:grid;place-items:center;color:#fff;font-size:28px;font-weight:800;">+</div></div>
                            <div class="profile-post-thumb"><span class="post-meta-badge">Play</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#1f1823,#120f16 45%,#44304b);display:grid;place-items:center;color:#fff;font-size:28px;font-weight:800;">FX</div></div>
                            <div class="profile-post-thumb"><span class="post-meta-badge">Play</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#14211b,#0e1411 45%,#294438);display:grid;place-items:center;color:#fff;font-size:22px;font-weight:700;">XAU</div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-tab-panel" data-profile-panel="trades">
                    <div class="profile-trades-panel">
                        <div class="profile-stats-grid">
                            <div class="profile-stat-card">
                                <div class="profile-stat-card-label">Total Trades</div>
                                <div class="profile-stat-card-value gold"><?= $total_trades ?></div>
                                <div class="profile-stat-card-sub"><?= $wins ?> wins · <?= $losses ?> losses</div>
                            </div>
                            <div class="profile-stat-card">
                                <div class="profile-stat-card-label">Win Rate</div>
                                <div class="profile-stat-card-value <?= $win_rate >= 50 ? 'positive' : 'negative' ?>"><?= $win_rate ?>%</div>
                                <div class="profile-stat-card-sub"><?= $win_rate >= 55 ? 'Above average' : ($win_rate >= 45 ? 'Average' : 'Below average') ?></div>
                            </div>
                            <div class="profile-stat-card">
                                <div class="profile-stat-card-label">Average P&amp;L</div>
                                <div class="profile-stat-card-value <?= $avg_pnl >= 0 ? 'positive' : 'negative' ?>"><?= $total_trades > 0 ? ($avg_pnl >= 0 ? '+' : '') . number_format($avg_pnl, 2) . '%' : '—' ?></div>
                                <div class="profile-stat-card-sub">Per closed trade</div>
                            </div>
                            <div class="profile-stat-card">
                                <div class="profile-stat-card-label">Best Trade</div>
                                <div class="profile-stat-card-value positive"><?= $best_trade > 0 ? '+' . number_format($best_trade, 2) . '%' : '—' ?></div>
                                <div class="profile-stat-card-sub">All time high</div>
                            </div>
                            <div class="profile-stat-card">
                                <div class="profile-stat-card-label">Wins</div>
                                <div class="profile-stat-card-value positive"><?= $wins ?></div>
                                <div class="profile-stat-card-sub">Profitable trades</div>
                            </div>
                            <div class="profile-stat-card">
                                <div class="profile-stat-card-label">Losses</div>
                                <div class="profile-stat-card-value negative"><?= $losses ?></div>
                                <div class="profile-stat-card-sub">Losing trades</div>
                            </div>
                        </div>

                        <div class="profile-activity-card">
                            <div class="profile-activity-head">
                                <div>
                                    <h3>Recent Activity</h3>
                                    <p>Performance overview from your journal</p>
                                </div>
                                <a class="profile-activity-link" href="/journal">Open Journal</a>
                            </div>
                            <?php if (empty($recent_trades)): ?>
                            <div class="profile-activity-empty">
                                No trades logged yet. <a href="/journal" style="color:#F2CA50;text-decoration:none;">Add your first trade →</a>
                            </div>
                            <?php else: ?>
                            <?php foreach ($recent_trades as $t):
                                $pnl = $t->profit_loss_pct !== null ? (float)$t->profit_loss_pct : null;
                                $outcome = strtolower($t->outcome ?? '');
                                $dir = strtolower($t->direction ?? '');
                            ?>
                            <div class="profile-activity-row">
                                <span class="profile-activity-symbol"><?= htmlspecialchars($t->symbol) ?></span>
                                <span class="profile-activity-dir <?= $dir ?>"><?= strtoupper($dir) ?></span>
                                <span class="profile-activity-outcome <?= $outcome === 'win' ? 'win' : ($outcome === 'loss' ? 'loss' : 'be') ?>"><?= strtoupper($t->outcome ?? 'BE') ?></span>
                                <span class="profile-activity-pnl <?= $pnl !== null && $pnl >= 0 ? 'pos' : 'neg' ?>"><?= $pnl !== null ? ($pnl >= 0 ? '+' : '') . number_format($pnl, 2) . '%' : '—' ?></span>
                                <span class="profile-activity-date"><?= date('d M Y', strtotime($t->entry_date)) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($is_own_profile): ?>
                <div class="profile-tab-panel" data-profile-panel="saved">
                    <div class="profile-feed-grid">
                        <div class="profile-post-thumb"><span class="post-meta-badge">Saved</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#1d2228,#111317 45%,#36414d);display:grid;place-items:center;color:#fff;font-size:20px;font-weight:700;">Macro</div></div>
                        <div class="profile-post-thumb"><span class="post-meta-badge">Saved</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#241d16,#15110d 45%,#4a3820);display:grid;place-items:center;color:#fff;font-size:20px;font-weight:700;">Gold</div></div>
                        <div class="profile-post-thumb"><span class="post-meta-badge">Saved</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#1b2119,#10140f 45%,#314232);display:grid;place-items:center;color:#fff;font-size:20px;font-weight:700;">FX</div></div>
                    </div>
                </div>

                <div class="profile-tab-panel" data-profile-panel="archive">
                    <div class="profile-feed-grid">
                        <div class="profile-post-thumb"><span class="post-meta-badge">Archived</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#2b2b2f,#1a1a1d 45%,#3b3b3f);display:grid;place-items:center;color:#fff;font-size:18px;font-weight:700;">Archived Post 1</div></div>
                        <div class="profile-post-thumb"><span class="post-meta-badge">Archived</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#241f1c,#12100f 45%,#4a403c);display:grid;place-items:center;color:#fff;font-size:18px;font-weight:700;">Archived Post 2</div></div>
                        <div class="profile-post-thumb"><span class="post-meta-badge">Archived</span><div style="width:100%;height:100%;background:linear-gradient(135deg,#152224,#0d1312 45%,#243a33);display:grid;place-items:center;color:#fff;font-size:18px;font-weight:700;">Archived Post 3</div></div>
                    </div>
                </div>
                <?php endif; ?>
        </section>

        <section class="floor-section" id="floor-groups-panel" hidden>
            <div class="floor-section-card tf-groups-workspace">
                <div id="tf-groups-app"></div>
            </div>
        </section>

<!-- Middle Feed -->
        <main class="tf-feed-col" id="feedCol">
            <?php if ($rich_debug_is_staff): ?>
            <div style="margin: 0 0 16px; padding: 12px 14px; border: 1px solid rgba(242,202,80,0.35); border-radius: 12px; background: rgba(242,202,80,0.08); color: #f5deb0; font: 12px/1.5 monospace; white-space: pre-wrap; word-break: break-word;">
                <?php echo htmlspecialchars(json_encode([
                    'feature' => 'trading-floor',
                    'row_found' => !empty($rich_debug_row),
                    'row' => $rich_debug_row,
                    'session_user_id' => $rich_debug_user_id,
                    'roles' => $rich_debug_roles,
                    'is_staff' => $rich_debug_is_staff,
                    'feature_enabled_for_user' => rich_feature_enabled('trading-floor', true, $rich_debug_user_id),
                    'wpdb_prefix' => isset($wpdb) ? $wpdb->prefix : null,
                    'candidate_tables' => isset($wpdb) ? rich_feature_table_candidates($wpdb) : [],
                    'resolved_table' => isset($wpdb) ? rich_find_feature_table($wpdb) : null,
                    'resolved_table_rows' => isset($wpdb) ? $wpdb->get_results("SELECT flag_key, is_enabled, allowed_roles FROM " . rich_find_feature_table($wpdb) . " ORDER BY flag_key ASC", ARRAY_A) : [],
                    'runtime' => [
                        '__FILE__' => __FILE__,
                        'wp_load_path' => dirname(__DIR__, 2) . '/wp-load.php',
                        'db_name' => defined('DB_NAME') ? DB_NAME : null,
                        'db_host' => defined('DB_HOST') ? DB_HOST : null,
                        'table_count' => isset($wpdb) ? $wpdb->get_var("SELECT COUNT(*) FROM " . rich_find_feature_table($wpdb)) : null,
                    ],
                    'sql_debug' => isset($wpdb) ? [
                        'single_row_sql' => $wpdb->prepare("SELECT flag_key, label, is_enabled, allowed_roles FROM " . rich_find_feature_table($wpdb) . " WHERE flag_key = %s LIMIT 1", 'trading-floor'),
                        'single_row' => $wpdb->get_row($wpdb->prepare("SELECT flag_key, label, is_enabled, allowed_roles FROM " . rich_find_feature_table($wpdb) . " WHERE flag_key = %s LIMIT 1", 'trading-floor'), ARRAY_A),
                        'last_error' => $wpdb->last_error,
                    ] : null,
                ], JSON_PRETTY_PRINT)); ?>
            </div>
            <?php endif; ?>

            <!-- Stories -->
            <div class="stories-row">
                <div class="story-item">
                    <div class="story-ring add-story" style="position:relative;">
                        <div class="story-avatar"><?php echo strtoupper(substr($user_name,0,1)); ?></div>
                        <div class="story-add-btn">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </div>
                    </div>
                    <span class="story-name">Your Story</span>
                </div>
                <?php
                $stories = [
                    ['init'=>'AT','name'=>'AlexT','time'=>'2h','color'=>'#7c3aed','symbol'=>'XAUUSD','pnl'=>'+2.4%','win'=>true,'dir'=>'LONG'],
                    ['init'=>'SK','name'=>'SaraK','time'=>'4h','color'=>'#059669','symbol'=>'BTCUSD','pnl'=>'+1.8%','win'=>true,'dir'=>'LONG'],
                    ['init'=>'MR','name'=>'MikeR','time'=>'6h','color'=>'#dc2626','symbol'=>'NAS100','pnl'=>'-0.7%','win'=>false,'dir'=>'SHORT'],
                    ['init'=>'CL','name'=>'ChenL','time'=>'8h','color'=>'#0284c7','symbol'=>'EURUSD','pnl'=>'+3.1%','win'=>true,'dir'=>'LONG'],
                    ['init'=>'DM','name'=>'DinaM','time'=>'11h','color'=>'#d97706','symbol'=>'XAGUSD','pnl'=>'+1.2%','win'=>true,'dir'=>'LONG'],
                    ['init'=>'JB','name'=>'JohnB','time'=>'14h','color'=>'#be185d','symbol'=>'US30','pnl'=>'+0.9%','win'=>true,'dir'=>'LONG'],
                    ['init'=>'LN','name'=>'LiaNg','time'=>'18h','color'=>'#0e7490','symbol'=>'GBPUSD','pnl'=>'-1.1%','win'=>false,'dir'=>'SHORT'],
                ];
                foreach ($stories as $i => $s): ?>
                <div class="story-item" onclick="openStory(<?= $i ?>)">
                    <div class="story-ring unseen" style="position:relative;">
                        <div class="story-avatar" style="color:<?= $s['color'] ?>;"><?= $s['init'] ?></div>
                        <span class="story-timer"><?= $s['time'] ?></span>
                    </div>
                    <span class="story-name"><?= $s['name'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Posts -->
            <?php
            $posts = [
                ['init'=>'AT','author'=>'Alex Thompson','badge'=>'FOREX TRADER','time'=>'35m ago','color'=>'#7c3aed',
                 'symbol'=>'XAUUSD','dir'=>'LONG','pnl'=>'+2.43%','win'=>true,
                 'entry'=>'2318.50','exit'=>'2374.90','rr'=>'3.2R','session'=>'NY',
                 'caption'=>'<strong>Clean MSS on Gold</strong> — waited for the London open pullback, got the confirmation at 2318 with heavy order flow. Held for the full NY session run. Closed at 2374.',
                 'tags'=>['#XAUUSD','#SmartMoney','#NYSession','#OrderFlow'],'likes'=>47,'comments'=>12],
                ['init'=>'SK','author'=>'Sara Kovač','badge'=>'CRYPTO ANALYST','time'=>'2h ago','color'=>'#059669',
                 'symbol'=>'BTCUSD','dir'=>'LONG','pnl'=>'+1.82%','win'=>true,
                 'entry'=>'67,420','exit'=>'68,650','rr'=>'2.8R','session'=>'ASIA',
                 'caption'=>'<strong>BTC daily demand respected.</strong> Textbook FVG fill at the 67.4k zone. Asia session accumulation confirmed before the push. Patience paid off again.',
                 'tags'=>['#Bitcoin','#BTCUSD','#AsiaSession','#FVG'],'likes'=>83,'comments'=>21],
                ['init'=>'MR','author'=>'Mike Rivera','badge'=>'FUTURES PRO','time'=>'5h ago','color'=>'#dc2626',
                 'symbol'=>'NAS100','dir'=>'SHORT','pnl'=>'-0.72%','win'=>false,
                 'entry'=>'19,840','exit'=>'19,984','rr'=>'-0.9R','session'=>'NY',
                 'caption'=>'Took the short too early at the resistance. Market pushed through my stop. Lesson: wait for the daily close confirmation before entering on intraday structure.',
                 'tags'=>['#NAS100','#Lesson','#ShortTrade','#RiskManagement'],'likes'=>34,'comments'=>28],
                ['init'=>'CL','author'=>'Chen Li','badge'=>'INDICES TRADER','time'=>'8h ago','color'=>'#0284c7',
                 'symbol'=>'EURUSD','dir'=>'LONG','pnl'=>'+3.10%','win'=>true,
                 'entry'=>'1.0845','exit'=>'1.0978','rr'=>'4.1R','session'=>'LONDON',
                 'caption'=>'<strong>Best trade of the week.</strong> EUR/USD weekly demand at 1.0845 held perfectly. London session momentum + ECB hawkish tone = clean run to weekly highs.',
                 'tags'=>['#EURUSD','#LondonSession','#Forex','#WeeklyDemand'],'likes'=>102,'comments'=>31],
            ];
            foreach ($posts as $idx => $p):
            ?>
            <div class="post-card">
                <div class="post-header">
                    <div class="post-avatar" style="background:linear-gradient(135deg,<?= $p['color'] ?>,<?= $p['color'] ?>99);"><?= $p['init'] ?></div>
                    <div class="post-meta">
                        <div class="post-author"><?= $p['author'] ?></div>
                        <div class="post-subtitle"><?= $p['badge'] ?></div>
                    </div>
                    <span class="post-time"><?= $p['time'] ?></span>
                    <button class="post-more-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="5" r="1" fill="currentColor"></circle>
                            <circle cx="12" cy="12" r="1" fill="currentColor"></circle>
                            <circle cx="12" cy="19" r="1" fill="currentColor"></circle>
                        </svg>
                    </button>
                </div>
                <div class="post-trade-card">
                    <div class="trade-card-header">
                        <span class="trade-symbol"><?= $p['symbol'] ?></span>
                        <div class="trade-direction <?= strtolower($p['dir']) ?>">
                            <span class="trade-direction-dot"></span><?= $p['dir'] ?>
                        </div>
                        <span class="trade-pnl-badge <?= $p['win'] ? 'win' : 'loss' ?>"><?= $p['pnl'] ?></span>
                    </div>
                    <div class="trade-stats-row">
                        <div class="trade-stat"><div class="trade-stat-label">Entry</div><div class="trade-stat-value"><?= $p['entry'] ?></div></div>
                        <div class="trade-stat"><div class="trade-stat-label">Exit</div><div class="trade-stat-value"><?= $p['exit'] ?></div></div>
                        <div class="trade-stat"><div class="trade-stat-label">R:R</div><div class="trade-stat-value"><?= $p['rr'] ?></div></div>
                        <div class="trade-stat"><div class="trade-stat-label">Session</div><div class="trade-stat-value"><?= $p['session'] ?></div></div>
                    </div>
                    <div class="trade-chart-area">
                        <canvas class="mini-chart" data-win="<?= $p['win'] ? '1' : '0' ?>" data-dir="<?= strtolower($p['dir']) ?>"></canvas>
                        <span class="trade-chart-label">Price Action</span>
                    </div>
                </div>
                <div class="post-body">
                    <p class="post-caption"><?= $p['caption'] ?></p>
                    <div class="post-tags">
                        <?php foreach ($p['tags'] as $tag): ?><span class="post-tag"><?= $tag ?></span><?php endforeach; ?>
                    </div>
                </div>
                <div class="post-actions">
                    <button class="post-action-btn" onclick="toggleLike(this)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <span class="like-count"><?= $p['likes'] ?></span>
                    </button>
                    <button class="post-action-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <?= $p['comments'] ?>
                    </button>
                    <button class="post-action-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                    </button>
                    <span class="post-action-spacer"></span>
                    <button class="post-action-btn" onclick="toggleBookmark(this)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

        </main>

        <!-- Right Sidebar -->
        <aside class="tf-right-col">
            <div class="right-user-card">
                <div class="right-avatar"><?php echo strtoupper(substr($user_name,0,1)); ?></div>
                <div class="right-user-info">
                    <div class="right-user-name"><?= htmlspecialchars($user_name) ?></div>
                    <div class="right-user-email"><?= htmlspecialchars($user_email) ?></div>
                </div>
                <button class="right-switch-btn" onclick="openFloorSection('profile')">Profile</button>
            </div>

            <p class="right-section-label">Suggested For You</p>
            <div class="suggested-list">
                <?php
                $suggested_pool = array_values(array_filter($trader_results, static function ($t) use ($user_id, $wpdb, $social_table) {
                    $candidate_user_id = (int) ($t['user_id'] ?? 0);
                    if ($candidate_user_id <= 0 || $candidate_user_id === (int) $user_id) {
                        return false;
                    }
                    $already_following = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$social_table} WHERE follower_id = %d AND following_id = %d",
                        $user_id,
                        $candidate_user_id
                    ));
                    return $already_following === 0;
                }));
                $suggested_count = count($suggested_pool);
                if ($suggested_count > 6) {
                    $rotation_seed = (int) floor(time() / 30);
                    $rotation_offset = $rotation_seed % $suggested_count;
                    $suggested_pool = array_merge(
                        array_slice($suggested_pool, $rotation_offset),
                        array_slice($suggested_pool, 0, $rotation_offset)
                    );
                }
                $suggested_pool = array_slice($suggested_pool, 0, 6);
                foreach ($suggested_pool as $t):
                    $suggested_user_id = (int) ($t['user_id'] ?? 0);
                    $suggested_name = trim((string) ($t['display_name'] ?? 'Trader'));
                    $suggested_initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $suggested_name), 0, 2));
                    if ($suggested_initials === '') { $suggested_initials = 'TR'; }
                    $suggested_market = trim((string) ($t['primary_market'] ?? ''));
                    $suggested_style = trim((string) ($t['trading_style'] ?? ''));
                    $suggested_reason = implode(' · ', array_values(array_filter([$suggested_market, $suggested_style])));
                    if ($suggested_reason === '') { $suggested_reason = 'View trader profile'; }
                    $suggested_color = substr(md5((string) $suggested_user_id), 0, 6);
                    $suggested_href = 'https://app.2rich.capital/trading-floor/?user_id=' . $suggested_user_id;
                ?>
                <div class="suggested-item">
                    <a href="<?= htmlspecialchars($suggested_href) ?>" style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;text-decoration:none;color:inherit;">
                        <div class="suggested-avatar" style="background:#<?= htmlspecialchars($suggested_color) ?>"><?= htmlspecialchars($suggested_initials) ?></div>
                        <div class="suggested-info">
                            <div class="suggested-name"><?= htmlspecialchars($suggested_name) ?></div>
                            <div class="suggested-reason"><?= htmlspecialchars($suggested_reason) ?></div>
                        </div>
                    </a>
                    <button class="follow-btn" type="button" data-suggested-follow data-user-id="<?= (int) $suggested_user_id ?>">Follow</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="right-footer-links">
                <span class="right-footer-link">About</span>
                <span class="right-footer-link">Help</span>
                <span class="right-footer-link">Privacy</span>
                <span class="right-footer-link">Terms</span>
                <span class="right-footer-link">© 2Rich Capital</span>
            </div>
        </aside>

    </div>

    <!-- DM Panel -->
    <div class="dm-panel" id="dmPanel">
        <div class="dm-panel-header" onclick="toggleDM()">
            <div class="dm-panel-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                Messages <span class="dm-unread-dot"></span>
            </div>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="18 15 12 9 6 15"></polyline>
            </svg>
        </div>
        <div class="dm-list">
            <?php
            $dms = [
                ['init'=>'AT','name'=>'Alex Thompson','preview'=>'Nice setup on Gold man 🔥','time'=>'2m','color'=>'#7c3aed','unread'=>true],
                ['init'=>'SK','name'=>'Sara Kovač','preview'=>'What entry did you use?','time'=>'18m','color'=>'#059669','unread'=>true],
                ['init'=>'CL','name'=>'Chen Li','preview'=>'4.1R — insane! Congrats','time'=>'1h','color'=>'#0284c7','unread'=>false],
                ['init'=>'MR','name'=>'Mike Rivera','preview'=>'Yeah happened to me too lol','time'=>'3h','color'=>'#dc2626','unread'=>false],
            ];
            foreach ($dms as $dm): ?>
            <div class="dm-item <?= $dm['unread'] ? 'unread' : '' ?>">
                <div class="dm-item-avatar" style="background:<?= $dm['color'] ?>"><?= $dm['init'] ?></div>
                <div class="dm-item-info">
                    <div class="dm-item-name"><?= $dm['name'] ?></div>
                    <div class="dm-item-preview"><?= $dm['preview'] ?></div>
                </div>
                <div class="dm-item-time"><?= $dm['time'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="dm-compose-row">
            <input type="text" class="dm-input" placeholder="New message...">
            <button style="color:#F2CA50;background:none;border:none;cursor:pointer;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>
    </div>

    <!-- Story Viewer -->
    <div class="story-overlay" id="storyOverlay" onclick="handleStoryClick(event)">
        <div class="story-viewer" id="storyViewer">
            <div class="story-progress-bar" id="storyProgressBar"></div>
            <button class="story-close-btn" onclick="closeStory();event.stopPropagation();">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="story-content" id="storyContent"></div>
        </div>
    </div>

    <!-- Create Modal -->
    <div class="modal-overlay" id="createModal">
        <div class="create-modal">
            <div class="create-modal-header">
                <span class="create-modal-title">Create</span>
                <button class="modal-close-btn" onclick="closeCreateModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="create-modal-body">
                <div class="create-tabs">
                    <button class="create-tab active" id="tabPost" onclick="switchCreateTab('post')">📊 Trade Post</button>
                    <button class="create-tab" id="tabStory" onclick="switchCreateTab('story')">⚡ Story</button>
                    <button class="create-tab" id="tabAnalysis" onclick="switchCreateTab('analysis')">📈 Analysis</button>
                </div>
                <form id="createPostForm" enctype="multipart/form-data">
                    <div class="create-form-row">
                        <div class="create-form-field">
                            <label class="create-form-label">Symbol</label>
                            <input type="text" class="create-form-input" id="createSymbol" name="symbol" placeholder="e.g. XAUUSD">
                        </div>
                        <div class="create-form-field">
                            <label class="create-form-label">Direction</label>
                            <select class="create-form-select" id="createDirection" name="direction">
                                <option value="">Select...</option>
                                <option value="LONG">LONG</option>
                                <option value="SHORT">SHORT</option>
                            </select>
                        </div>
                    </div>
                    <div class="create-form-row">
                        <div class="create-form-field">
                            <label class="create-form-label">P&L %</label>
                            <input type="number" class="create-form-input" id="createPnl" name="pnl_value" placeholder="+2.5" step="0.01">
                        </div>
                        <div class="create-form-field">
                            <label class="create-form-label">R:R</label>
                            <input type="text" class="create-form-input" id="createRr" name="rr_value" placeholder="e.g. 3.2R">
                        </div>
                    </div>
                    <div class="create-form-field">
                        <label class="create-form-label">Caption</label>
                        <textarea class="create-form-textarea" id="createCaption" name="caption" placeholder="Share your analysis, entry logic, lessons learned..." required></textarea>
                    </div>
                    <div class="create-form-field">
                        <label class="create-form-label">Chart Screenshot (optional)</label>
                        <input type="file" class="create-form-input" id="createImage" name="image" accept="image/*" style="padding:8px 14px;cursor:pointer;">
                    </div>
                    <div class="create-form-field" id="createFormStatus" style="display:none;font-size:12px;color:#a9afb8;"></div>
                    <button class="create-submit-btn" type="submit" id="createSubmitBtn">Post Trade</button>
                </form>
            </div>
        </div>
    </div>

    <div class="group-signal-modal" id="groupSignalModal" aria-hidden="true">
        <div class="group-signal-dialog">
            <div class="group-signal-head">
                <div>
            <div class="section-kicker">Group signal</div>
            <h3>Post signal</h3>
            <p>Manual signal entry for the active group. This creates the same type of group signal feed item that your future MT5 bot/API flow should write into.</p>
            <div class="group-signal-allowed" id="groupSignalAllowedSymbols">Allowed symbols: —</div>
        </div>
                <button class="modal-close-btn" type="button" onclick="closeGroupSignalModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <form class="group-signal-body" id="groupSignalForm">
                <div class="group-signal-grid">
                    <div class="group-signal-field">
                        <label class="group-signal-label" for="groupSignalSymbol">Symbol</label>
                        <input class="group-signal-input" id="groupSignalSymbol" name="symbol" type="text" placeholder="e.g. XAUUSD" required>
                    </div>
                    <div class="group-signal-field">
                        <label class="group-signal-label" for="groupSignalSide">Direction</label>
                        <select class="group-signal-select" id="groupSignalSide" name="side" required>
                            <option value="">Select...</option>
                            <option value="buy">Buy / Long</option>
                            <option value="sell">Sell / Short</option>
                        </select>
                    </div>
                    <div class="group-signal-field">
                        <label class="group-signal-label" for="groupSignalEntry">Entry</label>
                        <input class="group-signal-input" id="groupSignalEntry" name="entry" type="number" step="0.00001" placeholder="e.g. 3350.50">
                    </div>
                    <div class="group-signal-field">
                        <label class="group-signal-label" for="groupSignalStopLoss">Stop loss</label>
                        <input class="group-signal-input" id="groupSignalStopLoss" name="stop_loss" type="number" step="0.00001" placeholder="e.g. 3339.00">
                    </div>
                    <div class="group-signal-field">
                        <label class="group-signal-label" for="groupSignalTakeProfit">Take profit</label>
                        <input class="group-signal-input" id="groupSignalTakeProfit" name="take_profit" type="number" step="0.00001" placeholder="e.g. 3372.00">
                    </div>
                    <div class="group-signal-field">
                        <label class="group-signal-label" for="groupSignalTimeframe">Timeframe</label>
                        <input class="group-signal-input" id="groupSignalTimeframe" name="timeframe" type="text" placeholder="e.g. M15 / H1 / Swing">
                    </div>
                    <div class="group-signal-field">
                        <label class="group-signal-label" for="groupSignalRisk">Risk note</label>
                        <input class="group-signal-input" id="groupSignalRisk" name="risk_note" type="text" placeholder="e.g. 0.5% risk, scale in after retest">
                    </div>
                    <div class="group-signal-field">
                        <label class="group-signal-label" for="groupSignalStatusSelect">Status</label>
                        <select class="group-signal-select" id="groupSignalStatusSelect" name="status">
                            <option value="open">Open</option>
                            <option value="pending">Pending</option>
                            <option value="closed">Closed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="group-signal-field full">
                        <label class="group-signal-label" for="groupSignalMessage">Analysis / thesis</label>
                        <textarea class="group-signal-textarea" id="groupSignalMessage" name="message" placeholder="Why this trade, what confirms it, what invalidates it, what members should watch..."></textarea>
                    </div>
                </div>
                <div class="group-signal-status" id="groupSignalStatus"></div>
                <div class="group-signal-actions">
                    <div class="group-signal-note">Recommended structure: one manual form for human posting, one signed ingest endpoint for your MT5 bot, both saving into the same group signal feed.</div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button class="group-ghost-btn" type="button" onclick="closeGroupSignalModal()">Cancel</button>
                        <button class="group-pill-btn" type="submit">Post signal</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="social-list-modal" id="socialListModal" aria-hidden="true">
        <div class="social-list-dialog" role="dialog" aria-modal="true" aria-labelledby="socialListTitle">
            <div class="social-list-head">
                <div class="social-list-title" id="socialListTitle">Followers</div>
                <button type="button" class="social-list-close" data-social-list-close aria-label="Close">×</button>
            </div>
            <div id="socialListBody"><div class="social-list-empty">Loading…</div></div>
        </div>
    </div>
    <script>
    document.querySelectorAll('[data-social-list]').forEach((stat) => {
        stat.addEventListener('click', async () => {
            const modal = document.getElementById('socialListModal');
            const body = document.getElementById('socialListBody');
            const title = document.getElementById('socialListTitle');
            if (!modal || !body || !title) return;
            const listType = stat.dataset.socialList === 'following' ? 'following' : 'followers';
            const userId = Number(stat.dataset.userId || 0);
            title.textContent = listType === 'following' ? 'Following' : 'Followers';
            body.innerHTML = '<div class="social-list-empty">Loading…</div>';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            try {
                const response = await fetch(`./../api/social/list.php?user_id=${encodeURIComponent(userId)}&type=${listType}`, { credentials: 'include' });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load list');
                if (!Array.isArray(result.users) || result.users.length === 0) {
                    body.innerHTML = '<div class="social-list-empty">No users yet.</div>';
                    return;
                }
                body.innerHTML = result.users.map((user) => {
                    const name = String(user.display_name || 'Trader');
                    const initials = name.replace(/[^A-Za-z0-9]/g, '').slice(0, 2).toUpperCase() || 'TR';
                    const color = String(user.color || 'F2CA50').replace('#', '');
                    return `<div class="social-list-row"><div class="social-list-avatar" style="background:#${color}">${initials}</div><a class="social-list-name" href="./?user_id=${encodeURIComponent(user.user_id)}">${name}</a></div>`;
                }).join('');
            } catch (error) {
                body.innerHTML = '<div class="social-list-empty">Unable to load this list.</div>';
                console.error(error);
            }
        });
    });

    document.querySelector('[data-social-list-close]')?.addEventListener('click', () => {
        const modal = document.getElementById('socialListModal');
        if (modal) { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); }
    });
    document.getElementById('socialListModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'socialListModal') {
            event.currentTarget.classList.remove('open');
            event.currentTarget.setAttribute('aria-hidden', 'true');
        }
    });

    document.querySelectorAll('[data-profile-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.profileTab;
            const profileSection = document.getElementById('floor-profile-panel');
            document.querySelectorAll('[data-profile-tab]').forEach((tab) => tab.classList.toggle('active', tab === button));
            document.querySelectorAll('[data-profile-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.profilePanel === target));
            if (profileSection) profileSection.classList.toggle('profile-archive-mode', target === 'archive');
        });
    });

    document.querySelectorAll('[data-profile-action="follow"]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const targetUserId = <?php echo (int) $view_user_id; ?>;
            const following = btn.dataset.following === '1';
            const originalText = btn.textContent;
            btn.disabled = true;
            try {
                const response = await fetch('./../api/social/follow.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?> },
                    body: JSON.stringify({ user_id: targetUserId, action: following ? 'unfollow' : 'follow' })
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update follow state');
                btn.dataset.following = result.is_following ? '1' : '0';
                btn.textContent = result.is_following ? 'Following' : 'Follow';
                btn.classList.toggle('following', result.is_following);
                const statValues = document.querySelectorAll('.profile-stat-value');
                if (statValues[2] && typeof result.followers !== 'undefined') statValues[2].textContent = result.followers;
                if (statValues[3] && typeof result.following !== 'undefined') statValues[3].textContent = result.following;
                const ownProfileFollowingStat = document.querySelector('[data-own-following-count]');
                if (ownProfileFollowingStat && typeof result.current_user_following !== 'undefined') {
                    ownProfileFollowingStat.textContent = result.current_user_following;
                }
            } catch (error) {
                btn.textContent = originalText;
                console.error(error);
            } finally {
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-suggested-follow]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const targetUserId = Number(btn.dataset.userId || 0);
            if (!targetUserId) return;
            const following = btn.dataset.following === '1';
            const originalText = btn.textContent;
            btn.disabled = true;
            try {
                const response = await fetch('./../api/social/follow.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?> },
                    body: JSON.stringify({ user_id: targetUserId, action: following ? 'unfollow' : 'follow' })
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update follow state');
                btn.dataset.following = result.is_following ? '1' : '0';
                if (result.is_following) {
                    const suggestedItem = btn.closest('.suggested-item');
                    if (suggestedItem) suggestedItem.remove();
                } else {
                    btn.textContent = 'Follow';
                    btn.classList.remove('following');
                }
                const ownProfileFollowingStat = document.querySelector('[data-own-following-count]');
                if (ownProfileFollowingStat && typeof result.current_user_following !== 'undefined') {
                    ownProfileFollowingStat.textContent = result.current_user_following;
                }
            } catch (error) {
                btn.textContent = originalText;
                console.error(error);
            } finally {
                btn.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-profile-action="message"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const panel = document.getElementById('dmPanel');
            if (!panel) return;
            const title = panel.querySelector('.dm-panel-title');
            const name = btn.dataset.profileName || 'Trader';
            if (title) title.lastChild.textContent = ` Message ${name}`;
            panel.classList.add('open');
        });
    });

    document.querySelectorAll('[data-profile-action="view-archive"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            try { openFloorSection('profile'); } catch (e) {}
            const profileSection = document.getElementById('floor-profile-panel');
            const archivePanel = document.querySelector('[data-profile-panel="archive"]');
            const postsTab = document.querySelector('[data-profile-tab="posts"]');
            const tradesTab = document.querySelector('[data-profile-tab="trades"]');
            const savedTab = document.querySelector('[data-profile-tab="saved"]');

            if (profileSection) profileSection.classList.add('profile-archive-mode');

            [postsTab, tradesTab, savedTab].forEach((tab) => {
                if (tab) tab.classList.remove('active');
            });

            document.querySelectorAll('[data-profile-panel]').forEach((panel) => {
                panel.classList.toggle('active', panel.dataset.profilePanel === 'archive');
            });

            if (archivePanel && !archivePanel.querySelector('.archive-back-btn')) {
                const backBtn = document.createElement('button');
                backBtn.className = 'group-ghost-btn archive-back-btn';
                backBtn.textContent = 'Back to profile';
                backBtn.style.marginBottom = '12px';
                backBtn.addEventListener('click', () => {
                    const profileSection = document.getElementById('floor-profile-panel');
                    const postsTab = document.querySelector('[data-profile-tab="posts"]');
                    if (profileSection) profileSection.classList.remove('profile-archive-mode');
                    document.querySelectorAll('[data-profile-panel]').forEach((panel) => {
                        panel.classList.toggle('active', panel.dataset.profilePanel === 'posts');
                    });
                    if (postsTab) postsTab.classList.add('active');
                });
                archivePanel.insertBefore(backBtn, archivePanel.firstChild);
            }
        });
    });

    function openFloorSection(section) {
        const app = document.querySelector('.dashboard-container');
        const feed = document.getElementById('feedCol');
        const profile = document.getElementById('floor-profile-panel');
        const groups = document.getElementById('floor-groups-panel');
        if (section === 'home') {
            const homeUrl = new URL(window.location.href);
            homeUrl.searchParams.delete('user_id');
            homeUrl.hash = '';
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, homeUrl.pathname + homeUrl.search);
            }
        }
        [feed, profile, groups].forEach(el => { if (el) el.hidden = true; el.style.display = 'none'; });
        const homeLink = document.querySelector('[data-floor-nav="home"]');
        const groupsLink = document.querySelector('[data-floor-nav="groups"]');
        const profileLink = document.querySelector('[data-floor-nav="profile"]');
        [homeLink, groupsLink, profileLink].forEach(el => { if (el) el.classList.remove('active'); });
        if (app) app.classList.toggle('profile-mode', section === 'profile');
        if (section === 'profile' && profile) { profile.hidden = false; profile.style.display = 'block'; if (profileLink) profileLink.classList.add('active'); }
        else if (section === 'groups' && groups) { bootFloorSignals(); groups.hidden = false; groups.style.display = 'block'; if (groupsLink) groupsLink.classList.add('active'); }
        else if (feed) { feed.hidden = false; feed.style.display = 'block'; if (homeLink) homeLink.classList.add('active'); }
    }

    function openFloorSectionFromHash() {
        const rawHash = window.location.hash.replace(/^#/, '');
        const hash = rawHash.toLowerCase();
        const requestedGroup = new URLSearchParams(rawHash.replace(/&/g, '&')).get('group');
        const requestedUserId = new URLSearchParams(window.location.search).get('user_id');
        if (requestedUserId && /^\d+$/.test(requestedUserId) && Number(requestedUserId) > 0) {
            openFloorSection('profile');
        } else if (hash.startsWith('profile')) {
            openFloorSection('profile');
        } else if (hash.startsWith('groups')) {
            openFloorSection('groups');
            if (requestedGroup) {
                window.__requestedTradingGroupId = String(requestedGroup);
                if (typeof bootFloorSignals === 'function') bootFloorSignals();
            }
        } else {
            openFloorSection('home');
        }
    }

    document.addEventListener('DOMContentLoaded', openFloorSectionFromHash);
    window.addEventListener('hashchange', openFloorSectionFromHash);

    document.querySelector('[data-floor-nav="profile"]')?.addEventListener('click', (event) => {
        event.preventDefault();
        const profileUrl = new URL(window.location.href);
        profileUrl.searchParams.delete('user_id');
        profileUrl.hash = 'profile';
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, profileUrl.pathname + profileUrl.search + profileUrl.hash);
        }
        window.location.reload();
    });


    async function bootFloorSignals(force = false) {
        if (floorSignalsState.loading) return;
        if (floorSignalsState.booted && !force) {
            renderGroupsPanel();
            return;
        }
        floorSignalsState.loading = true;
        floorSignalsState.error = '';
        try {
            console.log('[TradingFloorDebug] groups fetch start', {
                listGroupsUrl: signalsUrl('list-groups.php'),
                myMembershipsUrl: signalsUrl('my-memberships.php'),
                pathname: window.location.pathname,
                origin: window.location.origin
            });
            const [groupsRes, membershipsRes] = await Promise.all([
                fetch(signalsUrl('list-groups.php'), {
                    credentials: 'include',
                    headers: { 'X-CSRF-Token': SIGNALS_CSRF }
                }),
                fetch(signalsUrl('my-memberships.php'), {
                    credentials: 'include',
                    headers: { 'X-CSRF-Token': SIGNALS_CSRF }
                })
            ]);
            console.log('[TradingFloorDebug] groups fetch responses', {
                listGroupsOk: groupsRes.ok,
                listGroupsStatus: groupsRes.status,
                listGroupsUrl: groupsRes.url,
                membershipsOk: membershipsRes.ok,
                membershipsStatus: membershipsRes.status,
                membershipsUrl: membershipsRes.url
            });
            const groupsData = await groupsRes.json().catch(() => ({}));
            const membershipsData = await membershipsRes.json().catch(() => ({}));
            floorSignalsState.groups = Array.isArray(groupsData.groups) ? groupsData.groups.map(normalizeSignalGroup) : [];
            floorSignalsState.memberships = Array.isArray(membershipsData.memberships) ? membershipsData.memberships : [];
            floorSignalsState.myDrafts = Array.isArray(groupsData.my_drafts) ? groupsData.my_drafts.map(normalizeSignalGroup) : [];
            floorSignalsState.requests = Array.isArray(groupsData.requests) ? groupsData.requests : [];
            const requestedGroupId = String(window.__requestedTradingGroupId || '');
            const requestedGroup = requestedGroupId && floorSignalsState.groups.find(group => String(group.id) === requestedGroupId);
            if (requestedGroup) {
                floorSignalsState.activeGroupId = requestedGroup.id;
                floorSignalsState.activeView = 'workspace';
                floorSignalsState.activeWorkspaceTab = 'room';
                floorSignalsState.groupMembers = await loadGroupMembers(requestedGroup.id);
                await Promise.all([loadGroupMessages(requestedGroup.id), loadGroupSignalFeed(requestedGroup.id)]);
                window.__requestedTradingGroupId = '';
            } else if (!floorSignalsState.activeGroupId && floorSignalsState.groups.length) {
                floorSignalsState.activeGroupId = floorSignalsState.groups[0].id;
            }
            floorSignalsState.booted = true;
        } catch (err) {
            floorSignalsState.error = err && err.message ? err.message : 'Could not load groups.';
        } finally {
            floorSignalsState.loading = false;
            renderGroupsPanel();
        }
    }

    function switchFloorSignalsTab(tab) {
        floorSignalsState.activeTab = tab;
        floorSignalsState.activeView = 'list';
        renderGroupsPanel();
    }

    async function loadGroupMembers(groupId) {
        if (!groupId) return [];
        try {
            const url = signalsUrl('group-staff.php') + '?group_id=' + encodeURIComponent(String(groupId));
            const res = await fetch(url, {
                credentials: 'include',
                headers: { 'X-CSRF-Token': SIGNALS_CSRF }
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data && data.message ? data.message : 'Could not load group members.');
            }
            return Array.isArray(data.staff) ? data.staff : [];
        } catch (err) {
            floorSignalsState.groupMembersError = err && err.message ? err.message : 'Could not load group members.';
            return [];
        }
    }

    async function loadGroupSignalFeed(groupId) {
        if (!groupId) return [];
        floorSignalsState.groupSignalsLoading = true;
        floorSignalsState.groupSignalsError = '';
        renderGroupsPanel();
        try {
            const url = signalsUrl('feed.php') + '?group_id=' + encodeURIComponent(String(groupId));
            const res = await fetch(url, {
                credentials: 'include',
                headers: { 'X-CSRF-Token': SIGNALS_CSRF }
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data && data.message ? data.message : 'Could not load group signals.');
            }
            const signals = Array.isArray(data.signals) ? data.signals : [];
            floorSignalsState.groupSignalsByGroup = floorSignalsState.groupSignalsByGroup || {};
            floorSignalsState.groupSignalsByGroup[String(groupId)] = signals;
            return signals;
        } catch (err) {
            floorSignalsState.groupSignalsError = err && err.message ? err.message : 'Could not load group signals.';
            floorSignalsState.groupSignalsByGroup = floorSignalsState.groupSignalsByGroup || {};
            floorSignalsState.groupSignalsByGroup[String(groupId)] = [];
            return [];
        } finally {
            floorSignalsState.groupSignalsLoading = false;
            renderGroupsPanel();
        }
    }

    async function loadGroupMessages(groupId) {
        if (!groupId) return [];
        floorSignalsState.groupMessagesLoading = true;
        floorSignalsState.groupMessagesError = '';
        renderGroupsPanel();
        try {
            const url = signalsUrl('messages.php') + '?group_id=' + encodeURIComponent(String(groupId));
            const res = await fetch(url, {
                credentials: 'include',
                headers: { 'X-CSRF-Token': SIGNALS_CSRF }
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data && data.message ? data.message : 'Could not load group messages.');
            }
            const messages = Array.isArray(data.messages) ? data.messages : [];
            floorSignalsState.groupMessagesByGroup = floorSignalsState.groupMessagesByGroup || {};
            floorSignalsState.groupMessagesByGroup[String(groupId)] = messages;
            return messages;
        } catch (err) {
            floorSignalsState.groupMessagesError = err && err.message ? err.message : 'Could not load group messages.';
            floorSignalsState.groupMessagesByGroup = floorSignalsState.groupMessagesByGroup || {};
            floorSignalsState.groupMessagesByGroup[String(groupId)] = [];
            return [];
        } finally {
            floorSignalsState.groupMessagesLoading = false;
            renderGroupsPanel();
        }
    }

    async function sendGroupMessage(groupId, message) {
        const text = String(message || '').trim();
        if (!groupId || !text) {
            throw new Error('Message is empty.');
        }
        const res = await fetch(signalsUrl('messages.php'), {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': SIGNALS_CSRF
            },
            body: JSON.stringify({ group_id: groupId, message: text })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
            throw new Error(data && data.message ? data.message : 'Could not send group message.');
        }
        return data;
    }

    async function sendCurrentGroupMessage() {
        const groupId = floorSignalsState.activeGroupId;
        const input = document.getElementById('groupMessageInput');
        const text = input ? input.value : '';
        if (!groupId || !input || !String(text || '').trim()) return;
        floorSignalsState.groupMessagesError = '';
        input.disabled = true;
        try {
            await sendGroupMessage(groupId, text);
            input.value = '';
            await loadGroupMessages(groupId);
        } catch (err) {
            floorSignalsState.groupMessagesError = err && err.message ? err.message : 'Could not send group message.';
            renderGroupsPanel();
        } finally {
            input.disabled = false;
            input.focus();
        }
    }

    async function openFloorSignalGroup(groupId) {
        floorSignalsState.activeGroupId = groupId;
        floorSignalsState.activeView = 'workspace';
        floorSignalsState.activeWorkspaceTab = 'room';
        floorSignalsState.groupMembers = await loadGroupMembers(groupId);
        await Promise.all([loadGroupMessages(groupId), loadGroupSignalFeed(groupId)]);
        renderGroupsPanel();
        openFloorSection('groups');
    }

    function closeFloorSignalWorkspace() {
        floorSignalsState.activeView = 'list';
        renderGroupsPanel();
    }

    function normalizeSignalGroup(group) {
        return {
            id: group.id,
            name: group.name || 'Unnamed Group',
            team_name: group.team_name || '',
            description: group.description || '',
            category: group.category || 'General',
            pricing_type: group.pricing_type || 'free',
            price: Number(group.price || 0),
            visibility: group.visibility || 'listed',
            join_mode: group.join_mode || 'open',
            status: group.status || 'live',
            is_active: Number(group.is_active ?? 1),
            member_count: Number(group.member_count || 0),
            signal_count: Number(group.signal_count || 0),
            owner_user_id: Number(group.owner_user_id || 0),
            my_role: group.my_role || group.role || null,
            is_owner: !!group.is_owner,
            is_joined: !!group.is_joined,
            is_staff: !!group.is_staff,
            can_post: !!group.can_post,
            can_manage: !!group.can_manage,
            joined_at: group.joined_at || null,
            intro_text: group.intro_text || '',
            rules_text: group.rules_text || '',
            avatar_url: group.avatar_url || '',
            cover_url: group.cover_url || '',
            accent_color: group.accent_color || '',
            requires_stop_loss: !!group.requires_stop_loss,
            requires_take_profit: !!group.requires_take_profit,
            allowed_symbols_json: group.allowed_symbols_json || '[]',
            last_signal_at: group.last_signal_at || null,
            verification_status: group.verification_status || 'none',
            is_verified: !!group.is_verified || group.verification_status === 'verified',
            created_at: group.created_at || null,
            updated_at: group.updated_at || null,
        };
    }

    function groupColorPresets() {
        return ['#F2CA50', '#6EE7B7', '#38BDF8', '#A78BFA', '#FB7185', '#F59E0B'];
    }

    function groupAccentColor(group) {
        const presets = groupColorPresets();
        const value = String(group && group.accent_color ? group.accent_color : '').trim();
        if (value && presets.some(p => p.toUpperCase() === value.toUpperCase())) return value;
        return presets[(Number(group && group.id) || 1) % presets.length];
    }

    function groupColorPickerHtml(selectedColor, fieldName = 'accent_color') {
        const presets = groupColorPresets();
        return `<div class="group-color-palette" data-color-picker="${fieldName}">${presets.map(color => `<button type="button" class="group-color-swatch ${String(selectedColor || '').toUpperCase() === color.toUpperCase() ? 'is-selected' : ''}" data-color="${color}" style="background:${color};" aria-label="Choose ${color} for group color"></button>`).join('')}</div><input type="hidden" name="${fieldName}" value="${selectedColor || presets[0]}">`;
    }

    function bindGroupColorPickers(root) {
        const scopes = root ? [root] : Array.from(document.querySelectorAll('[data-color-picker]'));
        scopes.forEach(scope => {
            scope.querySelectorAll('.group-color-swatch').forEach(btn => {
                btn.onclick = () => {
                    const color = btn.getAttribute('data-color') || '';
                    const input = scope.parentElement && scope.parentElement.querySelector(`input[name="${scope.getAttribute('data-color-picker') || 'accent_color'}"]`);
                    if (input) input.value = color;
                    scope.querySelectorAll('.group-color-swatch').forEach(swatch => swatch.classList.toggle('is-selected', swatch === btn));
                };
            });
        });
    }

    function moneyLabel(group) {
        if (!group) return 'Free';
        if (group.pricing_type === 'paid') return '$' + Number(group.price || 0).toFixed(2);
        return 'Free';
    }

    function visibilityLabel(value) {
        if (value === 'private') return 'Private';
        if (value === 'unlisted') return 'Unlisted';
        return 'Listed';
    }

    function joinModeLabel(value) {
        if (value === 'request') return 'Request to join';
        if (value === 'invite') return 'Invite only';
        return 'Open join';
    }

    function statusBadge(value) {
        if (value === 'draft') return 'Draft';
        if (value === 'pending_review') return 'Pending review';
        if (value === 'suspended') return 'Suspended';
        if (value === 'archived') return 'Archived';
        return 'Live';
    }

    function verifiedBadge(group) {
        if (!group || !group.is_verified) return '';
        return '<span class="group-verified-badge" title="Verified group">✓ Verified</span>';
    }

    function groupCardAccent(group) {
        const palette = ['#F2CA50', '#6EE7B7', '#38BDF8', '#A78BFA', '#FB7185', '#F59E0B'];
        return palette[(Number(group.id) || 1) % palette.length];
    }

    function groupAvatarHtml(group) {
        const src = group && (group.avatar_url || group.owner_avatar || group.avatar || group.profile_image || group.image || group.owner_profile_photo || group.group_avatar);
        const label = String((group && (group.name || group.group_name)) || 'G').trim();
        const initial = label ? label.charAt(0).toUpperCase() : 'G';
        const esc = (value) => String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
        if (src) {
            return `<span class="group-card-avatar"><img src="${esc(String(src))}" alt="${esc(label)} avatar"></span>`;
        }
        return `<span class="group-card-avatar">${esc(initial)}</span>`;
    }

    function renderGroupsPanel() {
        const mount = document.getElementById('tf-groups-app');
        if (!mount) return;

        const activeTab = floorSignalsState.activeTab || 'discovery';
        const activeView = floorSignalsState.activeView || 'list';
        const memberships = Array.isArray(floorSignalsState.memberships) ? floorSignalsState.memberships : [];
        const groups = Array.isArray(floorSignalsState.groups) ? floorSignalsState.groups : [];
        const drafts = Array.isArray(floorSignalsState.myDrafts) ? floorSignalsState.myDrafts : [];
        const requests = Array.isArray(floorSignalsState.requests) ? floorSignalsState.requests : [];
        const discoveryGroups = groups.filter(g => g.visibility === 'listed' && g.status === 'live');
        const joinedGroups = memberships.filter(m => (m.status || 'active') === 'active');
        const current = groups.find(g => String(g.id) === String(floorSignalsState.activeGroupId))
            || joinedGroups.map(m => normalizeSignalGroup({
                id: m.group_id || m.id,
                name: m.group_name || m.name,
                description: m.description || '',
                visibility: m.visibility || 'listed',
                status: m.group_status || m.status || 'live',
                join_mode: m.join_mode || 'open',
                member_count: m.member_count || 0,
                signal_count: m.signal_count || 0,
                category: m.category || 'General',
                my_role: m.role || 'member',
                is_owner: !!m.is_owner,
                is_joined: true,
                can_manage: !!m.can_manage,
                can_post: !!m.can_post,
                intro_text: m.intro_text || '',
                rules_text: m.rules_text || '',
                team_name: m.team_name || '',
                group_id: m.group_id || m.id
            })).find(g => String(g.id) === String(floorSignalsState.activeGroupId))
            || drafts.find(g => String(g.id) === String(floorSignalsState.activeGroupId))
            || (!floorSignalsState.activeGroupId ? discoveryGroups[0] : null)
            || (!floorSignalsState.activeGroupId && joinedGroups[0] ? normalizeSignalGroup({
                id: joinedGroups[0].group_id || joinedGroups[0].id,
                name: joinedGroups[0].group_name || joinedGroups[0].name,
                description: joinedGroups[0].description || '',
                visibility: joinedGroups[0].visibility || 'listed',
                status: joinedGroups[0].group_status || joinedGroups[0].status || 'live',
                join_mode: joinedGroups[0].join_mode || 'open',
                member_count: joinedGroups[0].member_count || 0,
                signal_count: joinedGroups[0].signal_count || 0,
                category: joinedGroups[0].category || 'General',
                my_role: joinedGroups[0].role || 'member',
                is_owner: !!joinedGroups[0].is_owner,
                is_joined: true,
                can_manage: !!joinedGroups[0].can_manage,
                can_post: !!joinedGroups[0].can_post,
                intro_text: joinedGroups[0].intro_text || '',
                rules_text: joinedGroups[0].rules_text || '',
                team_name: joinedGroups[0].team_name || '',
                group_id: joinedGroups[0].group_id || joinedGroups[0].id
            }) : null)
            || (!floorSignalsState.activeGroupId ? drafts[0] : null)
            || null;

        const workspaceHtml = current ? (() => {
            const allowedSymbols = String(current.allowed_symbols_json || '').trim()
                ? (() => { try { const parsed = JSON.parse(current.allowed_symbols_json); return Array.isArray(parsed) ? parsed : []; } catch (_) { return []; } })()
                : [];
            const symbolsLabel = allowedSymbols.length
                ? allowedSymbols.map(s => String(s).trim().toUpperCase()).filter(Boolean).join(', ')
                : 'ALL';
            const roleLabel = current.is_owner ? 'Owner' : (current.my_role ? String(current.my_role).charAt(0).toUpperCase() + String(current.my_role).slice(1) : 'Member');
            const accessLabel = current.can_manage ? 'Can manage' : (current.can_post ? 'Can post' : 'Read only');
            const groupRequestCount = requests.filter(req => String(req.group_id || '') === String(current.id || current.group_id || '')).length;
            const rulesMeta = [
                `Allowed symbols: ${symbolsLabel}`,
                current.requires_stop_loss ? 'Stop loss required' : 'Stop loss optional',
                current.requires_take_profit ? 'Take profit required' : 'Take profit optional',
                accessLabel
            ].join(' · ');
            return `
            <section class="tf-groups-shell tf-groups-workspace tf-group-workspace">
                <div class="group-workspace-hero">
                    <div class="group-workspace-copy">
                        <div class="section-kicker">${current.team_name || 'Group workspace'} · ${current.category} · ${current.member_count} members</div>
                        <h2>${current.name}</h2>
                        <p>${current.intro_text || current.description || 'Private room for desk coordination, member updates, live analysis, and calls.'}</p>
                    </div>
                    <div class="group-card-actions">
                        <button class="group-ghost-btn" type="button" onclick="closeFloorSignalWorkspace()">Back</button>
                    </div>
                </div>
                <div class="group-workspace-tabs">
                    <button class="${floorSignalsState.activeWorkspaceTab === 'room' ? 'group-pill-btn is-active' : 'group-pill-btn'}" type="button" onclick="floorSignalsState.activeWorkspaceTab='room';renderGroupsPanel();">Room</button>
                    <button class="${floorSignalsState.activeWorkspaceTab === 'members' ? 'group-pill-btn is-active' : 'group-pill-btn'}" type="button" onclick="floorSignalsState.activeWorkspaceTab='members';renderGroupsPanel();">Members</button>
                    <button class="${floorSignalsState.activeWorkspaceTab === 'content' ? 'group-pill-btn is-active' : 'group-pill-btn'}" type="button" onclick="floorSignalsState.activeWorkspaceTab='content';renderGroupsPanel();">Content</button>
                    <button class="${floorSignalsState.activeWorkspaceTab === 'calls' ? 'group-pill-btn is-active' : 'group-pill-btn'}" type="button" onclick="floorSignalsState.activeWorkspaceTab='calls';renderGroupsPanel();">Calls</button>
                    ${current.can_manage || current.is_owner ? `<button class="${floorSignalsState.activeWorkspaceTab === 'requests' ? 'group-pill-btn is-active' : 'group-pill-btn'}" type="button" onclick="floorSignalsState.activeWorkspaceTab='requests';renderGroupsPanel();">Requests${groupRequestCount ? ` (${groupRequestCount})` : ''}</button>` : ''}
                    <button class="${floorSignalsState.activeWorkspaceTab === 'settings' ? 'group-pill-btn is-active' : 'group-pill-btn'}" type="button" onclick="floorSignalsState.activeWorkspaceTab='settings';renderGroupsPanel();">Settings</button>
                </div>
                ${floorSignalsState.activeWorkspaceTab === 'members' ? `
                <div class="group-feed-card" style="margin-top:18px;">
                    <div class="group-card-kicker">Members</div>
                    <div class="group-feed-body">${(floorSignalsState.groupMembers || []).length ? floorSignalsState.groupMembers.map(member => {
                        const memberName = member.display_name || member.user_email || ('User #' + (member.user_id || ''));
                        const memberRole = member.role ? String(member.role).charAt(0).toUpperCase() + String(member.role).slice(1) : 'Member';
                        const memberStatus = member.status || 'active';
                        const memberAccess = member.access_type || (member.role === 'owner' ? 'owner' : 'member');
                        const canEditMember = !!current.is_owner && member.role !== 'owner';
                        const visibleMemberEmail = current.is_owner ? (member.user_email || '') : '';
                        return `<div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.08);align-items:center;"><div><div style="font-weight:700;color:#f5f5f5;">${memberName}</div><div style="font-size:12px;color:#a9afb8;">${memberRole} · ${memberAccess} · ${memberStatus}</div></div><div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;">${visibleMemberEmail ? `<div style="font-size:12px;color:#8e949e;">${visibleMemberEmail}</div>` : ''}${canEditMember ? `<select onchange="updateGroupMemberRole(${JSON.stringify(String(current.id || current.group_id || ''))}, ${JSON.stringify(String(member.user_id || ''))}, this.value)" style="min-height:34px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:10px;color:#f5f5f5;padding:0 10px;"><option value="member" ${member.role === 'member' ? 'selected' : ''}>Member</option><option value="analyst" ${member.role === 'analyst' ? 'selected' : ''}>Analyst</option><option value="admin" ${member.role === 'admin' ? 'selected' : ''}>Admin</option></select><button class="group-ghost-btn" type="button" onclick='removeGroupMember(${JSON.stringify(String(current.id || current.group_id || ''))}, ${JSON.stringify(String(member.user_id || ''))}, ${JSON.stringify(String(memberName))})'>Remove</button>` : ''}</div></div>`;
                    }).join('') : 'No members loaded yet.'}</div>
                </div>
                ` : ''}
                ${floorSignalsState.activeWorkspaceTab === 'room' ? (() => {
                    const roomSignals = ((floorSignalsState.groupSignalsByGroup || {})[String(current.id || current.group_id || '')] || []);
                    const signalCards = floorSignalsState.groupSignalsLoading
                        ? '<div class="group-feed-card"><div class="group-feed-body">Loading group signals…</div></div>'
                        : floorSignalsState.groupSignalsError
                            ? `<div class="group-feed-card"><div class="group-feed-body">${floorSignalsState.groupSignalsError}</div></div>`
                            : roomSignals.length
                                ? `<div class="group-signal-list">${roomSignals.map(signal => {
                                    const direction = String(signal.direction || '').toUpperCase();
                                    const status = String(signal.status || 'open').replace(/_/g, ' ');
                                    const result = signal.result ? ` · ${signal.result}` : '';
                                    const posted = signal.posted_at ? new Date(signal.posted_at.replace(' ', 'T')).toLocaleString() : 'Just now';
                                    const priceLine = [
                                        signal.entry_price != null ? `Entry ${Number(signal.entry_price).toFixed(5).replace(/\.?0+$/, '')}` : null,
                                        signal.stop_loss != null ? `SL ${Number(signal.stop_loss).toFixed(5).replace(/\.?0+$/, '')}` : null,
                                        signal.take_profit != null ? `TP ${Number(signal.take_profit).toFixed(5).replace(/\.?0+$/, '')}` : null
                                    ].filter(Boolean);
                                    return `<div class="group-signal-card"><div class="group-signal-card-header"><div><div class="group-signal-card-symbol">${signal.symbol || 'Signal'} · ${direction || 'TRADE'}</div><div class="group-signal-card-title">${signal.symbol || 'Signal'}</div></div><div><div class="group-signal-card-status">${status}${result}</div><div class="group-signal-card-levels">${priceLine.length ? priceLine.join('<br>') : 'No price levels attached.'}</div></div><div class="group-signal-card-time">Posted<br>${posted}</div></div>${signal.notes ? `<div class="group-signal-card-notes">${signal.notes}</div>` : ''}</div>`;
                                }).join('')}</div>`
                                : '<div class="group-feed-card"><div class="group-feed-body">No signals have been posted to this room yet.</div></div>';
                    return `
                <div class="group-workspace-grid">
                    <article class="group-workspace-panel group-workspace-panel--signals">
                        <div class="group-workspace-signal-head" style="display:flex;align-items:center;justify-content:flex-start;gap:12px;min-height:24px;">
                            <div class="group-workspace-tooltip" style="display:flex;align-items:center;">
                                <div class="section-kicker" tabindex="0" style="margin:0;line-height:1;">Signals</div>
                                <div class="group-workspace-tooltip-copy">See the latest posted group signals directly inside the workspace, instead of only in dashboard cards.</div>
                            </div>
                            <div class="group-workspace-signal-actions" style="display:flex;align-items:center;gap:10px;transform:translateY(2px);">
                                <button class="group-workspace-signal-action" type="button" onclick="openGroupSignalModal()" aria-label="Post signal">
                                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    <span>Post</span>
                                </button>
                                <button class="group-workspace-signal-action group-workspace-signal-action--mt5" type="button" aria-label="Automation settings">
                                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10.9 2.4a2.8 2.8 0 0 0-3.45 3.45L3.2 10.1a1.6 1.6 0 0 0 0 2.26l.44.44a1.6 1.6 0 0 0 2.26 0l4.25-4.25A2.8 2.8 0 0 0 13.6 5.1l-1.72 1.72-1.6-.18-.18-1.6L10.9 2.4Z" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span>AUTOMATION</span>
                                </button>
                            </div>
                        </div>
                        ${signalCards}
                    </article>
                    <article class="group-workspace-panel">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:24px;">
                            <div class="section-kicker" style="margin:0;line-height:1;">Messages · LIVE TEST</div>
                            <div style="font-size:12px;color:#F2CA50;line-height:1;display:flex;align-items:center;">${(((floorSignalsState.groupMessagesByGroup || {})[String(current.id || current.group_id || '')] || []).length)} in room</div>
                        </div>
                        <div class="group-feed-card" style="display:flex;flex-direction:column;gap:10px;padding:10px 9px 9px 8px;overflow:hidden;">
                            ${(() => {
                                const groupKey = String(current.id || current.group_id || '');
                                const roomMessages = ((floorSignalsState.groupMessagesByGroup || {})[groupKey] || []);
                                let messagesMarkup = '';
                                if (floorSignalsState.groupMessagesLoading) {
                                    messagesMarkup = '<div style="font-size:13px;color:#8f95a3;">Loading messages…</div>';
                                } else if (floorSignalsState.groupMessagesError) {
                                    messagesMarkup = '<div style="font-size:13px;color:#F2CA50;">' + floorSignalsState.groupMessagesError + '</div>';
                                } else if (roomMessages.length) {
                                    messagesMarkup = roomMessages.map(function (msg) {
                                        const author = msg.author_name || msg.user_name || 'Member';
                                        const timestamp = msg.created_at ? new Date(String(msg.created_at).replace(' ', 'T')).toLocaleString() : 'Just now';
                                        const initial = String(author).trim().charAt(0).toUpperCase() || 'M';
                                        const bubbleBg = String(msg.user_id || '') === String(CURRENT_USER_ID) ? 'rgba(242,202,80,0.16)' : 'rgba(255,255,255,0.08)';
                                        return '<div style="display:flex;gap:10px;align-items:flex-start;">'
                                            + '<div style="width:30px;height:30px;border-radius:999px;background:' + bubbleBg + ';display:flex;align-items:center;justify-content:center;color:#f5f5f5;font-size:12px;font-weight:800;">' + initial + '</div>'
                                            + '<div style="flex:1;">'
                                            + '<div style="display:flex;justify-content:space-between;gap:8px;"><strong style="font-size:13px;">' + author + '</strong><span style="font-size:12px;color:#8f95a3;">' + timestamp + '</span></div>'
                                            + '<div style="font-size:13px;color:#cfd4dd;line-height:1.45;white-space:pre-wrap;">' + (msg.message || '') + '</div>'
                                            + '</div>'
                                            + '</div>';
                                    }).join('');
                                } else {
                                    messagesMarkup = '<div style="font-size:13px;color:#8f95a3;">No messages yet. Start the conversation for this group.</div>';
                                }
                                return '<div style="display:flex;flex-direction:column;gap:8px;max-height:240px;overflow:auto;padding-right:2px;min-width:0;">' + messagesMarkup + '</div>'
                                    + '<div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end;width:100%;min-width:0;overflow:hidden;">'
                                    + '<textarea id="groupMessageInput" placeholder="Message" style="display:block;width:100%;min-width:0;box-sizing:border-box;min-height:44px;height:44px;max-height:160px;border-radius:22px;border:1px solid rgba(255,255,255,0.12);background:linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.028));padding:10px 18px;color:#f5f5f5;resize:vertical;box-shadow:inset 0 1px 0 rgba(255,255,255,0.05), 0 10px 24px rgba(0,0,0,0.18);font:600 13px/1.45 Montserrat,sans-serif;"></textarea>'
                                    + '<button class="group-pill-btn" type="button" onclick="sendCurrentGroupMessage()" aria-label="Send message" style="display:inline-flex;align-items:center;justify-content:center;align-self:end;white-space:nowrap;max-width:100%;min-width:52px;min-height:44px;padding:0 14px;border-radius:999px;box-shadow:0 8px 20px rgba(242,202,80,0.22);">&#8594;</button>'
                                    + '</div>';
                            })()}
                        </div>
                    </article>
                </div>
                    `;
                })() : ''}
                ${floorSignalsState.activeWorkspaceTab === 'requests' ? `
                <div class="group-feed-card" style="margin-top:18px;">
                    <div class="group-card-kicker">Requests</div>
                    <div class="group-feed-body">${requests.filter(req => String(req.group_id || '') === String(current.id || current.group_id || '')).length ? requests.filter(req => String(req.group_id || '') === String(current.id || current.group_id || '')).map(req => {
                        const requester = req.requester_name || req.requester_email || ('User #' + (req.requester_user_id || ''));
                        return `<div style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.08);align-items:center;"><div><div style="font-weight:700;color:#f5f5f5;">${requester}</div><div style="font-size:12px;color:#a9afb8;">${req.status || 'pending'}${req.message ? ' · ' + req.message : ''}</div></div><div style="display:flex;gap:8px;flex-wrap:wrap;"><button class="group-pill-btn" type="button" onclick='reviewWorkspaceJoinRequest(${JSON.stringify(String(req.id))}, "approve", ${JSON.stringify(String(current.id || current.group_id || ''))})'>Approve</button><button class="group-ghost-btn" type="button" onclick='reviewWorkspaceJoinRequest(${JSON.stringify(String(req.id))}, "reject", ${JSON.stringify(String(current.id || current.group_id || ''))})'>Reject</button></div></div>`;
                    }).join('') : 'No pending requests for this group.'}</div>
                </div>
                ` : ''}
                ${floorSignalsState.activeWorkspaceTab === 'content' ? `
                <div class="group-feed-card" style="margin-top:18px;">
                    <div class="group-card-kicker">Content</div>
                    <div class="group-feed-body">Analysis feed, files, screenshots, and structured desk content will appear here.</div>
                </div>
                ` : ''}
                ${floorSignalsState.activeWorkspaceTab === 'calls' ? `
                <div class="group-feed-card" style="margin-top:18px;">
                    <div class="group-card-kicker">Calls</div>
                    <div class="group-feed-body">Live sessions, scheduled calls, and join controls will appear here.</div>
                </div>
                ` : ''}
                <div class="group-feed-card" style="margin-top:18px;">
                    <div class="group-card-kicker">Room rules</div>
                    <div class="group-feed-body">${current.rules_text || 'Room rules and publishing guidance will appear here.'}</div>
                </div>
                <div class="group-feed-card" style="margin-top:14px;">
                    <div class="group-card-kicker">Posting rules</div>
                    <div class="group-feed-body">${rulesMeta}</div>
                </div>
                ${floorSignalsState.activeWorkspaceTab === 'settings' ? `
                <div class="group-feed-card" style="margin-top:18px;">
                    <div class="group-card-kicker">Settings</div>
                    ${current.can_manage || current.is_owner ? `
                    <form onsubmit="saveFloorSignalGroupSettings(event)" style="display:grid;gap:14px;">
                        <input type="hidden" name="group_id" value="${current.id || current.group_id || ''}">
                        <div style="display:grid;gap:8px;">
                            <div class="group-card-kicker">Group profile</div>
                            <p style="margin:0;color:#bcc1ca;font-size:13px;">Update the public identity of this group, including the title, profile image, and summary text members see.</p>
                        </div>
                        <div style="display:grid;grid-template-columns:140px minmax(0,1fr);gap:16px;align-items:start;">
                            <div style="display:grid;gap:10px;justify-items:start;">
                                <div style="width:96px;height:96px;border-radius:24px;overflow:hidden;border:1px solid rgba(255,255,255,0.12);background:#111827;display:flex;align-items:center;justify-content:center;">
                                    ${current.avatar_url ? `<img src="${current.avatar_url}" alt="${current.name || 'Group'} avatar" style="width:100%;height:100%;object-fit:cover;">` : `<span style="font-size:32px;font-weight:700;color:#f2ca50;">${String(current.name || 'G').trim().charAt(0).toUpperCase()}</span>`}
                                </div>
                                <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;width:100%;">Account picture URL
                                    <input name="avatar_url" value="${current.avatar_url || ''}" placeholder="https://..." style="min-height:42px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:0 12px;">
                                </label>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                                <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;grid-column:1 / -1;">Group name
                                    <input name="name" value="${current.name || ''}" required style="min-height:42px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:0 12px;">
                                </label>
                                <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Team / subtitle
                                    <input name="team_name" value="${current.team_name || ''}" placeholder="Desk name or short subtitle" style="min-height:42px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:0 12px;">
                                </label>
                                <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Category
                                    <input name="category" value="${current.category || ''}" placeholder="Macro, Forex, Crypto..." style="min-height:42px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:0 12px;">
                                </label>
                                <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;grid-column:1 / -1;">Short description
                                    <textarea name="description" rows="3" style="background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:12px;resize:vertical;">${current.description || ''}</textarea>
                                </label>
                                <div style="display:grid;gap:8px;grid-column:1 / -1;">
                                    <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Group color</label>
                                    ${groupColorPickerHtml(current.accent_color || groupAccentColor(current), 'accent_color')}
                                </div>
                                <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;grid-column:1 / -1;">Cover image URL
                                    <input name="cover_url" value="${current.cover_url || ''}" placeholder="https://..." style="min-height:42px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:0 12px;">
                                </label>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                            <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Join mode
                                <select name="join_mode" style="min-height:40px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:0 12px;">
                                    <option value="open" ${current.join_mode === 'open' ? 'selected' : ''}>Open</option>
                                    <option value="request" ${current.join_mode === 'request' ? 'selected' : ''}>Request</option>
                                    <option value="invite" ${current.join_mode === 'invite' ? 'selected' : ''}>Invite</option>
                                </select>
                            </label>
                            <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Visibility
                                <select name="visibility" style="min-height:40px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:0 12px;">
                                    <option value="listed" ${current.visibility === 'listed' ? 'selected' : ''}>Listed</option>
                                    <option value="unlisted" ${current.visibility === 'unlisted' ? 'selected' : ''}>Unlisted</option>
                                    <option value="private" ${current.visibility === 'private' ? 'selected' : ''}>Private</option>
                                </select>
                            </label>
                        </div>
                        <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Allowed symbols
                            <input name="allowed_symbols" value="${symbolsLabel}" placeholder="ALL or BTCUSD,ETHUSD" style="min-height:42px;background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:0 12px;">
                        </label>
                        <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Intro text
                            <textarea name="intro_text" rows="3" style="background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:12px;resize:vertical;">${current.intro_text || current.description || ''}</textarea>
                        </label>
                        <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Rules text
                            <textarea name="rules_text" rows="4" style="background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:12px;color:#f5f5f5;padding:12px;resize:vertical;">${current.rules_text || ''}</textarea>
                        </label>
                        <div style="display:flex;flex-wrap:wrap;gap:14px;color:#d7dbe2;font-size:13px;">
                            <label><input type="checkbox" name="requires_stop_loss" ${current.requires_stop_loss ? 'checked' : ''}> Requires stop loss</label>
                            <label><input type="checkbox" name="requires_take_profit" ${current.requires_take_profit ? 'checked' : ''}> Requires take profit</label>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
                            <button class="group-pill-btn" type="submit" ${isGroupsBusy(`settings:${String(current.id || current.group_id || '')}`) ? 'disabled' : ''}>${isGroupsBusy(`settings:${String(current.id || current.group_id || '')}`) ? 'Saving…' : 'Save settings'}</button>
                            ${current.is_owner ? `<button class="group-ghost-btn" type="button" ${isGroupsBusy(`archive:${String(current.id || current.group_id || '')}`) ? 'disabled' : ''} onclick='archiveFloorSignalGroup(${JSON.stringify(String(current.id || current.group_id || ''))}, ${JSON.stringify(String(current.name || 'this group'))})'>${isGroupsBusy(`archive:${String(current.id || current.group_id || '')}`) ? 'Archiving…' : 'Archive group'}</button>` : ''}
                    ${current.is_owner ? `<button class="group-ghost-btn" type="button" ${isGroupsBusy(`delete:${String(current.id || current.group_id || '')}`) ? 'disabled' : ''} onclick='deleteFloorSignalGroup(${JSON.stringify(String(current.id || current.group_id || ''))}, ${JSON.stringify(String(current.name || 'this group'))})'>${isGroupsBusy(`delete:${String(current.id || current.group_id || '')}`) ? 'Deleting…' : 'Delete group'}</button>` : ''}
                        </div>
                    </form>
                    ` : `<div class="group-feed-body">Only owners and admins can edit this group’s settings.</div>`}
                </div>
                ` : ''}
            </section>`;
        })() : '';

        const discoveryHtml = discoveryGroups.length ? discoveryGroups.map(group => {
            const accent = groupAccentColor(group);
            const joined = !!group.is_joined;
            return `
                <article class="group-card" style="--group-accent:${accent}">
                    <div class="group-card-main">
                        <div class="group-card-head">
                            ${groupAvatarHtml(group)}
                            <div class="group-card-copy">
                                <h3>${group.name}</h3>
                                <div class="group-card-meta">
                                    <span>${group.category}</span>
                                    <span>${moneyLabel(group)}</span>
                                    <span>${group.member_count} members</span>
                                    <span>${group.signal_count} signals</span>
                                </div>
                                <p>${group.description || 'No description yet.'}</p>
                            </div>
                        </div>
                    </div>
                    <div class="group-card-side">
                        <div class="group-card-badge">${verifiedBadge(group)}</div>
                        <div class="group-card-actions">
                            <button class="group-pill-btn" type="button" onclick='openFloorSignalGroup(${JSON.stringify(String(group.id))})'>Open</button>
                            <button class="group-ghost-btn" type="button" ${joined || isGroupsBusy(`join:${String(group.id)}`) ? 'disabled' : ''} onclick='joinFloorSignalGroup(${JSON.stringify(String(group.id))})'>${joined ? 'Joined' : (isGroupsBusy(`join:${String(group.id)}`) ? 'Joining…' : 'Join')}</button>
                        </div>
                    </div>
                </article>
            `;
        }).join('') : `<div class="floor-profile-note" style="padding:18px 0;">No public groups yet. Create a listed group to seed Discovery.</div>`;

        const joinedHtml = joinedGroups.length ? joinedGroups.map(m => {
            const name = m.group_name || m.name || `Group #${m.group_id || m.id}`;
            const roleLabel = m.is_owner ? 'Owner' : (m.role ? String(m.role).charAt(0).toUpperCase() + String(m.role).slice(1) : 'Member');
            const accessLabel = m.can_manage ? 'Can manage' : (m.can_post ? 'Can post' : 'Read only');
            return `
                <article class="group-row-card">
                    <div>
                        <div class="group-card-kicker">Your membership · ${roleLabel}</div>
                        <h3>${name}</h3>
                        <p>${m.visibility || 'listed'} · ${m.status || 'live'} · ${accessLabel}</p>
                    </div>
                    <div class="group-row-actions">
                        <button class="group-pill-btn" type="button" onclick='openFloorSignalGroup(${JSON.stringify(String(m.group_id || m.id))})'>Open</button>
                        ${m.can_manage ? `<button class="group-ghost-btn" type="button" onclick='openFloorSignalGroup(${JSON.stringify(String(m.group_id || m.id))})'>Manage</button>` : ''}
                        ${m.is_owner ? '' : `<button class="group-ghost-btn" type="button" onclick='leaveFloorSignalGroup(${JSON.stringify(String(m.group_id || m.id))}, ${JSON.stringify(String(name))})'>Leave</button>`}
                    </div>
                </article>
            `;
        }).join('') : `<div class="floor-profile-note" style="padding:18px 0;">You have not joined any groups yet.</div>`;

        const draftsHtml = drafts.length ? drafts.map((group, idx) => {
            const readiness = [];
            if (!String(group.description || '').trim()) readiness.push('Description');
            if (!String(group.intro_text || '').trim()) readiness.push('Intro text');
            if (!String(group.rules_text || '').trim()) readiness.push('Rules');
            if ((group.pricing_type || 'free') === 'paid' && Number(group.price || 0) <= 0) readiness.push('Price');
            const readinessHtml = readiness.length
                ? `<div style="margin-top:10px;font-size:13px;color:#f6d28b;display:flex;align-items:flex-start;gap:8px;"><span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:999px;background:rgba(245,158,11,0.18);color:#fbbf24;font-size:12px;font-weight:700;line-height:1;">!</span><span><strong>Before publishing</strong>, add: ${readiness.join(', ')}.</span></div>`
                : `<div style="margin-top:10px;font-size:13px;color:#86efac;display:flex;align-items:flex-start;gap:8px;"><span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:999px;background:rgba(34,197,94,0.16);color:#86efac;font-size:12px;font-weight:700;line-height:1;">✓</span><span>Ready to publish.</span></div>`;
            return `
            <article class="group-row-card">
                <div>
                    <div class="group-card-kicker">Draft · ${group.visibility || 'listed'}</div>
                    <h3>${group.name}</h3>
                    <p>${group.description || 'Draft group ready for review.'}</p>
                    ${readinessHtml}
                </div>
                <div class="group-row-actions">
                    <button class="group-pill-btn" type="button" onclick='openFloorSignalGroup(${JSON.stringify(String(group.id || idx))}); setTimeout(() => { floorSignalsState.activeWorkspaceTab = "settings"; renderGroupsPanel(); }, 0);'>Edit</button>
                    <button class="group-pill-btn" type="button" data-draft-publish data-group-id="${String(group.id || idx)}">Publish</button>
                </div>
            </article>
        `;
        }).join('') : `<div class="floor-profile-note" style="padding:18px 0;">No drafts yet.</div>`;

        const requestsHtml = requests.length ? requests.map(req => `
            <article class="group-row-card">
                <div>
                    <div class="group-card-kicker">Request · ${req.status || 'pending'}</div>
                    <h3>${req.requester_name || req.requester_email || ('User #' + (req.requester_user_id || ''))}</h3>
                    <p>${req.group_name || ''}${req.message ? ' · ' + req.message : ''}</p>
                </div>
                <div class="group-row-actions">
                    <button class="group-pill-btn" type="button" onclick='openFloorSignalGroup(${JSON.stringify(String(req.group_id || ''))}); setTimeout(() => { floorSignalsState.activeWorkspaceTab = "requests"; renderGroupsPanel(); }, 0);'>Open group</button>
                    <button class="group-pill-btn" type="button" onclick='reviewJoinRequest(${JSON.stringify(String(req.id))}, "approve")'>Approve</button>
                    <button class="group-ghost-btn" type="button" onclick='reviewJoinRequest(${JSON.stringify(String(req.id))}, "reject")'>Reject</button>
                </div>
            </article>
        `).join('') : `<div class="floor-profile-note" style="padding:18px 0;">No pending requests.</div>`;

        const managerHtml = `
            <div class="tf-groups-shell">
                <div class="tf-groups-header">
                    <div>
                        <div class="section-kicker">Creator workspace</div>
                        <h2>Create Group</h2>
                        <p>Build a draft, define access, and publish when ready.</p>
                    </div>
                </div>
                <form class="group-create-form" id="groupCreateForm">
                    <div class="form-grid">
                        <label>Name<input name="name" required></label>
                        <label>Team name<input name="team_name"></label>
                        <label>Category<input name="category" placeholder="Forex, Gold, Crypto"></label>
                        <label>Pricing<select name="pricing_type"><option value="free">Free</option><option value="paid">Paid</option></select></label>
                        <label>Price<input name="price" type="number" step="0.01" min="0"></label>
                        <label>Visibility<select name="visibility"><option value="listed">Listed</option><option value="unlisted">Unlisted</option><option value="private">Private</option></select></label>
                        <label>Join mode<select name="join_mode"><option value="open">Open</option><option value="request">Request</option><option value="invite">Invite</option></select></label>
                        <label>Allowed symbols<input name="allowed_symbols" placeholder="XAUUSD, EURUSD or ALL"></label>
                        <div style="grid-column:1 / -1; display:grid; gap:8px;">
                            <label style="display:grid;gap:6px;font-size:12px;color:#bfc5cf;">Group color</label>
                            ${groupColorPickerHtml('#F2CA50', 'accent_color')}
                        </div>
                    </div>
                    <label>Description<textarea name="description" rows="3"></textarea></label>
                    <label>Intro text<textarea name="intro_text" rows="3"></textarea></label>
                    <label>Rules<textarea name="rules_text" rows="4"></textarea></label>
                    <div class="group-card-actions">
                        <label><input type="checkbox" name="requires_stop_loss"> Requires stop loss</label>
                        <label><input type="checkbox" name="requires_take_profit"> Requires take profit</label>
                    </div>
                    <div class="group-card-actions">
                        <button class="group-pill-btn" type="submit">Create draft</button>
                    </div>
                </form>
            </div>`;

        mount.innerHTML = `
            <div class="tf-groups-shell tf-groups-workspace">
                ${activeView !== 'workspace' ? `
                <div class="tf-groups-header">
                    <div>
                        <h2>Groups</h2>
                        <p>Discover groups, manage memberships, create drafts, and run creator rooms.</p>
                    </div>
                </div>
                <div class="tf-groups-toolbar">
                    <button type="button" class="group-pill-btn ${activeTab === 'discovery' ? 'is-active' : ''}" onclick="switchFloorSignalsTab('discovery')">Discovery</button>
                    <button type="button" class="group-pill-btn ${activeTab === 'joined' ? 'is-active' : ''}" onclick="switchFloorSignalsTab('joined')">My Groups</button>
                    <button type="button" class="group-pill-btn ${activeTab === 'drafts' ? 'is-active' : ''}" onclick="switchFloorSignalsTab('drafts')">Drafts</button>
                    <button type="button" class="group-pill-btn ${activeTab === 'requests' ? 'is-active' : ''}" onclick="switchFloorSignalsTab('requests')">Requests${requests.length ? ` (${requests.length})` : ''}</button>
                    <button type="button" class="group-pill-btn ${activeTab === 'manager' ? 'is-active' : ''}" onclick="switchFloorSignalsTab('manager')">Create Group</button>
                </div>` : ''}
                ${floorSignalsState.error ? `<div class="floor-profile-note" style="padding:18px 0;color:#ffb4b4;">${floorSignalsState.error}</div>` : ''}
                ${activeView === 'workspace' ? workspaceHtml : ''}
                ${activeView !== 'workspace' && activeTab === 'discovery' ? `<div class="group-grid">${discoveryHtml}</div>` : ''}
                ${activeView !== 'workspace' && activeTab === 'joined' ? `<div class="group-list">${joinedHtml}</div>` : ''}
                ${activeView !== 'workspace' && activeTab === 'drafts' ? `<div class="group-list">${draftsHtml}</div>` : ''}
                ${activeView !== 'workspace' && activeTab === 'requests' ? `<div class="group-list">${requestsHtml}</div>` : ''}
                ${activeView !== 'workspace' && activeTab === 'manager' ? managerHtml : ''}
            </div>`;

        bindGroupColorPickers(mount);
        const form = document.getElementById('groupCreateForm');
        if (form) form.onsubmit = createFloorSignalGroup;
        const resetBtn = document.getElementById('groupCreateResetBtn');
        if (resetBtn) resetBtn.onclick = function () { fillCreateGroupForm({}); };
        const msg = document.getElementById('groupCreateMessage');
        if (msg) msg.textContent = floorSignalsState.createMessage || '';
    }

    function isGroupsBusy(key = 'global') {
        return floorSignalsState.busyKey === key || floorSignalsState.busyKey === 'global';
    }

    function setGroupsBusy(key) {
        floorSignalsState.busyKey = key || 'global';
    }

    function clearGroupsBusy(key = 'global') {
        if (floorSignalsState.busyKey === key || key === 'global') floorSignalsState.busyKey = '';
    }

    function fillCreateGroupForm(draft = {}) {
        floorSignalsState.activeTab = 'manager';
        renderGroupsPanel();
        setTimeout(() => {
            const form = document.getElementById('groupCreateForm');
            if (!form) return;
            if (form.name) form.name.value = draft.name || '';
            if (form.team_name) form.team_name.value = draft.team_name || '';
            if (form.description) form.description.value = draft.description || '';
            if (form.category) form.category.value = draft.category || '';
            if (form.pricing_type) form.pricing_type.value = draft.pricing_type || 'free';
            if (form.price) form.price.value = typeof draft.price === 'number' ? String(draft.price) : (draft.price || 0);
            if (form.visibility) form.visibility.value = draft.visibility || 'listed';
            if (form.join_mode) form.join_mode.value = draft.join_mode || 'open';
            if (form.intro_text) form.intro_text.value = draft.intro_text || '';
            if (form.rules_text) form.rules_text.value = draft.rules_text || '';
            if (form.requires_stop_loss) form.requires_stop_loss.checked = !!draft.requires_stop_loss;
            if (form.requires_take_profit) form.requires_take_profit.checked = !!draft.requires_take_profit;
            if (form.allowed_symbols) {
                const symbols = Array.isArray(draft.allowed_symbols)
                    ? draft.allowed_symbols
                    : String(draft.allowed_symbols_json || '').trim()
                        ? (() => { try { return JSON.parse(draft.allowed_symbols_json); } catch (_) { return []; } })()
                        : [];
                form.allowed_symbols.value = Array.isArray(symbols) ? symbols.join(', ') : '';
            }
        }, 0);
        openFloorSection('groups');
    }

    async function joinFloorSignalGroup(groupId) {
        setGroupsBusy(`join:${groupId}`);
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('join.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify({ group_id: groupId })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Joined successfully.' : 'Join failed.');
            await bootFloorSignals();
            openFloorSection('groups');
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Join failed.';
            renderGroupsPanel();
        } finally {
            clearGroupsBusy(`join:${groupId}`);
            renderGroupsPanel();
        }
    }

    async function reviewWorkspaceJoinRequest(requestId, action, groupId) {
        setGroupsBusy(`workspace-request:${requestId}`);
        floorSignalsState.createMessage = action === 'approve' ? 'Approving request…' : 'Rejecting request…';
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('approve-request.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify({ request_id: requestId, decision: action })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Request updated.' : 'Request update failed.');
            await bootFloorSignals();
            if (groupId) {
                floorSignalsState.activeGroupId = groupId;
                floorSignalsState.activeView = 'workspace';
                floorSignalsState.activeWorkspaceTab = 'requests';
                floorSignalsState.groupMembers = await loadGroupMembers(groupId);
            }
            renderGroupsPanel();
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Request update failed.';
            renderGroupsPanel();
        } finally {
            clearGroupsBusy(`workspace-request:${requestId}`);
            renderGroupsPanel();
        }
    }

    async function updateGroupMemberRole(groupId, targetUserId, role) {
        setGroupsBusy(`member-role:${targetUserId}`);
        floorSignalsState.createMessage = 'Updating member role…';
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('group-staff.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify({ group_id: groupId, target_user_id: targetUserId, role, action: 'set-role' })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Member updated.' : 'Could not update member.');
            floorSignalsState.groupMembers = await loadGroupMembers(groupId);
            renderGroupsPanel();
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Could not update member.';
            renderGroupsPanel();
        }
    }

    async function removeGroupMember(groupId, targetUserId, label) {
        const memberLabel = label || 'this member';
        if (!window.confirm(`Remove ${memberLabel} from this group?`)) return;
        floorSignalsState.createMessage = `Removing ${memberLabel}…`;
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('group-staff.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify({ group_id: groupId, target_user_id: targetUserId, action: 'remove' })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Member removed.' : 'Could not remove member.');
            floorSignalsState.groupMembers = await loadGroupMembers(groupId);
            await bootFloorSignals();
            floorSignalsState.activeView = 'workspace';
            floorSignalsState.activeWorkspaceTab = 'members';
            renderGroupsPanel();
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Could not remove member.';
            renderGroupsPanel();
        }
    }

    async function archiveFloorSignalGroup(groupId, groupName) {
        const label = groupName || 'this group';
        if (!window.confirm(`Archive ${label}? This moves the group to archived groups and removes it from active views.`)) return;
        setGroupsBusy(`archive:${groupId}`);
        floorSignalsState.createMessage = `Archiving ${label}…`;
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('archive-group.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify({ group_id: groupId, action: 'archive', status: 'archived' })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Group archived.' : 'Could not archive group.');
            if (data.success && String(floorSignalsState.activeGroupId || '') === String(groupId)) {
                floorSignalsState.activeGroupId = null;
                floorSignalsState.activeView = 'list';
                floorSignalsState.activeWorkspaceTab = 'room';
            }
            await bootFloorSignals();
            openFloorSection('groups');
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Could not archive group.';
            renderGroupsPanel();
        } finally {
            clearGroupsBusy(`archive:${groupId}`);
            renderGroupsPanel();
        }
    }

    async function deleteFloorSignalGroup(groupId, groupName) {
        const label = groupName || 'this group';
        if (!window.confirm(`Delete ${label} permanently? This cannot be undone.`)) return;
        setGroupsBusy(`delete:${groupId}`);
        floorSignalsState.createMessage = `Deleting ${label}…`;
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('archive-group.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify({ group_id: groupId, action: 'delete' })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Group deleted.' : 'Could not delete group.');
            if (data.success) {
                floorSignalsState.activeGroupId = null;
                floorSignalsState.activeView = 'list';
                floorSignalsState.activeTab = 'joined';
                floorSignalsState.activeWorkspaceTab = 'room';
            }
            await bootFloorSignals();
            openFloorSection('groups');
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Could not delete group.';
            renderGroupsPanel();
        } finally {
            clearGroupsBusy(`delete:${groupId}`);
            renderGroupsPanel();
        }
    }

    async function leaveFloorSignalGroup(groupId, groupName) {
        const label = groupName || 'this group';
        if (!window.confirm(`Leave ${label}?`)) return;
        setGroupsBusy(`leave:${groupId}`);
        floorSignalsState.createMessage = `Leaving ${label}…`;
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('leave.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify({ group_id: groupId })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Left group successfully.' : 'Could not leave group.');
            if (data && data.success && String(floorSignalsState.activeGroupId || '') === String(groupId)) {
                floorSignalsState.activeGroupId = null;
            }
            await bootFloorSignals();
            openFloorSection('groups');
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Could not leave group.';
            renderGroupsPanel();
        } finally {
            clearGroupsBusy(`leave:${groupId}`);
            renderGroupsPanel();
        }
    }

    async function saveFloorSignalGroupSettings(ev) {
        ev.preventDefault();
        const form = ev.currentTarget;
        const groupId = form.group_id.value;
        const allowedSymbolsRaw = String(form.allowed_symbols.value || '').trim();
        const allowedSymbols = allowedSymbolsRaw.toUpperCase() === 'ALL'
            ? []
            : allowedSymbolsRaw.split(',').map(s => s.trim()).filter(Boolean);
        setGroupsBusy(`settings:${groupId}`);
        floorSignalsState.createMessage = 'Saving group settings…';
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('update-group.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify({
                    group_id: groupId,
                    name: form.name.value.trim(),
                    team_name: form.team_name.value.trim(),
                    description: form.description.value.trim(),
                    category: form.category.value.trim(),
                    avatar_url: form.avatar_url.value.trim(),
                    cover_url: form.cover_url.value.trim(),
                    accent_color: form.accent_color ? form.accent_color.value.trim() : '',
                    join_mode: form.join_mode.value,
                    visibility: form.visibility.value,
                    intro_text: form.intro_text.value.trim(),
                    rules_text: form.rules_text.value.trim(),
                    requires_stop_loss: !!form.requires_stop_loss.checked,
                    requires_take_profit: !!form.requires_take_profit.checked,
                    allowed_symbols: allowedSymbols
                })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Group settings saved.' : 'Could not save group settings.');
            await bootFloorSignals();
            if (groupId) {
                floorSignalsState.activeGroupId = groupId;
                floorSignalsState.activeView = 'workspace';
                floorSignalsState.activeWorkspaceTab = 'settings';
                floorSignalsState.groupMembers = await loadGroupMembers(groupId);
            }
            openFloorSection('groups');
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Could not save group settings.';
            renderGroupsPanel();
        } finally {
            clearGroupsBusy(`settings:${groupId}`);
            renderGroupsPanel();
        }
    }

    async function createFloorSignalGroup(ev) {
        ev.preventDefault();
        const form = ev.currentTarget;
        const allowedSymbolsRaw = String(form.allowed_symbols.value || '').trim();
        const allowedSymbols = allowedSymbolsRaw.toUpperCase() === 'ALL'
            ? []
            : allowedSymbolsRaw.split(',').map(s => s.trim()).filter(Boolean);
        const payload = {
            name: form.name.value.trim(),
            team_name: form.team_name.value.trim(),
            description: form.description.value.trim(),
            category: form.category.value.trim(),
            pricing_type: form.pricing_type.value,
            price: form.pricing_type.value === 'paid' ? Number(form.price.value || 0) : 0,
            visibility: form.visibility.value,
            join_mode: form.join_mode.value,
            accent_color: form.accent_color ? form.accent_color.value.trim() : '#F2CA50',
            intro_text: form.intro_text.value.trim(),
            rules_text: form.rules_text.value.trim(),
            requires_stop_loss: !!form.requires_stop_loss.checked,
            requires_take_profit: !!form.requires_take_profit.checked,
            allowed_symbols: allowedSymbols,
        };
        floorSignalsState.createMessage = 'Creating draft group…';
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('create-group.php'), {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SIGNALS_CSRF
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Draft group created successfully.' : 'Could not create group.');
            if (data.success) {
                form.reset();
                if (form.price) form.price.value = 0;
                floorSignalsState.activeTab = 'drafts';
                floorSignalsState.activeGroupId = data.group_id || floorSignalsState.activeGroupId;
                floorSignalsState.booted = false;
                await bootFloorSignals();
            }
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Could not create group.';
        }
        renderGroupsPanel();
    }

    async function publishDraftGroup(groupId) {
        floorSignalsState.createMessage = 'Publishing group…';
        console.log('[TradingFloorDebug] publishDraftGroup start', { groupId, url: signalsUrl('publish-group.php') });
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('publish-group.php'), {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SIGNALS_CSRF },
                body: JSON.stringify({ group_id: groupId })
            });
            console.log('[TradingFloorDebug] publishDraftGroup response meta', { ok: res.ok, status: res.status, url: res.url });
            const data = await res.json().catch(() => ({}));
            console.log('[TradingFloorDebug] publishDraftGroup response body', data);
            const errors = Array.isArray(data.errors) ? data.errors.filter(Boolean) : [];
            const publishErrors = errors.length ? ' Complete these items first: ' + errors.join(' ') : '';
            floorSignalsState.createMessage = (data.message || (data.success ? 'Group published.' : 'Publish failed.')) + publishErrors;
            if (!data.success && errors.length) {
                floorSignalsState.booted = false;
                await bootFloorSignals();
                floorSignalsState.activeTab = 'drafts';
            }
            if (data.success) {
                floorSignalsState.activeTab = 'joined';
                floorSignalsState.activeGroupId = data.group_id || floorSignalsState.activeGroupId;
                floorSignalsState.booted = false;
                await bootFloorSignals();
            }
        } catch (err) {
            console.log('[TradingFloorDebug] publishDraftGroup error', err);
            floorSignalsState.createMessage = err && err.message ? err.message : 'Publish failed.';
        }
        renderGroupsPanel();
    }

    async function reviewJoinRequest(requestId, action) {
        floorSignalsState.createMessage = action === 'approve' ? 'Approving request…' : 'Rejecting request…';
        renderGroupsPanel();
        try {
            const res = await fetch(signalsUrl('approve-request.php'), {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SIGNALS_CSRF },
                body: JSON.stringify({ request_id: requestId, action })
            });
            const data = await res.json().catch(() => ({}));
            floorSignalsState.createMessage = data.message || (data.success ? 'Request updated.' : 'Request update failed.');
            if (data.success) {
                floorSignalsState.booted = false;
                await bootFloorSignals();
            }
        } catch (err) {
            floorSignalsState.createMessage = err && err.message ? err.message : 'Request update failed.';
        }
        renderGroupsPanel();
    }

    async function loadGroupAnalytics(groupId) {
        try {
            const res = await fetch(`${signalsUrl('group-analytics.php')}?group_id=${encodeURIComponent(groupId)}`, {
                credentials: 'include',
                headers: { 'X-CSRF-Token': SIGNALS_CSRF }
            });
            return await res.json().catch(() => ({}));
        } catch (err) {
            return { success: false, message: err && err.message ? err.message : 'Analytics unavailable.' };
        }
    }

    async function loadGroupStaff(groupId) {
        try {
            const res = await fetch(`${signalsUrl('group-staff.php')}?group_id=${encodeURIComponent(groupId)}`, {
                credentials: 'include',
                headers: { 'X-CSRF-Token': SIGNALS_CSRF }
            });
            return await res.json().catch(() => ({}));
        } catch (err) {
            return { success: false, message: err && err.message ? err.message : 'Staff unavailable.' };
        }
    }

    async function inviteGroupStaff(groupId, email, role) {
        const res = await fetch(signalsUrl('invite.php'), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SIGNALS_CSRF },
            body: JSON.stringify({ group_id: groupId, invitee: email, role })
        });
        return await res.json().catch(() => ({}));
    }

    async function createGroupSignal(payload) {
        const res = await fetch(signalsUrl('create-signal.php'), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SIGNALS_CSRF },
            body: JSON.stringify(payload)
        });
        return await res.json().catch(() => ({}));
    }

    async function updateGroupSignal(payload) {
        const res = await fetch(signalsUrl('update-signal.php'), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SIGNALS_CSRF },
            body: JSON.stringify(payload)
        });
        return await res.json().catch(() => ({}));
    }

    async function closeGroupSignal(payload) {
        const res = await fetch(signalsUrl('close-signal.php'), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SIGNALS_CSRF },
            body: JSON.stringify(payload)
        });
        return await res.json().catch(() => ({}));
    }

    async function updateOwnedGroup(payload) {
        const res = await fetch(signalsUrl('update-group.php'), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SIGNALS_CSRF },
            body: JSON.stringify(payload)
        });
        return await res.json().catch(() => ({}));
    }

    async function archiveOwnedGroup(groupId) {
        const res = await fetch(signalsUrl('archive-group.php'), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': SIGNALS_CSRF },
            body: JSON.stringify({ group_id: groupId })
        });
        return await res.json().catch(() => ({}));
    }



    const SIGNALS_CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    const CURRENT_USER_ID = <?php echo json_encode((string)($_SESSION['user_id'] ?? '')); ?>;
    const SIGNALS_API_BASE = window.location.origin + '/api/signals';
    function signalsUrl(path) {
        return `${SIGNALS_API_BASE}/${String(path).replace(/^\/+/, '')}`;
    }
    const floorSignalsState = { groups: [], memberships: [], activeGroupId: null, activeTab: 'discovery', activeView: 'list', activeWorkspaceTab: 'room', groupMembers: [], groupMembersError: '', groupMessagesByGroup: {}, groupMessagesLoading: false, groupMessagesError: '', groupSignalsByGroup: {}, groupSignalsLoading: false, groupSignalsError: '', loading: false, booted: false, creating: false, error: '', csrf: SIGNALS_CSRF, myDrafts: [], joinMessage: '', createMessage: '', postingSignal: false, busyKey: '' };

    // Legacy duplicate Groups renderer (groupFeedState + second renderGroupsPanel/switchFloorSignalsTab/bootFloorSignals) removed.

    document.addEventListener('DOMContentLoaded', function () {
        const groupsNav = document.getElementById('groupsNavLink');
        console.log('[TradingFloor] DOMContentLoaded, groupsNav present:', !!groupsNav);
        if (groupsNav) {
            groupsNav.addEventListener('click', function () {
                console.log('[TradingFloor] event listener groups click fired');
            });
        }
    });


    document.addEventListener('click', function (event) {
        const editBtn = event.target.closest('[data-draft-edit]');
        if (editBtn) {
            const draftKey = editBtn.getAttribute('data-draft-key') || '';
            const draftMap = floorSignalsState.__draftMap || {};
            fillCreateGroupForm(draftMap[draftKey] || {});
            return;
        }
    });

    // Mini sparkline charts
    document.querySelectorAll('.mini-chart').forEach(canvas => {
        const win = canvas.dataset.win === '1';
        const ctx = canvas.getContext('2d');
        const W = canvas.offsetWidth || 600;
        const H = canvas.offsetHeight || 120;
        canvas.width = W; canvas.height = H;
        const pts = 60, data = [];
        let v = 50;
        const trend = win ? 0.4 : -0.4;
        for (let i = 0; i < pts; i++) {
            v += (Math.random() - 0.48 + trend * (i/pts)) * 2.2;
            v = Math.max(10, Math.min(90, v));
            data.push(v);
        }
        const min = Math.min(...data), max = Math.max(...data);
        const pad = 10;
        const scale = v => H - pad - ((v - min) / (max - min + 0.01)) * (H - pad * 2);
        const xStep = W / (pts - 1);
        const color = win ? '#4ade80' : '#f87171';
        const grad = ctx.createLinearGradient(0, 0, 0, H);
        grad.addColorStop(0, color + '22'); grad.addColorStop(1, color + '00');
        ctx.beginPath(); ctx.moveTo(0, scale(data[0]));
        for (let i = 1; i < pts; i++) ctx.lineTo(i * xStep, scale(data[i]));
        ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath();
        ctx.fillStyle = grad; ctx.fill();
        ctx.beginPath(); ctx.moveTo(0, scale(data[0]));
        for (let i = 1; i < pts; i++) ctx.lineTo(i * xStep, scale(data[i]));
        ctx.strokeStyle = color; ctx.lineWidth = 1.5; ctx.stroke();
        [[' ENTRY', W*0.22, scale(data[Math.floor(pts*0.22)]), '#F2CA50'],
         [' EXIT',  W*0.85, scale(data[Math.floor(pts*0.85)]), color]].forEach(([l,x,y,c])=>{
            ctx.beginPath(); ctx.arc(x, y, 4, 0, Math.PI*2); ctx.fillStyle = c; ctx.fill();
            ctx.font = '700 8px Montserrat,sans-serif'; ctx.fillStyle = c+'cc'; ctx.fillText(l, x+7, y+3);
        });
    });

    // Story viewer
    const storiesData = <?= json_encode($stories) ?>;
    let currentStory = 0, storyTimer = null;
    function openStory(idx) { currentStory = idx; renderStory(); document.getElementById('storyOverlay').classList.add('active'); startStoryTimer(); }
    function renderStory() {
        const s = storiesData[currentStory];
        document.getElementById('storyProgressBar').innerHTML = storiesData.map((_,i) =>
            `<div class="story-progress-segment"><div class="story-progress-fill ${i<currentStory?'done':i===currentStory?'active':''}"></div></div>`
        ).join('');
        document.getElementById('storyContent').innerHTML = `
            <div class="story-user-row">
                <div class="story-user-mini-avatar">${s.init}</div>
                <div><div class="story-user-mini-name">${s.name}</div><div class="story-user-mini-time">${s.time} ago</div></div>
            </div>
            <div class="story-trade-symbol">${s.symbol}</div>
            <div class="story-trade-pnl ${s.win?'win':'loss'}">${s.pnl}</div>
            <div class="story-trade-detail">${s.dir==='LONG'?'▲ LONG':'▼ SHORT'} · 24h Story</div>`;
    }
    function startStoryTimer() {
        clearTimeout(storyTimer);
        storyTimer = setTimeout(() => {
            if (currentStory < storiesData.length - 1) { currentStory++; renderStory(); startStoryTimer(); }
            else closeStory();
        }, 5000);
    }
    function handleStoryClick(e) {
        const viewer = document.getElementById('storyViewer');
        if (!viewer.contains(e.target)) { closeStory(); return; }
        const mid = viewer.getBoundingClientRect().left + viewer.getBoundingClientRect().width / 2;
        if (e.clientX > mid) { if (currentStory < storiesData.length-1){currentStory++;renderStory();startStoryTimer();}else closeStory(); }
        else { if (currentStory > 0){currentStory--;renderStory();startStoryTimer();} }
    }
    function closeStory() { clearTimeout(storyTimer); document.getElementById('storyOverlay').classList.remove('active'); }

    // Search
    function toggleSearch() {
        const o = document.getElementById('searchOverlay');
        const open = o.classList.toggle('active');
        document.getElementById('searchBtn').classList.toggle('active', open);
        if (open) setTimeout(() => document.getElementById('searchInput').focus(), 50);
    }

    // DM
    function toggleDM() { document.getElementById('dmPanel').classList.toggle('open'); }

    // Create modal
    const tfAjaxEndpoint = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;
    const tfFeedInitialPosts = <?php echo wp_json_encode($home_feed_posts); ?>;
    const tfProfileInitialPosts = <?php echo wp_json_encode($profile_posts); ?>;
    const tfViewedUserId = <?php echo (int) $view_user_id; ?>;
    const tfCurrentUserId = <?php echo (int) $user_id; ?>;
    let tfCreateType = 'trade';
    let tfSubmittingPost = false;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatPnlBadge(post) {
        if (post.pnl_value === null || post.pnl_value === undefined || post.pnl_value === '') return '';
        const numeric = Number(post.pnl_value);
        if (Number.isNaN(numeric)) return '';
        const cls = numeric >= 0 ? 'style="color:#6ee7b7;"' : 'style="color:#fda4af;"';
        const prefix = numeric > 0 ? '+' : '';
        return `<span ${cls}>${prefix}${numeric.toFixed(2)}%</span>`;
    }

    function renderSocialPostCard(post, compact = false) {
        const typeLabel = post.post_type === 'analysis' ? 'Analysis' : 'Trade';
        const author = escapeHtml(post.author_name || 'Trader');
        const caption = escapeHtml(post.caption || '');
        const symbol = escapeHtml(post.symbol || '');
        const direction = escapeHtml(post.direction || '');
        const rr = escapeHtml(post.rr_value || '');
        const time = escapeHtml(post.created_label || 'Just now');
        const image = post.image_url ? `<div style="margin-top:12px;"><img src="${escapeHtml(post.image_url)}" alt="Post chart" style="width:100%;border-radius:14px;border:1px solid rgba(255,255,255,0.08);object-fit:cover;max-height:${compact ? '180px' : '320px'};"></div>` : '';
        const metaBits = [symbol, direction, rr ? `R:R ${rr}` : '', formatPnlBadge(post)].filter(Boolean).join(' · ');
        return `<article class="group-feed-card" data-post-id="${escapeHtml(post.id)}"><div class="group-feed-top"><div><div class="group-card-kicker">${typeLabel}</div><div class="group-feed-title" style="font-size:${compact ? '16px' : '18px'};">${author}</div><div class="group-feed-meta">${time}</div></div></div>${metaBits ? `<div class="group-feed-body" style="margin-top:8px;color:#f2ca50;">${metaBits}</div>` : ''}${caption ? `<div class="group-feed-body">${caption.replace(/\n/g, '<br>')}</div>` : ''}${image}</article>`;
    }

    function renderHomeFeedPosts(posts) {
        const host = document.getElementById('tfHomeFeedPosts');
        if (!host) return;
        if (!Array.isArray(posts) || !posts.length) {
            host.innerHTML = '<div class="group-feed-card"><div class="group-feed-body">No posts yet. Use CREATE to publish the first trade or analysis.</div></div>';
            return;
        }
        host.innerHTML = posts.map(post => renderSocialPostCard(post, false)).join('');
    }

    function renderProfilePosts(posts) {
        const host = document.getElementById('profileFeedGrid');
        if (!host) return;
        if (!Array.isArray(posts) || !posts.length) {
            host.innerHTML = '<div class="group-feed-card" style="grid-column:1/-1;"><div class="group-feed-body">No posts published yet.</div></div>';
            return;
        }
        host.innerHTML = posts.map(post => renderSocialPostCard(post, true)).join('');
    }

    function setCreateFormStatus(message, isError = false) {
        const el = document.getElementById('createFormStatus');
        if (!el) return;
        if (!message) {
            el.style.display = 'none';
            el.textContent = '';
            return;
        }
        el.style.display = 'block';
        el.style.color = isError ? '#fda4af' : '#a9afb8';
        el.textContent = message;
    }

    function applyCreateTabUi(tab) {
        document.querySelectorAll('.create-tab').forEach(t => t.classList.remove('active'));
        const activeTab = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        if (activeTab) activeTab.classList.add('active');
        const submit = document.getElementById('createSubmitBtn');
        const symbol = document.getElementById('createSymbol');
        const direction = document.getElementById('createDirection');
        const pnl = document.getElementById('createPnl');
        const rr = document.getElementById('createRr');
        if (submit) submit.textContent = tab === 'analysis' ? 'Post Analysis' : 'Post Trade';
        const isTrade = tab === 'trade';
        if (symbol) symbol.disabled = !isTrade;
        if (direction) direction.disabled = !isTrade;
        if (pnl) pnl.disabled = !isTrade;
        if (rr) rr.disabled = !isTrade;
    }

    function openCreateModal(type='post') {
        document.getElementById('createModal').classList.add('active');
        switchCreateTab(type);
        setCreateFormStatus('');
    }
    function closeCreateModal() { document.getElementById('createModal').classList.remove('active'); }
    document.getElementById('createModal').addEventListener('click', e => { if(e.target===document.getElementById('createModal'))closeCreateModal(); });
    function switchCreateTab(tab) {
        tfCreateType = tab === 'analysis' ? 'analysis' : 'trade';
        applyCreateTabUi(tfCreateType);
        setCreateFormStatus(tfCreateType === 'analysis' ? 'Analysis posts use caption and optional image.' : 'Trade posts use symbol, direction, performance and caption.');
    }

    const createPostForm = document.getElementById('createPostForm');
    if (createPostForm) {
        createPostForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (tfSubmittingPost) return;
            const submitBtn = document.getElementById('createSubmitBtn');
            const payload = new FormData(createPostForm);
            payload.append('action', 'tf_create_post');
            payload.append('post_type', tfCreateType);
            tfSubmittingPost = true;
            if (submitBtn) submitBtn.disabled = true;
            setCreateFormStatus('Publishing post...');
            try {
                const response = await fetch(tfAjaxEndpoint, { method: 'POST', body: payload, credentials: 'same-origin' });
                const data = await response.json();
                if (!data || !data.success || !data.post) {
                    throw new Error(data && data.message ? data.message : 'Could not publish post.');
                }
                tfFeedInitialPosts.unshift(data.post);
                renderHomeFeedPosts(tfFeedInitialPosts);
                if (Number(data.post.user_id || 0) === Number(tfViewedUserId || 0)) {
                    tfProfileInitialPosts.unshift(data.post);
                    renderProfilePosts(tfProfileInitialPosts);
                }
                createPostForm.reset();
                switchCreateTab(tfCreateType);
                setCreateFormStatus('Post published successfully.');
                setTimeout(() => { closeCreateModal(); setCreateFormStatus(''); }, 700);
            } catch (error) {
                setCreateFormStatus(error && error.message ? error.message : 'Could not publish post.', true);
            } finally {
                tfSubmittingPost = false;
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    renderHomeFeedPosts(tfFeedInitialPosts);
    renderProfilePosts(tfProfileInitialPosts);

    function getActiveSignalGroup() {
        const activeId = floorSignalsState.activeGroupId;
        const groups = Array.isArray(floorSignalsState.groups) ? floorSignalsState.groups : [];
        const memberships = Array.isArray(floorSignalsState.memberships) ? floorSignalsState.memberships : [];
        return groups.find(g => String(g.id) === String(activeId))
            || memberships.find(m => String(m.group_id || m.id) === String(activeId))
            || null;
    }

    function openGroupSignalModal() {
        const modal = document.getElementById('groupSignalModal');
        const form = document.getElementById('groupSignalForm');
        const status = document.getElementById('groupSignalStatus');
        const current = getActiveSignalGroup();
        if (!modal || !form) return;
        form.reset();
        floorSignalsState.postingSignal = false;
        if (status) {
            status.textContent = current ? `Posting to ${current.group_name || current.name || 'selected group'}.` : 'Posting to the selected group.';
        }
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        const firstField = document.getElementById('groupSignalSymbol');
        if (firstField) setTimeout(() => firstField.focus(), 20);
    }

    function closeGroupSignalModal() {
        const modal = document.getElementById('groupSignalModal');
        const status = document.getElementById('groupSignalStatus');
        if (!modal) return;
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        if (status) status.textContent = '';
    }

<?php
if (isset($_POST['action']) && $_POST['action'] === 'tf_create_post') {
    if (!$user_id) {
        wp_send_json(['success' => false, 'message' => 'You must be logged in to post.']);
    }

    $post_type = sanitize_key($_POST['post_type'] ?? 'trade');
    if (!in_array($post_type, ['trade', 'analysis'], true)) {
        $post_type = 'trade';
    }

    $symbol = strtoupper(sanitize_text_field($_POST['symbol'] ?? ''));
    $direction = strtoupper(sanitize_text_field($_POST['direction'] ?? ''));
    $caption = trim(wp_kses_post($_POST['caption'] ?? ''));
    $rr_value = sanitize_text_field($_POST['rr_value'] ?? '');
    $pnl_value = isset($_POST['pnl_value']) && $_POST['pnl_value'] !== '' ? (float) $_POST['pnl_value'] : null;

    if ($caption === '') {
        wp_send_json(['success' => false, 'message' => 'Caption is required.']);
    }
    if ($post_type === 'trade' && $symbol === '') {
        wp_send_json(['success' => false, 'message' => 'Symbol is required for trade posts.']);
    }

    $image_url = '';
    $image_path = '';
    if (!empty($_FILES['image']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload($_FILES['image'], ['test_form' => false]);
        if (!empty($upload['error'])) {
            wp_send_json(['success' => false, 'message' => $upload['error']]);
        }
        $image_url = (string) ($upload['url'] ?? '');
        $image_path = (string) ($upload['file'] ?? '');
    }

    $inserted = $wpdb->insert($post_table, [
        'user_id' => $user_id,
        'post_type' => $post_type,
        'symbol' => $symbol ?: null,
        'direction' => $direction ?: null,
        'pnl_value' => $pnl_value,
        'rr_value' => $rr_value ?: null,
        'caption' => $caption,
        'image_url' => $image_url ?: null,
        'image_path' => $image_path ?: null,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['%d','%s','%s','%s','%f','%s','%s','%s','%s','%s','%s']);

    if (!$inserted) {
        wp_send_json(['success' => false, 'message' => 'Database error while creating the post.']);
    }

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, u.display_name, u.user_nicename, u.user_login
         FROM {$post_table} p
         LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
         WHERE p.id = %d
         LIMIT 1",
        (int) $wpdb->insert_id
    ));

    wp_send_json(['success' => true, 'post' => tf_format_social_post($row, wp_get_current_user()->display_name ?: 'Trader')]);
}
?>

    const groupSignalModal = document.getElementById('groupSignalModal');
    if (groupSignalModal) {
        groupSignalModal.addEventListener('click', e => {
            if (e.target === groupSignalModal) closeGroupSignalModal();
        });
    }

    const groupSignalForm = document.getElementById('groupSignalForm');
    if (groupSignalForm) {
        groupSignalForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (floorSignalsState.postingSignal) return;
            const current = getActiveSignalGroup();
            const status = document.getElementById('groupSignalStatus');
            const allowedEl = document.getElementById('groupSignalAllowedSymbols');
            const submitBtn = groupSignalForm.querySelector('button[type="submit"]');
            const symbolEl = document.getElementById('groupSignalSymbol');
            const sideEl = document.getElementById('groupSignalSide');
            const allowedRaw = String(current?.allowed_symbols_json || current?.allowed_symbols || '').trim();
            let allowedSymbols = [];
            if (allowedRaw) {
                try {
                    const parsed = JSON.parse(allowedRaw);
                    if (Array.isArray(parsed)) allowedSymbols = parsed;
                } catch (_) {
                    allowedSymbols = allowedRaw.split(',').map(s => s.trim()).filter(Boolean);
                }
            }
            const isAll = !allowedSymbols.length || allowedSymbols.some(s => String(s).trim().toUpperCase() === 'ALL');
            if (allowedEl) {
                allowedEl.textContent = isAll ? 'Allowed symbols: ALL' : `Allowed symbols: ${allowedSymbols.map(s => String(s).trim().toUpperCase()).filter(Boolean).join(', ')}`;
            }
            if (!current || !(current.group_id || current.id)) {
                if (status) status.textContent = 'No active group selected.';
                return;
            }
            const formData = new FormData(groupSignalForm);
            const payload = {
                group_id: String(current.group_id || current.id),
                symbol: String((formData.get('symbol') || (symbolEl && symbolEl.value) || '')).trim(),
                direction: String((formData.get('side') || (sideEl && sideEl.value) || '')).trim().toUpperCase() === 'BUY' ? 'LONG' : (String((formData.get('side') || (sideEl && sideEl.value) || '')).trim().toUpperCase() === 'SELL' ? 'SHORT' : ''),
                entry_price: String(formData.get('entry') || '').trim(),
                stop_loss: String(formData.get('stop_loss') || '').trim(),
                take_profit: String(formData.get('take_profit') || '').trim(),
                timeframe: String(formData.get('timeframe') || '').trim(),
                risk_note: String(formData.get('risk_note') || '').trim(),
                status: String(formData.get('status') || 'open').trim() === 'pending' ? 'open' : String(formData.get('status') || 'open').trim(),
                notes: String(formData.get('message') || '').trim(),
                session_name: String(formData.get('timeframe') || '').trim(),
                source: 'manual_group_entry'
            };
            console.log('[TradingFloorDebug] signal form payload', payload);
            if (!payload.symbol || !payload.direction) {
                if (status) status.textContent = `Symbol and direction are required. symbol="${payload.symbol}" direction="${payload.direction}"`;
                return;
            }
            if (!isAll) {
                const normalizedSymbol = payload.symbol.toUpperCase();
                const allowedUpper = allowedSymbols.map(s => String(s).trim().toUpperCase()).filter(Boolean);
                if (!allowedUpper.includes(normalizedSymbol)) {
                    if (status) status.textContent = `This group only allows: ${allowedUpper.join(', ')}.`;
                    floorSignalsState.postingSignal = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Post signal';
                    }
                    return;
                }
            }
            floorSignalsState.postingSignal = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Posting...';
            }
            if (status) status.textContent = 'Saving signal...';
            try {
                const res = await createGroupSignal(payload);
                console.log('[TradingFloorDebug] createGroupSignal response', res);
                if (res && res.success) {
                    if (status) status.textContent = res.message || 'Signal posted successfully.';
                    await bootFloorSignals();
                    setTimeout(() => closeGroupSignalModal(), 450);
                } else {
                    if (status) status.textContent = (res && res.message) ? res.message : 'Signal could not be posted.';
                }
            } catch (err) {
                if (status) status.textContent = err && err.message ? err.message : 'Signal could not be posted.';
            } finally {
                floorSignalsState.postingSignal = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Post signal';
                }
            }
        });
    }

    // Interactions
    function toggleLike(btn) { btn.classList.toggle('liked'); const s=btn.querySelector('.like-count'),n=parseInt(s.textContent); s.textContent=btn.classList.contains('liked')?n+1:n-1; }
    function toggleBookmark(btn) { btn.classList.toggle('bookmarked'); }
    function toggleFollow(btn) { btn.classList.toggle('following'); btn.textContent=btn.classList.contains('following')?'Following':'Follow'; }

    document.addEventListener('keydown', e => { if(e.key==='Escape'){closeStory();document.getElementById('searchOverlay').classList.remove('active');closeCreateModal();closeGroupSignalModal();} });
    document.addEventListener('click', function (event) {
        const publishBtn = event.target.closest('[data-draft-publish]');
        if (publishBtn) {
            const groupId = publishBtn.getAttribute('data-group-id') || '';
            console.log('[TradingFloorDebug] draft publish click', { groupId });
            event.preventDefault();
            publishDraftGroup(groupId);
            return;
        }
    });
    </script>

</body>
</html>
