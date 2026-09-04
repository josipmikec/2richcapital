<?php
// Run this file ONCE (while logged in as an admin) to migrate the Signals schema to the
// Group Creation Protocol: owner, roles, visibility/status, requests, invites, audit log, metrics.
// Safe to run multiple times — every ALTER checks for column existence first, dbDelta is idempotent.
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

$groups_table       = $wpdb->prefix . 'rich_signal_groups';
$memberships_table  = $wpdb->prefix . 'rich_signal_memberships';
$requests_table     = $wpdb->prefix . 'rich_signal_group_requests';
$invites_table       = $wpdb->prefix . 'rich_signal_group_invites';
$audit_table         = $wpdb->prefix . 'rich_signal_group_audit_log';
$reports_table       = $wpdb->prefix . 'rich_signal_group_reports';
$metrics_table       = $wpdb->prefix . 'rich_signal_group_metrics_daily';

$log = [];

function rich_column_exists($wpdb, $table, $column) {
    $found = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM {$table} LIKE %s", $column
    ));
    return !empty($found);
}

function rich_add_column($wpdb, $table, $column, $definition, &$log) {
    if (!rich_column_exists($wpdb, $table, $column)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$definition}");
        $log[] = "Added column {$column} to {$table}";
    } else {
        $log[] = "Column {$column} already exists on {$table} (skipped)";
    }
}

// ── 1. Extend rich_signal_groups ──────────────────────────────────────────
rich_add_column($wpdb, $groups_table, 'owner_user_id',        "owner_user_id INT NULL AFTER id", $log);
rich_add_column($wpdb, $groups_table, 'visibility',           "visibility ENUM('listed','unlisted','private') NOT NULL DEFAULT 'listed' AFTER pricing_type", $log);
rich_add_column($wpdb, $groups_table, 'join_mode',            "join_mode ENUM('open','request','invite') NOT NULL DEFAULT 'open' AFTER visibility", $log);
rich_add_column($wpdb, $groups_table, 'status',               "status ENUM('draft','pending_review','live','suspended','archived') NOT NULL DEFAULT 'draft' AFTER join_mode", $log);
rich_add_column($wpdb, $groups_table, 'category',             "category VARCHAR(60) NULL AFTER status", $log);
rich_add_column($wpdb, $groups_table, 'avatar_url',           "avatar_url VARCHAR(255) NULL AFTER category", $log);
rich_add_column($wpdb, $groups_table, 'cover_url',             "cover_url VARCHAR(255) NULL AFTER avatar_url", $log);
rich_add_column($wpdb, $groups_table, 'intro_text',            "intro_text TEXT NULL AFTER cover_url", $log);
rich_add_column($wpdb, $groups_table, 'rules_text',            "rules_text TEXT NULL AFTER intro_text", $log);
rich_add_column($wpdb, $groups_table, 'requires_stop_loss',    "requires_stop_loss TINYINT(1) NOT NULL DEFAULT 0 AFTER rules_text", $log);
rich_add_column($wpdb, $groups_table, 'requires_take_profit',  "requires_take_profit TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_stop_loss", $log);
rich_add_column($wpdb, $groups_table, 'allowed_symbols_json',  "allowed_symbols_json TEXT NULL AFTER requires_take_profit", $log);
rich_add_column($wpdb, $groups_table, 'posted_signals_count',  "posted_signals_count INT NOT NULL DEFAULT 0 AFTER allowed_symbols_json", $log);
rich_add_column($wpdb, $groups_table, 'api_key',               "api_key VARCHAR(64) NULL AFTER posted_signals_count", $log);
rich_add_column($wpdb, $groups_table, 'last_signal_at',        "last_signal_at TIMESTAMP NULL AFTER posted_signals_count", $log);
rich_add_column($wpdb, $groups_table, 'verification_status',  "verification_status ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none' AFTER last_signal_at", $log);
rich_add_column($wpdb, $groups_table, 'verification_note',    "verification_note VARCHAR(500) NULL AFTER verification_status", $log);
rich_add_column($wpdb, $groups_table, 'verification_requested_at', "verification_requested_at TIMESTAMP NULL AFTER verification_note", $log);
rich_add_column($wpdb, $groups_table, 'verified_at',           "verified_at TIMESTAMP NULL AFTER verification_requested_at", $log);
rich_add_column($wpdb, $groups_table, 'verified_by',           "verified_by INT NULL AFTER verified_at", $log);

// Backfill: any existing seeded groups become 'live' + 'listed' + owned by nobody (system groups, owner_user_id NULL)
$wpdb->query("UPDATE {$groups_table} SET status = 'live' WHERE status = 'draft' AND is_active = 1");

// Index for status/visibility filtering used by Discovery
$existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$groups_table} WHERE Key_name = 'status_visibility_idx'");
if (empty($existing_indexes)) {
    $wpdb->query("ALTER TABLE {$groups_table} ADD INDEX status_visibility_idx (status, visibility)");
    $log[] = "Added index status_visibility_idx to {$groups_table}";
} else {
    $log[] = "Index status_visibility_idx already exists (skipped)";
}

// ── 2. Extend rich_signal_memberships ─────────────────────────────────────
rich_add_column($wpdb, $memberships_table, 'role',            "role ENUM('owner','admin','analyst','member') NOT NULL DEFAULT 'member' AFTER group_id", $log);
rich_add_column($wpdb, $memberships_table, 'access_type',     "access_type ENUM('free','paid','comped') NOT NULL DEFAULT 'free' AFTER status", $log);
rich_add_column($wpdb, $memberships_table, 'billing_status',  "billing_status VARCHAR(40) NULL AFTER access_type", $log);
rich_add_column($wpdb, $memberships_table, 'approved_by',     "approved_by INT NULL AFTER billing_status", $log);
rich_add_column($wpdb, $memberships_table, 'approved_at',     "approved_at TIMESTAMP NULL AFTER approved_by", $log);
rich_add_column($wpdb, $memberships_table, 'cancelled_at',    "cancelled_at TIMESTAMP NULL AFTER approved_at", $log);

// ── 2. Extend rich_signals ────────────────────────────────────────────────
rich_add_column($wpdb, $signals_table, 'external_id', "external_id VARCHAR(120) NULL AFTER result", $log);

// ── 3. New tables ──────────────────────────────────────────────────────────
$sql_requests = "CREATE TABLE IF NOT EXISTS {$requests_table} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
    message VARCHAR(255) NULL,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_group_idx (user_id, group_id),
    INDEX group_status_idx (group_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql_invites = "CREATE TABLE IF NOT EXISTS {$invites_table} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    code VARCHAR(40) NOT NULL,
    created_by INT NOT NULL,
    max_uses INT NULL,
    use_count INT NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NULL,
    revoked TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY code_idx (code),
    INDEX group_idx (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql_audit = "CREATE TABLE IF NOT EXISTS {$audit_table} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    actor_user_id INT NOT NULL,
    action VARCHAR(80) NOT NULL,
    meta_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX group_idx (group_id),
    INDEX action_idx (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql_reports = "CREATE TABLE IF NOT EXISTS {$reports_table} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    reported_by INT NOT NULL,
    reason VARCHAR(120) NOT NULL,
    details VARCHAR(500) NULL,
    status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX group_idx (group_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$sql_metrics = "CREATE TABLE IF NOT EXISTS {$metrics_table} (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    metric_date DATE NOT NULL,
    member_count INT NOT NULL DEFAULT 0,
    new_members INT NOT NULL DEFAULT 0,
    signals_posted INT NOT NULL DEFAULT 0,
    win_rate DECIMAL(5,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY group_date_idx (group_id, metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql_requests);
dbDelta($sql_invites);
dbDelta($sql_audit);
dbDelta($sql_reports);
dbDelta($sql_metrics);

$log[] = 'Ensured tables: ' . $requests_table . ', ' . $invites_table . ', ' . $audit_table . ', ' . $reports_table . ', ' . $metrics_table;

// ── 4. Backfill existing memberships: any existing active membership on a group
// with no owner should NOT be auto-promoted. Owners are only ever set explicitly
// going forward via create-group.php. This migration only adds columns/tables.

$tables_exist = [
    $requests_table => $wpdb->get_var("SHOW TABLES LIKE '$requests_table'") === $requests_table,
    $invites_table  => $wpdb->get_var("SHOW TABLES LIKE '$invites_table'") === $invites_table,
    $audit_table    => $wpdb->get_var("SHOW TABLES LIKE '$audit_table'") === $audit_table,
    $reports_table  => $wpdb->get_var("SHOW TABLES LIKE '$reports_table'") === $reports_table,
    $metrics_table  => $wpdb->get_var("SHOW TABLES LIKE '$metrics_table'") === $metrics_table,
];

$all_ok = !in_array(false, $tables_exist, true);

echo json_encode([
    'success' => $all_ok,
    'message' => $all_ok ? 'Migration completed successfully.' : 'Some tables failed to create — check log.',
    'tables'  => $tables_exist,
    'log'     => $log,
], JSON_PRETTY_PRINT);
