<?php
require_once dirname(__DIR__, 2) . '/wp-load.php';
require_once __DIR__ . '/../stripe/webhook.php'; // This contains send_payment_confirmation_email

if (!current_user_can('manage_options')) {
    echo "You must be logged in as an admin to test this.";
    exit;
}

$current_user = wp_get_current_user();
$email = $current_user->user_email;
$displayName = $current_user->display_name;
$username = $current_user->user_login;

send_payment_confirmation_email(
    $email,
    $displayName,
    $username,
    'Premium Group (Test)',
    true, // isNew
    'test_password_123'
);

echo "Test email sent to {$email}!";
