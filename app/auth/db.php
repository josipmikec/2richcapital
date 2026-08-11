<?php
// /auth/db.php — PDO connection for app.2rich.capital

if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            'mysql:unix_socket=/var/lib/mysql/mysql.sock;dbname=richcapit_wp_2rich_capital;charset=utf8mb4',
            'richcapit_wp_2rich_capital',
            'ewWwsKqQVYmnKjrUvRq4',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('2Rich DB connection failed: ' . $e->getMessage());
        $pdo = null;
    }
}
