<?php
/**
 * Stripe Webhook Handler
 * URL to register in Stripe Dashboard:
 *   https://app.2rich.capital/stripe/webhook.php
 *
 * Events to listen for:
 *   - checkout.session.completed
 *   - customer.subscription.created
 *   - invoice.payment_succeeded  (for renewals)
 */

// ── Config ────────────────────────────────────────────────────────────────────
define('STRIPE_WEBHOOK_SECRET', 'whsec_hx9eSzUJwdt5lm1ex3C2yk3NEKvtpcVV');
define('STRIPE_SECRET_KEY',     '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');

define('SMTP_HOST',      'mail.2rich.capital');
define('SMTP_PORT',      465);
define('SMTP_USER',      'noreply@2rich.capital');
define('SMTP_PASS',      'PkCRHMdhdcQSyvbqMguk');
define('SMTP_FROM',      'noreply@2rich.capital');
define('SMTP_FROM_NAME', '2RICH CAPITAL');
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../auth/phpmailer/Exception.php';
require_once __DIR__ . '/../auth/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../auth/phpmailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load WordPress so we can create WP users
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';

// ── Verify Stripe signature ───────────────────────────────────────────────────
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($sigHeader)) {
    http_response_code(400);
    exit('Missing signature');
}

// Manual HMAC verification (no Stripe SDK needed)
$parts    = [];
foreach (explode(',', $sigHeader) as $part) {
    [$k, $v] = explode('=', $part, 2);
    $parts[$k][] = $v;
}
$timestamp = $parts['t'][0] ?? 0;
$signed    = $timestamp . '.' . $payload;
$expected  = hash_hmac('sha256', $signed, STRIPE_WEBHOOK_SECRET);
$received  = $parts['v1'][0] ?? '';

if (!hash_equals($expected, $received)) {
    http_response_code(400);
    exit('Invalid signature');
}

// Reject events older than 5 minutes (replay attack protection)
if (abs(time() - (int)$timestamp) > 300) {
    http_response_code(400);
    exit('Timestamp too old');
}

$event = json_decode($payload, true);
$type  = $event['type'] ?? '';

http_response_code(200); // Always respond 200 to Stripe quickly

// ── Handle events ────────────────────────────────────────────────────────────
switch ($type) {

    case 'checkout.session.completed':
        $session     = $event['data']['object'];
        $customerEmail = $session['customer_details']['email'] ?? $session['customer_email'] ?? '';
        $customerName  = $session['customer_details']['name']  ?? '';
        $planName      = get_plan_name_from_session($session);
        handle_new_member($customerEmail, $customerName, $planName);
        break;

    case 'customer.subscription.created':
        // Handled via checkout.session.completed — no duplicate action needed
        break;

    case 'invoice.payment_succeeded':
        // Renewal — just log for now, extend access if needed
        $invoice = $event['data']['object'];
        if (($invoice['billing_reason'] ?? '') === 'subscription_create') break; // already handled
        $customerEmail = get_email_from_customer($invoice['customer'] ?? '');
        if ($customerEmail) {
            error_log("[2RICH] Renewal payment for: {$customerEmail}");
            // Optionally send renewal confirmation email here
        }
        break;
}

exit;

// ── Helper: create/update WP account + send credentials email ─────────────────
function handle_new_member(string $email, string $fullName, string $planName): void {
    if (empty($email)) { error_log('[2RICH webhook] No email in event'); return; }

    $email    = strtolower(trim($email));
    $fullName = trim($fullName) ?: 'Member';

    // Check if user already exists
    $existingUser = get_user_by('email', $email);

    if ($existingUser) {
        // Existing user — upgrade their role and notify
        $existingUser->set_role('subscriber');
        update_user_meta($existingUser->ID, '2rich_plan', $planName);
        send_payment_confirmation_email($email, $existingUser->display_name, $existingUser->user_login, $planName, false);
        error_log("[2RICH webhook] Existing user upgraded: {$email} -> {$planName}");
        return;
    }

    // New user — generate credentials
    $nameParts   = explode(' ', $fullName);
    $baseUsername = strtolower(preg_replace('/[^a-z0-9]/i', '', $nameParts[0]));
    $username     = sanitize_user($baseUsername . rand(100, 999));
    $tempPassword = generate_temp_password();

    $user_id = wp_create_user($username, $tempPassword, $email);

    if (is_wp_error($user_id)) {
        error_log('[2RICH webhook] Failed to create user: ' . $user_id->get_error_message());
        return;
    }

    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $fullName,
        'first_name'   => $nameParts[0],
        'last_name'    => $nameParts[1] ?? ''
    ]);

    $user = new WP_User($user_id);
    $user->set_role('subscriber');
    update_user_meta($user_id, '2rich_plan', $planName);

    send_payment_confirmation_email($email, $fullName, $username, $planName, true, $tempPassword);
    error_log("[2RICH webhook] New member created: {$email} ({$username}) -> {$planName}");
}

// ── Helper: send credentials/confirmation email ───────────────────────────────
function send_payment_confirmation_email(
    string $email,
    string $displayName,
    string $username,
    string $planName,
    bool   $isNew,
    string $tempPassword = ''
): void {
    $loginUrl  = 'https://app.2rich.capital/login';
    $firstName = explode(' ', $displayName)[0];

    $credentialsBlock = $isNew ? '
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#0E0E0E;border:1px solid #222;border-radius:10px;margin-bottom:32px;">
          <tr><td style="padding:8px 20px;border-bottom:1px solid #1a1a1a;">
            <p style="font-size:10px;font-weight:700;letter-spacing:0.15em;color:#444;text-transform:uppercase;margin:0;">YOUR CREDENTIALS</p>
          </td></tr>
          <tr><td style="padding:20px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;padding-bottom:12px;width:40%;">Username</td>
                <td style="font-size:13px;color:#F2CA50;font-weight:600;padding-bottom:12px;font-family:monospace;">' . htmlspecialchars($username) . '</td>
              </tr>
              <tr>
                <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;padding-bottom:12px;">Password</td>
                <td style="font-size:13px;color:#F2CA50;font-weight:600;padding-bottom:12px;font-family:monospace;">' . htmlspecialchars($tempPassword) . '</td>
              </tr>
              <tr>
                <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;">Plan</td>
                <td style="font-size:13px;color:#ccc;">' . htmlspecialchars($planName) . '</td>
              </tr>
            </table>
          </td></tr>
        </table>' : '
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#0E0E0E;border:1px solid #222;border-radius:10px;margin-bottom:32px;">
          <tr><td style="padding:20px;">
            <table width="100%"><tr>
              <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;width:40%;">Plan</td>
              <td style="font-size:13px;color:#F2CA50;font-weight:600;">' . htmlspecialchars($planName) . '</td>
            </tr></table>
          </td></tr>
        </table>';

    $headline  = $isNew ? 'Access Granted.' : 'Payment Confirmed.';
    $subline   = $isNew ? 'Your account has been created' : 'Your membership has been renewed';
    $bodyText  = $isNew
        ? 'Your payment was successful and your 2RICH CAPITAL account is ready. Use the credentials below to access the platform immediately.'
        : 'Your 2RICH CAPITAL membership has been renewed successfully. Your access continues uninterrupted.';

    $html = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0A0A0A;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:48px 0;">
  <tr><td align="center">
    <table width="520" cellpadding="0" cellspacing="0" style="background:#111;border:1px solid #1e1e1e;border-radius:16px;overflow:hidden;">
      <tr><td style="height:3px;background:linear-gradient(90deg,transparent 0%,#F2CA50 50%,transparent 100%);"></td></tr>
      <tr><td style="padding:44px 48px 32px;text-align:center;border-bottom:1px solid #1a1a1a;">
        <p style="font-size:10px;font-weight:700;letter-spacing:0.2em;color:#F2CA50;text-transform:uppercase;margin:0 0 16px;">2RICH CAPITAL</p>
        <h1 style="font-size:26px;font-weight:700;color:#f5f5f5;margin:0 0 8px;letter-spacing:-0.02em;">' . $headline . '</h1>
        <p style="font-size:13px;color:#555;margin:0;letter-spacing:0.04em;text-transform:uppercase;">' . $subline . '</p>
      </td></tr>
      <tr><td style="padding:36px 48px;">
        <p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 28px;">' . $bodyText . '</p>
        ' . $credentialsBlock . '
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="center">
            <a href="' . $loginUrl . '" style="display:inline-block;padding:16px 48px;background:linear-gradient(135deg,#F2CA50,#FFDB70);color:#0A0A0A;font-size:11px;font-weight:800;letter-spacing:0.14em;text-decoration:none;border-radius:8px;text-transform:uppercase;">ACCESS THE PLATFORM &rarr;</a>
          </td></tr>
        </table>
      </td></tr>
      <tr><td style="padding:24px 48px;border-top:1px solid #1a1a1a;text-align:center;">
        <p style="font-size:11px;color:#2a2a2a;margin:0;letter-spacing:0.08em;text-transform:uppercase;">&copy; 2026 2RICH CAPITAL &nbsp;&bull;&nbsp; app.2rich.capital</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>';

    $plain = ($isNew
        ? "Your payment was successful. Here are your credentials:\n\nUsername: {$username}\nPassword: {$tempPassword}\nPlan: {$planName}"
        : "Your {$planName} membership has been renewed successfully."
    ) . "\n\nAccess the platform: {$loginUrl}\n\n-- 2RICH CAPITAL Team";

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($email, $displayName);
        $mail->isHTML(true);
        $mail->Subject = $isNew
            ? '2RICH CAPITAL - Your Account is Ready'
            : '2RICH CAPITAL - Payment Confirmed';
        $mail->Body    = $html;
        $mail->AltBody = $plain;
        $mail->send();
    } catch (Exception $e) {
        error_log('Stripe welcome email failed: ' . $e->getMessage());
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function generate_temp_password(int $length = 12): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
    $pass  = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

function get_plan_name_from_session(array $session): string {
    // Try metadata first (set this in your Stripe payment links)
    if (!empty($session['metadata']['plan'])) return $session['metadata']['plan'];
    // Fallback to amount
    $amount = ($session['amount_total'] ?? 0) / 100;
    if ($amount <= 30)  return 'Starter';
    if ($amount <= 100) return 'Pro';
    return 'Elite';
}

function get_email_from_customer(string $customerId): string {
    if (empty($customerId)) return '';
    // Fetch from Stripe API
    $response = wp_remote_get("https://api.stripe.com/v1/customers/{$customerId}", [
        'headers' => ['Authorization' => 'Bearer ' . STRIPE_SECRET_KEY]
    ]);
    if (is_wp_error($response)) return '';
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['email'] ?? '';
}
?>