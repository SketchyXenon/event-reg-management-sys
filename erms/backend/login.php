<?php
// ============================================================
//  login.php
//  Handles student/admin login with:
//  - PDO prepared statements
//  - bcrypt password verification
//  - Session management & fixation protection
//  - Login rate limiting & account lockout
//  Event Registration Management System
// ============================================================

session_start();
session_regenerate_id(true);

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect = $_SESSION['role'] === 'admin' ? '../admin/dashboard.php' : '../dashboard.php';
    header("Location: $redirect");
    exit();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/password_helper.php';
require_once __DIR__ . '/login_limiter.php';

$errors       = [];
$warning      = '';
$email        = '';
$ip           = get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Collect & sanitize inputs ──────────────────────────
    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'] ?? '';

    // ── 2. Basic validation ────────────────────────────────────
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {

        // ── 3. Check IP-level block first ──────────────────────
        $ip_check = is_ip_blocked($ip);
        if ($ip_check['blocked']) {
            $errors[] = $ip_check['message'];

        } else {

            // ── 4. Check account-level lockout ─────────────────
            $lockout = is_account_locked($email);
            if ($lockout['locked']) {
                $errors[] = $lockout['message'];

            } else {

                // ── 5. Lookup user in database ──────────────────
                try {
                    $stmt = $pdo->prepare(
                        "SELECT user_id, full_name, email, password, role
                           FROM users
                          WHERE email = :email
                          LIMIT 1"
                    );
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch();

                    // ── 6. Verify password ──────────────────────
                    if (!$user || !verify_password($password, $user['password'])) {

                        // Record failure & get updated attempt info
                        $attempt_info = record_failed_attempt($email, $ip);

                        if ($attempt_info['locked']) {
                            $errors[] = "Too many failed attempts. Your account is locked for <strong>{$attempt_info['lockout_time']}</strong>.";
                        } else {
                            $errors[] = 'Invalid email or password. Please try again.';
                            $warning  = remaining_attempts_message($attempt_info['attempts']);
                        }

                    } else {

                        // ── 7. Rehash if cost factor upgraded ───
                        if (needs_rehash($user['password'])) {
                            try {
                                $new_hash = hash_password($password);
                                $rehash_stmt = $pdo->prepare(
                                    "UPDATE users SET password = :password WHERE user_id = :user_id"
                                );
                                $rehash_stmt->execute([
                                    ':password' => $new_hash,
                                    ':user_id'  => $user['user_id'],
                                ]);
                            } catch (PDOException $e) {
                                error_log("Rehash error: " . $e->getMessage());
                            }
                        }

                        // ── 8. Record success & clear lockout ───
                        record_successful_login($email, $ip);

                        // ── 9. Set session variables ─────────────
                        $_SESSION['user_id']   = $user['user_id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email']     = $user['email'];
                        $_SESSION['role']      = $user['role'];

                        // ── 10. Redirect based on role ───────────
                        if ($user['role'] === 'admin') {
                            header("Location: ../admin/dashboard.php");
                        } else {
                            header("Location: ../dashboard.php");
                        }
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
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="form-container">
  <h2>Welcome Back</h2>
  <p class="subtitle">Log in to access events</p>

  <?php if (isset($_GET['registered'])): ?>
    <div class="alert alert-success">Account created! You can now log in.</div>
  <?php endif; ?>

  <?php if (isset($_GET['logout'])): ?>
    <div class="alert alert-success">You have been logged out successfully.</div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?= $error ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!empty($warning)): ?>
    <div class="alert alert-warning">
      ⚠️ <?= htmlspecialchars($warning) ?>
    </div>
  <?php endif; ?>

  <form action="login.php" method="POST" novalidate>

    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($email) ?>"
             placeholder="you@school.edu.ph" required>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             placeholder="Enter your password" required>
    </div>

    <button type="submit" class="btn-primary">Log In</button>

    <p class="form-footer">No account yet? <a href="register.php">Register here</a></p>

  </form>
</div>

<script src="../assets/js/validate.js"></script>
</body>
</html>