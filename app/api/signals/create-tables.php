<?php
// Run this file ONCE (while logged in) to create the Signals tables + seed starter groups
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

// Table 1: Signal Groups (discovery catalogue)
$groups_table = $wpdb->prefix . 'rich_signal_groups';
$sql1 = "CREATE TABLE IF NOT EXISTS $groups_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    team_name VARCHAR(120) NULL,
    description VARCHAR(255) NULL,
    pricing_type ENUM('free','paid') NOT NULL DEFAULT 'free',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    member_count INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 999,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY slug_idx (slug),
    INDEX active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Table 2: Memberships (who has joined which group)
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$sql2 = "CREATE TABLE IF NOT EXISTS $memberships_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_group_idx (user_id, group_id),
    INDEX user_idx (user_id),
    INDEX group_idx (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Table 3: Signals (the feed content posted into a group)
$signals_table = $wpdb->prefix . 'rich_signals';
$sql3 = "CREATE TABLE IF NOT EXISTS $signals_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    direction ENUM('BUY','SELL') NOT NULL,
    entry_price DECIMAL(12,4) NULL,
    stop_loss DECIMAL(12,4) NULL,
    take_profit DECIMAL(12,4) NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    result ENUM('win','loss','pending') NOT NULL DEFAULT 'pending',
    notes VARCHAR(255) NULL,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX group_idx (group_id),
    INDEX posted_idx (posted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

dbDelta($sql1);
dbDelta($sql2);
dbDelta($sql3);

// Seed a few starter groups so Discovery isn't empty
$seed_groups = [
    ['slug' => 'gold-desk',        'name' => 'Gold Desk',         'team_name' => '2RICH Gold Desk',      'description' => 'XAUUSD-focused intraday signals from the core analyst team.', 'pricing_type' => 'free', 'price' => 0,  'display_order' => 1],
    ['slug' => 'fx-majors',        'name' => 'FX Majors',         'team_name' => '2RICH FX Team',        'description' => 'Swing and intraday setups across EUR, GBP, and JPY pairs.',      'pricing_type' => 'free', 'price' => 0,  'display_order' => 2],
    ['slug' => 'index-futures',    'name' => 'Index Futures Pro', 'team_name' => '2RICH Futures Desk',   'description' => 'Premium NQ and ES scalps with full risk parameters.',           'pricing_type' => 'paid', 'price' => 49, 'display_order' => 3],
    ['slug' => 'crypto-signals',   'name' => 'Crypto Signals',    'team_name' => '2RICH Digital Assets', 'description' => 'BTC and ETH swing signals with weekly bias updates.',           'pricing_type' => 'paid', 'price' => 29, 'display_order' => 4],
];

foreach ($seed_groups as $g) {
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$groups_table} WHERE slug = %s", $g['slug']));
    if (!$exists) {
        $wpdb->insert($groups_table, $g);
    }
}

$tables_exist = [
    'rich_signal_groups'       => $wpdb->get_var("SHOW TABLES LIKE '$groups_table'") === $groups_table,
    'rich_signal_memberships'  => $wpdb->get_var("SHOW TABLES LIKE '$memberships_table'") === $memberships_table,
    'rich_signals'             => $wpdb->get_var("SHOW TABLES LIKE '$signals_table'") === $signals_table,
];

$all_ok = !in_array(false, $tables_exist, true);

echo json_encode([
    'success' => $all_ok,
    'message' => $all_ok ? 'Signals tables created and seeded successfully!' : 'Failed to create one or more tables',
    'tables'  => $tables_exist
]);
