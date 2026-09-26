<?php
require_once dirname(__DIR__, 2) . '/auth/session-config.php';
require_once dirname(__DIR__, 3) . '/wp-load.php';
require_once dirname(__DIR__, 2) . '/stripe/webhook.php';

if (!isset($_SESSION['user_id'])) {
    echo "You must be logged into the custom dashboard to test this.";
    exit;
}

$user_id = $_SESSION['user_id'];
$user = get_userdata($user_id);
if (!$user) {
    echo "User not found.";
    exit;
}

$email = $user->user_email;
$displayName = $user->display_name;
$username = $user->user_login;

send_payment_confirmation_email(
    $email,
    $displayName,
    $username,
    'Premium Group (Test)',
    true, // isNew
    'test_password_123'
);

echo "Test email sent to {$email}!";
