<?php
require_once __DIR__ . '/backend/auth_guard.php';
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/csrf_helper.php';

require_login('login.php');

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// ── Handle Registration POST ───────────────────────────────
$msg      = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    csrf_verify();
    $event_id = (int)($_POST['event_id'] ?? 0);

    if (!$event_id) {
        $msg = 'Invalid event.';
        $msg_type = 'error';
    } else {
        // Check event exists and is active
        $ev = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
        $ev->execute([$event_id]);
        $ev = $ev->fetch(PDO::FETCH_ASSOC);

        if (!$ev) {
            $msg = 'Event not found or no longer available.';
            $msg_type = 'error';
        } else {
            // Check already registered
            $exists = $pdo->prepare("SELECT registration_id FROM registrations WHERE user_id = ? AND event_id = ? AND status != 'cancelled'");
            $exists->execute([$user_id, $event_id]);

            if ($exists->fetch()) {
                $msg = 'You are already registered for this event.';
                $msg_type = 'error';
            } else {
                // Check slots
                $enrolled = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status != 'cancelled'");
                $enrolled->execute([$event_id]);
                $enrolled = $enrolled->fetchColumn();

                if ($enrolled >= $ev['max_slots']) {
                    $msg = 'Sorry, this event is already full.';
                    $msg_type = 'error';
                } else {
                    $pdo->prepare(
                        "INSERT INTO registrations (user_id, event_id, status, registered_at)
                         VALUES (?, ?, 'confirmed', NOW())"
                    )->execute([$user_id, $event_id]);

                    $msg = 'Successfully registered for <strong>' . htmlspecialchars($ev['title']) . '</strong>!';
                    $msg_type = 'success';
                }
            }
        }
    }
}

// ── Filters ────────────────────────────────────────────────
$search    = trim($_GET['q'] ?? '');
$filter    = $_GET['filter'] ?? 'all';  // all | available | registered
$sort      = $_GET['sort'] ?? 'date';   // date | slots

// ── Fetch Events ───────────────────────────────────────────
$where  = ["e.date_time >= CURDATE()"];
$params = [];

if ($search) {
    $where[]  = "(e.title LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$order = $sort === 'slots' ? 'slots_left ASC' : 'e.date_time ASC';
$where_sql = 'WHERE ' . implode(' AND ', $where);

$events = $pdo->prepare(
    "SELECT e.event_id, e.title, e.description, e.date_time, e.venue, e.max_slots,
            COUNT(DISTINCT r.registration_id) AS enrolled,
            (e.max_slots - COUNT(DISTINCT r.registration_id)) AS slots_left,
            MAX(CASE WHEN r2.user_id = ? AND r2.status != 'cancelled' THEN 1 ELSE 0 END) AS is_registered,
            MAX(CASE WHEN r2.user_id = ? AND r2.status != 'cancelled' THEN r2.status ELSE NULL END) AS my_status
     FROM events e
     LEFT JOIN registrations r  ON r.event_id  = e.event_id AND r.status  != 'cancelled'
     LEFT JOIN registrations r2 ON r2.event_id = e.event_id AND r2.user_id = ?
     $where_sql
     GROUP BY e.event_id
     ORDER BY $order"
);
$params = array_merge([$user_id, $user_id, $user_id], $params);
$events->execute($params);
$all_events = $events->fetchAll(PDO::FETCH_ASSOC);

// Apply filter
if ($filter === 'available') {
    $all_events = array_filter($all_events, fn($e) => !$e['is_registered'] && $e['slots_left'] > 0);
} elseif ($filter === 'registered') {
    $all_events = array_filter($all_events, fn($e) => $e['is_registered']);
}

$total = count($all_events);
$available_count  = count(array_filter($all_events, fn($e) => !$e['is_registered'] && $e['slots_left'] > 0));
$registered_count = count(array_filter($all_events, fn($e) => $e['is_registered']));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Browse Events — ERMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="has-sidebar">

<!-- ══ SIDEBAR ═══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-crest">E</div>
    <div class="brand-text"><h1>ERMS</h1><p>Student Portal</p></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Menu</div>
    <a href="dashboard.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="events.php" class="nav-item active">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Browse Events
    </a>
    <a href="my-registrations.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      My Registrations
    </a>
    <div class="nav-label" style="margin-top:8px">Account</div>
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

<!-- ══ TOPBAR ════════════════════════════════════════════════ -->
<div class="topbar">
  <button id="menuBtn" style="background:none;border:none;cursor:pointer;color:var(--text-2);display:none;padding:4px" onclick="document.getElementById('sidebar').classList.toggle('open')">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
  </button>
  <div>
    <div class="topbar-title">Browse Events</div>
    <div class="topbar-sub"><?= $total ?> event<?= $total !== 1 ? 's' : '' ?> found</div>
  </div>
  <div class="topbar-space"></div>
  <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>
</div>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<main class="main">
  <div class="page">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg_type ?>">
        <?= $msg_type === 'success' ? '✓' : '✕' ?> <?= $msg ?>
      </div>
    <?php endif; ?>

    <div class="page-header">
      <h2>Browse Events</h2>
      <p>Discover and register for upcoming academic events on campus.</p>
    </div>

    <!-- Toolbar -->
    <form method="GET" id="filterForm">
      <div class="toolbar">
        <div class="search-box">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search events, venues…" id="searchInput">
        </div>

        <div class="filter-tabs">
          <button type="submit" name="filter" value="all"
            class="filter-tab <?= $filter==='all'?'active':'' ?>">All</button>
          <button type="submit" name="filter" value="available"
            class="filter-tab <?= $filter==='available'?'active':'' ?>">Available</button>
          <button type="submit" name="filter" value="registered"
            class="filter-tab <?= $filter==='registered'?'active':'' ?>">Registered</button>
        </div>

        <select name="sort" class="sort-select" onchange="this.form.submit()">
          <option value="date"  <?= $sort==='date' ?'selected':'' ?>>Sort: Earliest First</option>
          <option value="slots" <?= $sort==='slots'?'selected':'' ?>>Sort: Slots Available</option>
        </select>

        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      </div>
    </form>

    <!-- Counts -->
    <div class="counts-row">
      <div class="count-chip blue"><div class="dot"></div><?= $total ?> Total</div>
      <div class="count-chip green"><div class="dot"></div><?= $registered_count ?> Registered</div>
      <div class="count-chip gold"><div class="dot"></div><?= $available_count ?> Available</div>
    </div>

    <!-- Events Grid -->
    <div class="events-grid">
      <?php if (!empty($all_events)): ?>
        <?php foreach ($all_events as $ev):
          $slots_left = max(0, $ev['slots_left']);
          $enrolled   = $ev['enrolled'];
          $max        = $ev['max_slots'];
          $pct        = $max > 0 ? min(100, round(($enrolled / $max) * 100)) : 0;
          $is_full    = $slots_left <= 0;
          $is_reg     = (bool)$ev['is_registered'];
          $bar_class  = $pct >= 100 ? 'full' : ($pct >= 75 ? 'warn' : 'ok');
          $card_class = $is_reg ? 'registered' : ($is_full ? 'full' : 'available');
        ?>
          <div class="event-card <?= $card_class ?>">
            <div class="event-card-body">
              <div class="event-card-top">
                <div class="event-title"><?= htmlspecialchars($ev['title']) ?></div>
                <?php if ($is_reg): ?>
                  <span class="event-status-badge badge-<?= $ev['my_status'] ?? 'confirmed' ?>">
                    ✓ <?= ucfirst($ev['my_status'] ?? 'Registered') ?>
                  </span>
                <?php elseif ($is_full): ?>
                  <span class="event-status-badge badge-full">Full</span>
                <?php else: ?>
                  <span class="event-status-badge badge-available">Open</span>
                <?php endif; ?>
              </div>

              <?php if ($ev['description']): ?>
                <div class="event-desc"><?= htmlspecialchars($ev['description']) ?></div>
              <?php endif; ?>

              <div class="event-meta">
                <div class="meta-item date">
                  <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  <?= date('D, M d, Y · g:i A', strtotime($ev['date_time'])) ?>
                </div>
                <div class="meta-item">
                  <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  <?= htmlspecialchars($ev['venue']) ?>
                </div>
              </div>

              <div class="slots-info">
                <div class="slots-row">
                  <span><?= $enrolled ?> / <?= $max ?> registered</span>
                  <span><?= $is_full ? 'Full' : $slots_left . ' left' ?></span>
                </div>
                <div class="prog"><div class="prog-bar <?= $bar_class ?>" style="width:<?= $pct ?>%"></div></div>
              </div>
            </div>

            <div class="event-card-footer">
              <div class="event-date-short">
                <?= date('M d', strtotime($ev['date_time'])) ?>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn btn-ghost btn-sm"
                  onclick='openDetail(<?= json_encode([
                    "id"          => $ev["id"],
                    "title"       => $ev["title"],
                    "description" => $ev["description"] ?? "",
                    "date"        => date("D, F d, Y · g:i A", strtotime($ev["date_time"])),
                    "venue"    => $ev["venue"],
                    "enrolled"    => $enrolled,
                    "max_slots"   => $max,
                    "slots_left"  => $slots_left,
                    "is_reg"      => $is_reg,
                    "my_status"   => $ev["my_status"] ?? "",
                    "is_full"     => $is_full,
                  ]) ?>)'>
                  Details
                </button>
                <?php if ($is_reg): ?>
                  <button class="btn btn-success btn-sm" disabled>✓ Registered</button>
                <?php elseif ($is_full): ?>
                  <button class="btn btn-disabled btn-sm" disabled>Full</button>
                <?php else: ?>
                  <button class="btn btn-primary btn-sm"
                    onclick='openRegister(<?= json_encode(["id" => $ev["id"], "title" => $ev["title"], "slots_left" => $slots_left]) ?>)'>
                    Register →
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          <h3><?= $search ? 'No events found' : 'No events available' ?></h3>
          <p><?= $search ? 'Try a different search term or clear your filters.' : 'Check back soon for upcoming events.' ?></p>
          <?php if ($search || $filter !== 'all'): ?>
            <a href="events.php" class="btn btn-ghost" style="margin-top:16px;display:inline-flex">Clear Filters</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<!-- ══ DETAIL MODAL ══════════════════════════════════════════ -->
<div class="modal-overlay" id="detailModal">
  <div class="modal">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="detail-title"></div>
        <div class="modal-sub" id="detail-sub"></div>
      </div>
      <button class="modal-close" onclick="closeModal('detailModal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-detail-row">
        <span class="label">Date</span>
        <span class="val" id="detail-date"></span>
      </div>
      <div class="modal-detail-row">
        <span class="label">Location</span>
        <span class="val" id="detail-venue"></span>
      </div>
      <div class="modal-detail-row">
        <span class="label">Capacity</span>
        <span class="val" id="detail-capacity"></span>
      </div>
      <div class="modal-detail-row" id="detail-desc-row">
        <span class="label">About</span>
        <span class="val" id="detail-desc"></span>
      </div>
      <div id="detail-warning"></div>
    </div>
    <div class="modal-footer" id="detail-footer"></div>
  </div>
</div>

<!-- ══ REGISTER CONFIRM MODAL ════════════════════════════════ -->
<div class="modal-overlay" id="registerModal">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div>
        <div class="modal-title">Confirm Registration</div>
        <div class="modal-sub" id="reg-event-name"></div>
      </div>
      <button class="modal-close" onclick="closeModal('registerModal')">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:0.87rem;color:var(--text-2);line-height:1.6">
        You are about to register for this event. Your spot will be confirmed immediately.
      </p>
      <div id="reg-slots-warning" style="margin-top:12px"></div>
    </div>
    <form method="POST">
      <?= csrf_token_field() ?>
      <input type="hidden" name="action" value="register">
      <input type="hidden" name="event_id" id="reg-event-id">
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('registerModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Confirm Registration</button>
      </div>
    </form>
  </div>
</div>


<script src="assets/js/global.js"></script>
<script>
// ── Search debounce ───────────────────────────────────────
let searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => document.getElementById('filterForm').submit(), 500);
});

// ── Event detail modal ────────────────────────────────────
function openDetail(ev) {
  document.getElementById('detail-title').textContent    = ev.title;
  document.getElementById('detail-sub').textContent      = ev.is_reg ? '✓ You are registered for this event' : '';
  document.getElementById('detail-date').textContent     = ev.date;
  document.getElementById('detail-venue').textContent    = ev.venue;
  document.getElementById('detail-capacity').textContent = `${ev.enrolled} / ${ev.max_slots} registered (${ev.slots_left} slots left)`;

  const descRow = document.getElementById('detail-desc-row');
  if (ev.description) {
    document.getElementById('detail-desc').textContent = ev.description;
    descRow.style.display = 'flex';
  } else {
    descRow.style.display = 'none';
  }

  const warn = document.getElementById('detail-warning');
  if (ev.is_full && !ev.is_reg) {
    warn.innerHTML = '<div class="slots-warning full">⚠ This event is full. No slots remaining.</div>';
  } else if (ev.slots_left <= 5 && !ev.is_reg) {
    warn.innerHTML = `<div class="slots-warning low">⚡ Only ${ev.slots_left} slot${ev.slots_left===1?'':'s'} left — register soon!</div>`;
  } else {
    warn.innerHTML = '';
  }

  const footer = document.getElementById('detail-footer');
  if (ev.is_reg) {
    footer.innerHTML = `<button class="btn btn-success" disabled>✓ ${ev.my_status ? ev.my_status.charAt(0).toUpperCase()+ev.my_status.slice(1) : 'Registered'}</button>`;
  } else if (ev.is_full) {
    footer.innerHTML = `<button class="btn btn-disabled" disabled>Event Full</button>`;
  } else {
    footer.innerHTML = `
      <button class="btn btn-ghost" onclick="closeModal('detailModal')">Close</button>
      <button class="btn btn-primary" onclick="closeModal('detailModal'); openRegister({id:${ev.id}, title:${JSON.stringify(ev.title)}, slots_left:${ev.slots_left}})">Register →</button>`;
  }

  openModal('detailModal');
}

function openRegister(ev) {
  document.getElementById('reg-event-name').textContent = ev.title;
  document.getElementById('reg-event-id').value         = ev.id;

  const warn = document.getElementById('reg-slots-warning');
  warn.innerHTML = ev.slots_left <= 5
    ? `<div class="slots-warning low">⚡ Only ${ev.slots_left} slot${ev.slots_left===1?'':'s'} remaining!</div>`
    : '';

  openModal('registerModal');
}
</script>
</body>
</html>