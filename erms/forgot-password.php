<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/security_headers.php';
require_once __DIR__ . '/backend/csrf_helper.php';

// Resend API called directly via curl — no SDK required

if (session_status() === PHP_SESSION_NONE) session_start();


if (isset($_SESSION['user_id'])) {
  header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
  exit;
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $email = trim($_POST['email'] ?? '');

  if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } else {
    $success = true;

    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
      // Invalidate any previous unused tokens for this email
      $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")
        ->execute([$email]);

      $token   = bin2hex(random_bytes(32));
      $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

      $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
        ->execute([$email, $token, $expires]);

      $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $host      = $_SERVER['HTTP_HOST'];
      $basePath  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
      $resetLink = "{$protocol}://{$host}{$basePath}/reset-password.php?token=" . urlencode($token);

      // ── Send email via Resend API (direct curl — no SDK) ──────
      if (!defined('RESEND_API_KEY') || empty(RESEND_API_KEY)) {
        error_log('ERMS forgot-password: RESEND_API_KEY is not defined in config.php');
      } else {
        try {
          $payload = json_encode([
            'from'    => 'ERMS <noreply@resend.dev>',
            'to'      => [$email],
            'subject' => 'Reset your ERMS password',
            'text'    => "Hello,\n\n" .
              "We received a request to reset your ERMS password.\n\n" .
              "Click the link below to set a new password (expires in 1 hour):\n" .
              "$resetLink\n\n" .
              "If you didn't request this, you can safely ignore this email — " .
              "your password won't change.\n\n" .
              "— ERMS Team",
            'html'    => "<!DOCTYPE html><html><body style=\"font-family:sans-serif;color:#1a1a2e;background:#f5f5f5;padding:32px\">" .
              "<div style=\"max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.08)\">" .
              "<h2 style=\"margin-top:0;color:#1a1a2e\">Reset your password</h2>" .
              "<p>We received a request to reset the password for your ERMS account.</p>" .
              "<p style=\"margin:24px 0\">" .
              "<a href=\"{$resetLink}\" style=\"display:inline-block;padding:12px 24px;background:#4a7ab5;color:#fff;border-radius:8px;text-decoration:none;font-weight:600\">Reset Password &rarr;</a>" .
              "</p>" .
              "<p style=\"font-size:.85rem;color:#888\">This link expires in <strong>1 hour</strong>. If you didn't request a password reset, ignore this email.</p>" .
              "<hr style=\"border:none;border-top:1px solid #eee;margin:24px 0\">" .
              "<p style=\"font-size:.8rem;color:#aaa\">If the button doesn't work, copy and paste this link:<br><a href=\"{$resetLink}\" style=\"color:#4a7ab5\">{$resetLink}</a></p>" .
              "</div></body></html>",
          ]);

          $ch = curl_init('https://api.resend.com/emails');
          curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
              'Authorization: Bearer ' . RESEND_API_KEY,
              'Content-Type: application/json',
            ],
          ]);

          $response = curl_exec($ch);
          $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          curl_close($ch);

          if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            error_log("ERMS: Password reset email sent to {$email}, ID: " . ($result['id'] ?? 'unknown'));
          } else {
            error_log("ERMS: Resend API error {$httpCode}: {$response}");
          }

        } catch (\Exception $e) {
          error_log('ERMS email exception: ' . $e->getMessage());
        }
      }
      // ── End email ─────────────────────────────────────────────
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password - CTU Danao ERMS</title>
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>

<body class="auth-layout">

  <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
    <span id="themeIcon">☀️</span>
  </button>

  <div class="auth-wrap">

    <!-- Brand Panel -->
    <aside class="auth-brand">
      <div class="brand-top">
        <div class="brand-crest">CTU</div>
        <div class="brand-name"><span>CTU Danao</span> Registration and Management System</div>
      </div>
      <div class="brand-hero">
        <h2 class="brand-title">Reset your<br><em>Password</em></h2>
        <div class="brand-quote">
          <p>"The secret of getting ahead is getting started."</p>
          <cite>— Mark Twain</cite>
        </div>
      </div>
    </aside>

    <!-- Form Panel -->
    <div class="auth-form-panel">
      <div class="auth-form-box">

        <a href="login.php" class="back-link">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to Sign In
        </a>

        <div class="form-heading">
          <h1>Forgot Password</h1>
          <p>Enter your registered email and we'll send you a reset link.</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-error" data-auto-dismiss>
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <!-- Success state -->
          <div class="alert alert-success">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            If that email is registered, a reset link has been sent. Check your inbox (and spam folder).
          </div>
          <p style="text-align:center;margin-top:24px">
            <a href="login.php" class="btn btn-primary">Back to Sign In</a>
          </p>

        <?php else: ?>
          <!-- Form state -->
          <form method="POST" action="forgot-password.php" id="forgotForm">
            <?= csrf_token_field() ?>

            <div class="form-group">
              <label class="form-label" for="email">Email Address</label>
              <div class="input-wrap">
                <input type="email" name="email" id="email" class="form-control"
                  placeholder="you@university.edu"
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                  required autofocus>
                <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
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
    document.getElementById('forgotForm')?.addEventListener('submit', function() {
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Sending…';
    });
  </script>
</body>

</html>