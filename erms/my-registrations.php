<?php
require_once __DIR__ . '/backend/auth_guard.php';
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/csrf_helper.php';

require_login('login.php');

if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$msg       = '';
$msg_type  = '';

// ── Handle Cancel POST ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $reg_id = (int)($_POST['reg_id'] ?? 0);

    if ($action === 'cancel' && $reg_id) {
        $check = $pdo->prepare(
            "SELECT r.registration_id, e.date_time
             FROM registrations r
             JOIN events e ON e.event_id = r.event_id
             WHERE r.registration_id = ? AND r.user_id = ? AND r.status != 'cancelled'"
        );
        $check->execute([$reg_id, $user_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $msg = 'Registration not found or already cancelled.';
            $msg_type = 'error';
        } elseif (strtotime($row['date_time']) < time()) {
            $msg = 'You cannot cancel a registration for a past event.';
            $msg_type = 'error';
        } else {
            $pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE registration_id = ? AND user_id = ?")
                ->execute([$reg_id, $user_id]);
            $msg = 'Registration cancelled successfully.';
            $msg_type = 'success';
        }
    }
}

// ── Filters ────────────────────────────────────────────────
$filter = $_GET['status'] ?? 'all';
$valid_filters = ['all', 'confirmed', 'pending', 'cancelled'];
if (!in_array($filter, $valid_filters)) $filter = 'all';

// ── Stats ──────────────────────────────────────────────────
$stats_q = $pdo->prepare(
    "SELECT
       COUNT(*) AS total,
       SUM(r.status = 'confirmed') AS confirmed,
       SUM(r.status = 'pending')   AS pending,
       SUM(r.status = 'cancelled') AS cancelled,
       SUM(r.status != 'cancelled' AND e.date_time >= NOW()) AS upcoming
     FROM registrations r
     JOIN events e ON e.event_id = r.event_id
     WHERE r.user_id = ?"
);
$stats_q->execute([$user_id]);
$stats = $stats_q->fetch(PDO::FETCH_ASSOC);

// ── Registrations query ────────────────────────────────────
$where  = "WHERE r.user_id = ?";
$params = [$user_id];
if ($filter !== 'all') {
    $where  .= " AND r.status = ?";
    $params[] = $filter;
}

$regs = $pdo->prepare(
    "SELECT r.registration_id, r.status, r.registered_at,
            e.event_id, e.title, e.date_time, e.venue, e.max_slots, e.description,
            COUNT(r2.registration_id) AS enrolled
     FROM registrations r
     JOIN events e ON e.event_id = r.event_id
     LEFT JOIN registrations r2 ON r2.event_id = e.event_id AND r2.status != 'cancelled'
     $where
     GROUP BY r.registration_id
     ORDER BY e.date_time ASC"
);
$regs->execute($params);
$regs = $regs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Registrations — ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
</head>
<body class="has-sidebar">

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sb-brand-top">
      <div class="brand-crest">E</div>
      <div class="sb-brand-text">
        <h1>ERMS</h1>
        <p>Student Portal</p>
      </div>
    </div>
    <div class="sb-clock">
      <div class="sb-clock__time" id="sbTime">--:--:--</div>
      <div class="sb-clock__date" id="sbDate">--- --, ----</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Menu</div>

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

    <a href="my-registrations.php" class="nav-item active">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
      My Registrations
    </a>

    <div class="nav-label" style="margin-top:8px">Account</div>

    <a href="profile.php" class="nav-item">
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

<!-- ── Main ────────────────────────────────────────────────── -->
<div class="main">

  <!-- Topbar -->
  <header class="topbar">
    <button id="menuBtn" class="theme-toggle-btn" style="display:none"
            onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
    <div>
      <div class="topbar-title">My Registrations</div>
      <div class="topbar-sub">Track and manage your event sign-ups</div>
    </div>
    <div class="topbar-space"></div>
    <a href="events.php" class="btn btn-primary btn-sm">
      <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      Browse Events
    </a>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
      <span id="themeIcon">☀️</span>
    </button>
  </header>

  <div class="page">

    <!-- Alert -->
    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg_type ?>" data-auto-dismiss>
        <?php if ($msg_type === 'success'): ?>
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <?php else: ?>
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <?php endif; ?>
        <div><?= htmlspecialchars($msg) ?></div>
      </div>
    <?php endif; ?>

    <!-- Stat cards — 4 col -->
    <div class="stats-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
      <div class="stat-card blue">
        <div class="stat-label">Total Registered</div>
        <div class="stat-val"><?= (int)$stats['total'] ?></div>
        <div class="stat-desc">All time</div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Confirmed</div>
        <div class="stat-val"><?= (int)$stats['confirmed'] ?></div>
        <div class="stat-desc">Active spots</div>
      </div>
      <div class="stat-card gold">
        <div class="stat-label">Upcoming</div>
        <div class="stat-val"><?= (int)$stats['upcoming'] ?></div>
        <div class="stat-desc">Events ahead</div>
      </div>
      <div class="stat-card red">
        <div class="stat-label">Pending</div>
        <div class="stat-val"><?= (int)$stats['pending'] ?></div>
        <div class="stat-desc">Awaiting confirm</div>
      </div>
    </div>

    <!-- Registrations card -->
    <div class="card">
      <!-- Header: title left, filter tabs right -->
      <div class="card-header">
        <div>
          <div class="card-title">My Registrations</div>
          <div class="card-sub"><?= count($regs) ?> record<?= count($regs) !== 1 ? 's' : '' ?><?= ($filter !== 'all') ? ' &mdash; filtered' : '' ?></div>
        </div>
        <!-- Filter tabs -->
        <div class="mr-tabs">
          <?php foreach (['all'=>'All','confirmed'=>'Confirmed','pending'=>'Pending','cancelled'=>'Cancelled'] as $val => $label): ?>
            <a href="?status=<?= $val ?>" class="mr-tab <?= $filter === $val ? 'mr-tab--active' : '' ?>">
              <?= $label ?>
              <?php if ($val === 'all'): ?>
                <span class="mr-tab-count"><?= (int)$stats['total'] ?></span>
              <?php elseif ($val === 'confirmed'): ?>
                <span class="mr-tab-count"><?= (int)$stats['confirmed'] ?></span>
              <?php elseif ($val === 'pending'): ?>
                <span class="mr-tab-count"><?= (int)$stats['pending'] ?></span>
              <?php elseif ($val === 'cancelled'): ?>
                <span class="mr-tab-count"><?= (int)$stats['cancelled'] ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Empty state -->
      <?php if (empty($regs)): ?>
        <div style="text-align:center;padding:60px 24px">
          <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24"
               style="color:var(--text-3);margin-bottom:14px">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
          <div style="font-size:1rem;font-weight:600;color:var(--text);margin-bottom:6px">
            No registrations<?= $filter !== 'all' ? " with status \"$filter\"" : '' ?> yet
          </div>
          <div style="font-size:0.84rem;color:var(--text-3);margin-bottom:20px">
            <?= $filter === 'all' ? "You haven't signed up for any events." : "Try a different filter above." ?>
          </div>
          <a href="events.php" class="btn btn-primary">Browse Available Events →</a>
        </div>

      <?php else: ?>
        <div class="mr-list">
          <?php foreach ($regs as $r):
            $is_past    = strtotime($r['date_time']) < time();
            $can_cancel = !$is_past && $r['status'] !== 'cancelled';
            $pct        = $r['max_slots'] > 0 ? min(100, round(($r['enrolled'] / $r['max_slots']) * 100)) : 0;
            $bar_mod    = $pct >= 90 ? 'full' : ($pct >= 60 ? 'warn' : 'ok');
          ?>
          <div class="mr-row <?= $is_past ? 'mr-row--past' : '' ?>">

            <!-- Left colour stripe -->
            <div class="mr-stripe mr-stripe--<?= $r['status'] ?>"></div>

            <!-- Event details -->
            <div class="mr-info">
              <div class="mr-title"><?= htmlspecialchars($r['title']) ?></div>

              <div class="mr-meta">
                <span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  <?= date('D, M j, Y', strtotime($r['date_time'])) ?>
                </span>
                <span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?= date('g:i A', strtotime($r['date_time'])) ?>
                </span>
                <span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  <?= htmlspecialchars($r['venue']) ?>
                </span>
                <span class="mr-meta-reg">
                  Registered <?= date('M j, Y', strtotime($r['registered_at'])) ?>
                </span>
              </div>

              <!-- Slot progress bar -->
              <div class="mr-slots">
                <span class="mr-slots-lbl"><?= $r['enrolled'] ?>/<?= $r['max_slots'] ?> slots</span>
                <div class="prog" style="flex:1;max-width:140px">
                  <div class="prog-bar <?= $bar_mod ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="mr-slots-pct"><?= $pct ?>%</span>
              </div>
            </div>

            <!-- Right: status badge + cancel button -->
            <div class="mr-actions">
              <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
              <?php if ($is_past): ?>
                <span class="badge" style="background:var(--bg-hover);color:var(--text-3);border:1px solid var(--border)">Past</span>
              <?php endif; ?>
              <?php if ($can_cancel): ?>
                <button class="btn btn-ghost btn-sm mr-cancel-btn"
                        data-reg-id="<?= $r['registration_id'] ?>"
                        data-event="<?= htmlspecialchars($r['title'], ENT_QUOTES) ?>">
                  <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  Cancel
                </button>
              <?php endif; ?>
            </div>

          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /page -->
</div><!-- /main -->

<!-- ── Cancel Confirm Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="cancelModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Cancel Registration?</span>
      <button class="modal-close" onclick="closeModal('cancelModal')">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:0.87rem;color:var(--text-2);line-height:1.6;margin-bottom:14px">
        Are you sure you want to cancel your registration for
        <strong id="cancelEventName" style="color:var(--text)"></strong>?
        This will free up your slot for other students.
      </p>
      <div class="mr-warning">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        This action cannot be undone. You may re-register if slots are still available.
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('cancelModal')">Keep Registration</button>
      <form method="POST" id="cancelForm" style="display:inline">
        <?= csrf_token_field() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="reg_id" id="cancelRegId">
        <button type="submit" class="btn btn-danger btn-sm">Yes, Cancel It</button>
      </form>
    </div>
  </div>
</div>

<style>
/* ── Sidebar brand clock (matches admin panel) ─────────── */
.sb-brand-top      { display:flex; align-items:center; gap:11px; }
.sb-brand-text     { display:flex; flex-direction:column; }
.sidebar-brand     { display:flex; flex-direction:column; padding:18px 18px 16px;
                     border-bottom:1px solid var(--border); flex-shrink:0; }
.sidebar-brand h1  { font-family:var(--ff-d); font-size:.93rem; font-weight:600;
                     color:var(--text); line-height:1.25; }
.sidebar-brand p   { font-size:.63rem; color:var(--text-3);
                     letter-spacing:.09em; text-transform:uppercase; margin-top:1px; }
.sb-clock          { margin-top:11px; padding-top:11px; border-top:1px solid var(--border); width:100%; }
.sb-clock__time    { font-family:var(--ff-m,'JetBrains Mono',monospace); font-size:1.18rem;
                     font-weight:500; color:var(--text); letter-spacing:.07em; line-height:1; }
.sb-clock__date    { font-size:.61rem; color:var(--text-3);
                     letter-spacing:.05em; margin-top:4px; text-transform:uppercase; }

/* ── Filter tabs ───────────────────────────────────────── */
.mr-tabs          { display:flex; gap:4px; }
.mr-tab           { display:inline-flex; align-items:center; gap:5px; padding:5px 12px;
                    border-radius:6px; font-size:.78rem; font-weight:500;
                    color:var(--text-3); text-decoration:none; border:1px solid transparent;
                    transition:all .18s; white-space:nowrap; }
.mr-tab:hover     { color:var(--text); background:var(--bg-hover); }
.mr-tab--active   { background:rgba(74,122,181,.14); color:var(--blue-l);
                    border-color:rgba(74,122,181,.3); }
.mr-tab-count     { font-size:.68rem; font-weight:700; background:var(--bg-hover);
                    padding:1px 5px; border-radius:10px; color:var(--text-3); }
.mr-tab--active .mr-tab-count { background:rgba(74,122,181,.2); color:var(--blue-l); }

/* ── Registration list rows ────────────────────────────── */
.mr-list  { }
.mr-row   { display:flex; align-items:center; gap:18px; padding:18px 22px;
            border-bottom:1px solid var(--border); transition:background .15s; }
.mr-row:last-child      { border-bottom:none; }
.mr-row:hover           { background:var(--bg-hover); }
.mr-row--past           { opacity:.6; }

.mr-stripe              { width:3px; align-self:stretch; border-radius:3px; flex-shrink:0; }
.mr-stripe--confirmed   { background:var(--green); }
.mr-stripe--pending     { background:var(--gold); }
.mr-stripe--cancelled   { background:var(--border); }

.mr-info    { flex:1; min-width:0; }
.mr-title   { font-size:.94rem; font-weight:600; color:var(--text);
              white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
              margin-bottom:6px; }

.mr-meta    { display:flex; flex-wrap:wrap; gap:10px; font-size:.77rem;
              color:var(--text-2); margin-bottom:10px; }
.mr-meta span           { display:flex; align-items:center; gap:4px; }
.mr-meta-reg            { color:var(--text-3); font-size:.73rem; }

.mr-slots               { display:flex; align-items:center; gap:8px; font-size:.74rem; color:var(--text-3); }
.mr-slots-lbl           { white-space:nowrap; }
.mr-slots-pct           { font-family:var(--ff-m,'JetBrains Mono',monospace);
                          font-size:.7rem; min-width:30px; text-align:right; }

.mr-actions             { display:flex; flex-direction:column; align-items:flex-end;
                          gap:8px; flex-shrink:0; }

/* ── Warning box in modal ──────────────────────────────── */
.mr-warning { display:flex; align-items:flex-start; gap:8px; padding:10px 14px;
              border-radius:8px; font-size:.82rem; line-height:1.5;
              color:var(--gold-l); background:rgba(201,168,76,.08);
              border:1px solid rgba(201,168,76,.25); }

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width:768px) {
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0); }
  .main { margin-left:0 !important; }
  #menuBtn { display:flex !important; }
  .mr-row { flex-wrap:wrap; }
  .mr-actions { flex-direction:row; align-items:center; width:100%; justify-content:flex-end; }
  .mr-tabs { flex-wrap:wrap; }
  div[style*="grid-template-columns:repeat(4"] { grid-template-columns:repeat(2,1fr) !important; }
}
</style>

<script src="assets/js/global.js"></script>
<script>
/* Cancel modal */
document.querySelectorAll('.mr-cancel-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('cancelRegId').value           = btn.dataset.regId;
    document.getElementById('cancelEventName').textContent = btn.dataset.event;
    openModal('cancelModal');
  });
});

/* Realtime clock */
(function() {
  var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  function tick() {
    var now = new Date();
    var h   = String(now.getHours()).padStart(2,'0');
    var m   = String(now.getMinutes()).padStart(2,'0');
    var s   = String(now.getSeconds()).padStart(2,'0');
    var tEl = document.getElementById('sbTime');
    var dEl = document.getElementById('sbDate');
    if (tEl) tEl.textContent = h + ':' + m + ':' + s;
    if (dEl) dEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ' ' + now.getFullYear();
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
</body>
</html>