<?php
require_once __DIR__ . '/backend/auth_guard.php';
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/csrf_helper.php';
require_once __DIR__ . '/backend/password_helper.php';

require_login('login.php');
if ($_SESSION['role'] === 'admin') { header('Location: admin/dashboard.php'); exit; }

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$success   = '';
$error     = '';
$active_tab = 'info';

// ── Fetch current user ─────────────────────────────────────
$user = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$user->execute([$user_id]);
$user = $user->fetch(PDO::FETCH_ASSOC);

// ── Fetch registration stats for profile header ────────────
$reg_stats = $pdo->prepare(
    "SELECT
       COUNT(*) AS total,
       SUM(r.status = 'confirmed') AS confirmed,
       SUM(r.status != 'cancelled' AND e.date_time >= NOW()) AS upcoming
     FROM registrations r
     JOIN events e ON e.event_id = r.event_id
     WHERE r.user_id = ?"
);
$reg_stats->execute([$user_id]);
$reg_stats = $reg_stats->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();  // no args — reads from $_POST automatically
    $action = $_POST['action'] ?? '';

    // ── Update profile info ────────────────────────────────
    if ($action === 'update_info') {
        $active_tab = 'info';
        $new_name   = trim($_POST['full_name'] ?? '');
        $new_email  = trim($_POST['email']     ?? '');

        if (!$new_name) {
            $error = 'Full name is required.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $check->execute([$new_email, $user_id]);
            if ($check->fetchColumn() > 0) {
                $error = 'That email is already in use by another account.';
            } else {
                $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?")
                    ->execute([$new_name, $new_email, $user_id]);
                $_SESSION['full_name'] = $new_name;
                $_SESSION['email']     = $new_email;
                $full_name             = $new_name;
                $user['full_name']     = $new_name;
                $user['email']         = $new_email;
                $success = 'Profile updated successfully.';
            }
        }
    }

    // ── Change password ────────────────────────────────────
    if ($action === 'change_password') {
        $active_tab = 'password';
        $current    = $_POST['current_password']  ?? '';
        $new_pw     = $_POST['password']           ?? '';
        $confirm    = $_POST['confirm_password']   ?? '';

        if (!verify_password($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (!validate_password_strength($new_pw)) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($new_pw !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (verify_password($new_pw, $user['password'])) {
            $error = 'New password must be different from your current one.';
        } else {
            $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")
                ->execute([hash_password($new_pw), $user_id]);
            $success = 'Password changed successfully.';
            $active_tab = 'info';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile — ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
</head>
<body class="has-sidebar">

<!-- ── Sidebar ────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div style="display:flex;flex-direction:column;flex-shrink:0;padding:16px 14px 14px;border-bottom:1px solid var(--border);">
    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
      <div style="width:34px;height:34px;flex-shrink:0;background:linear-gradient(135deg,#4a7ab5,#c9a84c);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.95rem;font-weight:700;color:#080b10;box-shadow:0 0 14px rgba(74,122,181,.4);">E</div>
      <div style="min-width:0;overflow:hidden;">
        <div style="font-family:var(--ff-d);font-size:.9rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;">ERMS</div>
        <div style="font-size:.6rem;color:var(--text-3);letter-spacing:.1em;text-transform:uppercase;margin-top:2px;">Student Portal</div>
      </div>
    </div>
    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
      <div id="sbTime" style="font-family:var(--ff-m,'JetBrains Mono',monospace);font-size:1.1rem;font-weight:500;color:var(--text);letter-spacing:.06em;">--:--:--</div>
      <div id="sbDate" style="font-size:.58rem;color:var(--text-3);letter-spacing:.04em;text-transform:uppercase;margin-top:4px;">--- --, ----</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Menu</div>
    <a href="dashboard.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="events.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Browse Events
    </a>
    <a href="my-registrations.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      My Registrations
    </a>
    <div class="nav-label" style="margin-top:8px">Account</div>
    <a href="profile.php" class="nav-item active">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      My Profile
    </a>
    <a href="index.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3"/></svg>
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
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Sign Out
    </a>
  </div>
</aside>

<!-- ── Topbar ──────────────────────────────────────────────── -->
<header class="topbar">
  <button id="menuBtn" class="theme-toggle-btn" style="display:none"
          onclick="document.getElementById('sidebar').classList.toggle('open')">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
  </button>
  <div>
    <div class="topbar-title">My Profile</div>
    <div class="topbar-sub">Manage your account settings</div>
  </div>
  <div class="topbar-space"></div>
  <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>
</header>

<!-- ── Main ───────────────────────────────────────────────── -->
<main class="main">
  <div class="page">

    <?php if ($success): ?>
      <div class="alert alert-success" data-auto-dismiss>
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" data-auto-dismiss>
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Profile header card -->
    <div class="profile-hero">
      <div class="profile-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
      <div class="profile-hero-info">
        <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        <div class="profile-meta">
          <?php if ($user['student_id']): ?>
            <span>
              <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
              ID: <?= htmlspecialchars($user['student_id']) ?>
            </span>
          <?php endif; ?>
          <span>
            <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Member since <?= date('F Y', strtotime($user['created_at'])) ?>
          </span>
        </div>
      </div>
      <!-- Mini stats -->
      <div class="profile-stats">
        <div class="pstat">
          <div class="pstat-val"><?= (int)$reg_stats['total'] ?></div>
          <div class="pstat-lbl">Registered</div>
        </div>
        <div class="pstat">
          <div class="pstat-val"><?= (int)$reg_stats['confirmed'] ?></div>
          <div class="pstat-lbl">Confirmed</div>
        </div>
        <div class="pstat">
          <div class="pstat-val"><?= (int)$reg_stats['upcoming'] ?></div>
          <div class="pstat-lbl">Upcoming</div>
        </div>
      </div>
    </div>

    <!-- Two-column forms -->
    <div class="profile-grid">

      <!-- Personal Information -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Personal Information</div>
            <div class="card-sub">Update your name and email address</div>
          </div>
        </div>
        <div class="card-body">
          <form method="POST" action="profile.php">
            <?= csrf_token_field() ?>
            <input type="hidden" name="action" value="update_info">

            <div class="form-group">
              <label class="form-label" for="full_name">Full Name</label>
              <div class="input-wrap">
                <input type="text" name="full_name" id="full_name" class="form-control"
                  value="<?= htmlspecialchars($user['full_name']) ?>" required>
                <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="email">Email Address</label>
              <div class="input-wrap">
                <input type="email" name="email" id="email" class="form-control"
                  value="<?= htmlspecialchars($user['email']) ?>" required>
                <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Student ID</label>
              <div class="input-wrap">
                <input type="text" class="form-control"
                  value="<?= htmlspecialchars($user['student_id'] ?? '—') ?>"
                  disabled style="opacity:.5;cursor:not-allowed">
              </div>
              <div class="input-hint">Student ID cannot be changed. Contact admin if needed.</div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:4px">Save Changes</button>
          </form>
        </div>
      </div>

      <!-- Change Password -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Change Password</div>
            <div class="card-sub">Choose a strong, unique password</div>
          </div>
        </div>
        <div class="card-body">
          <form method="POST" action="profile.php">
            <?= csrf_token_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
              <label class="form-label" for="current_password">Current Password</label>
              <div class="input-wrap">
                <input type="password" name="current_password" id="current_password" class="form-control"
                  placeholder="Enter current password" required>
                <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <button type="button" class="pw-toggle" id="pwToggle">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="password">New Password</label>
              <div class="input-wrap">
                <input type="password" name="password" id="password" class="form-control"
                  placeholder="Min. 8 characters" required>
                <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <button type="button" class="pw-toggle" id="pwToggle1">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
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
                <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <button type="button" class="pw-toggle" id="pwToggle2">
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
              </div>
              <div class="input-hint" id="confirmHint"></div>
            </div>

            <button type="submit" class="btn btn-outline" style="width:100%;margin-top:4px">Update Password</button>
          </form>
        </div>
      </div>

    </div><!-- /profile-grid -->
  </div>
</main>

<style>
/* ── Profile hero card ─────────────────────────────────── */
.profile-hero       { display:flex; align-items:center; gap:24px; flex-wrap:wrap;
                      background:var(--bg-card); border:1px solid var(--border);
                      border-radius:12px; padding:24px 28px; margin-bottom:24px; }
.profile-avatar     { width:72px; height:72px; border-radius:50%; flex-shrink:0;
                      background:linear-gradient(135deg,var(--blue),var(--blue-l));
                      display:flex; align-items:center; justify-content:center;
                      font-family:var(--ff-d); font-size:2rem; font-weight:700; color:#fff; }
.profile-hero-info  { flex:1; min-width:0; }
.profile-name       { font-family:var(--ff-d); font-size:1.4rem; font-weight:700; color:var(--text); }
.profile-email      { font-size:.85rem; color:var(--text-2); margin-top:3px; }
.profile-meta       { display:flex; flex-wrap:wrap; gap:14px; margin-top:7px;
                      font-size:.74rem; color:var(--text-3); }
.profile-meta span  { display:flex; align-items:center; gap:4px; }
.profile-stats      { display:flex; gap:24px; flex-shrink:0; }
.pstat              { text-align:center; }
.pstat-val          { font-family:var(--ff-d); font-size:1.6rem; font-weight:700; color:var(--text); line-height:1; }
.pstat-lbl          { font-size:.68rem; color:var(--text-3); text-transform:uppercase;
                      letter-spacing:.05em; margin-top:3px; }
/* ── Profile grid ──────────────────────────────────────── */
.profile-grid       { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
/* ── Responsive ─────────────────────────────────────────── */
@media (max-width:900px) { .profile-grid { grid-template-columns:1fr; } }
@media (max-width:768px) {
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0); }
  .main { margin-left:0 !important; }
  #menuBtn { display:flex !important; }
  .profile-stats { gap:16px; }
  .profile-hero { flex-direction:column; align-items:flex-start; }
}
</style>

<script src="assets/js/global.js"></script>
<script>
(function() {
  var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  function tick() {
    var now = new Date();
    var t = document.getElementById('sbTime');
    var d = document.getElementById('sbDate');
    if (t) t.textContent = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0') + ':' + String(now.getSeconds()).padStart(2,'0');
    if (d) d.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ' ' + now.getFullYear();
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
</body>
</html>