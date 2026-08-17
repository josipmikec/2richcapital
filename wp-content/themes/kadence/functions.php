<?php
/**
 * Kadence functions and definitions
 *
 * This file must be parseable by PHP 5.2.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package kadence
 */

define( 'KADENCE_VERSION', '1.4.5' );
define( 'KADENCE_MINIMUM_WP_VERSION', '6.0' );
define( 'KADENCE_MINIMUM_PHP_VERSION', '7.4' );

// Bail if requirements are not met.
if ( version_compare( $GLOBALS['wp_version'], KADENCE_MINIMUM_WP_VERSION, '<' ) || version_compare( phpversion(), KADENCE_MINIMUM_PHP_VERSION, '<' ) ) {
	require get_template_directory() . '/inc/back-compat.php';
	return;
}
// Include WordPress shims.
require get_template_directory() . '/inc/wordpress-shims.php';

// Load the `kadence()` entry point function.
require get_template_directory() . '/inc/class-theme.php';

// Load the `kadence()` entry point function.
require get_template_directory() . '/inc/functions.php';

// Initialize the theme.
call_user_func( 'Kadence\kadence' );

// =============================================================================
// 2RICH CAPITAL — Research Technical Engine (Twelve Data proxy)
// =============================================================================

if ( ! function_exists( 'tworich_research_send_cors_headers' ) ) {
    function tworich_research_send_cors_headers() {
        $allowed_origins = array(
            'https://app.2rich.capital',
            'https://2rich.capital',
            'https://app.2rich.test',
            'http://app.2rich.test',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        );

        if ( isset( $_SERVER['HTTP_ORIGIN'] ) && in_array( $_SERVER['HTTP_ORIGIN'], $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN'] );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-WP-Nonce' );
            header( 'Vary: Origin' );
        }
    }
}

if ( ! function_exists( 'tworich_research_respond_to_preflight' ) ) {
    function tworich_research_respond_to_preflight() {
        tworich_research_send_cors_headers();
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
            status_header( 204 );
            exit;
        }
    }
}

add_action( 'init', 'tworich_research_respond_to_preflight', 0 );

if ( ! function_exists( 'tworich_research_allow_http_origins' ) ) {
    function tworich_research_allow_http_origins( $origins ) {
        $extra_origins = array(
            'https://app.2rich.capital',
            'https://2rich.capital',
            'https://app.2rich.test',
            'http://app.2rich.test',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        );

        return array_values( array_unique( array_merge( $origins, $extra_origins ) ) );
    }
}

add_filter( 'allowed_http_origins', 'tworich_research_allow_http_origins' );

if ( ! function_exists( 'tworich_research_get_remote_json' ) ) {
    function tworich_research_get_remote_json( $url ) {
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) {
            return array( 'error' => 'fetch_failed' );
        }
        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) {
            return array( 'error' => 'empty_response' );
        }
        $data = json_decode( $body, true );
        if ( ! is_array( $data ) ) {
            return array( 'error' => 'bad_json' );
        }
        return $data;
    }
}

// Live quote endpoint (cached 5 seconds)
function tworich_research_quote_endpoint() {
    tworich_research_send_cors_headers();
    header( 'Content-Type: application/json; charset=utf-8' );

    $cache_key = 'tworich_research_xauusd_quote';
    $cached    = get_transient( $cache_key );
    if ( $cached ) {
        echo wp_json_encode( $cached );
        wp_die();
    }

    if ( ! defined( 'TWORICH_RESEARCH_TWELVEDATA_KEY' ) ) {
        echo wp_json_encode( array( 'error' => 'missing_api_key' ) );
        wp_die();
    }

    $url  = 'https://api.twelvedata.com/quote?symbol=XAU/USD&apikey=' . urlencode( TWORICH_RESEARCH_TWELVEDATA_KEY );
    $data = tworich_research_get_remote_json( $url );

    set_transient( $cache_key, $data, 5 );

    echo wp_json_encode( $data );
    wp_die();
}
add_action( 'wp_ajax_nopriv_tworich_research_quote', 'tworich_research_quote_endpoint' );
add_action( 'wp_ajax_tworich_research_quote', 'tworich_research_quote_endpoint' );

// Weekly time_series endpoint (cached 10 minutes)
function tworich_research_timeseries_endpoint() {
    tworich_research_send_cors_headers();
    header( 'Content-Type: application/json; charset=utf-8' );

    $cache_key = 'tworich_research_xauusd_timeseries';
    $cached    = get_transient( $cache_key );
    if ( $cached ) {
        echo wp_json_encode( $cached );
        wp_die();
    }

    if ( ! defined( 'TWORICH_RESEARCH_TWELVEDATA_KEY' ) ) {
        echo wp_json_encode( array( 'error' => 'missing_api_key' ) );
        wp_die();
    }

    $url  = 'https://api.twelvedata.com/time_series?symbol=XAU/USD&interval=1week&outputsize=9&apikey=' . urlencode( TWORICH_RESEARCH_TWELVEDATA_KEY );
    $data = tworich_research_get_remote_json( $url );

    set_transient( $cache_key, $data, 600 );

    echo wp_json_encode( $data );
    wp_die();
}
add_action( 'wp_ajax_nopriv_tworich_research_timeseries', 'tworich_research_timeseries_endpoint' );
add_action( 'wp_ajax_tworich_research_timeseries', 'tworich_research_timeseries_endpoint' );

// Helper: fetch latest daily closes for XAU/USD from Twelve Data (newest first)
function tworich_research_get_daily_closes_xauusd( $needed = 220 ) {
    if ( ! defined( 'TWORICH_RESEARCH_TWELVEDATA_KEY' ) ) {
        return null;
    }

    $url  = 'https://api.twelvedata.com/time_series'
          . '?symbol=' . urlencode( 'XAU/USD' )
          . '&interval=1day'
          . '&outputsize=' . intval( $needed )
          . '&apikey=' . urlencode( TWORICH_RESEARCH_TWELVEDATA_KEY );

    $data = tworich_research_get_remote_json( $url );

    if ( ! is_array( $data ) ) {
        return null;
    }
    if ( ! isset( $data['values'] ) || ! is_array( $data['values'] ) ) {
        return null;
    }

    $values = $data['values'];
    $closes = array();

    foreach ( $values as $row ) {
        if ( isset( $row['close'] ) ) {
            $closes[] = (float) $row['close'];
        }
    }

    if ( empty( $closes ) ) {
        return null;
    }

    return $closes;
}

// Daily 200 SMA endpoint (cached 10 minutes)
function tworich_research_sma200_endpoint() {
    tworich_research_send_cors_headers();
    header( 'Content-Type: application/json; charset=utf-8' );

    $cache_key = 'tworich_research_xauusd_sma200';
    $cached    = get_transient( $cache_key );
    if ( $cached ) {
        echo wp_json_encode( $cached );
        wp_die();
    }

    $closes = tworich_research_get_daily_closes_xauusd( 220 );
    if ( ! is_array( $closes ) || count( $closes ) < 200 ) {
        echo wp_json_encode( array( 'error' => 'not_enough_data' ) );
        wp_die();
    }

    $sum   = 0.0;
    $count = 0;

    for ( $i = 0; $i < 200; $i++ ) {
        $sum  += $closes[ $i ];
        $count++;
    }

    if ( $count === 0 ) {
        echo wp_json_encode( array( 'error' => 'no_closes' ) );
        wp_die();
    }

    $sma = $sum / $count;

    $result = array(
        'sma'   => $sma,
        'count' => $count,
    );

    set_transient( $cache_key, $result, 600 );

    echo wp_json_encode( $result );
    wp_die();
}
add_action( 'wp_ajax_nopriv_tworich_research_sma200', 'tworich_research_sma200_endpoint' );
add_action( 'wp_ajax_tworich_research_sma200', 'tworich_research_sma200_endpoint' );

// Helper: compute 14-period RSI from an array of closes (newest first)
function tworich_research_compute_rsi_14( array $closes ) {
    if ( count( $closes ) < 15 ) {
        return null;
    }

    $closes_rev = array_reverse( $closes );

    $gains  = array();
    $losses = array();

    for ( $i = 1; $i <= 14; $i++ ) {
        $delta = $closes_rev[ $i ] - $closes_rev[ $i - 1 ];
        if ( $delta > 0 ) {
            $gains[]  = $delta;
            $losses[] = 0.0;
        } else {
            $gains[]  = 0.0;
            $losses[] = -$delta;
        }
    }

    $avg_gain = array_sum( $gains ) / 14.0;
    $avg_loss = array_sum( $losses ) / 14.0;

    $total = count( $closes_rev );
    for ( $i = 15; $i < $total; $i++ ) {
        $delta = $closes_rev[ $i ] - $closes_rev[ $i - 1 ];
        $gain  = $delta > 0 ? $delta : 0.0;
        $loss  = $delta < 0 ? -$delta : 0.0;

        $avg_gain = ( ( $avg_gain * 13.0 ) + $gain ) / 14.0;
        $avg_loss = ( ( $avg_loss * 13.0 ) + $loss ) / 14.0;
    }

    if ( $avg_loss == 0.0 ) {
        return 100.0;
    }

    $rs  = $avg_gain / $avg_loss;
    $rsi = 100.0 - ( 100.0 / ( 1.0 + $rs ) );

    return $rsi;
}

// Daily RSI endpoint (uses same daily closes)
function tworich_research_rsi_endpoint() {
    tworich_research_send_cors_headers();
    header( 'Content-Type: application/json; charset=utf-8' );

    $closes = tworich_research_get_daily_closes_xauusd( 100 );

    if ( ! is_array( $closes ) || count( $closes ) < 15 ) {
        echo wp_json_encode( array( 'error' => 'not_enough_data' ) );
        wp_die();
    }

    $rsi = tworich_research_compute_rsi_14( $closes );

    if ( $rsi === null ) {
        echo wp_json_encode( array( 'error' => 'rsi_failed' ) );
        wp_die();
    }

    echo wp_json_encode( array(
        'rsi'    => $rsi,
        'period' => 14,
    ) );
    wp_die();
}
add_action( 'wp_ajax_nopriv_tworich_research_rsi', 'tworich_research_rsi_endpoint' );
add_action( 'wp_ajax_tworich_research_rsi', 'tworich_research_rsi_endpoint' );

// ==========================================
// LIVE MACRO FEED - FINNHUB NEWS (FIXED)
// ==========================================

define('TWORICH_FINNHUB_API_KEY', 'd87nmkpr01qmhakgk7mgd87nmkpr01qmhakgk7n0'); // Replace with your key

function tworich_macro_feed_ajax() {
    $cache_key = 'tworich_macro_feed';
    $cached = get_transient($cache_key);
    
    if ($cached) {
        wp_send_json($cached);
    }
    
    $api_key = TWORICH_FINNHUB_API_KEY;
    $url = "https://finnhub.io/api/v1/news?category=general&token={$api_key}";
    
    $response = wp_remote_get($url, ['timeout' => 15]);
    
    if (is_wp_error($response)) {
        wp_send_json([]);
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    // Check for errors or invalid response
    if (!is_array($data) || isset($data['error'])) {
        error_log('Finnhub API Error: ' . print_r($data, true));
        wp_send_json([]);
    }
    
    $headlines = [];
    
    foreach ($data as $item) {
        // Skip if missing required fields
        if (!isset($item['datetime']) || !isset($item['headline'])) {
            continue;
        }
        
        // Convert unix timestamp to DateTime
        $event_time = new DateTime('@' . $item['datetime']);
        $event_time->setTimezone(new DateTimeZone('America/New_York'));
        
        $headlines[] = [
            'timestamp' => $event_time->getTimestamp(),
            'time' => $event_time->format('H:i:s'),
            'text' => $item['headline'],
            'url' => $item['url'] ?? '#',
        ];
    }
    
    // Sort by newest first
    usort($headlines, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // Take top 5
    $headlines = array_slice($headlines, 0, 5);
    
    // Remove timestamp from output
    foreach ($headlines as &$headline) {
        unset($headline['timestamp']);
    }
    
    // Only cache if we have results
    if (!empty($headlines)) {
        set_transient($cache_key, $headlines, 10 * MINUTE_IN_SECONDS);
    }
    
    wp_send_json($headlines);
}

add_action('wp_ajax_tworich_macro_feed', 'tworich_macro_feed_ajax');
add_action('wp_ajax_nopriv_tworich_macro_feed', 'tworich_macro_feed_ajax');

// Manual cache clear
add_action('init', function () {
    if (isset($_GET['clear_macro_cache']) && current_user_can('manage_options')) {
        delete_transient('tworich_macro_feed');
        echo 'Macro feed cache cleared!';
        exit;
    }
});

// Debug endpoint
add_action('init', function () {
    if (isset($_GET['debug_macro_feed']) && current_user_can('manage_options')) {
        $api_key = TWORICH_FINNHUB_API_KEY;
        $url = "https://finnhub.io/api/v1/news?category=general&token={$api_key}";
        
        $response = wp_remote_get($url, ['timeout' => 15]);
        $body = wp_remote_retrieve_body($response);
        
        header('Content-Type: application/json');
        echo $body;
        exit;
    }
});

// =============================================================================
// 2RICH CAPITAL — Economic Calendar (Forex Factory feed proxy)
// =============================================================================

function tworich_economic_calendar_cors() {
    $allowed_origins = [
        'https://app.2rich.capital',
        'https://2rich.capital',
        'https://www.2rich.capital',
    ];

    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

    if (in_array($origin, $allowed_origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }

    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(204);
        exit;
    }
}

function tworich_economic_calendar_ajax() {
    tworich_economic_calendar_cors();

    // ── Week range param ─────────────────────────────────────────────────
    $week = isset($_GET['week']) && $_GET['week'] === 'next_week' ? 'next_week' : 'this_week';

    $cache_key = 'tworich_economic_calendar_' . $week;

    // Allow frontend to bust the transient cache
    $force_refresh = isset($_GET['bust']) && !empty($_GET['bust']);

    // Check if any cached events are imminent (within ±30 min of now in NY time).
    // Only skip cache logic for this_week — next_week events are never imminent.
    if (!$force_refresh && $week === 'this_week') {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $ny_now      = new DateTime('now', new DateTimeZone('America/New_York'));
            $window_start = clone $ny_now;
            $window_start->modify('-30 minutes');
            $window_end   = clone $ny_now;
            $window_end->modify('+30 minutes');

            $has_imminent = false;
            foreach ($cached as $ev) {
                $time_clean = preg_replace('/\s*(EST|EDT|ET)\s*/i', '', $ev['time'] ?? '');
                $year = $ny_now->format('Y');
                try {
                    $ev_dt = new DateTime($ev['date'] . ' ' . $year . ' ' . $time_clean, new DateTimeZone('America/New_York'));
                    if ($ev_dt >= $window_start && $ev_dt <= $window_end) {
                        $has_imminent = true;
                        break;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            if (!$has_imminent) {
                wp_send_json($cached);
                return;
            }
        }
    } elseif (!$force_refresh && $week === 'next_week') {
        // Next week: no imminent-event bypass needed — use cache normally
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            wp_send_json($cached);
            return;
        }
    }

    // ── Feed URL: this week or next week ─────────────────────────────────
    if ($week === 'next_week') {
        $url = 'https://nfs.faireconomy.media/ff_calendar_nextweek.json';
    } else {
        $url = 'https://nfs.faireconomy.media/ff_calendar_thisweek.json';
    }

    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => ['User-Agent' => 'Mozilla/5.0'],
    ]);

    if (is_wp_error($response)) {
        wp_send_json([]);
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data)) {
        wp_send_json([]);
        return;
    }

    $events = [];

    foreach ($data as $event) {
        if (!isset($event['date'])) continue;

        $impact = isset($event['impact']) ? $event['impact'] : 'Low';

        try {
            $event_time = new DateTime($event['date']);
            $event_time->setTimezone(new DateTimeZone('America/New_York'));

            $previous = (isset($event['previous']) && $event['previous'] !== '') ? $event['previous'] : '-';
            $forecast = (isset($event['forecast']) && $event['forecast'] !== '') ? $event['forecast'] : '-';
            $actual   = (isset($event['actual'])   && $event['actual']   !== '') ? $event['actual']   : '-';

            $events[] = [
                'timestamp' => $event_time->getTimestamp(),
                'utc'       => $event_time->getTimestamp(),   // Unix seconds — used by frontend for TZ conversion
                'time'      => $event_time->format('H:i') . ' EST',
                'date'      => $event_time->format('M j'),
                'title'     => $event['title'] ?? 'Economic Event',
                'currency'  => isset($event['country']) ? strtoupper($event['country']) : 'N/A',
                'impact'    => $impact,
                'previous'  => $previous,
                'forecast'  => $forecast,
                'actual'    => $actual,
            ];
        } catch (Exception $e) {
            continue;
        }
    }

    usort($events, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });

    foreach ($events as &$event) {
        unset($event['timestamp']);
    }
    unset($event);

    // Cache: 1 min for this_week (actuals), 5 min for next_week (stable data)
    $ttl = ($week === 'next_week') ? 5 * MINUTE_IN_SECONDS : 1 * MINUTE_IN_SECONDS;
    set_transient($cache_key, $events, $ttl);

    wp_send_json($events);
}

add_action('wp_ajax_tworich_economic_calendar',        'tworich_economic_calendar_ajax');
add_action('wp_ajax_nopriv_tworich_economic_calendar', 'tworich_economic_calendar_ajax');