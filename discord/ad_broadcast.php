<?php
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2Rich Ad Broadcaster — Standalone
// Sends one promotional image embed to a Discord channel every 3 hours
// Cron: 0 */3 * * * /usr/local/bin/php /home/richcapit/domains/2rich.capital/public_html/discord/ad_broadcast.php

// ── Security: block browser access ────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

// ── Config ────────────────────────────────────────────────────────────────────
define('DISCORD_WEBHOOK_ADS', 'https://discord.com/api/webhooks/1536108227324805211/gUf7S1axlyUoj7yQGVFxdwL5oJpys3ntFzAtR3GPfTcjD_4vUzRHa-thlzoBNnq1zx8U');
define('DISCORD_BOT_NAME',    '2rich');
define('DISCORD_BOT_AVATAR',  'https://2rich.capital/discord/img/2rich-logo.png');
define('SITE_URL',            'https://2rich.capital');
define('DISCORD_DIR',         '/home/richcapit/domains/2rich.capital/public_html/discord');
define('LOG_FILE',            DISCORD_DIR . '/cron_test.log');

// Shared filesystem paths, not sys_get_temp_dir()
$lockDir   = DISCORD_DIR . '/.ad_broadcast.lock';
$pidFile   = $lockDir . '/pid';
$stateFile = DISCORD_DIR . '/.ad_broadcast_state.json';
$stateTtl  = 180; // seconds

function log_line(string $message): void
{
    @file_put_contents(
        LOG_FILE,
        date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

function acquire_lock(string $lockDir, string $pidFile): bool
{
    if (is_dir($lockDir)) {
        if (file_exists($pidFile)) {
            $pid = (int) trim((string) @file_get_contents($pidFile));

            if ($pid > 0 && function_exists('posix_kill')) {
                if (@posix_kill($pid, 0)) {
                    return false;
                }
            }
        }

        @unlink($pidFile);
        @rmdir($lockDir);
    }

    if (!@mkdir($lockDir, 0700)) {
        return false;
    }

    @file_put_contents($pidFile, (string) getmypid());
    return true;
}

function release_lock(string $lockDir, string $pidFile): void
{
    @unlink($pidFile);
    @rmdir($lockDir);
}

if (!acquire_lock($lockDir, $pidFile)) {
    echo "[SKIP] Another instance is already running.\n";
    log_line('SKIP | PID ' . getmypid() . ' | Another instance is already running');
    exit(0);
}

register_shutdown_function(function () use ($lockDir, $pidFile) {
    release_lock($lockDir, $pidFile);
});

// ── Ad definitions ────────────────────────────────────────────────────────────
$ads = [
    [
        'image' => 'https://2rich.capital/discord/img/LIVE_NEWS_ACCESS_3.jpg',
        'title' => 'Get Free Live News Feed Widget Here',
        'url'   => SITE_URL,
    ],
    [
        'image' => 'https://2rich.capital/discord/img/LIVE_FEED_ACCESS.jpg',
        'title' => 'Free Pop-Out News Widget',
        'url'   => SITE_URL,
    ],
];

// ── Optional CLI test override: --ad=0 or --ad=1 ─────────────────────────────
$forcedAd = null;

if (!empty($argv)) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--ad=') === 0) {
            $forcedAd = (int) substr($arg, 5);
        }
    }
}

// ── Freeze time once per run so selection can't drift mid-execution ───────────
$nowTs   = time();
$hour    = (int) date('G', $nowTs);
$dayKey  = date('Y-m-d', $nowTs);
$window  = (int) floor($hour / 3);

// ── Rotate ads every 3 hours ──────────────────────────────────────────────────
if ($forcedAd !== null) {
    $block = $forcedAd % count($ads);
} else {
    $block = $window % count($ads);
}

$ad = $ads[$block];

// ── Idempotency guard: same ad + same 3h window + short retry shield ─────────
$currentKey = hash(
    'sha256',
    $ad['title'] . '|' .
    $ad['image'] . '|' .
    $dayKey . '|' .
    $window
);

$previousState = null;
if (file_exists($stateFile)) {
    $raw = @file_get_contents($stateFile);
    $decoded = json_decode((string) $raw, true);
    if (is_array($decoded)) {
        $previousState = $decoded;
    }
}

if (
    is_array($previousState) &&
    isset($previousState['key'], $previousState['time']) &&
    hash_equals((string) $previousState['key'], $currentKey) &&
    ($nowTs - (int) $previousState['time']) < $stateTtl
) {
    echo "[SKIP] Duplicate send prevented.\n";
    log_line('SKIP | PID ' . getmypid() . ' | Duplicate prevented | ' . $ad['title']);
    exit(0);
}

// ── Build Discord payload ─────────────────────────────────────────────────────
$data = [
    'username'   => DISCORD_BOT_NAME,
    'avatar_url' => DISCORD_BOT_AVATAR,
    'embeds'     => [[
        'title'  => $ad['title'],
        'url'    => $ad['url'],
        'color'  => 0x0e0e0e,
        'image'  => ['url' => $ad['image']],
        'footer' => ['text' => 'Free to join -> ' . SITE_URL],
    ]],
];

$payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($payload === false) {
    echo '[FAIL] JSON encode error: ' . json_last_error_msg() . "\n";
    log_line('FAIL | PID ' . getmypid() . ' | JSON encode error: ' . json_last_error_msg());
    exit(1);
}

log_line(
    'START | PID ' . getmypid() .
    ' | Window ' . $dayKey . ' #' . $window .
    ' | Ad: ' . $ad['title']
);

// ── Send via file_get_contents ────────────────────────────────────────────────
$context = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", [
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (compatible; 2RichBot/1.0)',
            'Content-Length: ' . strlen($payload),
        ]),
        'content'       => $payload,
        'timeout'       => 20,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$response = @file_get_contents(DISCORD_WEBHOOK_ADS, false, $context);
$httpCode = 0;

if (!empty($http_response_header)) {
    preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
    $httpCode = isset($matches[1]) ? (int) $matches[1] : 0;
}

// ── Result ────────────────────────────────────────────────────────────────────
if ($httpCode === 204) {
    echo '[OK] Ad sent: ' . $ad['title'] . "\n";
    echo '[INFO] Image: ' . $ad['image'] . "\n";

    @file_put_contents(
        $stateFile,
        json_encode([
            'key'   => $currentKey,
            'time'  => $nowTs,
            'title' => $ad['title'],
            'image' => $ad['image'],
            'day'   => $dayKey,
            'slot'  => $window,
            'pid'   => getmypid(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    log_line('OK | PID ' . getmypid() . ' | ' . $ad['title']);
    exit(0);
}

if ($httpCode === 0) {
    echo "[FAIL] No response received. Check if outbound HTTPS is allowed on your host.\n";
    echo "[INFO] allow_url_fopen = " . (ini_get('allow_url_fopen') ? 'ON' : 'OFF') . "\n";

    log_line('FAIL | PID ' . getmypid() . ' | No response');
    exit(1);
}

echo '[FAIL] HTTP ' . $httpCode . "\n";
echo '[FAIL] Response: ' . $response . "\n";

log_line(
    'FAIL | PID ' . getmypid() .
    ' | HTTP ' . $httpCode .
    ' | Response: ' . trim((string) $response)
);
exit(1);
