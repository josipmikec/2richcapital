<?php
require_once 'config.php';
require_once 'session-config.php';

// phppass fallback: WordPress-compatible password hashing
if (!class_exists('PasswordHash')) {
    class PasswordHash {
        private $itoa64;
        private $iteration_count_log2;
        private $portable_hashes;
        private $random_state;

        public function __construct($iteration_count_log2, $portable_hashes) {
            $this->itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31) $iteration_count_log2 = 8;
            $this->iteration_count_log2 = $iteration_count_log2;
            $this->portable_hashes = $portable_hashes;
            $this->random_state = microtime() . getmypid();
        }

        private function get_random_bytes($count) {
            $output = '';
            if (function_exists('random_bytes')) {
                try { return random_bytes($count); } catch (Exception $e) {}
            }
            if (@is_readable('/dev/urandom') && ($fh = @fopen('/dev/urandom','rb'))) {
                $output = fread($fh, $count); fclose($fh);
            }
            if (strlen($output) < $count) {
                $output = '';
                for ($i = 0; $i < $count; $i += 16) {
                    $this->random_state = md5(microtime() . $this->random_state);
                    $output .= pack('H*', md5($this->random_state));
                }
                $output = substr($output, 0, $count);
            }
            return $output;
        }

        private function encode64($input, $count) {
            $output = ''; $i = 0;
            do {
                $value = ord($input[$i++]);
                $output .= $this->itoa64[$value & 0x3f];
                if ($i < $count) $value |= ord($input[$i]) << 8;
                $output .= $this->itoa64[($value >> 6) & 0x3f];
                if ($i++ >= $count) break;
                if ($i < $count) $value |= ord($input[$i]) << 16;
                $output .= $this->itoa64[($value >> 12) & 0x3f];
                if ($i++ >= $count) break;
                $output .= $this->itoa64[($value >> 18) & 0x3f];
            } while ($i < $count);
            return $output;
        }

        private function gensalt_private($input) {
            $output  = '$P$';
            $output .= $this->itoa64[min($this->iteration_count_log2 + 5, 30)];
            $output .= $this->encode64($input, 6);
            return $output;
        }

        private function crypt_private($password, $setting) {
            $output = '*0';
            if (substr($setting, 0, 2) === $output) $output = '*1';
            $id = substr($setting, 0, 3);
            if ($id !== '$P$' && $id !== '$H$') return $output;
            $count_log2 = strpos($this->itoa64, $setting[3]);
            if ($count_log2 < 7 || $count_log2 > 30) return $output;
            $count = 1 << $count_log2;
            $salt  = substr($setting, 4, 8);
            if (strlen($salt) !== 8) return $output;
            $hash = md5($salt . $password, true);
            do { $hash = md5($hash . $password, true); } while (--$count);
            $output = substr($setting, 0, 12);
            $output .= $this->encode64($hash, 16);
            return $output;
        }

        private function gensalt_blowfish($input) {
            $itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $output = '$2a$'; $output .= chr(ord('0') + $this->iteration_count_log2 / 10);
            $output .= chr(ord('0') + $this->iteration_count_log2 % 10); $output .= '$';
            $i = 0;
            do {
                $c1 = ord($input[$i++]); $output .= $itoa64[$c1 >> 2];
                $c1 = ($c1 & 0x03) << 4;
                if ($i >= 16) { $output .= $itoa64[$c1]; break; }
                $c2 = ord($input[$i++]); $c1 |= $c2 >> 4; $output .= $itoa64[$c1];
                $c1 = ($c2 & 0x0f) << 2;
                $c2 = ord($input[$i++]); $c1 |= $c2 >> 6;
                $output .= $itoa64[$c1]; $output .= $itoa64[$c2 & 0x3f];
            } while (true);
            return $output;
        }

        public function HashPassword($password) {
            if (strlen($password) > 4096) return '*';
            $random = '';
            if (CRYPT_BLOWFISH === 1 && !$this->portable_hashes) {
                $random = $this->get_random_bytes(16);
                $hash   = crypt($password, $this->gensalt_blowfish($random));
                if (strlen($hash) === 60) return $hash;
            }
            if (strlen($random) < 6) $random = $this->get_random_bytes(6);
            return $this->crypt_private($password, $this->gensalt_private($random));
        }

        public function CheckPassword($password, $stored_hash) {
            if (strlen($password) > 4096) return false;
            $hash = $this->crypt_private($password, $stored_hash);
            if ($hash[0] === '*') $hash = crypt($password, $stored_hash);
            return hash_equals($stored_hash, $hash);
        }
    }
}

function hash_password_wp_compat(string $password): string {
    if (defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH === 1) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    $hasher = new PasswordHash(8, true);
    return $hasher->HashPassword($password);
}

// Handle POST (form submission to set new password)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input    = json_decode(file_get_contents('php://input'), true);
    $token    = isset($input['token'])    ? trim($input['token'])  : '';
    $password = isset($input['password']) ? $input['password']     : '';
    $confirm  = isset($input['confirm'])  ? $input['confirm']      : '';

    if (empty($token) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }

    $conn = get_db_connection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    try {
        $stmt = $conn->prepare('
            SELECT email FROM `2rich_password_resets`
            WHERE token = ? AND expires_at > NOW() AND used = 0
            LIMIT 1
        ');
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            echo json_encode(['success' => false, 'message' => 'Reset link is invalid or has expired']);
            exit;
        }

        $email          = $reset['email'];
        $hashedPassword = hash_password_wp_compat($password);

        $upd = $conn->prepare('UPDATE wp_users SET user_pass = ?, user_activation_key = "" WHERE user_email = ?');
        $upd->execute([$hashedPassword, $email]);

        $del = $conn->prepare('UPDATE `2rich_password_resets` SET used = 1 WHERE token = ?');
        $del->execute([$token]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);

    } catch (PDOException $e) {
        error_log('Reset password error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit;
}

// Handle GET (show reset form page)
$token      = isset($_GET['token']) ? trim($_GET['token']) : '';
$validToken = false;
$tokenError = '';

if ($token) {
    $conn = get_db_connection();
    if ($conn) {
        try {
            // First check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE '2rich_password_resets'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $stmt = $conn->prepare('
                    SELECT email, expires_at, used FROM `2rich_password_resets`
                    WHERE token = ? LIMIT 1
                ');
                $stmt->execute([$token]);
                $row = $stmt->fetch();
                if (!$row) {
                    $tokenError = 'Token not found. Please request a new reset link.';
                } elseif ($row['used']) {
                    $tokenError = 'This reset link has already been used.';
                } elseif (strtotime($row['expires_at']) < time()) {
                    $tokenError = 'This reset link has expired. Please request a new one.';
                } else {
                    $validToken = true;
                }
            } else {
                $tokenError = 'Reset system not initialised. Please use Forgot Password again.';
            }
        } catch (PDOException $e) {
            $tokenError = 'Database error. Please try again.';
        }
    }
} else {
    $tokenError = 'No reset token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2RICH CAPITAL - Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .reset-msg {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            text-align: center;
            margin-top: 12px;
            line-height: 1.6;
        }
        .reset-success { color: #4ade80; font-weight: 600; }
        .reset-error-msg { color: #ff6b6b; font-size: 11px; margin-top: 8px; display: none; }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            font-size: 11px;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.3);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: rgba(255,255,255,0.7); }
        .strength-bar {
            height: 3px;
            border-radius: 2px;
            background: rgba(255,255,255,0.07);
            margin-top: 6px;
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            border-radius: 2px;
            width: 0;
            transition: width 0.3s, background 0.3s;
        }
        .token-error {
            font-size: 12px;
            color: #ff6b6b;
            text-align: center;
            margin-top: 8px;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="system-status">
        <span class="status-dot"></span>
        <span class="status-text">SYSTEM ONLINE</span>
    </div>
    <div class="login-card">
        <div class="login-header">
            <img src="https://2rich.capital/wp-content/uploads/2026/05/2rich-trading-macro-research-desk.webp" alt="2RICH CAPITAL" class="brand-logo">
        </div>

        <?php if (!$validToken): ?>
            <h2 class="login-title">INVALID LINK</h2>
            <p class="reset-msg">This reset link is invalid or has expired.<br>Please request a new one.</p>
            <?php if ($tokenError): ?>
                <p class="token-error"><?= htmlspecialchars($tokenError) ?></p>
            <?php endif; ?>
            <a href="https://app.2rich.capital/login" class="back-link">&larr; BACK TO LOGIN</a>

        <?php else: ?>
            <h2 class="login-title">NEW PASSWORD</h2>

            <div id="resetFormWrap">
                <div class="form-group">
                    <label for="newPassword">NEW ACCESS KEY</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="newPassword" placeholder="Min. 8 characters" autocomplete="new-password">
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">CONFIRM ACCESS KEY</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="confirmPassword" placeholder="Repeat password" autocomplete="new-password">
                    </div>
                </div>

                <p class="reset-error-msg" id="resetError"></p>
                <button class="submit-btn" id="resetBtn">UPDATE PASSWORD &rarr;</button>
            </div>

            <div id="resetSuccessWrap" style="display:none;">
                <p class="reset-msg"><span class="reset-success">&checkmark; Password updated!</span><br>You can now log in with your new password.</p>
                <a href="https://app.2rich.capital/login" class="back-link">&rarr; GO TO LOGIN</a>
            </div>

        <?php endif; ?>

        <div class="login-footer" style="margin-top: 24px;">
            <span class="footer-text">&copy; 2026</span>
            <span class="footer-text">2RICH.CAPITAL</span>
        </div>
    </div>
</div>

<?php if ($validToken): ?>
<script>
const TOKEN = <?= json_encode($token) ?>;

document.getElementById('newPassword').addEventListener('input', function() {
    const val = this.value;
    const fill = document.getElementById('strengthFill');
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const pct    = (score / 5) * 100;
    const colors = ['#ff6b6b','#facc15','#facc15','#4ade80','#4ade80'];
    fill.style.width      = pct + '%';
    fill.style.background = colors[Math.max(0, score - 1)] || '#ff6b6b';
});

document.getElementById('resetBtn').addEventListener('click', async function() {
    const password = document.getElementById('newPassword').value;
    const confirm  = document.getElementById('confirmPassword').value;
    const errorEl  = document.getElementById('resetError');
    errorEl.style.display = 'none';

    if (password.length < 8) {
        errorEl.textContent = 'Password must be at least 8 characters.';
        errorEl.style.display = 'block'; return;
    }
    if (password !== confirm) {
        errorEl.textContent = 'Passwords do not match.';
        errorEl.style.display = 'block'; return;
    }

    this.disabled = true;
    this.textContent = 'UPDATING...';

    try {
        const res  = await fetch('/auth/reset-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: TOKEN, password, confirm })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('resetFormWrap').style.display  = 'none';
            document.getElementById('resetSuccessWrap').style.display = 'block';
        } else {
            errorEl.textContent   = data.message || 'An error occurred.';
            errorEl.style.display = 'block';
            this.disabled    = false;
            this.textContent = 'UPDATE PASSWORD ->';
        }
    } catch(e) {
        errorEl.textContent   = 'Connection error. Please try again.';
        errorEl.style.display = 'block';
        this.disabled    = false;
        this.textContent = 'UPDATE PASSWORD ->';
    }
});
</script>
<?php endif; ?>
</body>
</html>