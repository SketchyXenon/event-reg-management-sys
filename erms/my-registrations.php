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
        // Verify this registration belongs to the logged-in student
        $check = $pdo->prepare(
            "SELECT r.registration_id, e.date_time
             FROM registrations r
             JOIN events e ON e.event_id = r.event_id
             WHERE r.registration_id = ? AND r.user_id = ? AND r.status != 'cancelled'"
        );
        $check->execute([$reg_id, $user_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $msg      = 'Registration not found or already cancelled.';
            $msg_type = 'error';
        } elseif (strtotime($row['date_time']) < time()) {
            $msg      = 'You cannot cancel a registration for a past event.';
            $msg_type = 'error';
        } else {
            $pdo->prepare("UPDATE registrations SET status = 'cancelled' WHERE registration_id = ? AND user_id = ?")
                ->execute([$reg_id, $user_id]);
            $msg      = 'Registration cancelled successfully.';
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
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="has-sidebar">

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-crest">E</div>
    <div class="brand-text">
      <h1>ERMS</h1>
      <p>Student Portal</p>
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
    <button id="menuBtn" class="topbar-btn" onclick="document.getElementById('sidebar').classList.toggle('open')" style="display:none">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div>
      <div class="topbar-title">My Registrations</div>
      <div class="topbar-subtitle">Track and manage your event sign-ups</div>
    </div>
    <div class="topbar-spacer"></div>
    <a href="events.php" class="btn btn-primary btn-sm">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Browse Events
    </a>
    <button class="topbar-btn theme-toggle" id="themeToggle" aria-label="Toggle theme">
      <span id="themeIcon">☀️</span>
    </button>
  </header>

  <div class="page-content">

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

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
      <div class="stat-card blue">
        <div class="stat-label">Total Registered</div>
        <div class="stat-value"><?= (int)$stats['total'] ?></div>
        <div class="stat-change neutral">All time</div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Confirmed</div>
        <div class="stat-value"><?= (int)$stats['confirmed'] ?></div>
        <div class="stat-change up">✓ Active</div>
      </div>
      <div class="stat-card gold">
        <div class="stat-label">Upcoming</div>
        <div class="stat-value"><?= (int)$stats['upcoming'] ?></div>
        <div class="stat-change neutral">Events ahead</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?= (int)$stats['pending'] ?></div>
        <div class="stat-change neutral">Awaiting confirm</div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Registrations</div>
          <div class="card-subtitle"><?= count($regs) ?> record<?= count($regs) !== 1 ? 's' : '' ?> found</div>
        </div>
        <div class="tabs" style="margin-bottom:0">
          <?php foreach (['all'=>'All','confirmed'=>'Confirmed','pending'=>'Pending','cancelled'=>'Cancelled'] as $val=>$label): ?>
            <a href="?status=<?= $val ?>" class="tab <?= $filter===$val?'active':'' ?>"><?= $label ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (empty($regs)): ?>
        <div class="card-body" style="text-align:center;padding:56px 24px">
          <div style="font-size:2.5rem;margin-bottom:12px">📋</div>
          <div style="font-size:1rem;font-weight:600;color:var(--text);margin-bottom:6px">No registrations yet</div>
          <div style="font-size:0.85rem;color:var(--text-3);margin-bottom:20px">
            <?= $filter === 'all' ? "You haven't signed up for any events." : "No {$filter} registrations found." ?>
          </div>
          <a href="events.php" class="btn btn-primary">Browse Available Events →</a>
        </div>
      <?php else: ?>
        <div class="reg-list">
          <?php foreach ($regs as $r):
            $is_past     = strtotime($r['date_time']) < time();
            $is_upcoming = !$is_past && $r['status'] !== 'cancelled';
            $can_cancel  = !$is_past && $r['status'] !== 'cancelled';
            $pct         = $r['max_slots'] > 0 ? min(100, round(($r['enrolled'] / $r['max_slots']) * 100)) : 0;
            $bar_class   = $pct >= 90 ? 'red' : ($pct >= 60 ? 'gold' : 'blue');
            $date_fmt    = date('D, M j, Y', strtotime($r['date_time']));
            $time_fmt    = date('g:i A', strtotime($r['date_time']));
          ?>
          <div class="reg-item <?= $is_past ? 'reg-past' : '' ?>">

            <!-- Status stripe -->
            <div class="reg-stripe reg-stripe-<?= $r['status'] ?>"></div>

            <!-- Event info -->
            <div class="reg-info">
              <div class="reg-title"><?= htmlspecialchars($r['title']) ?></div>
              <div class="reg-meta">
                <span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  <?= $date_fmt ?>
                </span>
                <span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?= $time_fmt ?>
                </span>
                <span>
                  <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  <?= htmlspecialchars($r['venue']) ?>
                </span>
                <span style="color:var(--text-3);font-size:0.75rem">
                  Registered <?= date('M j, Y', strtotime($r['registered_at'])) ?>
                </span>
              </div>

              <!-- Slot progress -->
              <div class="reg-slots">
                <span class="slot-text"><?= $r['enrolled'] ?>/<?= $r['max_slots'] ?> slots filled</span>
                <div class="prog" style="flex:1;max-width:160px">
                  <div class="prog-bar <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="slot-pct"><?= $pct ?>%</span>
              </div>
            </div>

            <!-- Status + actions -->
            <div class="reg-actions">
              <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
              <?php if ($is_past): ?>
                <span class="badge" style="background:rgba(92,100,120,0.15);color:var(--text-3);border:1px solid var(--border)">Past Event</span>
              <?php endif; ?>

              <?php if ($can_cancel): ?>
                <button class="btn btn-ghost btn-sm cancel-btn"
                  data-reg-id="<?= $r['registration_id'] ?>"
                  data-event="<?= htmlspecialchars($r['title']) ?>">
                  <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  Cancel
                </button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div><!-- /page-content -->
</div><!-- /main -->

<!-- ── Cancel Confirm Modal ─────────────────────────────────── -->
<div class="modal-overlay" id="cancelModal">
  <div class="modal" style="width:400px">
    <div class="modal-header">
      <span class="modal-title">Cancel Registration?</span>
      <button class="modal-close" onclick="closeModal('cancelModal')">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:0.87rem;color:var(--text-2);line-height:1.6">
        Are you sure you want to cancel your registration for
        <strong id="cancelEventName" style="color:var(--text)"></strong>?
        This will free up your slot for other students.
      </p>
      <div class="alert alert-warning" style="margin-top:14px;margin-bottom:0;font-size:0.82rem">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>This action cannot be undone. You may re-register if slots are still available.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost btn-sm" onclick="closeModal('cancelModal')">Keep Registration</button>
      <form method="POST" id="cancelForm">
        <?= csrf_token_field() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="reg_id" id="cancelRegId">
        <button type="submit" class="btn btn-danger btn-sm">Yes, Cancel It</button>
      </form>
    </div>
  </div>
</div>

<style>
/* ── Registration list ──────────────────────────────────── */
.reg-list { }

.reg-item {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 18px 22px;
  border-bottom: 1px solid var(--border);
  position: relative;
  transition: background 0.15s ease;
}
.reg-item:last-child { border-bottom: none; }
.reg-item:hover { background: var(--bg-hover); }
.reg-item.reg-past { opacity: 0.65; }

.reg-stripe {
  width: 3px;
  align-self: stretch;
  border-radius: 3px;
  flex-shrink: 0;
}
.reg-stripe-confirmed { background: var(--green); }
.reg-stripe-pending   { background: var(--gold); }
.reg-stripe-cancelled { background: var(--border); }

.reg-info { flex: 1; min-width: 0; }

.reg-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.reg-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 0.78rem;
  color: var(--text-2);
  margin-bottom: 10px;
}
.reg-meta span { display: flex; align-items: center; gap: 4px; }

.reg-slots {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.75rem;
  color: var(--text-3);
}
.slot-pct { font-family: var(--ff-m); font-size: 0.7rem; color: var(--text-3); min-width: 30px; }

.reg-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  flex-shrink: 0;
}

/* ── alert-warning (reuse from global) ───────────────────── */
.alert-warning {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 10px 14px; border-radius: 8px; font-size: 0.83rem;
  line-height: 1.5; color: var(--gold-l);
  background: rgba(201,168,76,0.08);
  border: 1px solid rgba(201,168,76,0.25);
}

/* ── Modal danger btn ─────────────────────────────────────── */
.btn-danger {
  background: rgba(196,92,92,0.15);
  color: #d87c7c;
  border: 1px solid rgba(196,92,92,0.3);
}
.btn-danger:hover { background: rgba(196,92,92,0.25); }

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main { margin-left: 0 !important; }
  #menuBtn { display: flex !important; }
  .reg-item { flex-wrap: wrap; }
  .reg-actions { flex-direction: row; align-items: center; width: 100%; }
  .stats-grid { grid-template-columns: repeat(2,1fr) !important; }
}
</style>


<script src="assets/js/global.js"></script>
<script>
// ── Cancel modal wiring ───────────────────────────────────
document.querySelectorAll('.cancel-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('cancelRegId').value          = btn.dataset.regId;
    document.getElementById('cancelEventName').textContent = btn.dataset.event;
    openModal('cancelModal');
  });
});
</script>
</body>
</html>