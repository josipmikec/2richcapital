<?php
define('WP_USE_THEMES', false); 
require_once dirname(__DIR__, 3) . '/wp-load.php'; 
global $wpdb; 
header('Content-Type: application/json; charset=utf-8'); 

$sync_table = $wpdb->prefix . 'rich_market_sync_state'; 
$symbols_table = $wpdb->prefix . 'rich_market_symbols'; 

$rows = $wpdb->get_results("
    SELECT s.*, sym.mt5_symbol, sym.display_symbol
    FROM {$sync_table} s
    LEFT JOIN {$symbols_table} sym ON s.symbol_id = sym.id
    ORDER BY s.last_attempt_at DESC 
    LIMIT 200
", ARRAY_A); 

wp_send_json(['ok'=>true,'states'=>$rows?:[]]);
