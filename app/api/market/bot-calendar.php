<?php
// app/api/market/bot-calendar.php
require_once dirname(__DIR__, 3) . '/wp-load.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// We need an API key
if (!isset($_GET['api_key']) || empty($_GET['api_key'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Missing api_key parameter']));
}

$api_key = sanitize_text_field($_GET['api_key']);

// Find user by this API key
$users = get_users([
    'meta_key' => '2rich_calendar_api_key',
    'meta_value' => $api_key,
    'number' => 1
]);

if (empty($users)) {
    http_response_code(401);
    die(json_encode(['error' => 'Invalid API key']));
}

// Key is valid! Return the calendar
// To be safe and fast, we just grab the existing WordPress transient.
// The bot doesn't need to specify weeks or bust the cache. We just give it the current week's cached events.
$cached = get_transient('tworich_economic_calendar_this_week');

if ($cached === false) {
    // If cache is empty, we don't want the bot to hit Forex Factory directly to avoid spam.
    // They can just wait 1 minute for the backend or a user to populate it.
    die(json_encode([]));
}

// Optional: filter by impact if the bot requests it ?impact=High
$min_impact = isset($_GET['impact']) ? sanitize_text_field($_GET['impact']) : null;

$filtered = [];
foreach ($cached as $event) {
    // Format utc for the bot just in case they don't read standard unix timestamps properly
    if (!isset($event['utc'])) {
        continue;
    }
    
    // Impact filter
    if ($min_impact) {
        if (strtolower($event['impact']) !== strtolower($min_impact)) {
            continue;
        }
    }
    
    // Currency filter (?currency=USD)
    if (isset($_GET['currency'])) {
        $currencies = explode(',', strtoupper(sanitize_text_field($_GET['currency'])));
        if (!in_array(strtoupper($event['currency']), $currencies, true)) {
            continue;
        }
    }

    $filtered[] = $event;
}

echo json_encode($filtered);
