<?php
// Database credentials from WordPress
define('DB_HOST', 'localhost:/var/lib/mysql/mysql.sock');
define('DB_NAME', 'richcapit_wp_2rich_capital');
define('DB_USER', 'richcapit_wp_2rich_capital');
define('DB_PASS', 'ewWwsKqQVYmnKjrUvRq4');
define('DB_PREFIX', 'wp_');  // Usually 'wp_' but check your WordPress

// Create connection
function get_db_connection() {
    try {
        $conn = new PDO(
            "mysql:unix_socket=/var/lib/mysql/mysql.sock;dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
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