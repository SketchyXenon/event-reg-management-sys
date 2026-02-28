<?php
require_once __DIR__ . '/backend/auth_guard.php';
require_once __DIR__ . '/backend/db_connect.php';

require_login('login.php');

// Redirect admins to their own panel
if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// ── Stats ──────────────────────────────────────────────────
$total_regs = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ?");
$total_regs->execute([$user_id]);
$total_regs = $total_regs->fetchColumn();

$confirmed = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND status = 'confirmed'");
$confirmed->execute([$user_id]);
$confirmed = $confirmed->fetchColumn();

$pending = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND status = 'pending'");
$pending->execute([$user_id]);
$pending = $pending->fetchColumn();

$upcoming_count = $pdo->prepare(
    "SELECT COUNT(*) FROM registrations r
     JOIN events e ON e.event_id = r.event_id
     WHERE r.user_id = ? AND e.date_time >= NOW() AND r.status != 'cancelled'"
);
$upcoming_count->execute([$user_id]);
$upcoming_count = $upcoming_count->fetchColumn();

// ── My Registered Events ───────────────────────────────────
$my_events = $pdo->prepare(
    "SELECT r.registration_id AS reg_id, r.status, r.registered_at,
            e.event_id, e.title, e.date_time, e.venue, e.max_slots,
            COUNT(r2.registration_id) AS enrolled
     FROM registrations r
     JOIN events e ON e.event_id = r.event_id
     LEFT JOIN registrations r2 ON r2.event_id = e.event_id AND r2.status != 'cancelled'
     WHERE r.user_id = ?
     GROUP BY r.registration_id
     ORDER BY e.date_time ASC
     LIMIT 6"
);
$my_events->execute([$user_id]);
$my_events = $my_events->fetchAll(PDO::FETCH_ASSOC);

// ── Upcoming Events to Browse ──────────────────────────────
// Events the student has NOT registered for
// Safely browse upcoming events — no category join, no status filter
$browse = [];
try {
    $browse = $pdo->prepare(
        "SELECT e.event_id, e.title, e.date_time, e.venue, e.max_slots, e.description,
                NULL AS category_name,
                COUNT(r.registration_id) AS enrolled
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.event_id AND r.status != 'cancelled'
         WHERE e.status IN ('active','upcoming') AND e.date_time >= CURDATE()
           AND e.event_id NOT IN (
               SELECT event_id FROM registrations
               WHERE user_id = ? AND status != 'cancelled'
           )
         GROUP BY e.event_id
         ORDER BY e.date_time ASC
         LIMIT 6"
    );
    $browse->execute([$user_id]);
    $browse = $browse->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $browse = []; }

// ── Recent Activity ────────────────────────────────────────
$activity = $pdo->prepare(
    "SELECT r.status, r.registered_at, e.title, e.date_time
     FROM registrations r
     JOIN events e ON e.event_id = r.event_id
     WHERE r.user_id = ?
     ORDER BY r.registered_at DESC
     LIMIT 8"
);
$activity->execute([$user_id]);
$activity = $activity->fetchAll(PDO::FETCH_ASSOC);

// Welcome back message
$registered = isset($_GET['registered']);
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first_name = explode(' ', $full_name)[0];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Dashboard — ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="has-sidebar">

<!-- ══ SIDEBAR ═══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-crest">E</div>
    <div class="brand-text">
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

    <a href="dashboard.php" class="nav-item active">
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

<!-- ══ TOPBAR ════════════════════════════════════════════════ -->
<div class="topbar">
  <button id="menuBtn" style="background:none;border:none;cursor:pointer;color:var(--text-2);display:none;padding:4px" onclick="document.getElementById('sidebar').classList.toggle('open')">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
  </button>
  <div>
    <div class="topbar-title">My Dashboard</div>
    <div class="topbar-sub"><?= date('l, F j, Y') ?></div>
  </div>
  <div class="topbar-space"></div>
  <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
    <span id="themeIcon">☀️</span>
  </button>
</div>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<main class="main">
  <div class="page">

    <?php if ($registered): ?>
      <div class="alert-success">
        🎉 Welcome to ERMS, <?= htmlspecialchars($first_name) ?>! Your account is ready. Start browsing events below.
      </div>
    <?php endif; ?>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div class="welcome-greeting"><?= $greeting ?></div>
      <div class="welcome-name"><?= htmlspecialchars($full_name) ?> 👋</div>
      <div class="welcome-sub">
        You have <strong style="color:var(--blue-l)"><?= $upcoming_count ?></strong> upcoming
        <?= $upcoming_count === 1 ? 'event' : 'events' ?> registered.
        <?= !empty($browse) ? 'There are <strong style="color:var(--gold-l)">' . count($browse) . '</strong> new events waiting for you.' : 'All caught up!' ?>
      </div>
      <div class="welcome-actions">
        <a href="events.php" class="btn btn-primary">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Browse All Events
        </a>
        <a href="my-registrations.php" class="btn btn-ghost">My Registrations →</a>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="stats-row">
      <div class="stat-card blue">
        <div class="stat-label">Total Registered</div>
        <div class="stat-val"><?= $total_regs ?></div>
        <div class="stat-desc">All time sign-ups</div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Confirmed</div>
        <div class="stat-val"><?= $confirmed ?></div>
        <div class="stat-desc">Secured spots</div>
      </div>
      <div class="stat-card gold">
        <div class="stat-label">Pending</div>
        <div class="stat-val"><?= $pending ?></div>
        <div class="stat-desc">Awaiting confirmation</div>
      </div>
      <div class="stat-card red">
        <div class="stat-label">Upcoming</div>
        <div class="stat-val"><?= $upcoming_count ?></div>
        <div class="stat-desc">Events ahead</div>
      </div>
    </div>

    <!-- My Events + Activity -->
    <div class="grid-2">

      <!-- My Registered Events -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">My Registered Events</div>
            <div class="card-sub">Your upcoming &amp; past sign-ups</div>
          </div>
          <a href="my-registrations.php" class="btn btn-ghost btn-sm">View All →</a>
        </div>
        <div class="card-body">
          <?php if (!empty($my_events)): ?>
            <?php foreach ($my_events as $ev):
              $slots_left = max(0, $ev['max_slots'] - $ev['enrolled']);
              $pct = $ev['max_slots'] > 0 ? min(100, round(($ev['enrolled'] / $ev['max_slots']) * 100)) : 0;
              $bar_class = $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : '');
              $is_past = strtotime($ev['date_time']) < time();
            ?>
              <div class="event-row">
                <div class="event-dot <?= $ev['status'] ?>"></div>
                <div class="event-info">
                  <div class="event-title-row"><?= htmlspecialchars($ev['title']) ?></div>
                  <div class="event-meta-row">
                    📅 <?= date('M d, Y · g:i A', strtotime($ev['date_time'])) ?>
                    · 📍 <?= htmlspecialchars($ev['venue']) ?>
                  </div>
                  <div class="prog" style="margin-top:5px">
                    <div class="prog-bar <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                  </div>
                </div>
                <span class="badge badge-<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty">
              <p>No registrations yet</p>
              <span>Browse events and register for your first one!</span>
              <div style="margin-top:14px">
                <a href="events.php" class="btn btn-primary btn-sm">Browse Events</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Recent Activity</div>
            <div class="card-sub">Your latest actions</div>
          </div>
        </div>
        <div class="card-body">
          <?php if (!empty($activity)): ?>
            <?php foreach ($activity as $act): ?>
              <div class="activity-item">
                <div class="act-dot <?= $act['status'] ?>"></div>
                <div>
                  <div class="act-text">
                    <?php if ($act['status'] === 'confirmed'): ?>
                      Registered for <strong><?= htmlspecialchars($act['title']) ?></strong>
                    <?php elseif ($act['status'] === 'cancelled'): ?>
                      Cancelled registration for <strong><?= htmlspecialchars($act['title']) ?></strong>
                    <?php else: ?>
                      Pending registration for <strong><?= htmlspecialchars($act['title']) ?></strong>
                    <?php endif; ?>
                  </div>
                  <div class="act-time"><?= date('M d, Y · g:i A', strtotime($act['registered_at'])) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty">
              <p>No activity yet</p>
              <span>Actions will appear here once you register.</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Browse Upcoming Events -->
    <div class="card" style="animation: fadeUp 0.5s 0.3s ease both">
      <div class="card-header">
        <div>
          <div class="card-title">Discover Events</div>
          <div class="card-sub">Upcoming events you haven't registered for yet</div>
        </div>
        <a href="events.php" class="btn btn-gold btn-sm">View All Events →</a>
      </div>
      <div class="card-body">
        <?php if (!empty($browse)): ?>
          <div class="browse-grid">
            <?php foreach ($browse as $ev):
              $slots_left = max(0, $ev['max_slots'] - $ev['enrolled']);
              $is_full    = $slots_left <= 0;
              $slot_class = $is_full ? 'low' : ($slots_left <= 5 ? 'low' : 'good');
            ?>
              <div class="browse-card">
                <div class="browse-title"><?= htmlspecialchars($ev['title']) ?></div>
                <div class="browse-date">📅 <?= date('M d, Y · g:i A', strtotime($ev['date_time'])) ?></div>
                <div class="browse-loc">📍 <?= htmlspecialchars($ev['venue']) ?></div>
                <div class="browse-footer">
                  <span class="slots-pill <?= $slot_class ?>">
                    <?= $is_full ? '⚠ Full' : $slots_left . ' slots left' ?>
                  </span>
                  <?php if (!$is_full): ?>
                    <a href="events.php?id=<?= $ev['event_id'] ?>" class="btn btn-primary btn-sm">Register →</a>
                  <?php else: ?>
                    <span class="badge badge-cancelled">Full</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty">
            <p>You're all caught up!</p>
            <span>No new events available right now. Check back soon.</span>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</main>


<script src="assets/js/global.js"></script>
<script>
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