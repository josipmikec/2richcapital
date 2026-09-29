<?php
if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== '2c08820cf91fa7c45403c8772922ccbbd3e874d3a9a135b9d99d91622beb883e')) {
    http_response_code(401);
    die('Unauthorized');
}
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'app.2rich.capital';
    $_SERVER['SERVER_NAME'] = 'app.2rich.capital';
    $_SERVER['REQUEST_URI'] = '/';
}
define('WP_USE_THEMES', false);
require_once(dirname(__DIR__, 3) . '/wp-load.php');

$feeds = [
    'investing.com' => 'https://www.investing.com/rss/news_1.rss',
    'investinglive' => 'https://investinglive.com/feed/'
];

global $wpdb;
$table = $wpdb->prefix . 'rich_news_feed';

function fetch_rss($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code != 200) return false;
    return $data;
}

$inserted = 0;

foreach ($feeds as $author => $url) {
    echo "Fetching $url...\n";
    $rss_content = fetch_rss($url);
    if (!$rss_content) {
        echo "Failed to fetch $url\n";
        continue;
    }
    
    $rss = @simplexml_load_string($rss_content);
    if (!$rss || !isset($rss->channel->item)) {
        echo "Failed to parse XML for $url\n";
        continue;
    }
    
    $items = [];
    foreach ($rss->channel->item as $item) {
        $items[] = $item;
    }
    
    // Take the top 10 to avoid huge inserts initially
    $items = array_slice($items, 0, 10);
    $items = array_reverse($items);
    
    foreach ($items as $item) {
        $title = (string)$item->title;
        $link = (string)$item->link;
        $guid = (string)$item->guid;
        if (!$guid) $guid = $link;
        
        // Use crc32 to generate a numeric ID that fits in BIGINT or VARCHAR(20)
        $discord_id = (string) abs(crc32($guid));
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE discord_id = %s",
            $discord_id
        ));
        
        if (!$existing) {
            $message = "<strong>" . esc_html($title) . "</strong>\n<a href='" . esc_url($link) . "' target='_blank'>Read more</a>";
            
            $result = $wpdb->insert($table, [
                'message'    => $message,
                'author'     => ucfirst($author),
                'discord_id' => $discord_id,
                'created_at' => current_time('mysql')
            ]);
            
            if ($result) {
                $inserted++;
                echo "Inserted: $title\n";
            } else {
                echo "DB Error inserting $title: " . $wpdb->last_error . "\n";
            }
        }
    }
}

echo "Total inserted: $inserted\n";
