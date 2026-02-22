<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
admin_only();

// ── Fetch real counts ──────────────────────────────────────
$stats = [];

$rows = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$stats['students'] = $rows ?: 0;

$rows = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$stats['events'] = $rows ?: 0;

$rows = $pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$stats['registrations'] = $rows ?: 0;

$rows = $pdo->query("SELECT COUNT(*) FROM events WHERE status IN ('active','upcoming') AND date_time >= CURDATE()")->fetchColumn();
$stats['upcoming'] = $rows ?: 0;

// Recent registrations
$recent = $pdo->query(
  "SELECT r.registration_id, u.full_name, u.student_id, e.title AS event_title,
          r.status, r.registered_at
   FROM registrations r
   JOIN users u ON u.user_id = r.user_id
   JOIN events e ON e.event_id = r.event_id
   ORDER BY r.registered_at DESC LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

// Events capacity usage
$events_cap = $pdo->query(
  "SELECT e.title, e.max_slots, e.status,
          COUNT(r.registration_id) AS enrolled
   FROM events e
   LEFT JOIN registrations r ON r.event_id = e.event_id AND r.status != 'cancelled'
   GROUP BY e.event_id ORDER BY e.date_time ASC LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

// Monthly registrations for mini chart (last 6 months)
$chart = $pdo->query(
  "SELECT DATE_FORMAT(registered_at,'%b') AS month,
          COUNT(*) AS count
   FROM registrations
   WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
   GROUP BY MONTH(registered_at), DATE_FORMAT(registered_at,'%b')
   ORDER BY MONTH(registered_at) ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$admin = current_user();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard — ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="has-sidebar">

<!-- ══ SIDEBAR ═════════════════════════════════════════════ -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-crest">E</div>
    <h1>ERMS Admin</h1>
    <p>Control Panel</p>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <a href="dashboard.php" class="nav-item active">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>

    <div class="nav-section-label">Management</div>
    <a href="events.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Manage Events
    </a>
    <a href="users.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
      Manage Users
    </a>
    <a href="registrations.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
      Registrations
      <span class="badge"><?= $stats['registrations'] ?></span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($admin['full_name'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="name"><?= htmlspecialchars($admin['full_name']) ?></div>
        <div class="role">Administrator</div>
      </div>
    </div>
    <a href="../backend/logout.php" class="logout-btn">
      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Sign Out
    </a>
  </div>
</aside>

<!-- ══ TOPBAR ═══════════════════════════════════════════════ -->
<div class="topbar">
  <button id="menuToggle" class="topbar-btn" style="display:none">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
  </button>
  <div>
    <div class="topbar-title">Dashboard</div>
    <div class="topbar-subtitle"><?= date('l, F j, Y') ?></div>
  </div>
  <div class="topbar-spacer"></div>
    <button class="theme-toggle-btn" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>
</div>

<!-- ══ MAIN ═════════════════════════════════════════════════ -->
<main class="main">
  <div class="page-content">

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card blue">
        <div class="stat-label">Total Students</div>
        <div class="stat-value" data-target="<?= $stats['students'] ?>"><?= $stats['students'] ?></div>
        <div class="stat-change neutral">Registered accounts</div>
        <div class="stat-icon">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/></svg>
        </div>
      </div>
      <div class="stat-card gold">
        <div class="stat-label">Total Events</div>
        <div class="stat-value" data-target="<?= $stats['events'] ?>"><?= $stats['events'] ?></div>
        <div class="stat-change neutral">All categories</div>
        <div class="stat-icon">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Registrations</div>
        <div class="stat-value" data-target="<?= $stats['registrations'] ?>"><?= $stats['registrations'] ?></div>
        <div class="stat-change neutral">Total sign-ups</div>
        <div class="stat-icon">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
      </div>
      <div class="stat-card red">
        <div class="stat-label">Upcoming Events</div>
        <div class="stat-value" data-target="<?= $stats['upcoming'] ?>"><?= $stats['upcoming'] ?></div>
        <div class="stat-change neutral">Scheduled ahead</div>
        <div class="stat-icon">
          <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
        </div>
      </div>
    </div>

    <!-- Chart + Activity -->
    <div class="three-col" style="margin-bottom:20px">
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Registrations Overview</div>
            <div class="card-subtitle">Last 6 months</div>
          </div>
        </div>
        <div class="card-body">
          <?php if (!empty($chart)): ?>
            <?php $maxCount = max(array_column($chart, 'count')) ?: 1; ?>
            <div style="padding-bottom:28px; padding-top:24px;">
              <div class="chart-bar-group">
                <?php foreach ($chart as $bar): ?>
                  <div class="chart-bar" style="height:<?= round(($bar['count']/$maxCount)*100) ?>%">
                    <span class="bar-value"><?= $bar['count'] ?></span>
                    <span class="bar-label"><?= $bar['month'] ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <p style="color:var(--text-3);font-size:0.85rem;text-align:center;padding:20px 0">No registration data yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">Recent Activity</div>
        </div>
        <div class="card-body" style="padding:0 22px">
          <ul class="activity-list">
            <?php if (!empty($recent)): ?>
              <?php foreach (array_slice($recent,0,5) as $r): ?>
                <li class="activity-item">
                  <div class="activity-dot <?= $r['status']==='confirmed'?'green':($r['status']==='pending'?'gold':'red') ?>"></div>
                  <div>
                    <div class="activity-text">
                      <strong><?= htmlspecialchars($r['full_name']) ?></strong>
                      registered for <strong><?= htmlspecialchars($r['event_title']) ?></strong>
                    </div>
                    <div class="activity-time"><?= date('M d, Y g:i A', strtotime($r['registered_at'])) ?></div>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="activity-item"><div class="activity-text" style="color:var(--text-3)">No recent activity.</div></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>

    <!-- Events capacity + recent registrations table -->
    <div class="two-col">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Event Capacity</div>
          <a href="events.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="card-body">
          <?php foreach ($events_cap as $ev):
            $pct = $ev['max_slots'] > 0 ? round(($ev['enrolled']/$ev['max_slots'])*100) : 0;
          ?>
            <div style="margin-bottom:16px">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                <span style="font-size:0.82rem;color:var(--text)"><?= htmlspecialchars($ev['title']) ?></span>
                <span style="font-size:0.75rem;color:var(--text-3)"><?= $ev['enrolled'] ?>/<?= $ev['max_slots'] ?></span>
              </div>
              <div class="progress">
                <div class="progress-bar <?= $pct>80?'gold':'' ?>" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($events_cap)): ?>
            <p style="color:var(--text-3);font-size:0.85rem">No events created yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">Latest Registrations</div>
          <a href="registrations.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Event</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($recent)): ?>
                <?php foreach ($recent as $r): ?>
                  <tr>
                    <td>
                      <div class="td-primary"><?= htmlspecialchars($r['full_name']) ?></div>
                      <div class="td-mono"><?= htmlspecialchars($r['student_id']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($r['event_title']) ?></td>
                    <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="3" style="text-align:center;color:var(--text-3)">No registrations yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /page-content -->
</main>

<script src="assets/js/admin.js"></script>
<script>
(function() {
  const html = document.documentElement;
  const btn  = document.getElementById('themeToggle');
  const icon = document.getElementById('themeIcon');
  const saved = localStorage.getItem('erms-theme') || 'dark';
  html.setAttribute('data-theme', saved);
  icon.textContent = saved === 'dark' ? '☀️' : '🌙';
  btn.addEventListener('click', () => {
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('erms-theme', next);
    icon.textContent = next === 'dark' ? '☀️' : '🌙';
  });
})();
</script>
</body>
</html>