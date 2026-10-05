<?php
function getEmailHTML($title, $body, $btnText, $btnUrl) {
    return '
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
        ' . ($btnText ? '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
          <tr><td align="center">
            <a href="' . $btnUrl . '" style="display:inline-block;padding:16px 48px;background:linear-gradient(135deg,#F2CA50,#FFDB70);color:#0A0A0A;font-size:11px;font-weight:800;letter-spacing:0.14em;text-decoration:none;border-radius:8px;text-transform:uppercase;">' . $btnText . '</a>
          </td></tr>
        </table>' : '') . '
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
}

$firstName = 'Josip';
$accessUrl = 'https://2rich.capital/access/';

// EMAIL 1 (24 hours)
$title1 = 'Checking in, ' . htmlspecialchars($firstName) . '.';
$body1 = '<p style="font-size:15px;color:#f5f5f5;font-weight:600;margin:0 0 16px;">Did you catch the latest market data?</p>
<p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
  It\'s been 24 hours since you joined the desk. We\'ve just updated the dashboard with the latest market data and we wanted to make sure you saw it.
</p>
<p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
  If you want to take things to the next level and see what the elite traders are doing, you should consider upgrading to Elite Desk. You\'ll get access to high-conviction trade setups, live signals, and our premium trading groups.
</p>';
$btnText1 = 'UPGRADE NOW &rarr;';
file_put_contents(__DIR__ . '/email_24h.html', getEmailHTML($title1, $body1, $btnText1, $accessUrl));

// EMAIL 2 (5 days)
$title2 = 'Still thinking, ' . htmlspecialchars($firstName) . '?';
$body2 = '<p style="font-size:15px;color:#f5f5f5;font-weight:600;margin:0 0 16px;">The markets don\'t wait.</p>
<p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
  It\'s been a few days since you signed up, and we noticed you haven\'t upgraded your access yet. 
  The markets are moving fast, and our analysts are actively posting signals and research that you\'re missing out on.
</p>
<p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
  Don\'t sit on the sidelines. Join Elite Desk today and get the edge you need. No fluff, just actionable intelligence.
</p>';
$btnText2 = 'JOIN THE DESK &rarr;';
file_put_contents(__DIR__ . '/email_5d.html', getEmailHTML($title2, $body2, $btnText2, $accessUrl));

// WELCOME EMAIL (Free Registration)
$title3 = 'Welcome to 2RICH Capital.';
$body3 = '<p style="font-size:16px;color:#f5f5f5;font-weight:600;margin:0 0 16px;">You\'re in.</p>
<p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 24px;">
  You now have access to the 2RICH CAPITAL platform - your central hub for real-time macro research and market intelligence.
</p>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0E0E0E;border:1px solid #222;border-radius:10px;margin-bottom:32px;">
  <tr><td style="padding:8px 20px;border-bottom:1px solid #1a1a1a;">
    <p style="font-size:10px;font-weight:700;letter-spacing:0.15em;color:#444;text-transform:uppercase;margin:0;">YOUR CREDENTIALS</p>
  </td></tr>
  <tr><td style="padding:20px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;padding-bottom:12px;width:40%;">Username</td>
        <td style="font-size:13px;color:#F2CA50;font-weight:600;padding-bottom:12px;font-family:monospace;">Josip2Rich</td>
      </tr>
      <tr>
        <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;">Email</td>
        <td style="font-size:13px;color:#ccc;">josip@example.com</td>
      </tr>
    </table>
  </td></tr>
</table>
<div style="background:#1a1505;border:1px solid #332700;border-radius:10px;padding:24px;margin-bottom:32px;text-align:center;">
    <p style="font-size:14px;color:#F2CA50;font-weight:700;margin:0 0 12px;text-transform:uppercase;letter-spacing:0.1em;">Upgrade to Desk Access</p>
    <p style="font-size:13px;color:#bbb;line-height:1.7;margin:0 0 20px;">
      Serious about the markets? Unlock the full potential of 2RICH Capital with our Elite Desk access. Get high-conviction trade setups, live signals, and join our premium trading groups led by top analysts.
    </p>
    <a href="' . $accessUrl . '" style="display:inline-block;padding:12px 24px;background:#F2CA50;color:#000;font-size:11px;font-weight:800;letter-spacing:0.1em;text-decoration:none;border-radius:6px;text-transform:uppercase;">View Premium Features &rarr;</a>
</div>
<table width="100%" cellpadding="0" cellspacing="0">
  <tr><td align="center">
    <a href="https://app.2rich.capital/login" style="color:#777;font-size:11px;font-weight:600;letter-spacing:0.14em;text-decoration:none;text-transform:uppercase;border-bottom:1px solid #333;padding-bottom:4px;">ACCESS PLATFORM &rarr;</a>
  </td></tr>
</table>';
$btnText3 = '';
file_put_contents(__DIR__ . '/email_welcome.html', getEmailHTML($title3, $body3, $btnText3, 'https://app.2rich.capital/login'));

// NEW PURCHASE (Stripe Webhook)
$title4 = 'Access Granted.';
$body4 = '<p style="font-size:14px;color:#888;line-height:1.8;margin:0 0 28px;">Your payment was successful, and your 2RICH CAPITAL account is now fully active. You\'ve just unlocked access to elite trading signals, advanced copy-trading tools, and a community of high-net-worth traders.<br><br><span style="color:#F2CA50;"><b>YOUR NEXT STEPS:</b></span><br><br>&bull; <b>Log In:</b> Access the Trading Floor using your credentials below.<br>&bull; <b>Connect MT5:</b> Link your brokerage for enhanced experience.<br>&bull; <b>Join Groups:</b> Discover and join trading or signal groups.<br><br>The markets wait for no one. Let\'s get to work.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0E0E0E;border:1px solid #222;border-radius:10px;margin-bottom:32px;">
  <tr><td style="padding:8px 20px;border-bottom:1px solid #1a1a1a;">
    <p style="font-size:10px;font-weight:700;letter-spacing:0.15em;color:#444;text-transform:uppercase;margin:0;">YOUR CREDENTIALS</p>
  </td></tr>
  <tr><td style="padding:20px;">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;padding-bottom:12px;width:40%;">Username</td>
        <td style="font-size:13px;color:#F2CA50;font-weight:600;padding-bottom:12px;font-family:monospace;">Josip2Rich</td>
      </tr>
      <tr>
        <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;padding-bottom:12px;">Password</td>
        <td style="font-size:13px;color:#F2CA50;font-weight:600;padding-bottom:12px;font-family:monospace;">TempPass123!</td>
      </tr>
      <tr>
        <td style="font-size:11px;color:#444;letter-spacing:0.1em;text-transform:uppercase;">Plan</td>
        <td style="font-size:13px;color:#ccc;">Elite Desk</td>
      </tr>
    </table>
  </td></tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0">
  <tr><td align="center">
    <a href="https://app.2rich.capital/login" style="color:#777;font-size:11px;font-weight:600;letter-spacing:0.14em;text-decoration:none;text-transform:uppercase;border-bottom:1px solid #333;padding-bottom:4px;">ACCESS THE PLATFORM &rarr;</a>
  </td></tr>
</table>';
$btnText4 = '';
file_put_contents(__DIR__ . '/email_stripe_new.html', getEmailHTML($title4, $body4, $btnText4, 'https://app.2rich.capital/login'));

echo "Generated email_welcome.html and email_stripe_new.html\n";
