<?php
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/security_headers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── TODO: Implement forgot password logic ──────────────────
// Steps your colleague needs to implement:
// 1. Accept POST email input
// 2. Check if email exists in users table
// 3. Generate a secure token: bin2hex(random_bytes(32))
// 4. Store token + expiry (e.g. NOW() + 1 hour) in a password_resets table
//    CREATE TABLE password_resets (
//      id INT AUTO_INCREMENT PRIMARY KEY,
//      email VARCHAR(255),
//      token VARCHAR(64),
//      expires_at DATETIME,
//      used TINYINT(1) DEFAULT 0
//    );
// 5. Send reset link via mail() or PHPMailer:
//    $link = "http://localhost/erms/reset-password.php?token=$token";
// 6. Show success message regardless (don't reveal if email exists)

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
<<<<<<< HEAD

// 1. Check if email exists
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // 2. Generate secure token
    $token = bin2hex(random_bytes(32));
    // 3. Store token + expiry (1 hour)
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (email, token, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ");
    $stmt->execute([$email, $token]);

    // 4. Reset link (for now: just display / log it)
    $resetLink = "http://localhost/event-reg-management-sys-main/erms/reset-password.php?token=$token";

    //  DEV MODE ONLY (replace with email later)
    error_log("Password reset link: " . $resetLink);
}
$success = true;
=======
        // TODO: replace this block with actual email + token logic
        $success = true; // placeholder
>>>>>>> 1abe8f6eb5831a2dc1380e6163e6e27798b6c8ea
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password — ERMS</title>
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-layout">

<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>

<div class="auth-wrap">

  <!-- ── Brand Panel ─────────────────────────────────────── -->
  <aside class="auth-brand">
    <div class="brand-top">
      <div class="brand-crest">E</div>
      <div class="brand-name">ERMS</div>
    </div>
    <div class="brand-hero">
      <h2 class="brand-title">Reset your<br><em>Password</em></h2>
      <div class="brand-quote">
        <p>"The secret of getting ahead is getting started."</p>
        <cite>— Mark Twain</cite>
      </div>
    </div>
  </aside>

  <!-- ── Form Panel ──────────────────────────────────────── -->
  <div class="auth-form-panel">
    <div class="auth-form-box">

      <a href="login.php" class="back-link">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Sign In
      </a>

      <div class="form-heading">
        <h1>Forgot Password</h1>
        <p>Enter your registered email and we'll send you a reset link.</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error" data-auto-dismiss>⚠ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <!-- Success State -->
        <div class="alert alert-success">
          ✓ If that email is registered, a reset link has been sent. Check your inbox.
        </div>
        <p style="text-align:center; margin-top:24px">
          <a href="login.php" class="btn btn-primary">Back to Sign In</a>
        </p>

      <?php else: ?>
        <!-- Form State -->
        <form method="POST" action="forgot-password.php">
          <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <div class="input-wrap">
              <input type="email" name="email" id="email" class="form-control"
                placeholder="you@university.edu"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required autofocus>
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
              </svg>
            </div>
          </div>

          <button type="submit" class="btn-submit student-btn" id="submitBtn">
            Send Reset Link
          </button>
        </form>

      <?php endif; ?>

    </div>
  </div>

</div>

<script src="assets/js/global.js"></script>
<script>
document.getElementById('forgot-form')?.addEventListener('submit', function () {
  const btn = document.getElementById('submitBtn');
  btn.disabled    = true;
  btn.textContent = 'Sending…';
});
</script>
</body>
</html>