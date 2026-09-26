a<?php
require_once '../../auth/session-config.php';
require_once '../../auth/feature-flags.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated'])) {
    wp_send_json(['success' => false, 'message' => 'Unauthorized'], 401);
}

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 3) . '/wp-load.php';
global $wpdb;

$input = json_decode(file_get_contents('php://input'), true);
$group_id = absint($input['group_id'] ?? 0);

if (!$group_id) {
    wp_send_json(['success' => false, 'message' => 'Invalid group ID'], 400);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf'] ?? '');
if (!$csrf || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    wp_send_json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

$group_table = $wpdb->prefix . 'rich_signal_groups';
$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$group_table} WHERE id = %d LIMIT 1", $group_id));

if (!$group || $group->status !== 'live') {
    wp_send_json(['success' => false, 'message' => 'Group not found'], 404);
}

if ($group->pricing_type !== 'paid' || (float)$group->price <= 0) {
    wp_send_json(['success' => false, 'message' => 'Group is not paid, you can join directly'], 400);
}

// Ensure user isn't already a member
$memberships_table = $wpdb->prefix . 'rich_signal_memberships';
$existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$memberships_table} WHERE user_id = %d AND group_id = %d AND status = 'active' LIMIT 1", $_SESSION['user_id'], $group_id));
if ($existing) {
    wp_send_json(['success' => false, 'message' => 'You are already a member of this group'], 400);
}


$user_id = (int) $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];

// Create a Stripe Checkout Session
$data = [
    'payment_method_types' => ['card'],
    'mode' => 'subscription',
    'success_url' => 'https://app.2rich.capital/trading-floor/?joined=' . $group_id,
    'cancel_url' => 'https://app.2rich.capital/trading-floor/',
    'customer_email' => $user_email,
    'client_reference_id' => (string) $user_id,
    'metadata' => [
        'group_id' => (string) $group_id,
        'action' => 'join_group'
    ],
    'line_items' => [
        [
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'Subscription: ' . $group->name,
                ],
                'unit_amount' => (int) round((float)$group->price * 100),
                'recurring' => [
                    'interval' => 'month'
                ]
            ],
            'quantity' => 1
        ]
    ]
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . STRIPE_SECRET_KEY,
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$res = json_decode($response, true);
if ($http_code === 200 && isset($res['url'])) {
    wp_send_json(['success' => true, 'url' => $res['url']]);
} else {
    error_log('Stripe checkout error: ' . $response);
    wp_send_json(['success' => false, 'message' => 'Failed to initialize checkout. Please try again.'], 500);
}
