<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
admin_only();

$admin = current_user();
$msg   = '';
$msg_type = '';

// ── Handle status update ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'update_status') {
    $reg_id = (int)$_POST['reg_id'];
    $status = in_array($_POST['status'], ['confirmed','pending','cancelled'])
              ? $_POST['status'] : 'pending';
    $pdo->prepare("UPDATE registrations SET status=? WHERE event_id=?")->execute([$status, $reg_id]);
    $msg = 'Registration status updated.';
    $msg_type = 'success';
  }

  if ($action === 'delete') {
    $reg_id = (int)$_POST['reg_id'];
    $pdo->prepare("DELETE FROM registrations WHERE event_id=?")->execute([$reg_id]);
    $msg = 'Registration removed.';
    $msg_type = 'success';
  }
}

// ── Filters ────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'all';
$event_filter  = (int)($_GET['event_id'] ?? 0);

$where = [];
$params = [];

if ($status_filter !== 'all') {
  $where[]  = "r.status = ?";
  $params[] = $status_filter;
}
if ($event_filter > 0) {
  $where[]  = "r.event_id = ?";
  $params[] = $event_filter;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

$registrations = $pdo->prepare(
  "SELECT r.registration_id, r.status, r.registered_at,
          u.full_name, u.student_id, u.email,
          e.title AS event_title, e.date_time, e.venue
   FROM registrations r
   JOIN users u ON u.user_id = r.user_id
   JOIN events e ON e.event_id = r.event_id
   $where_sql
   ORDER BY r.registered_at DESC"
);
$registrations->execute($params);
$registrations = $registrations->fetchAll(PDO::FETCH_ASSOC);

// Stats
$reg_stats = $pdo->query(
  "SELECT status, COUNT(*) AS count FROM registrations GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$total = array_sum($reg_stats);
$events_list = $pdo->query("SELECT id, title FROM events ORDER BY date_time DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registrations — ERMS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="has-sidebar">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-crest">E</div>
    <h1>ERMS Admin</h1>
    <p>Control Panel</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <a href="dashboard.php" class="nav-item">
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
    <a href="registrations.php" class="nav-item active">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
      Registrations
      <span class="badge"><?= $total ?></span>
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($admin['full_name'],0,1)) ?></div>
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

<!-- TOPBAR -->
<div class="topbar">
  <div>
    <div class="topbar-title">Registrations</div>
    <div class="topbar-subtitle"><?= count($registrations) ?> records shown</div>
  </div>
  <div class="topbar-spacer"></div>
  <div class="topbar-search">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" id="regSearch" placeholder="Search…">
  </div>
</div>

<!-- MAIN -->
<main class="main">
  <div class="page-content">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg_type ?>" data-auto-dismiss><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
      <div class="stat-card blue">
        <div class="stat-label">Total</div>
        <div class="stat-value"><?= $total ?></div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Confirmed</div>
        <div class="stat-value"><?= $reg_stats['confirmed'] ?? 0 ?></div>
      </div>
      <div class="stat-card gold">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= $reg_stats['pending'] ?? 0 ?></div>
      </div>
      <div class="stat-card red">
        <div class="stat-label">Cancelled</div>
        <div class="stat-value"><?= $reg_stats['cancelled'] ?? 0 ?></div>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
      <div class="tabs" style="margin-bottom:0">
        <a href="?status=all<?= $event_filter?"&event_id=$event_filter":'' ?>" class="tab <?= $status_filter==='all'?'active':'' ?>">All</a>
        <a href="?status=confirmed<?= $event_filter?"&event_id=$event_filter":'' ?>" class="tab <?= $status_filter==='confirmed'?'active':'' ?>">Confirmed</a>
        <a href="?status=pending<?= $event_filter?"&event_id=$event_filter":'' ?>" class="tab <?= $status_filter==='pending'?'active':'' ?>">Pending</a>
        <a href="?status=cancelled<?= $event_filter?"&event_id=$event_filter":'' ?>" class="tab <?= $status_filter==='cancelled'?'active':'' ?>">Cancelled</a>
      </div>

      <form method="GET" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <select name="event_id" class="form-control" style="width:220px;padding:7px 12px;font-size:0.82rem" onchange="this.form.submit()">
          <option value="0">— All Events —</option>
          <?php foreach ($events_list as $ev): ?>
            <option value="<?= $ev['event_id'] ?>" <?= $event_filter==$ev['event_id']?'selected':'' ?>>
              <?= htmlspecialchars($ev['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="card">
      <div class="table-wrapper">
        <table id="regTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Student</th>
              <th>Student ID</th>
              <th>Event</th>
              <th>Event Date</th>
              <th>Registered On</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($registrations)): ?>
              <?php foreach ($registrations as $r): ?>
                <tr>
                  <td class="td-mono"><?= $r['registration_id'] ?></td>
                  <td>
                    <div class="td-primary"><?= htmlspecialchars($r['full_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($r['email']) ?></div>
                  </td>
                  <td class="td-mono"><?= htmlspecialchars($r['student_id']) ?></td>
                  <td class="td-primary"><?= htmlspecialchars($r['event_title']) ?></td>
                  <td><?= date('M d, Y', strtotime($r['date_time'])) ?></td>
                  <td><?= date('M d, Y g:i A', strtotime($r['registered_at'])) ?></td>
                  <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                  <td>
                    <div style="display:flex;gap:6px">
                      <button class="btn btn-ghost btn-sm btn-icon" title="Update Status"
                        onclick='openStatusModal(<?= json_encode(["id"=>$r["id"],"status"=>$r["status"],"name"=>$r["full_name"],"event"=>$r["event_title"]]) ?>)'>
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      </button>
                      <form method="POST" style="display:inline">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="reg_id" value="<?= $r['registration_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Remove"
                          data-confirm="Remove this registration?">
                          <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No registrations found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<!-- STATUS MODAL -->
<div class="modal-overlay" id="statusModal">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div class="modal-title">Update Registration Status</div>
      <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrf_token_field() ?>
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="reg_id" id="status_reg_id">
      <div class="modal-body">
        <p style="color:var(--text-secondary);font-size:0.87rem;margin-bottom:14px">
          <strong id="status_name" style="color:var(--text-primary)"></strong>
          &mdash;
          <span id="status_event" style="color:var(--text-muted)"></span>
        </p>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" id="status_select" class="form-control">
            <option value="confirmed">Confirmed</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('statusModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/admin.js"></script>
<script>
function openStatusModal(reg) {
  document.getElementById('status_reg_id').value = reg.id;
  document.getElementById('status_name').textContent  = reg.name;
  document.getElementById('status_event').textContent = reg.event;
  document.getElementById('status_select').value = reg.status;
  openModal('statusModal');
}
filterTable('regSearch','regTable');
</script>
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