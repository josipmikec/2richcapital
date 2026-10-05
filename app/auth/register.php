<?php
ob_start();
require_once __DIR__ . '/session-config.php';

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 2) . '/wp-load.php';

ob_end_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('SMTP_HOST',      'mail.2rich.capital');
define('SMTP_PORT',      465);
define('SMTP_USER',      'noreply@2rich.capital');
define('SMTP_PASS',      'PkCRHMdhdcQSyvbqMguk');
define('SMTP_FROM',      'noreply@2rich.capital');
define('SMTP_FROM_NAME', '2RICH CAPITAL');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$fullname         = trim($_POST['fullname'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($fullname) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}
if ($password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

try {
    if (email_exists($email)) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }

    $username = sanitize_user(strtolower(str_replace(' ', '', $fullname)) . rand(100, 999));
    $user_id  = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create account: ' . $user_id->get_error_message()]);
        exit;
    }

    $nameParts = explode(' ', $fullname);
    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $fullname,
        'first_name'   => $nameParts[0],
        'last_name'    => $nameParts[1] ?? ''
    ]);

    $user = new WP_User($user_id);
    $user->set_role('subscriber');

    // ── Send welcome email ────────────────────────────────────────────────
    send_welcome_email($email, $fullname, $username);
    // ─────────────────────────────────────────────────────────────────────

    echo json_encode(['success' => true, 'message' => 'Account created successfully! Redirecting to login...']);

} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during registration']);
}

// ── Welcome email function ────────────────────────────────────────────────────
function send_welcome_email(string $email, string $displayName, string $username): void {
    $loginUrl  = 'https://app.2rich.capital/login';
    $accessUrl = 'https://2rich.capital/access/';
    $firstName = explode(' ', $displayName)[0];

    $html = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0A0A0A;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0A0A0A;padding:48px 0;">
  <tr><td align="center">
    <table width="520" cellpadding="0" cellspacing="0" style="background:#111;border:1px solid #1e1e1e;border-radius:16px;overflow:hidden;">

      <!-- Gold top bar -->
      <tr><td style="height:3px;background:linear-gradient(90deg,transparent 0%,#F2CA50 50%,transparent 100%);"></td></tr>

      <!-- Header -->
      <tr><td style="padding:44px 48px 32px;text-align:center;border-bottom:1px solid #1a1a1a;">
        <p style="font-size:10px;font-weight:700;letter-spacing:0.2em;color:#F2CA50;text-transform:uppercase;margin:0 0 16px;">2RICH CAPITAL</p>
        <h1 style="font-size:26px;font-weight:700;color:#f5f5f5;margin:0 0 8px;letter-spacing:-0.02em;">Welcome to 2RICH Capital.</h1>
        <p style="font-size:13px;color:#555;margin:0;letter-spacing:0.04em;text-transform:uppercase;">Welcome, ' . htmlspecialchars($firstName) . '.</p>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:36px 48px;">
        <p style="font-size:16px;color:#f5f5f5;font-weight:600;margin:0 0 16px;">You\'re in.</p>
        <p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
          You now have access to the 2RICH CAPITAL platform &mdash; your central hub for real-time macro research and market intelligence.
        </p>

        <div style="background:#1a1505;border:1px solid #332700;border-radius:10px;padding:24px;margin-bottom:32px;text-align:center;">
            <p style="font-size:14px;color:#F2CA50;font-weight:700;margin:0 0 12px;text-transform:uppercase;letter-spacing:0.1em;">Upgrade to the Inner Circle</p>
            <p style="font-size:13px;color:#bbb;line-height:1.7;margin:0 0 20px;">
              Serious about the markets? Unlock the full potential of 2RICH Capital with our Elite Desk access. Get high-conviction trade setups, live signals, and join our premium trading groups led by top analysts.
            </p>
            <a href="' . $accessUrl . '" style="display:inline-block;padding:12px 24px;background:#F2CA50;color:#000;font-size:11px;font-weight:800;letter-spacing:0.1em;text-decoration:none;border-radius:6px;text-transform:uppercase;">View Premium Features &rarr;</a>
        </div>

        <!-- Credentials box -->
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
                <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;">Email</td>
                <td style="font-size:13px;color:#ccc;">' . htmlspecialchars($email) . '</td>
              </tr>
            </table>
          </td></tr>
        </table>

        <!-- CTA button -->
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="center">
            <a href="' . $loginUrl . '" style="display:inline-block;padding:16px 48px;background:#1a1a1a;color:#fff;border:1px solid #333;font-size:11px;font-weight:800;letter-spacing:0.14em;text-decoration:none;border-radius:8px;text-transform:uppercase;">ACCESS PLATFORM</a>
          </td></tr>
        </table>
      </td></tr>

      <!-- Footer -->
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

    $plain = "Welcome to 2RICH CAPITAL.\n\n"
           . "Welcome, {$firstName}.\n"
           . "You now have access to the 2RICH CAPITAL platform — your central hub for real-time macro research and market intelligence.\n\n"
           . "Upgrade to the Inner Circle\n"
           . "Serious about the markets? Unlock the full potential of 2RICH Capital with our Elite Desk access. Get high-conviction trade setups, live signals, and join our premium trading groups led by top analysts.\n"
           . "View Premium Features: {$accessUrl}\n\n"
           . "Your account is ready.\n\n"
           . "Username: {$username}\n"
           . "Email:    {$email}\n\n"
           . "Access the platform: {$loginUrl}\n\n"
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
        $mail->addAddress($email, $displayName);
        $mail->isHTML(true);
        $mail->Subject = '2RICH CAPITAL - Welcome to the Platform';
        $mail->Body    = $html;
        $mail->AltBody = $plain;
        $mail->send();
    } catch (Exception $e) {
        error_log('Welcome email failed: ' . $e->getMessage());
    }
}
?>