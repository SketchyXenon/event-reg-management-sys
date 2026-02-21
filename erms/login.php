<?php
// ============================================================
//  login.php  (Frontend + Backend combined)
//  Dark Academic Institutional Design
//  Event Registration Management System
// ============================================================

session_start();
session_regenerate_id(true);

if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php';
    header("Location: $redirect");
    exit();
}

require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/password_helper.php';
require_once __DIR__ . '/backend/login_limiter.php';
require_once __DIR__ . '/backend/csrf_helper.php';
require_once __DIR__ . '/backend/security_headers.php';

$errors  = [];
$warning = '';
$email   = '';
$ip      = get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    if (empty($password))
        $errors[] = 'Password is required.';

    if (empty($errors)) {
        $ip_check = is_ip_blocked($ip);
        if ($ip_check['blocked']) {
            $errors[] = $ip_check['message'];
        } else {
            $lockout = is_account_locked($email);
            if ($lockout['locked']) {
                $errors[] = $lockout['message'];
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT user_id, full_name, email, password, role FROM users WHERE email = :email LIMIT 1"
                    );
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch();

                    if (!$user || !verify_password($password, $user['password'])) {
                        $attempt_info = record_failed_attempt($email, $ip);
                        if ($attempt_info['locked']) {
                            $errors[] = "Too many failed attempts. Account locked for <strong>{$attempt_info['lockout_time']}</strong>.";
                        } else {
                            $errors[] = 'Invalid email or password. Please try again.';
                            $warning  = remaining_attempts_message($attempt_info['attempts']);
                        }
                    } else {
                        if (needs_rehash($user['password'])) {
                            $new_hash = hash_password($password);
                            $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$new_hash, $user['user_id']]);
                        }
                        record_successful_login($email, $ip);
                        $_SESSION['user_id']            = $user['user_id'];
                        $_SESSION['full_name']           = $user['full_name'];
                        $_SESSION['email']               = $user['email'];
                        $_SESSION['role']                = $user['role'];
                        $_SESSION['last_activity']       = time();
                        $_SESSION['session_start_time']  = time();
                        header("Location: " . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
                        exit();
                    }
                } catch (PDOException $e) {
                    error_log("Login error: " . $e->getMessage());
                    $errors[] = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Event Registration System</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-wrapper">

  <!-- Left Branding Panel -->
  <div class="auth-panel">
    <div>
      <div class="brand-logo">🎓</div>
      <div class="brand-name">Event<br><span>Registration</span><br>System</div>
      <p class="brand-desc">
        Your institutional portal for discovering and joining campus events. Simple, secure, and always available.
      </p>
      <ul class="panel-features">
        <li>Browse all upcoming campus events</li>
        <li>Register in one click</li>
        <li>Track your registrations</li>
        <li>Instant confirmation</li>
      </ul>
    </div>
    <div class="panel-footer">
      v1.0.0 &nbsp;·&nbsp; Secured with bcrypt + CSRF
    </div>
  </div>

  <!-- Right Form Panel -->
  <div class="auth-form-panel">
    <div class="form-header">
      <h2>Welcome back</h2>
      <p>Sign in to your institutional account</p>
    </div>

    <?php if (isset($_GET['registered'])): ?>
      <div class="alert alert-success">✓ Account created successfully. You can now log in.</div>
    <?php endif; ?>

    <?php if (isset($_GET['logout'])): ?>
      <div class="alert alert-success">✓ You have been logged out successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['session'])): ?>
      <div class="alert alert-warning">
        ⏱ Your session has <?= $_GET['session'] === 'expired' ? 'expired' : 'timed out due to inactivity' ?>. Please log in again.
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= $err ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($warning)): ?>
      <div class="alert alert-warning">⚠ <?= htmlspecialchars($warning) ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST" novalidate>
      <?= csrf_token_field() ?>

      <div class="form-grid">

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email"
                 value="<?= htmlspecialchars($email) ?>"
                 placeholder="you@institution.edu.ph"
                 autocomplete="email" required>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password"
                   placeholder="Enter your password"
                   autocomplete="current-password" required>
            <button type="button" class="toggle-password" aria-label="Toggle password visibility">👁️</button>
          </div>
        </div>

      </div>

      <button type="submit" class="btn-primary">Sign In</button>

      <div class="form-divider">or</div>

      <p class="form-footer-link">
        Don't have an account? <a href="register.php">Register here</a>
      </p>

    </form>
  </div>

</div>

<script src="assets/js/validate.js"></script>
</body>
</html>