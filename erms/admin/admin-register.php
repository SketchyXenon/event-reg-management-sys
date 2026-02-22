<?php
require_once __DIR__ . '/../backend/db_connect.php';
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/csrf_helper.php';
require_once __DIR__ . '/../backend/password_helper.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'dashboard.php' : '../dashboard.php'));
    exit;
}

$error   = '';
$success = '';
$form    = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    $form = compact('full_name', 'email');

    if (!$full_name || !$email || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $error = 'Full name must be between 2 and 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!validate_password_strength($password)) {
        $error = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
    } else {
        $check = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hashed = hash_password($password);
            // Admin accounts use a placeholder student_id
            $admin_sid = 'ADM' . strtoupper(substr(md5($email . time()), 0, 4));
            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, student_id, email, password, role, is_active)
                 VALUES (?, ?, ?, ?, 'admin', 1)"
            );
            $stmt->execute([$full_name, $admin_sid, $email, $hashed]);

            $new_id = $pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id']       = (int)$new_id;
            $_SESSION['full_name']     = $full_name;
            $_SESSION['email']         = $email;
            $_SESSION['role']          = 'admin';
            $_SESSION['last_activity'] = time();
            $_SESSION['created_at']    = time();

            header('Location: dashboard.php?new_admin=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Registration - CTU Danao ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="icon" href="/assets/img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-layout">

<button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>

<div class="auth-wrap" style="display:flex;width:100%;min-height:100vh;">

  <!-- Brand Panel -->
  <aside class="auth-brand">
    <div class="brand-top">
      <div class="brand-crest">E</div>
      <h2 class="brand-title">Create an<br><em>Admin Account</em></h2>
      <p class="brand-desc">Set up your administrator profile to gain full access to the ERMS control panel.</p>
      <ul class="perks">
        <li>📋 Create &amp; manage all events</li>
        <li>👥 Full student account management</li>
        <li>✅ Approve &amp; cancel registrations</li>
        <li>📊 View analytics &amp; reports</li>
        <li>⚙️ System-wide configuration</li>
      </ul>
    </div>
    <div class="brand-quote">
      <p>"The secret of getting ahead is getting started."</p>
      <cite>— Mark Twain</cite>
    </div>
  </aside>

  <!-- Form Panel -->
  <div class="auth-form-panel">
    <div class="auth-form-box">

      <a href="../login.php?mode=admin" class="back-link">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to Admin Login
      </a>

      <!-- Admin strip -->
      <div class="admin-strip">
        <span class="admin-strip-icon">🛡️</span>
        <div class="admin-strip-text">
          You are creating an <strong>Administrator Account</strong>. This grants full system access.
        </div>
      </div>

      <div class="form-heading">
        <h1>Admin Registration <span class="admin-badge">🔐 Admin</span></h1>
        <p>Already have an account? <a href="../login.php?mode=admin">Sign in →</a></p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="regForm" novalidate>
        <?= csrf_token_field() ?>

        <div class="form-group">
          <label class="form-label" for="full_name">Full Name</label>
          <div class="input-wrap">
            <input type="text" name="full_name" id="full_name" class="form-control"
              placeholder="e.g. Juan dela Cruz"
              value="<?= htmlspecialchars($form['full_name']) ?>" required>
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-wrap">
            <input type="email" name="email" id="email" class="form-control"
              placeholder="admin@erms.com"
              value="<?= htmlspecialchars($form['email']) ?>" required>
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrap">
            <input type="password" name="password" id="password" class="form-control"
              placeholder="Min. 8 characters" required>
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <button type="button" class="pw-toggle" onclick="togglePw('password',this)">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
          <div class="pw-strength">
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            <div class="strength-label" id="strengthLabel">Enter a password</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm Password</label>
          <div class="input-wrap">
            <input type="password" name="confirm_password" id="confirm_password" class="form-control"
              placeholder="Re-enter password" required>
            <svg class="input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <button type="button" class="pw-toggle" onclick="togglePw('confirm_password',this)">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
          <div class="hint" id="matchHint"></div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          🔐 Create Administrator Account
        </button>
      </form>

      <div class="form-footer">
        Student? <a href="../register.php">Create a student account instead →</a>
      </div>

    </div>
  </div>
</div>

<script>
// Theme
const html=document.documentElement,themeBtn=document.getElementById('themeToggle'),themeIcon=document.getElementById('themeIcon');
const saved=localStorage.getItem('erms-theme')||'dark';
html.setAttribute('data-theme',saved);
themeIcon.textContent=saved==='dark'?'☀️':'🌙';
themeBtn.addEventListener('click',()=>{
  const next=html.getAttribute('data-theme')==='dark'?'light':'dark';
  html.setAttribute('data-theme',next);
  localStorage.setItem('erms-theme',next);
  themeIcon.textContent=next==='dark'?'☀️':'🌙';
});

// Password toggle
function togglePw(id,btn){
  const inp=document.getElementById(id);
  inp.type=inp.type==='password'?'text':'password';
}

// Password strength
document.getElementById('password').addEventListener('input',function(){
  const v=this.value;
  const fill=document.getElementById('strengthFill');
  const label=document.getElementById('strengthLabel');
  let score=0;
  if(v.length>=8) score++;
  if(/[A-Z]/.test(v)) score++;
  if(/[0-9]/.test(v)) score++;
  if(/[^A-Za-z0-9]/.test(v)) score++;
  const levels=[
    {w:'0%',bg:'transparent',txt:'Enter a password'},
    {w:'25%',bg:'#c45c5c',txt:'Weak'},
    {w:'50%',bg:'#c9a84c',txt:'Fair'},
    {w:'75%',bg:'#4a7ab5',txt:'Good'},
    {w:'100%',bg:'#4e9b72',txt:'Strong ✓'},
  ];
  const l=levels[score];
  fill.style.width=l.w; fill.style.background=l.bg; label.textContent=l.txt;
  checkMatch();
});

// Password match
document.getElementById('confirm_password').addEventListener('input',checkMatch);
function checkMatch(){
  const p=document.getElementById('password').value;
  const c=document.getElementById('confirm_password').value;
  const hint=document.getElementById('matchHint');
  if(!c){hint.textContent='';hint.className='hint';return;}
  if(p===c){hint.textContent='✓ Passwords match';hint.className='hint ok';}
  else{hint.textContent='✗ Passwords do not match';hint.className='hint err';}
}

// Submit
document.getElementById('regForm').addEventListener('submit',function(){
  const btn=document.getElementById('submitBtn');
  btn.disabled=true;
  btn.textContent='Creating account…';
});
</script>
</body>
</html>