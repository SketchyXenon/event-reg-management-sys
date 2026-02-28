<?php
require_once __DIR__ . '/backend/auth_guard.php';
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/csrf_helper.php';
require_once __DIR__ . '/backend/password_helper.php';

require_login('login.php');

if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// ── Fetch current user data ────────────────────────────────
$user = $pdo->prepare("SELECT full_name, email, student_id, created_at FROM users WHERE user_id = ?");
$user->execute([$user_id]);
$user = $user->fetch(PDO::FETCH_ASSOC);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = $_POST['action'] ?? '';

    //Update Profile Info 
    if ($action === 'update_info') {

        $full_name_input = trim($_POST['full_name'] ?? '');
        $email_input     = trim($_POST['email']     ?? '');

        if ($full_name_input === '') {
            $error = 'Full name is required.';
        } elseif (mb_strlen($full_name_input) > 100) {
            $error = 'Full name must not exceed 100 characters.';
        } elseif ($email_input === '') {
            $error = 'Email address is required.';
        } elseif (mb_strlen($email_input) > 150) {
            $error = 'Email must not exceed 150 characters.';
        } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email_input, $user_id]);

            if ($stmt->fetch()) {
                $error = 'Email already in use.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$full_name_input, $email_input, $user_id]);

                $_SESSION['full_name'] = $full_name_input;
                $full_name             = $full_name_input;

                $stmt = $pdo->prepare("SELECT full_name, email, student_id, created_at FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $success = 'Profile updated successfully.';
            }
        }

    //  Change Password 
    } elseif ($action === 'change_password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['password']         ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($current_password, $row['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $strength_result = validate_password_strength($new_password);

            if (!$strength_result['valid']) {
                $error = implode(' ', $strength_result['errors']);
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([hash_password($new_password), $user_id]);
                $success = 'Password changed successfully.';
            }
        }

    //Deactivate Account 
    } elseif ($action === 'deactivate_account') {

        $confirm = trim($_POST['confirm_deactivate'] ?? '');

        if ($confirm !== 'DEACTIVATE') {
            $error = 'Please type DEACTIVATE to confirm.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
            $stmt->execute([$user_id]);

            session_unset();
            session_destroy();
            header('Location: login.php?account=deactivated');
            exit;
        }
    }
}  // ← single closing brace for the POST block
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile — ERMS</title>
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="has-sidebar">

<!-- ══ SIDEBAR ═══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-crest">E</div>
    <span class="brand-name">ERMS</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Student</div>

    <a href="dashboard.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      Dashboard
    </a>

    <a href="events.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      Browse Events
    </a>

    <a href="my-registrations.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
      My Registrations
    </a>

    <div class="nav-label" style="margin-top:8px">Account</div>

    <a href="profile.php" class="nav-item active">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
      </svg>
      My Profile
    </a>

    <a href="index.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3"/>
      </svg>
      Homepage
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
      <div class="user-info">
        <div class="name"><?= htmlspecialchars($full_name) ?></div>
        <div class="role">Student</div>
      </div>
    </div>
    <a href="backend/logout.php" class="logout-btn">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Sign Out
    </a>
  </div>
</aside>

<!-- ══ TOPBAR ════════════════════════════════════════════════ -->
<div class="topbar">
  <button id="menuBtn" style="background:none;border:none;cursor:pointer;color:var(--text-2);display:none;padding:4px"
    onclick="document.getElementById('sidebar').classList.toggle('open')">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
  </button>
  <div>
    <div class="topbar-title">My Profile</div>
    <div class="topbar-sub">Manage your account settings</div>
  </div>
  <div class="topbar-space"></div>
  <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
    <span id="themeIcon">☀️</span>
  </button>
</div>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<main class="main">
  <div class="page">

    <?php if ($success): ?>
      <div class="alert alert-success" data-auto-dismiss>✓ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" data-auto-dismiss>⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── Profile Header ──────────────────────────────────── -->
    <div class="welcome-banner" style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
      <div class="user-avatar" style="width:72px;height:72px;font-size:2rem;flex-shrink:0">
        <?= strtoupper(substr($full_name, 0, 1)) ?>
      </div>
      <div>
        <div style="font-family:var(--ff-d);font-size:1.5rem;font-weight:700"><?= htmlspecialchars($full_name) ?></div>
        <div style="color:var(--text-2);font-size:0.85rem;margin-top:4px"><?= htmlspecialchars($user['email']) ?></div>
        <div style="color:var(--text-3);font-size:0.75rem;margin-top:2px;font-family:var(--ff-m)">
          Student ID: <?= htmlspecialchars($user['student_id'] ?? '—') ?>
          &nbsp;·&nbsp;
          Member since <?= date('F Y', strtotime($user['created_at'])) ?>
        </div>
      </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:28px;">

      <!-- ── Update Info Form ─────────────────────────────── -->
      <div class="card" style="padding:28px">
        <h2 style="font-family:var(--ff-d);font-size:1.1rem;margin-bottom:20px">Personal Information</h2>

        <form method="POST" action="profile.php">
          <?= csrf_token_field() ?>
          <input type="hidden" name="action" value="update_info">

          <div class="form-group">
            <label class="form-label" for="full_name">Full Name</label>
            <div class="input-wrap">
              <input type="text" name="full_name" id="full_name" class="form-control"
                value="<?= htmlspecialchars($user['full_name']) ?>" required>
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <div class="input-wrap">
              <input type="email" name="email" id="email" class="form-control"
                value="<?= htmlspecialchars($user['email']) ?>" required>
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
              </svg>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Student ID</label>
            <div class="input-wrap">
              <input type="text" class="form-control" value="<?= htmlspecialchars($user['student_id'] ?? '—') ?>" disabled
                style="opacity:0.5;cursor:not-allowed">
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
              </svg>
            </div>
            <div class="input-hint">Student ID cannot be changed. Contact admin if needed.</div>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%">
            Save Changes
          </button>
        </form>
      </div>

      <!-- ── Change Password Form ─────────────────────────── -->
      <div class="card" style="padding:28px">
        <h2 style="font-family:var(--ff-d);font-size:1.1rem;margin-bottom:20px">Change Password</h2>

        <form method="POST" action="profile.php">
          <?= csrf_token_field() ?>
          <input type="hidden" name="action" value="change_password">

          <div class="form-group">
            <label class="form-label" for="current_password">Current Password</label>
            <div class="input-wrap">
              <input type="password" name="current_password" id="current_password" class="form-control"
                placeholder="Enter current password" required>
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              <button type="button" class="pw-toggle" id="pwToggle">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="password">New Password</label>
            <div class="input-wrap">
              <input type="password" name="password" id="password" class="form-control"
                placeholder="Min. 8 characters" required>
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
              <button type="button" class="pw-toggle" id="pwToggle1">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
            <div class="pw-strength"><div class="pw-strength-bar" id="strengthBar"></div></div>
            <div class="input-hint" id="strengthLabel">Enter a password</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="confirm_password">Confirm New Password</label>
            <div class="input-wrap">
              <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                placeholder="Re-enter new password" required>
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
              <button type="button" class="pw-toggle" id="pwToggle2">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
            <div class="input-hint" id="confirmHint"></div>
          </div>

          <button type="submit" class="btn btn-outline" style="width:100%">
            Update Password
          </button>
        </form>
      </div>

    </div>

  <!-- ── Danger Zone ──────────────────────────────────────── -->
    <div class="card" style="padding:24px; margin-top:24px; border-color:rgba(196,92,92,0.3)">
      <h2 style="font-family:var(--ff-d);font-size:1rem;color:var(--red);margin-bottom:8px">Danger Zone</h2>
      <p style="color:var(--text-2);font-size:0.85rem;margin-bottom:16px">
        Deactivating your account will cancel all pending registrations. This cannot be undone.
      </p>
      <button class="btn btn-danger" onclick="openModal('deactivateModal')">Deactivate Account</button>
    </div>

    <!-- ── Deactivate Confirmation Modal ───────────────────── -->
    <div class="modal-overlay" id="deactivateModal">
      <div class="modal">
        <div class="modal-header">
          <h2 class="modal-title" style="color:var(--red)">⚠ Deactivate Account</h2>
          <button class="modal-close" onclick="closeModal('deactivateModal')">✕</button>
        </div>
        <form method="POST" action="profile.php">
          <?= csrf_token_field() ?>
          <input type="hidden" name="action" value="deactivate_account">
          <div class="modal-body">
            <p style="color:var(--text-2);font-size:0.9rem;margin-bottom:16px">
              This will <strong>permanently deactivate</strong> your account and cancel all pending registrations.
              You will be logged out immediately.
            </p>
            <div class="form-group">
              <label class="form-label">Type <strong style="color:var(--red)">DEACTIVATE</strong> to confirm</label>
              <input type="text" name="confirm_deactivate" class="form-control"
                placeholder="DEACTIVATE" autocomplete="off" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeModal('deactivateModal')">Cancel</button>
            <button type="submit" class="btn btn-danger">Yes, Deactivate My Account</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</main>

<script src="assets/js/global.js"></script>
</body>
</html>