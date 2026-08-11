<?php
require_once '../../auth/session-config.php';

if (
    (!isset($_SESSION['userid']) && !isset($_SESSION['user_id'])) ||
    !isset($_SESSION['authenticated'])
) {
    http_response_code(401);
    exit;
}

$user_id = (int) ($_SESSION['userid'] ?? $_SESSION['user_id']);
session_write_close();

ob_start();
define('WP_USE_THEMES', false);
require_once '../../../wp-load.php';
ob_end_clean();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Buffering: no');

@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', 1);

while (ob_get_level() > 0) {
    @ob_end_flush();
}
ob_implicit_flush(true);

global $wpdb;
$table = $wpdb->prefix . 'rich_news_feed';

$last_id = isset($_GET['since']) ? (int) $_GET['since'] : 0;

if ($last_id === 0) {
    $rows = $wpdb->get_results(
        "SELECT id, message, author, created_at
         FROM {$table}
         ORDER BY created_at DESC
         LIMIT 30",
        ARRAY_A
    );

    foreach ($rows as $row) {
        $data = json_encode([
            'id' => (int) $row['id'],
            'message' => $row['message'],
            'author' => $row['author'],
            'created_at' => $row['created_at'],
            'initial' => true
        ]);

        echo "id: {$row['id']}\n";
        echo "data: {$data}\n\n";

        $last_id = max($last_id, (int) $row['id']);
    }

    flush();
}

$max_runtime = 55;
$start = time();

while (true) {
    if (time() - $start >= $max_runtime) {
        echo "event: reconnect\n";
        echo "data: {}\n\n";
        flush();
        break;
    }

    $new_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, message, author, created_at
             FROM {$table}
             WHERE id > %d
             ORDER BY created_at ASC",
            $last_id
        ),
        ARRAY_A
    );

    foreach ($new_rows as $row) {
        $data = json_encode([
            'id' => (int) $row['id'],
            'message' => $row['message'],
            'author' => $row['author'],
            'created_at' => $row['created_at'],
            'initial' => false
        ]);

        echo "id: {$row['id']}\n";
        echo "data: {$data}\n\n";

        $last_id = max($last_id, (int) $row['id']);
    }

    echo ": heartbeat\n\n";
    flush();

    if (connection_aborted()) {
        break;
    }

    sleep(3);
}
?>
