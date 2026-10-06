<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;

$sync = $wpdb->prefix . 'rich_market_sync_state';
$wpdb->query("TRUNCATE TABLE {$sync}");
echo "Successfully wiped sync state for ALL symbols. The EA will now backfill everything.";
