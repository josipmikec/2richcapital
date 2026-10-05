<?php
// Script to send 24-hour and 5-day welcome follow-up emails.
// Run via cron hourly: `php /Users/josipmikec/Documents/2rich/2rich.capital/app/cron/send_email_sequence.php`

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 2) . '/wp-load.php';

require_once dirname(__DIR__) . '/auth/phpmailer/Exception.php';
require_once dirname(__DIR__) . '/auth/phpmailer/PHPMailer.php';
require_once dirname(__DIR__) . '/auth/phpmailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('SMTP_HOST',      'mail.2rich.capital');
define('SMTP_PORT',      465);
define('SMTP_USER',      'noreply@2rich.capital');
define('SMTP_PASS',      'PkCRHMdhdcQSyvbqMguk');
define('SMTP_FROM',      'noreply@2rich.capital');
define('SMTP_FROM_NAME', '2RICH CAPITAL');

$args = array(
    'date_query' => array(
        array(
            'after' => '10 days ago',
            'inclusive' => true,
        ),
    ),
);
$users = get_users($args);

$now = time();

foreach ($users as $user) {
    // Only process users who registered within the last 10 days (safety net)
    $registered_time = strtotime($user->user_registered);
    if ($now - $registered_time > 10 * 24 * 3600) {
        continue;
    }
    
    // Check if paying user (2rich_plan exists and is not empty)
    $plan = get_user_meta($user->ID, '2rich_plan', true);
    if (!empty($plan)) {
        continue;
    }

    // Skip if already fully processed
    $stage = get_user_meta($user->ID, '_2rich_email_stage', true);
    if (!$stage) $stage = 0;
    if ($stage >= 2) {
        continue;
    }

    $hours_since_registration = ($now - $registered_time) / 3600;
    
    if ($stage == 0 && $hours_since_registration >= 24) {
        send_reminder_email($user, 1);
        update_user_meta($user->ID, '_2rich_email_stage', 1);
    } elseif ($stage == 1 && $hours_since_registration >= (5 * 24)) {
        send_reminder_email($user, 2);
        update_user_meta($user->ID, '_2rich_email_stage', 2);
    }
}

function send_reminder_email($user, $type) {
    $email = $user->user_email;
    $firstName = $user->first_name ?: $user->display_name;
    $firstName = explode(' ', $firstName)[0];
    $accessUrl = 'https://2rich.capital/access/';

    if ($type === 1) {
        $subject = 'Checking in - Something new on your dashboard';
        $title = 'Checking in, ' . htmlspecialchars($firstName) . '.';
        $body = '<p style="font-size:15px;color:#f5f5f5;font-weight:600;margin:0 0 16px;">Did you catch the latest market data?</p>
        <p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
          It\'s been 24 hours since you joined the inner circle. We\'ve just updated the dashboard with the latest market data and we wanted to make sure you saw it.
        </p>
        <p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
          If you want to take things to the next level and see what the elite traders are doing, you should consider upgrading to Elite Desk. You\'ll get access to high-conviction trade setups, live signals, and our premium trading groups.
        </p>';
        $btnText = 'UPGRADE NOW &rarr;';
        $btnUrl = $accessUrl;
    } else {
        $subject = 'Still thinking? Join us at Elite Desk';
        $title = 'Still thinking, ' . htmlspecialchars($firstName) . '?';
        $body = '<p style="font-size:15px;color:#f5f5f5;font-weight:600;margin:0 0 16px;">The markets don\'t wait.</p>
        <p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
          It\'s been a few days since you signed up, and we noticed you haven\'t upgraded your access yet. 
          The markets are moving fast, and our analysts are actively posting signals and research that you\'re missing out on.
        </p>
        <p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
          Don\'t sit on the sidelines. Join Elite Desk today and get the edge you need. No fluff, just actionable intelligence.
        </p>';
        $btnText = 'JOIN THE INNER CIRCLE &rarr;';
        $btnUrl = $accessUrl;
    }

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
        <h1 style="font-size:26px;font-weight:700;color:#f5f5f5;margin:0 0 8px;letter-spacing:-0.02em;">' . $title . '</h1>
      </td></tr>
      <tr><td style="padding:36px 48px;">
        ' . $body . '
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
          <tr><td align="center">
            <a href="' . $btnUrl . '" style="display:inline-block;padding:16px 48px;background:linear-gradient(135deg,#F2CA50,#FFDB70);color:#0A0A0A;font-size:11px;font-weight:800;letter-spacing:0.14em;text-decoration:none;border-radius:8px;text-transform:uppercase;">' . $btnText . '</a>
          </td></tr>
        </table>
      </td></tr>
      <tr><td style="padding:24px 48px;border-top:1px solid #1a1a1a;text-align:center;">
        <p style="font-size:11px;color:#555;margin:0 0 16px;letter-spacing:0.08em;text-transform:uppercase;">
          <a href="https://www.instagram.com/2rich.capital/" style="color:#555;text-decoration:none;margin:0 8px;">Instagram</a> &bull;
          <a href="https://www.facebook.com/profile.php?id=61587034174676" style="color:#555;text-decoration:none;margin:0 8px;">Facebook</a> &bull;
          <a href="https://x.com/2RichCapital" style="color:#555;text-decoration:none;margin:0 8px;">X</a> &bull;
          <a href="https://www.youtube.com/@2RichCapital" style="color:#555;text-decoration:none;margin:0 8px;">YouTube</a>
        </p>
        <p style="font-size:11px;color:#2a2a2a;margin:0;letter-spacing:0.08em;text-transform:uppercase;">&copy; ' . date('Y') . ' 2RICH CAPITAL &nbsp;&bull;&nbsp; app.2rich.capital</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>';

    $plain = $subject . "\n\n"
           . $title . "\n\n"
           . strip_tags(str_replace('<p', "\n\n<p", $body)) . "\n\n"
           . $btnText . ": " . $btnUrl . "\n\n"
           . "-- 2RICH CAPITAL Team";

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
        $mail->addAddress($email, $user->display_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = trim($plain);
        $mail->send();
    } catch (Exception $e) {
        error_log('Follow up email failed: ' . $e->getMessage());
    }
}
