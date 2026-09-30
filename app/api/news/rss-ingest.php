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
    'investing.com' => 'https://www.investing.com/rss/news.rss',
    'barchart'      => 'https://www.barchart.com/news/authors/rss',
    'barchart '     => 'https://www.barchart.com/news/rss/commodities',
    'barchart  '    => 'https://www.barchart.com/news/rss/financials',
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

set_time_limit(0); // Allow script to run up to 3 minutes for spacing

$inserted = 0;
$pending_inserts = [];

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
        
        $pubDate = (string)$item->pubDate;
        if ($pubDate) {
            $timestamp = strtotime($pubDate);
            // Skip articles older than 2 hours
            if ($timestamp && $timestamp < time() - 7200) {
                continue;
            }
        }
        
        // Use crc32 to generate a numeric ID that fits in BIGINT or VARCHAR(20)
        $discord_id = (string) abs(crc32($guid));
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE discord_id = %s",
            $discord_id
        ));
        
        if (!$existing) {
            $message = esc_html($title) . " <a href='" . esc_url($link) . "' target='_blank' style='color:#a9afb8;display:inline-flex;align-items:center;margin-left:4px;' title='Read more'><svg width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'></path><polyline points='15 3 21 3 21 9'></polyline><line x1='10' y1='14' x2='21' y2='3'></line></svg></a>";
            
            $pending_inserts[] = [
                'title'      => $title,
                'message'    => $message,
                'author'     => ucfirst(trim($author)),
                'discord_id' => $discord_id
            ];
        }
    }
}

$total_new = count($pending_inserts);
if ($total_new > 0) {
    // 3 minutes = 180 seconds. Use 170 to leave a small buffer before next cron runs.
    $delay_seconds = floor(170 / $total_new);
    if ($delay_seconds < 2) $delay_seconds = 2; // minimum 2 seconds spacing
    if ($delay_seconds > 60) $delay_seconds = 60; // don't space them out *too* much if there are very few
    
    echo "Found $total_new new articles. Spacing inserts by $delay_seconds seconds...\n";
    
    foreach ($pending_inserts as $index => $data) {
        $result = $wpdb->insert($table, [
            'message'    => $data['message'],
            'author'     => $data['author'],
            'discord_id' => $data['discord_id'],
            'created_at' => current_time('mysql')
        ]);
        
        if ($result) {
            $inserted++;
            echo "Inserted: {$data['title']}\n";
        } else {
            echo "DB Error inserting {$data['title']}: " . $wpdb->last_error . "\n";
        }
        
        // Sleep for all but the last item
        if ($index < $total_new - 1) {
            sleep($delay_seconds);
        }
    }
}

echo "Total inserted: $inserted\n";
