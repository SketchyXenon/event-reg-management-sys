<?php
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/auth_guard.php';
require_once __DIR__ . '/backend/csrf_helper.php';
require_once __DIR__ . '/backend/password_helper.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

$field_errors = [];
$form = ['full_name' => '', 'student_id' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $full_name  = trim($_POST['full_name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    $form = compact('full_name', 'student_id', 'email');

    // ── Field validation ───────────────────────────────
    if (!$full_name)
        $field_errors['full_name'] = 'Full name is required.';
    elseif (strlen($full_name) < 2)
        $field_errors['full_name'] = 'Name must be at least 2 characters.';
    elseif (strlen($full_name) > 100)
        $field_errors['full_name'] = 'Name must not exceed 100 characters.';

    if (!$student_id)
        $field_errors['student_id'] = 'Student ID is required.';
    elseif (!preg_match('/^\d{7}$/', $student_id))
        $field_errors['student_id'] = 'Student ID must be exactly 7 digits (e.g. 3240000).';

    if (!$email)
        $field_errors['email'] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $field_errors['email'] = 'Please enter a valid email address.';

    if (!$password)
        $field_errors['password'] = 'Password is required.';
    elseif (!validate_password_strength($password))
        $field_errors['password'] = 'Must be 8+ characters with uppercase, lowercase, number & special character.';

    if (!$confirm)
        $field_errors['confirm_password'] = 'Please confirm your password.';
    elseif ($password && $confirm !== $password)
        $field_errors['confirm_password'] = 'Passwords do not match.';

    // ── DB duplicate checks (only if format is valid) ──
    if (empty($field_errors['email'])) {
        $chk = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch())
            $field_errors['email'] = 'This email is already registered. Try signing in instead.';
    }

    if (empty($field_errors['student_id'])) {
        $chk2 = $pdo->prepare("SELECT 1 FROM users WHERE student_id = ? LIMIT 1");
        $chk2->execute([$student_id]);
        if ($chk2->fetch())
            $field_errors['student_id'] = 'This Student ID is already registered.';
    }

    // ── Insert if all clear ────────────────────────────
    if (empty($field_errors)) {
        $hashed = hash_password($password);
        $stmt   = $pdo->prepare(
            "INSERT INTO users (full_name, student_id, email, password, role, is_active)
             VALUES (?, ?, ?, ?, 'student', 1)"
        );
        $stmt->execute([$full_name, $student_id, $email, $hashed]);
        $new_id = $pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user_id']       = (int)$new_id;
        $_SESSION['full_name']     = $full_name;
        $_SESSION['email']         = $email;
        $_SESSION['role']          = 'student';
        $_SESSION['last_activity'] = time();
        $_SESSION['created_at']    = time();

        header('Location: dashboard.php?registered=1');
        exit;
    }
}

// Helper: field CSS class
function field_cls($name, $errors) {
    return isset($errors[$name]) ? 'form-control is-error' : 'form-control';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account — ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-layout">

<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>

<div class="auth-wrap">

  <!-- ── Brand Panel ──────────────────────────────────── -->
  <aside class="auth-brand">
    <div class="brand-top">
      <div class="brand-crest">E</div>
      <h2 class="brand-title">Join <em>ERMS</em><br>Today</h2>
      <p class="brand-desc">Create your free student account and start registering for academic events, seminars, and campus activities.</p>
      <ul class="brand-perks">
        <li><div class="perk-dot"></div> Instant access to all campus events</li>
        <li><div class="perk-dot"></div> Track your registrations in one place</li>
        <li><div class="perk-dot"></div> Get notified of new opportunities</li>
        <li><div class="perk-dot"></div> Manage your academic schedule</li>
      </ul>
    </div>
    <div class="brand-quote">
      <p>"The beautiful thing about learning is that nobody can take it away from you."</p>
      <cite>— B.B. King</cite>
    </div>
  </aside>

  <!-- ── Form Panel ───────────────────────────────────── -->
  <div class="auth-form-panel">
    <div class="auth-form-box">

      <a href="index.php" class="back-link">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to homepage
      </a>

      <div class="form-heading">
        <h1>Create Account</h1>
        <p>Already have one? <a href="login.php">Sign in →</a></p>
      </div>

      <!-- Summary banner (only when errors exist) -->
      <?php if (!empty($field_errors)): ?>
        <?php $ec = count($field_errors); ?>
        <div class="alert-summary">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <div>
            <?= $ec === 1 ? 'Please fix 1 issue' : "Please fix {$ec} issues" ?> below before continuing.
          </div>
        </div>
      <?php endif; ?>

      <form method="POST" id="registerForm" novalidate>
        <?= csrf_token_field() ?>

        <!-- Row: Full Name + Student ID -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="full_name">Full Name</label>
            <div class="input-wrap">
              <input type="text" name="full_name" id="full_name"
                class="<?= field_cls('full_name', $field_errors) ?>"
                placeholder="Juan dela Cruz"
                value="<?= htmlspecialchars($form['full_name']) ?>"
                required autocomplete="name">
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
            <?php if (isset($field_errors['full_name'])): ?>
              <div class="field-error"><?= htmlspecialchars($field_errors['full_name']) ?></div>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label class="form-label" for="student_id">Student ID</label>
            <div class="input-wrap">
              <input type="text" name="student_id" id="student_id"
                class="<?= field_cls('student_id', $field_errors) ?>"
                placeholder="3240000"
                value="<?= htmlspecialchars($form['student_id']) ?>"
                required maxlength="7">
              <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2"/>
              </svg>
            </div>
            <?php if (isset($field_errors['student_id'])): ?>
              <?php if (str_contains($field_errors['student_id'], 'already')): ?>
                <div class="dup-alert">
                  🪪 <?= htmlspecialchars($field_errors['student_id']) ?>
                </div>
              <?php else: ?>
                <div class="field-error"><?= htmlspecialchars($field_errors['student_id']) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <div class="input-hint" id="idHint">7-digit number, e.g. 3240000</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-wrap">
            <input type="email" name="email" id="email"
              class="<?= field_cls('email', $field_errors) ?>"
              placeholder="you@university.edu"
              value="<?= htmlspecialchars($form['email']) ?>"
              required autocomplete="email">
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
            </svg>
          </div>
          <?php if (isset($field_errors['email'])): ?>
            <?php if (str_contains($field_errors['email'], 'already registered')): ?>
              <div class="dup-alert">
                📧 <?= htmlspecialchars($field_errors['email']) ?>
                &nbsp;<a href="login.php">Sign in instead →</a>
              </div>
            <?php else: ?>
              <div class="field-error"><?= htmlspecialchars($field_errors['email']) ?></div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrap">
            <input type="password" name="password" id="password"
              class="<?= field_cls('password', $field_errors) ?>"
              placeholder="Create a strong password"
              required autocomplete="new-password">
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <button type="button" class="pw-toggle" id="pwToggle1" aria-label="Show password">
              <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
          <div class="pw-strength"><div class="pw-strength-bar" id="strengthBar"></div></div>
          <?php if (isset($field_errors['password'])): ?>
            <div class="field-error"><?= htmlspecialchars($field_errors['password']) ?></div>
          <?php else: ?>
            <div class="input-hint" id="strengthLabel">Enter a password</div>
          <?php endif; ?>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm Password</label>
          <div class="input-wrap">
            <input type="password" name="confirm_password" id="confirm_password"
              class="<?= field_cls('confirm_password', $field_errors) ?>"
              placeholder="Repeat your password"
              required autocomplete="new-password">
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <button type="button" class="pw-toggle" id="pwToggle2" aria-label="Show password">
              <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
          <?php if (isset($field_errors['confirm_password'])): ?>
            <div class="field-error"><?= htmlspecialchars($field_errors['confirm_password']) ?></div>
          <?php else: ?>
            <div class="input-hint" id="confirmHint"></div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn-submit student-btn" id="submitBtn">Create My Account</button>
      </form>

      <div class="form-footer">
        Already have an account? <a href="login.php">Sign in →</a>
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

// ── Password toggles ───────────────────────────────────────
['pwToggle1','pwToggle2'].forEach((id, i) => {
  document.getElementById(id)?.addEventListener('click', () => {
    const inp = document.getElementById(i === 0 ? 'password' : 'confirm_password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
  });
});

// ── Student ID live hint ───────────────────────────────────
const idInput = document.getElementById('student_id');
const idHint  = document.getElementById('idHint');
if (idHint) {
  idInput.addEventListener('input', () => {
    const v = idInput.value.trim();
    if (!v) { idHint.textContent = '7-digit number, e.g. 3240000'; idHint.className = 'input-hint'; return; }
    if (/^\d{7}$/.test(v)) {
      idHint.textContent = '✓ Valid format'; idHint.className = 'input-hint valid';
      idInput.classList.remove('is-error'); idInput.classList.add('is-ok');
    } else {
      idHint.textContent = 'Must be exactly 7 digits'; idHint.className = 'input-hint invalid';
      idInput.classList.remove('is-ok');
    }
  });
}

// ── Password strength ──────────────────────────────────────
const pwInput     = document.getElementById('password');
const strengthBar = document.getElementById('strengthBar');
const strengthLbl = document.getElementById('strengthLabel');
if (strengthLbl) {
  const levels = [
    { pct:0,   color:'',          label:'Enter a password' },
    { pct:25,  color:'#c45c5c',   label:'Weak' },
    { pct:50,  color:'#c9a84c',   label:'Fair' },
    { pct:75,  color:'#6a96cc',   label:'Good' },
    { pct:100, color:'#4e9b72',   label:'Strong ✓' },
  ];
  pwInput.addEventListener('input', () => {
    const v = pwInput.value;
    let score = 0;
    if (v.length >= 8)           score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const lvl = v.length === 0 ? levels[0] : levels[score];
    strengthBar.style.width      = lvl.pct + '%';
    strengthBar.style.background = lvl.color;
    strengthLbl.textContent      = lvl.label;
    strengthLbl.style.color      = lvl.color || 'var(--text-3)';
  });
}

// ── Confirm match ──────────────────────────────────────────
const confirmInput = document.getElementById('confirm_password');
const confirmHint  = document.getElementById('confirmHint');
if (confirmHint) {
  confirmInput.addEventListener('input', () => {
    if (!confirmInput.value) { confirmHint.textContent = ''; return; }
    if (confirmInput.value === pwInput.value) {
      confirmHint.textContent = '✓ Passwords match';
      confirmHint.className   = 'input-hint valid';
      confirmInput.classList.add('is-ok'); confirmInput.classList.remove('is-error');
    } else {
      confirmHint.textContent = 'Passwords do not match';
      confirmHint.className   = 'input-hint invalid';
      confirmInput.classList.remove('is-ok');
    }
  });
}

// ── Submit state ───────────────────────────────────────────
document.getElementById('registerForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.disabled    = true;
  btn.textContent = 'Creating account…';
});
</script>
</body>
</html>