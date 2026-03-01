<?php
require_once __DIR__ . '/backend/auth_guard.php';
require_once __DIR__ . '/backend/db_connect.php';

require_login('login.php');

if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// ── Stats ──────────────────────────────────────────────────
$stats_q = $pdo->prepare(
    "SELECT
       COUNT(*)                                                         AS total_regs,
       SUM(r.status = 'confirmed')                                      AS confirmed,
       SUM(r.status = 'pending')                                        AS pending,
       SUM(r.status != 'cancelled' AND e.date_time >= NOW())            AS upcoming
     FROM registrations r
     JOIN events e ON e.event_id = r.event_id
     WHERE r.user_id = ?"
);
$stats_q->execute([$user_id]);
$stats = $stats_q->fetch(PDO::FETCH_ASSOC);

$total_regs    = (int)$stats['total_regs'];
$confirmed     = (int)$stats['confirmed'];
$pending       = (int)$stats['pending'];
$upcoming_count= (int)$stats['upcoming'];

// ── My Registered Events (latest 6) ───────────────────────
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

// ── Upcoming Events to Discover (not yet registered) ──────
$browse = [];
try {
    $browse_q = $pdo->prepare(
        "SELECT e.event_id, e.title, e.date_time, e.venue, e.max_slots,
                ec.category_name,
                COUNT(r.registration_id) AS enrolled
         FROM events e
         LEFT JOIN event_categories ec ON ec.category_id = e.category_id
         LEFT JOIN registrations r ON r.event_id = e.event_id AND r.status != 'cancelled'
         WHERE e.status = 'active' AND e.date_time >= CURDATE()
           AND e.event_id NOT IN (
               SELECT event_id FROM registrations
               WHERE user_id = ? AND status != 'cancelled'
           )
         GROUP BY e.event_id
         ORDER BY e.date_time ASC
         LIMIT 6"
    );
    $browse_q->execute([$user_id]);
    $browse = $browse_q->fetchAll(PDO::FETCH_ASSOC);
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

// ── Greeting ───────────────────────────────────────────────
$registered = isset($_GET['registered']);
date_default_timezone_set('Asia/Manila');
$hour       = (int)date('H');
$greeting   = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first_name = explode(' ', trim($full_name))[0];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Dashboard — ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/global.css">
</head>
<body class="has-sidebar">

<!-- ── Sidebar ────────────────────────────────────────────── -->
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
    <a href="dashboard.php" class="nav-item active">
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
    <a href="profile.php" class="nav-item">
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
    <div class="topbar-title">My Dashboard</div>
    <div class="topbar-sub"><?= date('l, F j, Y') ?></div>
  </div>
  <div class="topbar-space"></div>
  <a href="events.php" class="btn btn-primary btn-sm">
    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    Browse Events
  </a>
  <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
    <span id="themeIcon">☀️</span>
  </button>
</header>

<!-- ── Main ───────────────────────────────────────────────── -->
<main class="main">
  <div class="page">

    <?php if ($registered): ?>
      <div class="alert alert-success" data-auto-dismiss>
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Welcome to ERMS, <?= htmlspecialchars($first_name) ?>! Your account is ready — start browsing events below.
      </div>
    <?php endif; ?>

    <!-- Welcome banner -->
    <div class="welcome-banner">
      <div class="welcome-greeting"><?= $greeting ?>,</div>
      <div class="welcome-name"><?= htmlspecialchars($full_name) ?> 👋</div>
      <div class="welcome-sub">
        You have <strong style="color:var(--blue-l)"><?= $upcoming_count ?></strong>
        upcoming <?= $upcoming_count === 1 ? 'event' : 'events' ?> registered.
        <?php if (!empty($browse)): ?>
          <strong style="color:var(--gold-l)"><?= count($browse) ?></strong> new <?= count($browse) === 1 ? 'event is' : 'events are' ?> waiting for you.
        <?php else: ?>
          You're all caught up!
        <?php endif; ?>
      </div>
      <div class="welcome-actions">
        <a href="events.php" class="btn btn-primary">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          Browse All Events
        </a>
        <a href="my-registrations.php" class="btn btn-ghost">My Registrations →</a>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="stats-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
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
    <div class="grid-2" style="margin-bottom:24px">

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
              $pct       = $ev['max_slots'] > 0 ? min(100, round(($ev['enrolled'] / $ev['max_slots']) * 100)) : 0;
              $bar_class = $pct >= 100 ? 'full' : ($pct >= 80 ? 'warn' : 'ok');
            ?>
              <div class="event-row">
                <div class="event-dot <?= $ev['status'] ?>"></div>
                <div class="event-info">
                  <div class="event-title-row"><?= htmlspecialchars($ev['title']) ?></div>
                  <div class="event-meta-row">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= date('M d, Y · g:i A', strtotime($ev['date_time'])) ?>
                    &nbsp;·&nbsp;
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <?= htmlspecialchars($ev['venue']) ?>
                  </div>
                  <div class="prog" style="margin-top:6px">
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
              <div style="margin-top:14px"><a href="events.php" class="btn btn-primary btn-sm">Browse Events</a></div>
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
                      Pending — <strong><?= htmlspecialchars($act['title']) ?></strong>
                    <?php endif; ?>
                  </div>
                  <div class="act-time"><?= date('M d, Y · g:i A', strtotime($act['registered_at'])) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty">
              <p>No activity yet</p>
              <span>Actions will appear here once you register for an event.</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- Discover Events -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Discover Events</div>
          <div class="card-sub">Upcoming events you haven't registered for yet</div>
        </div>
        <a href="events.php" class="btn btn-gold btn-sm">View All →</a>
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
                <?php if ($ev['category_name']): ?>
                  <div class="browse-cat"><?= htmlspecialchars($ev['category_name']) ?></div>
                <?php endif; ?>
                <div class="browse-title"><?= htmlspecialchars($ev['title']) ?></div>
                <div class="browse-date">
                  <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  <?= date('M d, Y · g:i A', strtotime($ev['date_time'])) ?>
                </div>
                <div class="browse-loc">
                  <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  <?= htmlspecialchars($ev['venue']) ?>
                </div>
                <div class="browse-footer">
                  <span class="slots-pill <?= $slot_class ?>">
                    <?= $is_full ? '⚠ Full' : $slots_left . ' slots left' ?>
                  </span>
                  <?php if (!$is_full): ?>
                    <a href="events.php?highlight=<?= $ev['event_id'] ?>" class="btn btn-primary btn-sm">Register →</a>
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

<style>
/* ── Sidebar brand + clock (same as my-registrations) ──── */
.sb-brand-top    { display:flex; align-items:center; gap:11px; }
.sb-brand-text   { display:flex; flex-direction:column; }
.sidebar-brand   { display:flex; flex-direction:column; padding:18px 18px 16px;
                   border-bottom:1px solid var(--border); flex-shrink:0; }
.sidebar-brand h1{ font-family:var(--ff-d); font-size:.93rem; font-weight:600;
                   color:var(--text); line-height:1.25; }
.sidebar-brand p { font-size:.63rem; color:var(--text-3);
                   letter-spacing:.09em; text-transform:uppercase; margin-top:1px; }
.sb-clock        { margin-top:11px; padding-top:11px;
                   border-top:1px solid var(--border); width:100%; }
.sb-clock__time  { font-family:var(--ff-m,'JetBrains Mono',monospace); font-size:1.18rem;
                   font-weight:500; color:var(--text); letter-spacing:.07em; line-height:1; }
.sb-clock__date  { font-size:.61rem; color:var(--text-3);
                   letter-spacing:.05em; margin-top:4px; text-transform:uppercase; }

/* ── Browse card category pill ─────────────────────────── */
.browse-cat { font-size:.68rem; font-weight:600; color:var(--blue-l);
              background:rgba(74,122,181,.12); border:1px solid rgba(74,122,181,.25);
              padding:2px 8px; border-radius:10px; display:inline-block;
              margin-bottom:6px; }

/* ── event-meta-row uses inline SVGs ───────────────────── */
.event-meta-row { display:flex; align-items:center; gap:5px;
                  flex-wrap:wrap; font-size:.77rem; color:var(--text-2);
                  margin-bottom:4px; }

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width:768px) {
  .sidebar { transform:translateX(-100%); }
  .sidebar.open { transform:translateX(0); }
  .main { margin-left:0 !important; }
  #menuBtn { display:flex !important; }
  div[style*="grid-template-columns:repeat(4"] { grid-template-columns:repeat(2,1fr) !important; }
  .grid-2 { grid-template-columns:1fr !important; }
}
</style>

<script src="assets/js/global.js"></script>
<script>
(function() {
  var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  function tick() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2,'0');
    var m = String(now.getMinutes()).padStart(2,'0');
    var s = String(now.getSeconds()).padStart(2,'0');
    var tEl = document.getElementById('sbTime');
    var dEl = document.getElementById('sbDate');
    if (tEl) tEl.textContent = h + ':' + m + ':' + s;
    if (dEl) dEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ' ' + now.getFullYear();
  }
  tick(); setInterval(tick, 1000);
})();
</script>
</body>
</html>