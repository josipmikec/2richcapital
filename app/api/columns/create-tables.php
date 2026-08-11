<?php
// Run this file ONCE to create the custom columns tables
ob_start();
require_once '../../auth/session-config.php';
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');
ob_end_clean();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

global $wpdb;

// Table 1: Custom Column Definitions
$custom_columns_table = $wpdb->prefix . 'rich_custom_columns';
$sql1 = "CREATE TABLE IF NOT EXISTS $custom_columns_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    column_name VARCHAR(100) NOT NULL,
    column_key VARCHAR(50) NOT NULL,
    data_type ENUM('text', 'number', 'checkbox', 'select') DEFAULT 'text',
    select_options TEXT NULL,
    display_order INT DEFAULT 999,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX user_idx (user_id),
    INDEX key_idx (column_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Table 2: Custom Column Values
$custom_values_table = $wpdb->prefix . 'rich_custom_column_values';
$sql2 = "CREATE TABLE IF NOT EXISTS $custom_values_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trade_id INT NOT NULL,
    column_id INT NOT NULL,
    value TEXT,
    INDEX trade_idx (trade_id),
    INDEX column_idx (column_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

dbDelta($sql1);
dbDelta($sql2);

// Verify tables were created
$tables_exist = [
    'custom_columns' => $wpdb->get_var("SHOW TABLES LIKE '$custom_columns_table'") === $custom_columns_table,
    'custom_values' => $wpdb->get_var("SHOW TABLES LIKE '$custom_values_table'") === $custom_values_table
];

if ($tables_exist['custom_columns'] && $tables_exist['custom_values']) {
    echo json_encode([
        'success' => true,
        'message' => 'Custom columns tables created successfully!',
        'tables' => $tables_exist
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create one or more tables',
        'tables' => $tables_exist
    ]);
}
?>
