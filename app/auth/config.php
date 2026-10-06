<?php
// Load WordPress to get DB credentials and settings
if (!defined('ABSPATH')) {
    define('WP_USE_THEMES', false);
    require_once dirname(__DIR__, 2) . '/wp-load.php';
}

function get_db_connection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        if (strpos(DB_HOST, 'sock') !== false) {
            $parts = explode(':', DB_HOST);
            if (count($parts) > 1) {
                $dsn = "mysql:unix_socket=" . $parts[1] . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            }
        }
        $conn = new PDO(
            $dsn,
            DB_USER,
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}
?>