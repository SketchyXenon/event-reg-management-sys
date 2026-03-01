<?php
error_log("TEST: forgot-password.php loaded at " . date('Y-m-d H:i:s'));
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/security_headers.php';
require_once __DIR__ . '/backend/password_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// --- Get token from URL (for initial display) ---
$token   = trim($_GET['token'] ?? '');
$valid   = false;
$email   = '';
$success = false;
$error   = '';

// Validate token on GET (to decide whether to show the form)
if ($token) {
    $stmt = $pdo->prepare("
        SELECT email
        FROM password_resets
        WHERE token = ?
          AND used = 0
          AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) {
        $valid = true;
        $email = $row['email'];
    } else {
        $error = 'Invalid or expired reset link.';
    }
} else {
    $error = 'Missing reset token.';
}

// --- Process form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = $_POST['token'] ?? '';
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Re-validate the token (fresh check)
        $stmt = $pdo->prepare("
            SELECT email
            FROM password_resets
            WHERE token = ?
              AND used = 0
              AND expires_at > NOW()
        ");
        $stmt->execute([$postToken]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = 'Invalid or expired reset token.';
        } else {
            // Update password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")
                ->execute([$hashed, $reset['email']]);

            // Mark token as used
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
                ->execute([$postToken]);

            $success = true;
            $error = ''; // clear any previous errors
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — ERMS</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>

<body class="auth-layout">

    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>

    <div class="auth-wrap">

        <!-- Brand Panel -->
        <aside class="auth-brand">
            <div class="brand-top">
                <div class="brand-crest">E</div>
                <div class="brand-name">ERMS</div>
            </div>
            <div class="brand-hero">
                <h2 class="brand-title">Create a new<br><em>Password</em></h2>
                <div class="brand-quote">
                    <p>"Every new beginning comes from some other beginning's end."</p>
                    <cite>— Seneca</cite>
                </div>
            </div>
        </aside>

        <!-- Form Panel -->
        <div class="auth-form-panel">
            <div class="auth-form-box">

                <?php if (!$token): ?>
                    <!-- No token provided -->
                    <div class="form-heading">
                        <h1>Invalid Link</h1>
                        <p>This reset link is missing or malformed.</p>
                    </div>
                    <a href="forgot-password.php" class="btn btn-primary" style="display:block;text-align:center;margin-top:24px">
                        Request a new link
                    </a>

                <?php elseif (!$valid): ?>
                    <!-- Token expired or already used -->
                    <div class="form-heading">
                        <h1>Link Expired</h1>
                        <p>This reset link has expired or already been used.</p>
                    </div>
                    <a href="forgot-password.php" class="btn btn-primary" style="display:block;text-align:center;margin-top:24px">
                        Request a new link
                    </a>

                <?php elseif ($success): ?>
                    <!-- Password reset successfully -->
                    <div class="form-heading">
                        <h1>Password Updated ✓</h1>
                        <p>Your password has been changed. You can now sign in.</p>
                    </div>
                    <a href="login.php" class="btn btn-primary" style="display:block;text-align:center;margin-top:24px">
                        Sign In →
                    </a>

                <?php else: ?>
                    <!-- Show reset form -->
                    <div class="form-heading">
                        <h1>Reset Password</h1>
                        <p>Choose a strong new password for your account.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error" data-auto-dismiss>⚠ <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="reset-password.php" id="resetForm">
                        <!-- Pass the token in a hidden field (also keep it in the action URL for convenience) -->
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="form-group">
                            <label class="form-label" for="password">New Password</label>
                            <div class="input-wrap">
                                <input type="password" name="password" id="password" class="form-control"
                                    placeholder="Min. 8 characters" required autofocus>
                                <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <button type="button" class="pw-toggle" id="pwToggle1">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <div class="pw-strength">
                                <div class="pw-strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="input-hint" id="strengthLabel">Enter a password</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <div class="input-wrap">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                    placeholder="Re-enter password" required>
                                <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <button type="button" class="pw-toggle" id="pwToggle2">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                            <div class="input-hint" id="confirmHint"></div>
                        </div>

                        <button type="submit" class="btn-submit student-btn" id="submitBtn">
                            Update Password
                        </button>
                    </form>
                <?php endif; ?>

            </div> <!-- /.auth-form-box -->
        </div> <!-- /.auth-form-panel -->
    </div> <!-- /.auth-wrap -->

    <script src="assets/js/global.js"></script>
    <script>
        document.getElementById('resetForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Updating…';
        });
    </script>
</body>

</html>