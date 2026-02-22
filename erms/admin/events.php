<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
admin_only();

$admin = current_user();
$msg   = '';
$msg_type = '';

// ── Handle POST (Create / Update / Delete) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'create' || $action === 'update') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date_time  = $_POST['date_time'] ?? '';
    $venue    = trim($_POST['venue'] ?? '');
    $max_slots   = (int)($_POST['max_slots'] ?? 50);
    $status      = $_POST['status'] ?? 'active';
    $category_id = $_POST['category_id'] ?: null;

    if (!$title || !$date_time || !$venue) {
      $msg = 'Please fill in all required fields.';
      $msg_type = 'error';
    } else {
      if ($action === 'create') {
        $stmt = $pdo->prepare(
          "INSERT INTO events (title, description, date_time, venue, max_slots, status, category_id)
           VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$title, $description, $date_time, $venue, $max_slots, $status, $category_id]);
        $msg = 'Event created successfully.';
      } else {
        $id = (int)$_POST['event_id'];
        $stmt = $pdo->prepare(
          "UPDATE events SET title=?, description=?, date_time=?, venue=?, max_slots=?, status=?, category_id=?
           WHERE event_id=?"
        );
        $stmt->execute([$title, $description, $date_time, $venue, $max_slots, $status, $category_id, $event_id]);
        $msg = 'Event updated.';
      }
      $msg_type = 'success';
    }
  }

  if ($action === 'delete') {
    $id = (int)$_POST['event_id'];
    $pdo->prepare("DELETE FROM events WHERE event_id=?")->execute([$event_id]);
    $msg = 'Event deleted.';
    $msg_type = 'success';
  }
}

// ── Fetch data ─────────────────────────────────────────────
$events = $pdo->query(
  "SELECT e.*, ec.category_name,
          COUNT(r.registration_id) AS enrolled
   FROM events e
   LEFT JOIN event_categories ec ON ec.category_id = e.category_id
   LEFT JOIN registrations r ON r.event_id = e.event_id AND r.status != 'cancelled'
   GROUP BY e.event_id ORDER BY e.date_time DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT * FROM event_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Events — ERMS Admin</title>
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
    <a href="events.php" class="nav-item active">
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
    <div class="topbar-title">Manage Events</div>
    <div class="topbar-subtitle"><?= count($events) ?> events total</div>
  </div>
  <div class="topbar-spacer"></div>
  <div class="topbar-search">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" id="evtSearch" placeholder="Search events…">
  </div>
  <div class="topbar-actions">
    <button class="btn btn-primary" onclick="openModal('createModal')">
      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Event
    </button>
  </div>
</div>

<!-- MAIN -->
<main class="main">
  <div class="page-content">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg_type ?>" data-auto-dismiss>
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="table-wrapper">
        <table id="eventsTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Event Title</th>
              <th>Category</th>
              <th>Date</th>
              <th>Location</th>
              <th>Slots</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($events)): ?>
              <?php foreach ($events as $ev): ?>
                <tr>
                  <td class="td-mono"><?= $ev['event_id'] ?></td>
                  <td class="td-primary"><?= htmlspecialchars($ev['title']) ?></td>
                  <td><?= htmlspecialchars($ev['category_name'] ?? '—') ?></td>
                  <td><?= date('M d, Y', strtotime($ev['date_time'])) ?></td>
                  <td><?= htmlspecialchars($ev['venue']) ?></td>
                  <td>
                    <?= $ev['enrolled'] ?>/<?= $ev['max_slots'] ?>
                    <?php if ($ev['enrolled'] >= $ev['max_slots']): ?>
                      <span class="badge badge-full" style="margin-left:4px">Full</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge badge-<?= $ev['status'] === 'active' ? 'active' : ($ev['status']==='upcoming'?'upcoming':'inactive') ?>">
                      <?= ucfirst($ev['status']) ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex;gap:6px">
                      <button class="btn btn-ghost btn-sm btn-icon"
                        title="Edit"
                        onclick='openEditModal(<?= json_encode($ev) ?>)'>
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                      </button>
                      <form method="POST" style="display:inline">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="event_id" value="<?= $ev['event_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete"
                          data-confirm="Delete '<?= htmlspecialchars($ev['title'], ENT_QUOTES) ?>'? This cannot be undone.">
                          <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-3)">No events yet. Create your first one!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<!-- ══ CREATE MODAL ══════════════════════════════════════════ -->
<div class="modal-overlay" id="createModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Create New Event</div>
      <button class="modal-close" onclick="closeModal('createModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrf_token_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Event Title *</label>
          <input type="text" name="title" class="form-control" required placeholder="e.g. Annual Science Fair">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Event Date *</label>
            <input type="datetime-local" name="date_time" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-control">
              <option value="">— No category —</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Location *</label>
          <input type="text" name="venue" class="form-control" required placeholder="e.g. Main Auditorium">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Max Slots</label>
            <input type="number" name="max_slots" class="form-control" value="50" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="active">Active</option>
              <option value="upcoming">Upcoming</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" placeholder="Event details…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Event</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT MODAL ═══════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Edit Event</div>
      <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrf_token_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="event_id" id="edit_event_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Event Title *</label>
          <input type="text" name="title" id="edit_title" class="form-control" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Event Date *</label>
            <input type="datetime-local" name="date_time" id="edit_date_time" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category_id" id="edit_category_id" class="form-control">
              <option value="">— No category —</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Location *</label>
          <input type="text" name="venue" id="edit_venue" class="form-control" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Max Slots</label>
            <input type="number" name="max_slots" id="edit_max_slots" class="form-control" min="1">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="edit_status" class="form-control">
              <option value="active">Active</option>
              <option value="upcoming">Upcoming</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_description" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/admin.js"></script>
<script>
function openEditModal(ev) {
  document.getElementById('edit_event_id').value   = ev.id;
  document.getElementById('edit_title').value       = ev.title;
  document.getElementById('edit_venue').value    = ev.venue;
  document.getElementById('edit_max_slots').value   = ev.max_slots;
  document.getElementById('edit_status').value      = ev.status;
  document.getElementById('edit_description').value = ev.description || '';
  document.getElementById('edit_category_id').value = ev.category_id || '';
  // Format datetime-local
  const d = new Date(ev.date_time);
  const pad = n => String(n).padStart(2,'0');
  document.getElementById('edit_date_time').value =
    `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  openModal('editModal');
}

filterTable('evtSearch','eventsTable');
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