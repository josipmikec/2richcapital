<?php
require 'wp-load.php';
$url = 'https://nfs.faireconomy.media/ff_calendar_thisweek.json';
$response = wp_remote_get($url, ['timeout' => 15, 'headers' => ['User-Agent' => 'Mozilla/5.0']]);
if (is_wp_error($response)) {
    echo "WP ERROR: " . $response->get_error_message();
} else {
    echo "Status: " . wp_remote_retrieve_response_code($response) . "\n";
    $body = wp_remote_retrieve_body($response);
    echo "Body start: " . substr($body, 0, 100) . "\n";
}
