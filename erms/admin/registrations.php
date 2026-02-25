<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
admin_only();

$admin = current_user();
$msg = '';
$msg_type = '';

// ── Handle POST actions (status update / delete) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $reg_id = (int)$_POST['reg_id'];
        $status = in_array($_POST['status'], ['confirmed','pending','cancelled']) ? $_POST['status'] : 'pending';
        $pdo->prepare("UPDATE registrations SET status=? WHERE registration_id=?")->execute([$status, $reg_id]);
        $msg = 'Registration status updated.';
        $msg_type = 'success';
    }

    if ($action === 'delete') {
        $reg_id = (int)$_POST['reg_id'];
        $pdo->prepare("DELETE FROM registrations WHERE registration_id=?")->execute([$reg_id]);
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

// ── Fetch registrations ─────────────────────────────────────
$registrations_stmt = $pdo->prepare("
    SELECT r.registration_id, r.status, r.registered_at,
           u.full_name, u.student_id, u.email,
           e.title AS event_title, e.date_time, e.venue
    FROM registrations r
    JOIN users u ON u.user_id = r.user_id
    JOIN events e ON e.event_id = r.event_id
    $where_sql
    ORDER BY r.registered_at DESC
");
$registrations_stmt->execute($params);
$registrations = $registrations_stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Stats ────────────────────────────────────────────────
$reg_stats = $pdo->query(
    "SELECT status, COUNT(*) AS count FROM registrations GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$total = array_sum($reg_stats);
$events_list = $pdo->query("SELECT event_id, title FROM events ORDER BY date_time DESC")->fetchAll(PDO::FETCH_ASSOC);
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
    <a href="dashboard.php" class="nav-item">Dashboard</a>
    <div class="nav-section-label">Management</div>
    <a href="events.php" class="nav-item">Manage Events</a>
    <a href="users.php" class="nav-item">Manage Users</a>
    <a href="registrations.php" class="nav-item active">Registrations <span class="badge"><?= $total ?></span></a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($admin['full_name'],0,1)) ?></div>
      <div class="user-info">
        <div class="name"><?= htmlspecialchars($admin['full_name']) ?></div>
        <div class="role">Administrator</div>
      </div>
    </div>
    <a href="../backend/logout.php" class="logout-btn">Sign Out</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
<div class="page-content">

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type ?>" data-auto-dismiss><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card blue"><div class="stat-label">Total</div><div class="stat-value"><?= $total ?></div></div>
  <div class="stat-card green"><div class="stat-label">Confirmed</div><div class="stat-value"><?= $reg_stats['confirmed'] ?? 0 ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Pending</div><div class="stat-value"><?= $reg_stats['pending'] ?? 0 ?></div></div>
  <div class="stat-card red"><div class="stat-label">Cancelled</div><div class="stat-value"><?= $reg_stats['cancelled'] ?? 0 ?></div></div>
</div>

<!-- Filters -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
  <div class="tabs">
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
        <option value="<?= $ev['event_id'] ?>" <?= $event_filter==$ev['event_id']?'selected':'' ?>><?= htmlspecialchars($ev['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="card">
<div class="table-wrapper">
<table id="regTable">
<thead>
<tr>
<th>#</th><th>Student</th><th>Student ID</th><th>Event</th><th>Event Date</th><th>Registered On</th><th>Status</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php if (!empty($registrations)): ?>
    <?php foreach ($registrations as $r): ?>
        <tr>
            <td class="td-mono"><?= $r['registration_id'] ?></td>
            <td>
                <div class="td-primary"><?= htmlspecialchars($r['full_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-3)"><?= htmlspecialchars($r['email']) ?></div>
            </td>
            <td class="td-mono"><?= htmlspecialchars($r['student_id']) ?></td>
            <td class="td-primary"><?= htmlspecialchars($r['event_title']) ?></td>
            <td><?= date('M d, Y', strtotime($r['date_time'])) ?></td>
            <td><?= date('M d, Y g:i A', strtotime($r['registered_at'])) ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
                <div style="display:flex;gap:6px">
                    <button class="btn btn-ghost btn-sm btn-icon" title="Update Status"
                        onclick='openStatusModal(<?= json_encode([
                            "id"=>$r["registration_id"],
                            "status"=>$r["status"],
                            "name"=>$r["full_name"],
                            "event"=>$r["event_title"]
                        ]) ?>)'>Update</button>
                    <form method="POST" style="display:inline">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="reg_id" value="<?= $r['registration_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm btn-icon" data-confirm="Remove this registration?">Remove</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-3)">No registrations found.</td></tr>
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
<p style="color:var(--text-2);font-size:0.87rem;margin-bottom:14px">
<strong id="status_name" style="color:var(--text)"></strong> &mdash; <span id="status_event" style="color:var(--text-3)"></span>
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

<script src="../assets/js/global.js"></script>
<script src="assets/js/admin.js"></script>
<script>
function openStatusModal(reg) {
    document.getElementById('status_reg_id').value = reg.id;
    document.getElementById('status_name').textContent = reg.name;
    document.getElementById('status_event').textContent = reg.event;
    document.getElementById('status_select').value = reg.status;
    openModal('statusModal');
}
filterTable('regSearch', 'regTable');
</script>
</body>
</html>