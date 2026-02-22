<?php
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/auth_guard.php';
require_once __DIR__ . '/backend/csrf_helper.php';
require_once __DIR__ . '/backend/login_limiter.php';
require_once __DIR__ . '/backend/password_helper.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

$error        = '';
$success      = '';
$error_type   = '';
$seconds_left = 0;
$attempts_left = 0;

if (isset($_GET['session'])) {
    if ($_GET['session'] === 'timeout') { $error = 'Your session timed out. Please sign in again.';  $error_type = 'info'; }
    if ($_GET['session'] === 'expired') { $error = 'Your session has expired. Please sign in again.'; $error_type = 'info'; }
}
if (isset($_GET['logout'])) {
    $success = 'You have been signed out successfully.';
}

$login_mode = $_POST['login_mode'] ?? $_GET['mode'] ?? 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $login_mode = $_POST['login_mode'] ?? 'student';
    $ip         = $_SERVER['REMOTE_ADDR'];

    if (!$email || !$password) {
        $error = 'Please fill in both email and password.';
        $error_type = 'validation';
    } else {
        $ip_check = is_ip_blocked($ip);
        if ($ip_check['blocked']) {
            $error        = $ip_check['message'];
            $error_type   = 'rate_limit';
            $seconds_left = $ip_check['seconds_left'] ?? 0;
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $lock = $user
                ? is_account_locked($email)
                : ['locked' => false, 'seconds_left' => 0, 'message' => ''];

            if ($lock['locked']) {
                $error        = $lock['message'];
                $error_type   = 'account_locked';
                $seconds_left = $lock['seconds_left'] ?? 0;

            } elseif (!$user || !verify_password($password, $user['password'])) {
                record_failed_attempt($email, $ip);
                $recheck = $user ? is_account_locked($email) : ['locked' => false, 'seconds_left' => 0, 'message' => ''];

                if ($recheck['locked']) {
                    $error        = $recheck['message'];
                    $error_type   = 'account_locked';
                    $seconds_left = $recheck['seconds_left'] ?? 0;
                } else {
                    if ($user) {
                        $ua = $pdo->prepare("SELECT failed_attempts FROM users WHERE email = ? LIMIT 1");
                        $ua->execute([$email]);
                        $ua_row = $ua->fetch(PDO::FETCH_ASSOC);
                        $done   = $ua_row ? (int)$ua_row['failed_attempts'] : 1;
                    } else {
                        $done = 1;
                    }
                    $max_attempts  = 5;
                    $attempts_left = max(0, $max_attempts - $done);

                    if ($attempts_left > 0) {
                        $error = "Invalid email or password. "
                               . $attempts_left . " attempt" . ($attempts_left === 1 ? '' : 's')
                               . " remaining before your account is temporarily locked.";
                    } else {
                        $error = 'Invalid email or password.';
                    }
                    $error_type = 'invalid_credentials';
                }

            } elseif (!$user['is_active']) {
                $error      = 'Your account has been deactivated. Please contact the administrator.';
                $error_type = 'inactive';

            } elseif ($login_mode === 'admin' && $user['role'] !== 'admin') {
                record_failed_attempt($email, $ip);
                $error      = 'Access denied. This account does not have administrator privileges.';
                $error_type = 'access_denied';

            } else {
                record_successful_login($user['email'], $ip);
                session_regenerate_id(true);
                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['full_name']     = $user['full_name'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['created_at']    = time();
                header('Location: ' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign In — ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-layout mode-<?= htmlspecialchars($login_mode) ?>">

<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>

<div class="auth-wrap">

  <aside class="auth-brand mode-<?= htmlspecialchars($login_mode) ?>" id="brandPanel">
    <div class="brand-top">
      <div class="brand-crest">E</div>
      <h2 class="brand-title" id="brandTitle">
        <?php if ($login_mode === 'admin'): ?>Administrator<br><em>Access Portal</em>
        <?php else: ?>Welcome back to<br><em>CTU Danao ERMS</em><?php endif; ?>
      </h2>
      <p class="brand-desc">Your academic event hub. Sign in to view your registered events, discover new opportunities, and manage your campus schedule.</p>
      <ul class="admin-perks">
        <li>📋 Manage all campus events</li>
        <li>👥 Manage student accounts</li>
        <li>✅ Approve &amp; track registrations</li>
        <li>📊 View reports &amp; analytics</li>
        <li>⚙️ Full system configuration</li>
      </ul>
      <div class="admin-warning">
        <strong>⚠ Restricted Access</strong>
        This portal is for authorized administrators only. Unauthorized access attempts are logged and monitored.
      </div>
    </div>
    <div class="brand-quote" id="brandQuote">
      <?php if ($login_mode === 'admin'): ?>
        <p>"With great power comes great responsibility."</p><cite>— Stan Lee</cite>
      <?php else: ?>
        <p>"Education is not the filling of a pail, but the lighting of a fire."</p><cite>— W.B. Yeats</cite>
      <?php endif; ?>
    </div>
  </aside>

  <div class="auth-form-panel">
    <div class="auth-form-box">

      <a href="index.php" class="back-link">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to homepage
      </a>

      <div class="mode-toggle">
        <button type="button" class="mode-tab <?= $login_mode==='student'?'active-student':'' ?>" id="tabStudent" onclick="switchMode('student')">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          Student
        </button>
        <button type="button" class="mode-tab <?= $login_mode==='admin'?'active-admin':'' ?>" id="tabAdmin" onclick="switchMode('admin')">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          Admin
          <span class="admin-badge" id="adminBadge" <?= $login_mode!=='admin'?'style="display:none"':'' ?>>🔐 Secured</span>
        </button>
      </div>

      <div class="form-heading">
        <h1 id="formTitle">
          <?= $login_mode==='admin' ? 'Admin Sign In' : 'Sign In' ?>
          <?php if ($login_mode==='admin'): ?><span class="admin-badge">🔐 Admin</span><?php endif; ?>
        </h1>
        <p id="formSub">
          <?php if ($login_mode==='student'): ?>Don't have an account? <a href="register.php">Create one free →</a>
          <?php else: ?>Administrator access only. Unauthorized use is prohibited.<?php endif; ?>
        </p>
      </div>

      <div class="admin-notice <?= $login_mode==='admin'?'visible':'' ?>" id="adminNotice">
        <span class="admin-notice-icon">🛡️</span>
        <div><strong>Administrator Portal</strong> — Full system access including user management, event control, and registration oversight.</div>
      </div>

  

      <?php if ($error_type === 'rate_limit' || $error_type === 'account_locked'): ?>
        <div class="alert-lock">
          <div class="lock-icon-wrap">🔒</div>
          <div class="lock-body">
            <div class="lock-title">
              <?= $error_type === 'rate_limit' ? 'Too Many Attempts — IP Temporarily Blocked' : 'Account Temporarily Locked' ?>
            </div>
            <div class="lock-msg"><?= htmlspecialchars($error) ?></div>
            <?php if ($seconds_left > 0): ?>
              <div class="lock-timer">
                <span class="timer-label">Try again in:</span>
                <span class="timer-count" id="countdown" data-seconds="<?= (int)$seconds_left ?>"><?= gmdate('i:s', $seconds_left) ?></span>
              </div>
              <div class="lock-progress"><div class="lock-progress-bar" id="lockBar" style="width:100%"></div></div>
            <?php endif; ?>
          </div>
        </div>

      <?php elseif ($error_type === 'invalid_credentials'): ?>
        <div class="alert-attempts">
          <span class="att-icon">⚠️</span>
          <div class="att-body">
            <div class="att-msg"><?= htmlspecialchars($error) ?></div>
            <?php if ($attempts_left > 0): ?>
              <div class="att-pips">
                <?php $used = 5 - $attempts_left; for ($i = 0; $i < 5; $i++): ?>
                  <div class="att-pip <?= $i < $used ? 'used' : 'left' ?>"></div>
                <?php endfor; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <?php elseif ($error_type === 'access_denied' || $error_type === 'inactive'): ?>
        <div class="alert-warning">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <div><?= htmlspecialchars($error) ?></div>
        </div>

      <?php elseif ($error_type === 'info'): ?>
        <div class="alert-info">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <div><?= htmlspecialchars($error) ?></div>
        </div>

      <?php elseif ($error): ?>
        <div class="alert alert-error">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <div><?= htmlspecialchars($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <div><?= htmlspecialchars($success) ?></div>
        </div>
      <?php endif; ?>

      <!-- ── Form ───────────────────────────────────────── -->
      <form method="POST" id="loginForm" novalidate>
        <?= csrf_token_field() ?>
        <input type="hidden" name="login_mode" id="loginModeInput" value="<?= htmlspecialchars($login_mode) ?>">

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-wrap">
            <input type="email" name="email" id="email" class="form-control"
              placeholder="<?= $login_mode==='admin' ? 'admin@erms.com' : 'you@university.edu' ?>"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required autocomplete="email">
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrap">
            <input type="password" name="password" id="password" class="form-control"
              placeholder="••••••••" required autocomplete="current-password">
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
        </div>

        <button type="submit"
          class="btn-submit <?= $login_mode==='admin'?'admin-btn':'student-btn' ?>"
          id="submitBtn"
          <?= in_array($error_type, ['rate_limit','account_locked']) && $seconds_left > 0 ? 'disabled' : '' ?>>
          <?= $login_mode==='admin' ? '🔐 Sign In as Administrator' : 'Sign In' ?>
        </button>
      </form>

      <div class="divider">or</div>

      <div class="form-footer <?= $login_mode==='admin'?'hidden':'' ?>" id="formFooter">
        New to ERMS? <a href="register.php">Create your account →</a>
      </div>
      <div class="form-footer" id="adminRegFooter" <?= $login_mode!=='admin'?'style="display:none"':'' ?>>
        No admin account yet? <a href="admin/admin-register.php">Register as Administrator →</a>
      </div>

    </div>
  </div>
</div>

<script>
// ── Theme ──────────────────────────────────────────────────
const htmlEl = document.documentElement;
const saved  = localStorage.getItem('erms-theme') || 'dark';
htmlEl.setAttribute('data-theme', saved);
document.getElementById('themeIcon').textContent = saved === 'dark' ? '☀️' : '🌙';
document.getElementById('themeToggle').addEventListener('click', () => {
  const next = htmlEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  htmlEl.setAttribute('data-theme', next);
  localStorage.setItem('erms-theme', next);
  document.getElementById('themeIcon').textContent = next === 'dark' ? '☀️' : '🌙';
});

// ── Password toggle ────────────────────────────────────────
document.getElementById('pwToggle').addEventListener('click', () => {
  const inp = document.getElementById('password');
  inp.type = inp.type === 'password' ? 'text' : 'password';
});

// ── Mode switcher ──────────────────────────────────────────
function switchMode(mode) {
  const isAdmin = mode === 'admin';
  document.body.className = 'auth-layout mode-' + mode;
  document.getElementById('brandPanel').className = 'auth-brand mode-' + mode;
  document.getElementById('tabStudent').className  = 'mode-tab' + (isAdmin ? '' : ' active-student');
  document.getElementById('tabAdmin').className    = 'mode-tab' + (isAdmin ? ' active-admin' : '');
  document.getElementById('adminBadge').style.display = isAdmin ? 'inline-flex' : 'none';
  document.getElementById('adminNotice').className = 'admin-notice' + (isAdmin ? ' visible' : '');
  document.getElementById('loginModeInput').value  = mode;
  const btn = document.getElementById('submitBtn');
  if (!btn.disabled) {
    btn.className   = 'btn-submit ' + (isAdmin ? 'admin-btn' : 'student-btn');
    btn.textContent = isAdmin ? '🔐 Sign In as Administrator' : 'Sign In';
  }
  document.getElementById('formFooter').className = 'form-footer' + (isAdmin ? ' hidden' : '');
  document.getElementById('adminRegFooter').style.display = isAdmin ? 'block' : 'none';
  document.getElementById('formTitle').innerHTML  = isAdmin ? 'Admin Sign In <span class="admin-badge">🔐 Admin</span>' : 'Sign In';
  document.getElementById('formSub').innerHTML    = isAdmin
    ? 'Administrator access only. Unauthorized use is prohibited.'
    : 'Don\'t have an account? <a href="register.php">Create one free →</a>';
  document.getElementById('email').placeholder    = isAdmin ? 'admin@erms.com' : 'you@university.edu';
  document.getElementById('brandTitle').innerHTML = isAdmin ? 'Administrator<br><em>Access Portal</em>' : 'Welcome back to<br><em>ERMS</em>';
  document.getElementById('brandQuote').innerHTML = isAdmin
    ? '<p>"With great power comes great responsibility."</p><cite>— Stan Lee</cite>'
    : '<p>"Education is not the filling of a pail, but the lighting of a fire."</p><cite>— W.B. Yeats</cite>';
}

// ── Countdown timer ────────────────────────────────────────
const cdEl  = document.getElementById('countdown');
const barEl = document.getElementById('lockBar');
if (cdEl) {
  let secs = parseInt(cdEl.dataset.seconds, 10);
  const total = secs;
  const tick = () => {
    if (secs <= 0) {
      cdEl.textContent = '00:00';
      if (barEl) barEl.style.width = '0%';
      const sb   = document.getElementById('submitBtn');
      const mode = document.getElementById('loginModeInput').value;
      sb.disabled    = false;
      sb.className   = 'btn-submit ' + (mode === 'admin' ? 'admin-btn' : 'student-btn');
      sb.textContent = mode === 'admin' ? '🔐 Sign In as Administrator' : 'Sign In';
      return;
    }
    const m = String(Math.floor(secs / 60)).padStart(2, '0');
    const s = String(secs % 60).padStart(2, '0');
    cdEl.textContent = `${m}:${s}`;
    if (barEl) barEl.style.width = ((secs / total) * 100) + '%';
    secs--;
    setTimeout(tick, 1000);
  };
  tick();
}

// ── Submit loading state ───────────────────────────────────
document.getElementById('loginForm').addEventListener('submit', function(e) {
  const btn = document.getElementById('submitBtn');
  if (btn.disabled) { e.preventDefault(); return; }
  const mode = document.getElementById('loginModeInput').value;
  btn.disabled    = true;
  btn.textContent = mode === 'admin' ? 'Verifying admin access…' : 'Signing in…';
});
</script>
</body>
</html>