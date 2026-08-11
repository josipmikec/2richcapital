<?php
require_once 'config.php';
require_once 'session-config.php';

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim(strtolower($input['email'])) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

define('SMTP_HOST',      'mail.2rich.capital');
define('SMTP_PORT',      465);
define('SMTP_USER',      'noreply@2rich.capital');
define('SMTP_PASS',      'PkCRHMdhdcQSyvbqMguk');
define('SMTP_FROM',      'noreply@2rich.capital');
define('SMTP_FROM_NAME', '2RICH CAPITAL');

try {
    $stmt = $conn->prepare('SELECT ID, user_email, display_name FROM wp_users WHERE user_email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));

        $conn->exec("
            CREATE TABLE IF NOT EXISTS `2rich_password_resets` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `email`      VARCHAR(255) NOT NULL,
                `token`      VARCHAR(64)  NOT NULL,
                `expires_at` DATETIME     NOT NULL,
                `used`       TINYINT(1)   DEFAULT 0,
                `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_token` (`token`),
                INDEX `idx_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Delete old tokens for this email
        $del = $conn->prepare('DELETE FROM `2rich_password_resets` WHERE email = ?');
        $del->execute([$email]);

        // Use MySQL NOW() + INTERVAL to avoid PHP/MySQL timezone mismatch
        $ins = $conn->prepare('INSERT INTO `2rich_password_resets` (email, token, expires_at) VALUES (?, ?, NOW() + INTERVAL 24 HOUR)');
        $ins->execute([$email, $token]);

        $resetLink   = 'https://app.2rich.capital/auth/reset-password.php?token=' . $token;
        $displayName = $user['display_name'] ?: 'Member';

        $htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0E0E0E;font-family:\'Segoe UI\',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0E0E0E;padding:40px 0;">
    <tr><td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#151515;border:1px solid #1a1a1a;border-radius:12px;overflow:hidden;">
        <tr><td style="height:3px;background:linear-gradient(90deg,transparent,#F2CA50,transparent);"></td></tr>
        <tr><td style="padding:40px 40px 24px;text-align:center;">
          <p style="font-size:11px;font-weight:700;letter-spacing:0.15em;color:#555;text-transform:uppercase;margin:0 0 8px;">2RICH CAPITAL</p>
          <h1 style="font-size:22px;font-weight:600;color:#f4f4f4;margin:0 0 8px;">Password Reset</h1>
          <p style="font-size:13px;color:#666;margin:0;">Hi ' . htmlspecialchars($displayName) . ', we received your request.</p>
        </td></tr>
        <tr><td style="padding:0 40px 32px;">
          <p style="font-size:13px;color:#888;line-height:1.7;margin:0 0 28px;">
            Click the button below to set a new password.<br>This link is valid for <strong style="color:#f4f4f4;">24 hours</strong>.
          </p>
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td align="center">
              <a href="' . $resetLink . '" style="display:inline-block;padding:16px 40px;background:linear-gradient(135deg,#F2CA50,#FFDB70);color:#0E0E0E;font-size:11px;font-weight:800;letter-spacing:0.14em;text-decoration:none;border-radius:8px;text-transform:uppercase;">
                RESET PASSWORD &rarr;
              </a>
            </td></tr>
          </table>
          <p style="font-size:11px;color:#444;margin:24px 0 0;text-align:center;">
            Or copy this link: <span style="color:#F2CA50;">' . $resetLink . '</span>
          </p>
        </td></tr>
        <tr><td style="padding:20px 40px;border-top:1px solid #1a1a1a;text-align:center;">
          <p style="font-size:11px;color:#333;margin:0;">If you did not request this, you can safely ignore this email.</p>
          <p style="font-size:10px;color:#2a2a2a;margin:8px 0 0;letter-spacing:0.08em;text-transform:uppercase;">&copy; 2026 2RICH CAPITAL</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';

        $plainBody = "Hi {$displayName},\n\n"
                   . "You requested a password reset for your 2RICH CAPITAL account.\n\n"
                   . "Reset your password here (valid 24 hours):\n{$resetLink}\n\n"
                   . "If you did not request this, ignore this email.\n\n"
                   . "-- 2RICH CAPITAL Team";

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
        $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = '2RICH CAPITAL - Password Reset Request';
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody;

        $mail->send();
    }

    echo json_encode(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);

} catch (Exception $e) {
    error_log('Forgot password mailer error: ' . $e->getMessage());
    echo json_encode(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
} catch (PDOException $e) {
    error_log('Forgot password DB error: ' . $e->getMessage());
    echo json_encode(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);
}
?>