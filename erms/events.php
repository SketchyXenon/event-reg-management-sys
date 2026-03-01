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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
  csrf_verify();
  $event_id = (int)($_POST['event_id'] ?? 0);

  if (!$event_id) {
    $msg = 'Invalid event.';
    $msg_type = 'error';
  } else {
    $ev = $pdo->prepare("SELECT * FROM events WHERE event_id = ? AND status = 'active'");
    $ev->execute([$event_id]);
    $ev = $ev->fetch(PDO::FETCH_ASSOC);

    if (!$ev) {
      $msg = 'Event not found or no longer available.';
      $msg_type = 'error';
    } else {
      $exists = $pdo->prepare("SELECT registration_id FROM registrations WHERE user_id = ? AND event_id = ? AND status != 'cancelled'");
      $exists->execute([$user_id, $event_id]);
      if ($exists->fetch()) {
        $msg = 'You are already registered for this event.';
        $msg_type = 'error';
      } else {
        $enrolled = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status != 'cancelled'");
        $enrolled->execute([$event_id]);
        if ((int)$enrolled->fetchColumn() >= $ev['max_slots']) {
          $msg = 'Sorry, this event is already full.';
          $msg_type = 'error';
        } else {
          $pdo->prepare("INSERT INTO registrations (user_id, event_id, status, registered_at) VALUES (?, ?, 'confirmed', NOW())")
            ->execute([$user_id, $event_id]);
          $msg = 'Successfully registered for <strong>' . htmlspecialchars($ev['title']) . '</strong>!';
          $msg_type = 'success';
        }
      }
    }
  }
}

// ── Filters & pagination ───────────────────────────────────
require_once __DIR__ . '/backend/paginate.php';

$search     = trim($_GET['q']            ?? '');
$filter     = $_GET['filter']            ?? 'all';
$sort       = $_GET['sort']              ?? 'date';
$category_f = (int)($_GET['category_id'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 9;

$all_categories = $pdo->query("SELECT * FROM event_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// ── Build WHERE ────────────────────────────────────────────
$base_where  = "e.status = 'active' AND e.date_time >= CURDATE()";
$search_params = [];
if ($search) {
  $base_where   .= " AND (e.title LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)";
  $search_params = array_merge($search_params, ["%$search%", "%$search%", "%$search%"]);
}
if ($category_f > 0) {
  $base_where   .= " AND e.category_id = ?";
  $search_params[] = $category_f;
}

$order = $sort === 'slots' ? 'slots_left ASC' : 'e.date_time ASC';

// ── Tab counts ─────────────────────────────────────────────
$tab_q = $pdo->prepare(
  "SELECT
        COUNT(DISTINCT e.event_id) AS total,
        COUNT(DISTINCT CASE WHEN ur.user_id IS NOT NULL THEN e.event_id END) AS registered,
        COUNT(DISTINCT CASE WHEN ur.user_id IS NULL AND (e.max_slots - COALESCE(ec.enrolled,0)) > 0 THEN e.event_id END) AS available
     FROM events e
     LEFT JOIN (SELECT event_id, COUNT(*) AS enrolled FROM registrations WHERE status != 'cancelled' GROUP BY event_id) ec ON ec.event_id = e.event_id
     LEFT JOIN registrations ur ON ur.event_id = e.event_id AND ur.user_id = ? AND ur.status != 'cancelled'
     WHERE $base_where"
);
$tab_q->execute(array_merge([$user_id], $search_params));
$counts = $tab_q->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'registered' => 0, 'available' => 0];

// ── Filter HAVING ──────────────────────────────────────────
$filter_having = '';
if ($filter === 'available')  $filter_having = 'HAVING is_registered = 0 AND slots_left > 0';
if ($filter === 'registered') $filter_having = 'HAVING is_registered = 1';

// ── Count for pagination ───────────────────────────────────
$cnt_q = $pdo->prepare(
  "SELECT COUNT(*) FROM (
        SELECT e.event_id,
               (e.max_slots - COUNT(DISTINCT r.registration_id)) AS slots_left,
               MAX(CASE WHEN ur.user_id = ? THEN 1 ELSE 0 END) AS is_registered
        FROM events e
        LEFT JOIN registrations r  ON r.event_id = e.event_id AND r.status != 'cancelled'
        LEFT JOIN registrations ur ON ur.event_id = e.event_id AND ur.user_id = ? AND ur.status != 'cancelled'
        WHERE $base_where
        GROUP BY e.event_id
        $filter_having
    ) AS sub"
);
$cnt_q->execute(array_merge([$user_id, $user_id], $search_params));
$total = (int)$cnt_q->fetchColumn();

$pg = paginate($total, $per_page, $page);

// ── Paginated events ───────────────────────────────────────
$ev_q = $pdo->prepare(
  "SELECT e.event_id, e.title, e.description, e.date_time, e.venue, e.max_slots,
            ec.category_name,
            COUNT(DISTINCT r.registration_id) AS enrolled,
            (e.max_slots - COUNT(DISTINCT r.registration_id)) AS slots_left,
            MAX(CASE WHEN ur.user_id = ? THEN 1 ELSE 0 END) AS is_registered,
            MAX(CASE WHEN ur.user_id = ? THEN ur.status ELSE NULL END) AS my_status
     FROM events e
     LEFT JOIN event_categories ec ON ec.category_id = e.category_id
     LEFT JOIN registrations r  ON r.event_id = e.event_id AND r.status != 'cancelled'
     LEFT JOIN registrations ur ON ur.event_id = e.event_id AND ur.user_id = ? AND ur.status != 'cancelled'
     WHERE $base_where
     GROUP BY e.event_id
     $filter_having
     ORDER BY $order
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$ev_q->execute(array_merge([$user_id, $user_id, $user_id], $search_params));
$all_events = $ev_q->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Browse Events — ERMS</title>
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
      <a href="dashboard.php" class="nav-item">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        Dashboard
      </a>
      <a href="events.php" class="nav-item active">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Browse Events
      </a>
      <a href="my-registrations.php" class="nav-item">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        My Registrations
      </a>
      <div class="nav-label" style="margin-top:8px">Account</div>
      <a href="profile.php" class="nav-item">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        My Profile
      </a>
      <a href="index.php" class="nav-item">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3" />
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
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
        Sign Out
      </a>
    </div>
  </aside>

  <!-- ── Topbar ──────────────────────────────────────────────── -->
  <header class="topbar">
    <button id="menuBtn" class="theme-toggle-btn" style="display:none"
      onclick="document.getElementById('sidebar').classList.toggle('open')">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    <div>
      <div class="topbar-title">Browse Events</div>
      <div class="topbar-sub"><?= $total ?> event<?= $total !== 1 ? 's' : '' ?> found</div>
    </div>
    <div class="topbar-space"></div>
    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>
  </header>

  <!-- ── Main ───────────────────────────────────────────────── -->
  <main class="main">
    <div class="page">

      <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?>" data-auto-dismiss>
          <?= $msg_type === 'success'
            ? '<svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'
            : '<svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>'
          ?>
          <div><?= $msg ?></div>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <h2>Browse Events</h2>
        <p>Discover and register for upcoming events on campus.</p>
      </div>

      <!-- Toolbar -->
      <form method="GET" id="filterForm">
        <div class="toolbar">
          <div class="search-box">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search events, venues…" id="searchInput">
          </div>
          <div class="filter-tabs">
            <?php foreach (['all' => 'All', 'available' => 'Available', 'registered' => 'Registered'] as $val => $lbl): ?>
              <button type="button"
                class="filter-tab <?= $filter === $val ? 'active' : '' ?>"
                onclick="document.getElementById('filterInput').value='<?= $val ?>'; this.closest('form').submit();"><?= $lbl ?></button>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($all_categories)): ?>
            <select name="category_id" class="sort-select" onchange="this.form.submit()">
              <option value="0">All Categories</option>
              <?php foreach ($all_categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>" <?= $category_f === $cat['category_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['category_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
          <select name="sort" class="sort-select" onchange="this.form.submit()">
            <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>Earliest First</option>
            <option value="slots" <?= $sort === 'slots' ? 'selected' : '' ?>>Most Slots</option>
          </select>
          <input type="hidden" name="filter" id="filterInput" value="<?= htmlspecialchars($filter) ?>">
        </div>
      </form>

      <!-- Counts row -->
      <div class="counts-row">
        <div class="count-chip blue">
          <div class="dot"></div><?= (int)$counts['total'] ?> Total
        </div>
        <div class="count-chip green">
          <div class="dot"></div><?= (int)$counts['registered'] ?> Registered
        </div>
        <div class="count-chip gold">
          <div class="dot"></div><?= (int)$counts['available'] ?> Available
        </div>
      </div>

      <!-- Events grid -->
      <div class="events-grid">
        <?php if (!empty($all_events)): ?>
          <?php foreach ($all_events as $ev):
            $slots_left = max(0, (int)$ev['slots_left']);
            $enrolled   = (int)$ev['enrolled'];
            $max        = (int)$ev['max_slots'];
            $pct        = $max > 0 ? min(100, round(($enrolled / $max) * 100)) : 0;
            $is_full    = $slots_left <= 0;
            $is_reg     = (bool)$ev['is_registered'];
            $bar_class  = $pct >= 100 ? 'full' : ($pct >= 75 ? 'warn' : 'ok');
            $card_class = $is_reg ? 'registered' : ($is_full ? 'full' : 'available');
            // Build data for JS modals (use event_id — not id)
            $modal_data = [
              'event_id'    => (int)$ev['event_id'],
              'title'       => $ev['title'],
              'description' => $ev['description'] ?? '',
              'date'        => date('D, F d, Y · g:i A', strtotime($ev['date_time'])),
              'venue'       => $ev['venue'],
              'enrolled'    => $enrolled,
              'max_slots'   => $max,
              'slots_left'  => $slots_left,
              'is_reg'      => $is_reg,
              'my_status'   => $ev['my_status'] ?? '',
              'is_full'     => $is_full,
            ];
          ?>
            <div class="event-card <?= $card_class ?>">
              <div class="event-card-body">
                <div class="event-card-top">
                  <div class="event-title"><?= htmlspecialchars($ev['title']) ?></div>
                  <?php if ($is_reg): ?>
                    <span class="event-status-badge badge-<?= htmlspecialchars($ev['my_status'] ?? 'confirmed') ?>">
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

                <?php if ($ev['category_name']): ?>
                  <div style="margin-bottom:8px">
                    <span style="display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:600;
                    padding:2px 8px;border-radius:20px;
                    background:rgba(74,122,181,.12);color:var(--blue-l);border:1px solid rgba(74,122,181,.25)">
                      <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                      </svg>
                      <?= htmlspecialchars($ev['category_name']) ?>
                    </span>
                  </div>
                <?php endif; ?>

                <div class="event-meta">
                  <div class="meta-item date">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <?= date('D, M d, Y · g:i A', strtotime($ev['date_time'])) ?>
                  </div>
                  <div class="meta-item">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <?= htmlspecialchars($ev['venue']) ?>
                  </div>
                </div>

                <div class="slots-info">
                  <div class="slots-row">
                    <span><?= $enrolled ?>/<?= $max ?> registered</span>
                    <span><?= $is_full ? 'Full' : $slots_left . ' left' ?></span>
                  </div>
                  <div class="prog">
                    <div class="prog-bar <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
                  </div>
                </div>
              </div>

              <div class="event-card-footer">
                <div class="event-date-short"><?= date('M d', strtotime($ev['date_time'])) ?></div>
                <div style="display:flex;gap:8px">
                  <button class="btn btn-ghost btn-sm"
                    onclick='openDetail(<?= htmlspecialchars(json_encode($modal_data), ENT_QUOTES) ?>)'>
                    Details
                  </button>
                  <?php if ($is_reg): ?>
                    <button class="btn btn-success btn-sm" disabled>✓ Registered</button>
                  <?php elseif ($is_full): ?>
                    <button class="btn btn-disabled btn-sm" disabled>Full</button>
                  <?php else: ?>
                    <button class="btn btn-primary btn-sm"
                      onclick='openRegister(<?= htmlspecialchars(json_encode([
                                              "event_id"   => (int)$ev["event_id"],
                                              "title"      => $ev["title"],
                                              "slots_left" => $slots_left,
                                            ]), ENT_QUOTES) ?>)'>
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
            <p><?= $search ? 'Try a different search or clear your filters.' : 'Check back soon for upcoming events.' ?></p>
            <?php if ($search || $filter !== 'all' || $category_f): ?>
              <a href="events.php" class="btn btn-ghost" style="margin-top:16px;display:inline-flex">Clear Filters</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php render_pagination($pg, ['q' => $search, 'filter' => $filter, 'sort' => $sort, 'category_id' => $category_f ?: null]); ?>

    </div>
  </main>

  <!-- ── Detail Modal ───────────────────────────────────────── -->
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
        <div class="modal-detail-row"><span class="label">Date</span><span class="val" id="detail-date"></span></div>
        <div class="modal-detail-row"><span class="label">Location</span><span class="val" id="detail-venue"></span></div>
        <div class="modal-detail-row"><span class="label">Capacity</span><span class="val" id="detail-capacity"></span></div>
        <div class="modal-detail-row" id="detail-desc-row"><span class="label">About</span><span class="val" id="detail-desc"></span></div>
        <div id="detail-warning"></div>
      </div>
      <div class="modal-footer" id="detail-footer"></div>
    </div>
  </div>

  <!-- ── Register Confirm Modal ─────────────────────────────── -->
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
        <p style="font-size:.87rem;color:var(--text-2);line-height:1.6">
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

  <style>
    .sb-brand-top {
      display: flex;
      align-items: center;
      gap: 11px;
    }

    .sb-brand-text {
      display: flex;
      flex-direction: column;
    }

    .sidebar-brand {
      display: flex;
      flex-direction: column;
      padding: 18px 18px 16px;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
    }

    .sidebar-brand h1 {
      font-family: var(--ff-d);
      font-size: .93rem;
      font-weight: 600;
      color: var(--text);
      line-height: 1.25;
    }

    .sidebar-brand p {
      font-size: .63rem;
      color: var(--text-3);
      letter-spacing: .09em;
      text-transform: uppercase;
      margin-top: 1px;
    }

    .sb-clock {
      margin-top: 11px;
      padding-top: 11px;
      border-top: 1px solid var(--border);
      width: 100%;
    }

    .sb-clock__time {
      font-family: var(--ff-m, 'JetBrains Mono', monospace);
      font-size: 1.18rem;
      font-weight: 500;
      color: var(--text);
      letter-spacing: .07em;
      line-height: 1;
    }

    .sb-clock__date {
      font-size: .61rem;
      color: var(--text-3);
      letter-spacing: .05em;
      margin-top: 4px;
      text-transform: uppercase;
    }

    @media (max-width:768px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .main {
        margin-left: 0 !important;
      }

      #menuBtn {
        display: flex !important;
      }
    }
  </style>

  <script src="assets/js/global.js"></script>
  <script>
    /* Search debounce */
    let st;
    document.getElementById('searchInput').addEventListener('input', function() {
      clearTimeout(st);
      st = setTimeout(() => document.getElementById('filterForm').submit(), 500);
    });

    /* Detail modal */
    function openDetail(ev) {
      document.getElementById('detail-title').textContent = ev.title;
      document.getElementById('detail-sub').textContent = ev.is_reg ? '✓ You are registered for this event' : '';
      document.getElementById('detail-date').textContent = ev.date;
      document.getElementById('detail-venue').textContent = ev.venue;
      document.getElementById('detail-capacity').textContent = ev.enrolled + ' / ' + ev.max_slots + ' registered (' + ev.slots_left + ' slots left)';
      var descRow = document.getElementById('detail-desc-row');
      if (ev.description) {
        document.getElementById('detail-desc').textContent = ev.description;
        descRow.style.display = 'flex';
      } else {
        descRow.style.display = 'none';
      }
      var warn = document.getElementById('detail-warning');
      if (ev.is_full && !ev.is_reg) warn.innerHTML = '<div class="slots-warning full">⚠ This event is full.</div>';
      else if (ev.slots_left <= 5 && !ev.is_reg) warn.innerHTML = '<div class="slots-warning low">⚡ Only ' + ev.slots_left + ' slot' + (ev.slots_left === 1 ? '' : 's') + ' left!</div>';
      else warn.innerHTML = '';
      var footer = document.getElementById('detail-footer');
      if (ev.is_reg) footer.innerHTML = '<button class="btn btn-success" disabled>✓ ' + (ev.my_status ? ev.my_status.charAt(0).toUpperCase() + ev.my_status.slice(1) : 'Registered') + '</button>';
      else if (ev.is_full) footer.innerHTML = '<button class="btn btn-disabled" disabled>Event Full</button>';
      else footer.innerHTML = '<button class="btn btn-ghost" onclick="closeModal(\'detailModal\')">Close</button><button class="btn btn-primary" onclick="closeModal(\'detailModal\');openRegister({event_id:' + ev.event_id + ',title:' + JSON.stringify(ev.title) + ',slots_left:' + ev.slots_left + '})">Register →</button>';
      openModal('detailModal');
    }

    /* Register modal */
    function openRegister(ev) {
      document.getElementById('reg-event-name').textContent = ev.title;
      document.getElementById('reg-event-id').value = ev.event_id;
      var warn = document.getElementById('reg-slots-warning');
      warn.innerHTML = ev.slots_left <= 5 ? '<div class="slots-warning low">⚡ Only ' + ev.slots_left + ' slot' + (ev.slots_left === 1 ? '' : 's') + ' remaining!</div>' : '';
      openModal('registerModal');
    }


    (function() {
      var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
      var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

      function tick() {
        var now = new Date(),
          h = String(now.getHours()).padStart(2, '0'),
          m = String(now.getMinutes()).padStart(2, '0'),
          s = String(now.getSeconds()).padStart(2, '0');
        var tEl = document.getElementById('sbTime'),
          dEl = document.getElementById('sbDate');
        if (tEl) tEl.textContent = h + ':' + m + ':' + s;
        if (dEl) dEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ' ' + now.getFullYear();
      }
      tick();
      setInterval(tick, 1000);
    })();
  </script>
</body>

</html>