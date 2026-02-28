<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
require_once __DIR__ . '/../backend/csrf_helper.php';
require_once __DIR__ . '/../backend/paginate.php';
admin_only();

$admin    = current_user();
$msg      = '';
$msg_type = '';

/* ── POST ────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if (in_array($action, ['create', 'update'])) {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $date_time   = $_POST['date_time']         ?? '';
    $venue       = trim($_POST['venue']        ?? '');
    $max_slots   = max(1, (int)($_POST['max_slots'] ?? 50));
    $status      = in_array($_POST['status'] ?? '', ['active', 'inactive', 'cancelled']) ? $_POST['status'] : 'active';
    $category_id = ($_POST['category_id'] ?? '') ?: null;

    if (!$title || !$date_time || !$venue) {
      $msg = 'Please fill in all required fields (title, date, venue).';
      $msg_type = 'error';
    } else {
      if ($action === 'create') {
        $pdo->prepare("INSERT INTO events (title,description,date_time,venue,max_slots,status,category_id) VALUES (?,?,?,?,?,?,?)")
          ->execute([$title, $description, $date_time, $venue, $max_slots, $status, $category_id]);
        $msg = 'Event created successfully.';
      } else {
        $eid = (int)$_POST['event_id'];
        $pdo->prepare("UPDATE events SET title=?,description=?,date_time=?,venue=?,max_slots=?,status=?,category_id=? WHERE event_id=?")
          ->execute([$title, $description, $date_time, $venue, $max_slots, $status, $category_id, $eid]);
        $msg = 'Event updated successfully.';
      }
      $msg_type = 'success';
    }
  }

  if ($action === 'delete') {
    $eid = (int)$_POST['event_id'];
    $pdo->prepare("DELETE FROM registrations WHERE event_id=?")->execute([$eid]);
    $pdo->prepare("DELETE FROM events WHERE event_id=?")->execute([$eid]);
    $msg = 'Event deleted.';
    $msg_type = 'success';
  }
}

/* ── Filters ─────────────────────────────────────────────── */
$search   = trim($_GET['q']            ?? '');
$status_f = $_GET['status']            ?? 'all';
$cat_f    = (int)($_GET['category_id'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$params = [];
if ($search) {
  $where[] = "(e.title LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)";
  $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($status_f !== 'all') {
  $where[] = "e.status=?";
  $params[] = $status_f;
}
if ($cat_f > 0) {
  $where[] = "e.category_id=?";
  $params[] = $cat_f;
}
$ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt_q = $pdo->prepare("SELECT COUNT(*) FROM events e $ws");
$cnt_q->execute($params);
$total = (int)$cnt_q->fetchColumn();
$pg    = paginate($total, 15, $page);

$q = $pdo->prepare(
  "SELECT e.*, ec.category_name,
            COUNT(r.registration_id) AS enrolled
     FROM events e
     LEFT JOIN event_categories ec ON ec.category_id = e.category_id
     LEFT JOIN registrations r     ON r.event_id = e.event_id AND r.status != 'cancelled'
     $ws
     GROUP BY e.event_id
     ORDER BY e.date_time DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$q->execute($params);
$events = $q->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT * FROM event_categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

/* ── Stats ───────────────────────────────────────────────── */
$stat_total    = (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$stat_active   = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status='active'")->fetchColumn();
$stat_upcoming = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status='active' AND date_time >= NOW()")->fetchColumn();
$stat_full     = (int)$pdo->query("SELECT COUNT(*) FROM events e WHERE (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.event_id AND r.status!='cancelled') >= e.max_slots")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Events — ERMS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="assets/css/admin.css">
<style>
/* rdd — theme-aware, respects dark/light toggle */
.rdd{position:relative;display:inline-block;flex-shrink:0;}
.rdd--wide{min-width:160px;}.rdd--full{width:100%;}
.rdd__btn{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;padding:7px 11px;border-radius:8px;background:var(--bg2);border:1px solid var(--bdr);color:var(--t2);font-size:.82rem;cursor:pointer;outline:none;white-space:nowrap;transition:border-color .18s,color .18s;font-family:inherit;}
.rdd__btn:hover,.rdd--open .rdd__btn{border-color:var(--ab);color:var(--t1);}
.rdd__menu{display:none;position:absolute;top:calc(100% + 5px);left:0;min-width:100%;background:var(--bg2);border:1px solid var(--bdr);border-radius:8px;box-shadow:var(--sh3);z-index:9000;overflow:hidden;}
.rdd--open .rdd__menu{display:block;animation:rddIn .14s ease;}
@keyframes rddIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
.rdd__item{display:flex;align-items:center;gap:8px;padding:8px 13px;font-size:.82rem;color:var(--t2);cursor:pointer;white-space:nowrap;transition:background .12s,color .12s;font-family:inherit;}
.rdd__item:hover{background:var(--bgh);color:var(--t1);}
.rdd__item--active{color:var(--t1);background:rgba(74,122,181,.12);}
.rdd__dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
.rdd__dot--green{background:#4e9b72;}.rdd__dot--gold{background:#c9a84c;}.rdd__dot--red{background:#c45c5c;}
</style>
</head>

<body class="has-sidebar">

  <?php include 'partials/sidebar.php'; ?>

  <!-- ══ TOPBAR ════════════════════════════════════════════ -->
  <div class="topbar">
    <button id="menuToggle" class="topbar-btn" style="display:none"
      onclick="document.querySelector('.sidebar').classList.toggle('open')">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    <div>
      <div class="topbar-title">Manage Events</div>
      <div class="topbar-subtitle">Create, edit and monitor all events</div>
    </div>
    <div class="topbar-spacer"></div>
    <button class="c-btn c-btn--primary" onclick="evOpenModal('createModal')" style="margin-right:10px">
      <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
      </svg>
      New Event
    </button>
    <button class="theme-toggle-btn" id="themeToggle"><span id="themeIcon">☀️</span></button>
  </div>

  <!-- ══ MAIN ══════════════════════════════════════════════ -->
  <main class="main">
    <div class="page-content">

      <?php if ($msg): ?>
        <div class="c-alert c-alert--<?= $msg_type ?>" data-auto-dismiss>
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?php if ($msg_type === 'success'): ?>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            <?php else: ?>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            <?php endif; ?>
          </svg>
          <?= htmlspecialchars($msg) ?>
        </div>
      <?php endif; ?>

      <!-- ── Stat Cards ── -->
      <div class="c-stats">
        <div class="c-stat c-stat--gold">
          <div class="c-stat__label">Total Events</div>
          <div class="c-stat__value"><?= number_format($stat_total) ?></div>
          <div class="c-stat__sub">All time</div>
          <div class="c-stat__icon"><svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg></div>
        </div>
        <div class="c-stat c-stat--green">
          <div class="c-stat__label">Active</div>
          <div class="c-stat__value"><?= number_format($stat_active) ?></div>
          <div class="c-stat__sub">Published events</div>
          <div class="c-stat__icon"><svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg></div>
        </div>
        <div class="c-stat c-stat--blue">
          <div class="c-stat__label">Upcoming</div>
          <div class="c-stat__value"><?= number_format($stat_upcoming) ?></div>
          <div class="c-stat__sub">Scheduled ahead</div>
          <div class="c-stat__icon"><svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg></div>
        </div>
      </div>

      <!-- ── Table Card ── -->
      <div class="c-card">
        <!-- Card header: title left, filters right -->
        <div class="c-card__head">
          <div>
            <div class="c-card__title">All Events</div>
            <div class="c-card__sub">
              <?= number_format($total) ?> event<?= $total !== 1 ? 's' : '' ?>
              <?php if ($search || $status_f !== 'all' || $cat_f): ?>&mdash; filtered<?php endif; ?>
            </div>
          </div>
          <form method="GET" class="c-filter">
            <div class="c-search">
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search events, venue…">
            </div>

            <!-- Category dropdown -->
            <div class="rdd rdd--wide" id="rdd-cat">
              <input type="hidden" name="category_id" value="<?= $cat_f ?>">
              <button type="button" class="rdd__btn" onclick="rddToggle('rdd-cat')">
                <span id="rdd-cat-lbl"><?php
                                        if ($cat_f === 0) echo 'All Categories';
                                        else foreach ($categories as $c) if ($c['category_id'] === $cat_f) {
                                          echo htmlspecialchars($c['category_name']);
                                          break;
                                        }
                                        ?></span>
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div class="rdd__menu">
                <div class="rdd__item <?= $cat_f === 0 ? 'rdd__item--active' : '' ?>" onclick="rddPick('rdd-cat','0','All Categories',true)">All Categories</div>
                <?php foreach ($categories as $c): ?>
                  <div class="rdd__item <?= $cat_f === $c['category_id'] ? 'rdd__item--active' : '' ?>"
                    onclick="rddPick('rdd-cat','<?= $c['category_id'] ?>','<?= htmlspecialchars(addslashes($c['category_name'])) ?>',true)">
                    <?= htmlspecialchars($c['category_name']) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Status dropdown -->
            <div class="rdd" id="rdd-status">
              <input type="hidden" name="status" value="<?= htmlspecialchars($status_f) ?>">
              <button type="button" class="rdd__btn" onclick="rddToggle('rdd-status')">
                <span id="rdd-status-lbl"><?= $status_f === 'all' ? 'All Status' : ucfirst($status_f) ?></span>
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div class="rdd__menu">
                <div class="rdd__item <?= $status_f === 'all' ? 'rdd__item--active' : '' ?>" onclick="rddPick('rdd-status','all','All Status',true)">All Status</div>
                <div class="rdd__item <?= $status_f === 'active' ? 'rdd__item--active' : '' ?>" onclick="rddPick('rdd-status','active','Active',true)"><span class="rdd__dot rdd__dot--green"></span>Active</div>
                <div class="rdd__item <?= $status_f === 'inactive' ? 'rdd__item--active' : '' ?>" onclick="rddPick('rdd-status','inactive','Inactive',true)"><span class="rdd__dot rdd__dot--gold"></span>Inactive</div>
                <div class="rdd__item <?= $status_f === 'cancelled' ? 'rdd__item--active' : '' ?>" onclick="rddPick('rdd-status','cancelled','Cancelled',true)"><span class="rdd__dot rdd__dot--red"></span>Cancelled</div>
              </div>
            </div>

            <?php if ($search || $status_f !== 'all' || $cat_f): ?>
              <a href="events.php" class="c-btn c-btn--ghost">✕ Clear</a>
            <?php else: ?>
              <button type="submit" class="c-btn c-btn--ghost">Search</button>
            <?php endif; ?>
          </form>
        </div>

        <!-- Table -->
        <div class="c-table-scroll">
          <table class="c-table">
            <thead>
              <tr>
                <th style="width:44px">#</th>
                <th>Event</th>
                <th style="width:120px">Category</th>
                <th style="width:130px">Date &amp; Time</th>
                <th style="width:140px">Venue</th>
                <th style="width:110px">Slots</th>
                <th style="width:100px">Status</th>
                <th style="width:140px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($events)): ?>
                <tr>
                  <td colspan="8">
                    <div class="c-empty">
                      <svg width="60" height="60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      <h3><?= ($search || $status_f !== 'all' || $cat_f) ? 'No events match your filters' : 'No events yet' ?></h3>
                      <p><?= ($search || $status_f !== 'all' || $cat_f) ? 'Try adjusting your search or filters' : 'Create your first event using the button above' ?></p>
                      <?php if (!$search && $status_f === 'all' && !$cat_f): ?>
                        <button onclick="evOpenModal('createModal')" class="c-btn c-btn--primary">
                          <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                          </svg>
                          New Event
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php else: foreach ($events as $i => $ev):
                  $pct  = $ev['max_slots'] > 0 ? round($ev['enrolled'] / $ev['max_slots'] * 100) : 0;
                  $bclr = $pct >= 100 ? '#c45c5c' : ($pct >= 75 ? '#c9a84c' : '#4e9b72');
                ?>
                  <tr>
                    <!-- # -->
                    <td class="c-td-seq"><?= $pg['from'] + $i ?></td>

                    <!-- Event title + desc -->
                    <td>
                      <div class="c-name"><?= htmlspecialchars($ev['title']) ?></div>
                      <?php if ($ev['description']): ?>
                        <div class="c-cell-sub">
                          <?= htmlspecialchars($ev['description']) ?>
                        </div>
                      <?php endif; ?>
                    </td>

                    <!-- Category -->
                    <td>
                      <?php if ($ev['category_name']): ?>
                        <span class="badge badge-blue"><?= htmlspecialchars($ev['category_name']) ?></span>
                      <?php else: ?>
                        <span class="c-cell-dash">—</span>
                      <?php endif; ?>
                    </td>

                    <!-- Date & time -->
                    <td class="c-cell-date">
                      <div class="c-cell-date__main"><?= date('M j, Y', strtotime($ev['date_time'])) ?></div>
                      <div class="c-cell-date__sub"><?= date('g:i A', strtotime($ev['date_time'])) ?></div>
                    </td>

                    <!-- Venue -->
                    <td class="c-cell-venue">
                      <?= htmlspecialchars($ev['venue']) ?>
                    </td>

                    <!-- Slots + progress bar -->
                    <td style="min-width:90px">
                      <div class="c-cell-slots__txt"><?= $ev['enrolled'] ?>/<?= $ev['max_slots'] ?></div>
                      <div class="c-cell-slots__bar">
                        <div style="height:100%;width:<?= min($pct, 100) ?>%;background:<?= $bclr ?>;border-radius:10px;transition:width .4s"></div>
                      </div>
                    </td>

                    <!-- Status badge -->
                    <td>
                      <span class="ev-badge ev-badge--<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span>
                    </td>

                    <!-- Actions -->
                    <td>
                      <div class="c-actions">
                        <button type="button" class="c-btn c-btn--edit"
                          onclick="evEdit(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>)">
                          <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                          </svg>
                          Edit
                        </button>
                        <form method="POST" style="display:inline">
                          <?= csrf_token_field() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="event_id" value="<?= $ev['event_id'] ?>">
                          <button type="submit" class="c-btn c-btn--del"
                            onclick="return confirm('Delete \'<?= htmlspecialchars(addslashes($ev['title'])) ?>\'? All registrations will also be removed.')">
                            <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($pg['total_pages'] > 1): ?>
          <div class="c-pager">
            <span>Showing <?= $pg['from'] ?>–<?= $pg['to'] ?> of <?= number_format($pg['total']) ?></span>
            <div class="c-pager__btns">
              <?php for ($p = 1; $p <= $pg['total_pages']; $p++):
                $link = '?' . http_build_query(array_filter(['q' => $search, 'status' => $status_f !== 'all' ? $status_f : null, 'category_id' => $cat_f ?: null, 'page' => $p]));
              ?>
                <a href="<?= $link ?>" class="c-pager__btn <?= $p === $pg['current'] ? 'c-pager__btn--active' : '' ?>"><?= $p ?></a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>

  <!-- ══ CREATE MODAL ════════════════════════════════════════ -->
  <div class="c-overlay" id="createModal">
    <div class="c-modal c-modal--wide">
      <div class="c-modal__bar"></div>
      <div class="c-modal__head">
        <div>
          <div class="c-modal__title">New Event</div>
          <div class="c-modal__sub">Fill in the details to create a new event</div>
        </div>
        <button class="c-modal__close" onclick="evCloseModal('createModal')">✕</button>
      </div>
      <form method="POST">
        <?= csrf_token_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="c-modal__body">
          <div class="c-field">
            <label class="c-label">Title <span class="c-req">*</span></label>
            <input type="text" name="title" class="c-input" required placeholder="e.g. Annual Science Fair">
          </div>
          <div class="c-modal__2col">
            <div class="c-field">
              <label class="c-label">Date &amp; Time <span class="c-req">*</span></label>
              <input type="datetime-local" name="date_time" class="c-input" required>
            </div>
            <div class="c-field">
              <label class="c-label">Max Slots</label>
              <input type="number" name="max_slots" class="c-input" value="50" min="1">
            </div>
          </div>
          <div class="c-field">
            <label class="c-label">Venue <span class="c-req">*</span></label>
            <input type="text" name="venue" class="c-input" required placeholder="e.g. Main Auditorium">
          </div>
          <div class="c-modal__2col">
            <div class="c-field">
              <label class="c-label">Category</label>
              <select name="category_id" class="c-input">
                <option value="">— None —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="c-field">
              <label class="c-label">Status</label>
              <select name="status" class="c-input">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>
          <div class="c-field">
            <label class="c-label">Description</label>
            <textarea name="description" class="c-input c-textarea" rows="3" placeholder="Event details…"></textarea>
          </div>
        </div>
        <div class="c-modal__foot">
          <button type="button" class="c-btn c-btn--ghost" onclick="evCloseModal('createModal')">Cancel</button>
          <button type="submit" class="c-btn c-btn--primary">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Create Event
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══ EDIT MODAL ══════════════════════════════════════════ -->
  <div class="c-overlay" id="editModal">
    <div class="c-modal c-modal--wide">
      <div class="c-modal__bar"></div>
      <div class="c-modal__head">
        <div>
          <div class="c-modal__title">Edit Event</div>
          <div class="c-modal__sub">Update the event details</div>
        </div>
        <button class="c-modal__close" onclick="evCloseModal('editModal')">✕</button>
      </div>
      <form method="POST">
        <?= csrf_token_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="event_id" id="edit_event_id">
        <div class="c-modal__body">
          <div class="c-field">
            <label class="c-label">Title <span class="c-req">*</span></label>
            <input type="text" name="title" id="edit_title" class="c-input" required>
          </div>
          <div class="c-modal__2col">
            <div class="c-field">
              <label class="c-label">Date &amp; Time <span class="c-req">*</span></label>
              <input type="datetime-local" name="date_time" id="edit_date_time" class="c-input" required>
            </div>
            <div class="c-field">
              <label class="c-label">Max Slots</label>
              <input type="number" name="max_slots" id="edit_max_slots" class="c-input" min="1">
            </div>
          </div>
          <div class="c-field">
            <label class="c-label">Venue <span class="c-req">*</span></label>
            <input type="text" name="venue" id="edit_venue" class="c-input" required>
          </div>
          <div class="c-modal__2col">
            <div class="c-field">
              <label class="c-label">Category</label>
              <select name="category_id" id="edit_category_id" class="c-input">
                <option value="">— None —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="c-field">
              <label class="c-label">Status</label>
              <select name="status" id="edit_status" class="c-input">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>
          <div class="c-field">
            <label class="c-label">Description</label>
            <textarea name="description" id="edit_description" class="c-input c-textarea" rows="3"></textarea>
          </div>
        </div>
        <div class="c-modal__foot">
          <button type="button" class="c-btn c-btn--ghost" onclick="evCloseModal('editModal')">Cancel</button>
          <button type="submit" class="c-btn c-btn--primary">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/global.js"></script>
  <script src="assets/js/admin.js"></script>
  <script>
    /* ── Modal helpers (inline — no global.js dependency) ── */
    function evOpenModal(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.style.display = 'flex';
      requestAnimationFrame(function() {
        el.classList.add('c-overlay--open');
      });
    }

    function evCloseModal(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.classList.remove('c-overlay--open');
      setTimeout(function() {
        el.style.display = 'none';
      }, 220);
    }

    function evEdit(ev) {
      document.getElementById('edit_event_id').value = ev.event_id;
      document.getElementById('edit_title').value = ev.title;
      document.getElementById('edit_venue').value = ev.venue;
      document.getElementById('edit_max_slots').value = ev.max_slots;
      document.getElementById('edit_description').value = ev.description || '';
      document.getElementById('edit_status').value = ev.status;
      document.getElementById('edit_category_id').value = ev.category_id || '';
      var dt = new Date(ev.date_time.replace(' ', 'T'));
      var pad = function(n) {
        return String(n).padStart(2, '0');
      };
      document.getElementById('edit_date_time').value =
        dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
      evOpenModal('editModal');
    }

    /* Backdrop + Escape */
    document.querySelectorAll('.c-overlay').forEach(function(el) {
      el.addEventListener('click', function(e) {
        if (e.target === el) evCloseModal(el.id);
      });
    });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') document.querySelectorAll('.c-overlay--open').forEach(function(el) {
        evCloseModal(el.id);
      });
    });

    /* ── rdd dropdown helpers ── */
    function rddToggle(id) {
      var el = document.getElementById(id);
      var isOpen = el.classList.contains('rdd--open');
      document.querySelectorAll('.rdd--open').forEach(function(d) {
        d.classList.remove('rdd--open');
      });
      if (!isOpen) el.classList.add('rdd--open');
    }

    function rddPick(id, value, label, submitForm) {
      var el = document.getElementById(id);
      el.querySelector('input[type=hidden]').value = value;
      el.querySelector('.rdd__btn span').textContent = label;
      el.querySelectorAll('.rdd__item').forEach(function(i) {
        i.classList.remove('rdd__item--active');
      });
      el.classList.remove('rdd--open');
      if (submitForm) el.closest('form').submit();
    }
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.rdd')) document.querySelectorAll('.rdd--open').forEach(function(d) {
        d.classList.remove('rdd--open');
      });
    });
  </script>
</body>

</html>