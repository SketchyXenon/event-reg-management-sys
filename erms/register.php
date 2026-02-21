<?php
// ============================================================
//  register.php  (Frontend + Backend combined)
//  Dark Academic Institutional Design
//  Event Registration Management System
// ============================================================

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/password_helper.php';
require_once __DIR__ . '/backend/csrf_helper.php';
require_once __DIR__ . '/backend/security_headers.php';

$errors     = [];
$full_name  = '';
$student_id = '';
$email      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $full_name  = trim(htmlspecialchars($_POST['full_name']  ?? '', ENT_QUOTES, 'UTF-8'));
    $student_id = trim(htmlspecialchars($_POST['student_id'] ?? '', ENT_QUOTES, 'UTF-8'));
    $email      = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password   = $_POST['password']         ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name) || strlen($full_name) < 2 || strlen($full_name) > 150)
        $errors[] = 'Full name must be between 2 and 150 characters.';

    if (empty($student_id) || !preg_match('/^[A-Za-z0-9\-]+$/', $student_id))
        $errors[] = 'Student ID may only contain letters, numbers, and hyphens.';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } else {
        $strength = validate_password_strength($password);
        if (!$strength['valid']) $errors = array_merge($errors, $strength['errors']);
    }

    if ($password !== $confirm_pw)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email OR student_id = :student_id LIMIT 1");
            $stmt->execute([':email' => $email, ':student_id' => $student_id]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'An account with that email or student ID already exists.';
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }

    if (empty($errors)) {
        try {
            $hashed = hash_password($password);
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, student_id, email, password, role) VALUES (:fn, :sid, :em, :pw, 'student')"
            );
            $stmt->execute([':fn' => $full_name, ':sid' => $student_id, ':em' => $email, ':pw' => $hashed]);

            $_SESSION['user_id']           = $pdo->lastInsertId();
            $_SESSION['full_name']         = $full_name;
            $_SESSION['email']             = $email;
            $_SESSION['role']              = 'student';
            $_SESSION['last_activity']     = time();
            $_SESSION['session_start_time']= time();

            header("Location: dashboard.php?registered=1");
            exit();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — Event Registration System</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-wrapper" style="max-width: 960px;">

  <!-- Left Branding Panel -->
  <div class="auth-panel">
    <div>
      <div class="brand-logo">🎓</div>
      <div class="brand-name">Join the<br><span>Community</span></div>
      <p class="brand-desc">
        Create your institutional account to start exploring and registering for campus events.
      </p>
      <ul class="panel-features">
        <li>Instant account activation</li>
        <li>Secure bcrypt password protection</li>
        <li>Access all campus events</li>
        <li>Manage your registrations</li>
      </ul>
    </div>
    <div class="panel-footer">
      v1.0.0 &nbsp;·&nbsp; Secured with bcrypt + CSRF
    </div>
  </div>

  <!-- Right Form Panel -->
  <div class="auth-form-panel">
    <div class="form-header">
      <h2>Create an account</h2>
      <p>Fill in your institutional details to get started</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form action="register.php" method="POST" novalidate>
      <?= csrf_token_field() ?>

      <div class="form-grid">

        <!-- Row 1: Full Name + Student ID -->
        <div class="form-row">
          <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name"
                   value="<?= htmlspecialchars($full_name) ?>"
                   placeholder="Juan Dela Cruz"
                   autocomplete="name" required>
          </div>
          <div class="form-group">
            <label for="student_id">Student ID</label>
            <input type="text" id="student_id" name="student_id"
                   value="<?= htmlspecialchars($student_id) ?>"
                   placeholder="e.g. 2024-00123" required>
          </div>
        </div>

        <!-- Row 2: Email -->
        <div class="form-group">
          <label for="email">Institutional Email</label>
          <input type="email" id="email" name="email"
                 value="<?= htmlspecialchars($email) ?>"
                 placeholder="you@institution.edu.ph"
                 autocomplete="email" required>
        </div>

        <!-- Row 3: Password -->
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <input type="password" id="password" name="password"
                   placeholder="Min. 8 chars — uppercase, number, symbol"
                   autocomplete="new-password" required>
            <button type="button" class="toggle-password" aria-label="Toggle password">👁️</button>
          </div>
          <div class="strength-bar"><div class="strength-fill"></div></div>
          <span class="strength-text"></span>
        </div>

        <!-- Row 4: Confirm Password -->
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <div class="input-wrapper">
            <input type="password" id="confirm_password" name="confirm_password"
                   placeholder="Re-enter your password"
                   autocomplete="new-password" required>
            <button type="button" class="toggle-password" aria-label="Toggle confirm password">👁️</button>
          </div>
        </div>

      </div><!-- end form-grid -->

      <button type="submit" class="btn-primary">Create Account</button>

      <div class="form-divider">already have an account?</div>

      <p class="form-footer-link">
        <a href="login.php">Sign in here</a>
      </p>

    </form>
  </div>

</div>

<script src="assets/js/validate.js"></script>
</body>
</html>