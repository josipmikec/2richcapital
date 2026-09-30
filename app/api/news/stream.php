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
ob_start();
define('WP_USE_THEMES', false);
require_once '../../../wp-load.php';
ob_end_clean();

session_write_close(); // Release lock AFTER wp-load to prevent plugins from holding it

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

$is_reconnect = isset($_GET['since']) && (int)$_GET['since'] > 0;
$sent_ids = [];
$first_batch_done = false;

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
             WHERE created_at <= %s
             ORDER BY created_at DESC
             LIMIT 200",
            current_time('mysql')
        ),
        ARRAY_A
    );
    
    // If it's the very first load (no reconnect), we send DESC so the frontend's prepend() puts oldest at top.
    // Otherwise, we are doing real-time or reconnect pushes (appendChild), so we send ASC (oldest first) so newest goes to very bottom.
    $is_initial_load = (!$is_reconnect && !$first_batch_done);
    if (!$is_initial_load) {
        $new_rows = array_reverse($new_rows);
    }

    foreach ($new_rows as $row) {
        $id = (int)$row['id'];
        if (!in_array($id, $sent_ids)) {
            $data = json_encode([
                'id' => $id,
                'message' => $row['message'],
                'author' => $row['author'],
                'created_at' => $row['created_at'],
                'initial' => $is_initial_load
            ]);

            echo "id: {$id}\n";
            echo "data: {$data}\n\n";

            $sent_ids[] = $id;
        }
    }
    
    $first_batch_done = true;

    echo ": heartbeat\n\n";
    flush();

    if (connection_aborted()) {
        break;
    }

    sleep(3);
}
?>
