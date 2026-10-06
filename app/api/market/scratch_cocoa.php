<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;

$symbols = $wpdb->prefix . 'rich_market_symbols';
$sync = $wpdb->prefix . 'rich_market_sync_state';

// Find COCOA
$row = $wpdb->get_row("SELECT id FROM {$symbols} WHERE display_symbol='COCOA' OR mt5_symbol='COCOA' LIMIT 1", ARRAY_A);
if ($row) {
    $symbol_id = (int)$row['id'];
    $wpdb->query($wpdb->prepare("DELETE FROM {$sync} WHERE symbol_id = %d", $symbol_id));
    echo "Successfully wiped sync state for COCOA (Symbol ID: {$symbol_id}). The EA will now backfill it.";
} else {
    echo "COCOA not found.";
}
