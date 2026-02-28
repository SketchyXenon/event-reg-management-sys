<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
require_once __DIR__ . '/../backend/csrf_helper.php';
require_once __DIR__ . '/../backend/paginate.php';
admin_only();

$admin    = current_user();
$msg      = '';
$msg_type = '';

/* ── POST ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $rid    = (int)($_POST['reg_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['confirmed','pending','cancelled'])
                  ? $_POST['status'] : 'pending';
        $pdo->prepare("UPDATE registrations SET status=? WHERE registration_id=?")
            ->execute([$status, $rid]);
        $msg = 'Status updated successfully.'; $msg_type = 'success';

    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM registrations WHERE registration_id=?")
            ->execute([(int)($_POST['reg_id'] ?? 0)]);
        $msg = 'Registration removed.'; $msg_type = 'success';
    }
}

/* ── Filters ──────────────────────────────────────────────── */
$search   = trim($_GET['q']        ?? '');
$status_f = $_GET['status']        ?? 'all';
$event_f  = (int)($_GET['event_id'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = []; $params = [];
if ($search)           { $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR e.title LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status_f !== 'all') { $where[] = "r.status=?";   $params[] = $status_f; }
if ($event_f > 0)        { $where[] = "r.event_id=?"; $params[] = $event_f;  }
$ws = $where ? 'WHERE '.implode(' AND ', $where) : '';

$base = "FROM registrations r
         JOIN users u  ON u.user_id   = r.user_id
         JOIN events e ON e.event_id  = r.event_id
         LEFT JOIN event_categories ec ON ec.category_id = e.category_id";

$cnt = $pdo->prepare("SELECT COUNT(*) $base $ws");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pg    = paginate($total, 20, $page);

$rows = $pdo->prepare(
    "SELECT r.registration_id, r.status, r.registered_at,
            u.full_name, u.student_id, u.email,
            e.title AS event_title, e.date_time, e.venue,
            ec.category_name
     $base $ws
     ORDER BY r.registered_at DESC
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$rows->execute($params);
$regs = $rows->fetchAll(PDO::FETCH_ASSOC);

$events_list = $pdo->query("SELECT event_id,title FROM events ORDER BY date_time DESC")
                   ->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->query("SELECT status, COUNT(*) AS cnt FROM registrations GROUP BY status")
             ->fetchAll(PDO::FETCH_KEY_PAIR);

$total_all = array_sum($stats);
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

<!-- ══ TOPBAR ════════════════════════════════════════════════ -->
<div class="topbar">
    <button id="menuToggle" class="topbar-btn" style="display:none"
            onclick="document.querySelector('.sidebar').classList.toggle('open')">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
    <div>
        <div class="topbar-title">Registrations</div>
        <div class="topbar-subtitle">Manage &amp; monitor all event sign-ups</div>
    </div>
    <div class="topbar-spacer"></div>
    <button class="theme-toggle-btn" id="themeToggle"><span id="themeIcon">☀️</span></button>
</div>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<main class="main">
<div class="page-content">

    <?php if ($msg): ?>
    <div class="c-alert c-alert--<?= $msg_type ?>" data-auto-dismiss>
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?php if ($msg_type === 'success'): ?>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            <?php else: ?>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            <?php endif; ?>
        </svg>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- ── Stat cards ── -->
    <div class="c-stats">
        <div class="c-stat c-stat--green">
            <div class="c-stat__label">Confirmed</div>
            <div class="c-stat__value"><?= number_format($stats['confirmed'] ?? 0) ?></div>
            <div class="c-stat__sub">Approved registrations</div>
            <div class="c-stat__icon">
                <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="c-stat c-stat--gold">
            <div class="c-stat__label">Pending</div>
            <div class="c-stat__value"><?= number_format($stats['pending'] ?? 0) ?></div>
            <div class="c-stat__sub">Awaiting confirmation</div>
            <div class="c-stat__icon">
                <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div class="c-stat c-stat--red">
            <div class="c-stat__label">Cancelled</div>
            <div class="c-stat__value"><?= number_format($stats['cancelled'] ?? 0) ?></div>
            <div class="c-stat__sub">Withdrawn sign-ups</div>
            <div class="c-stat__icon">
                <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- ── Table card ── -->
    <div class="c-card">
        <!-- Card header: title left, filters right -->
        <div class="c-card__head">
            <div>
                <div class="c-card__title">All Registrations</div>
                <div class="c-card__sub">
                    <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?>
                    <?php if ($search || $status_f !== 'all' || $event_f): ?>&mdash; filtered<?php endif; ?>
                </div>
            </div>
            <form method="GET" class="c-filter">
                <div class="c-search">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q"
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search student, event…">
                </div>
                <!-- Status dropdown -->
                <div class="rdd" id="rdd-status">
                    <input type="hidden" name="status" id="rdd-status-val" value="<?= htmlspecialchars($status_f) ?>">
                    <button type="button" class="rdd__btn" onclick="rddToggle('rdd-status')">
                        <span id="rdd-status-lbl"><?= $status_f === 'all' ? 'All Status' : ucfirst($status_f) ?></span>
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="rdd__menu">
                        <div class="rdd__item <?= $status_f==='all'?'rdd__item--active':'' ?>" onclick="rddPick('rdd-status','all','All Status',true)">All Status</div>
                        <div class="rdd__item <?= $status_f==='confirmed'?'rdd__item--active':'' ?>" onclick="rddPick('rdd-status','confirmed','Confirmed',true)">
                            <span class="rdd__dot rdd__dot--green"></span>Confirmed
                        </div>
                        <div class="rdd__item <?= $status_f==='pending'?'rdd__item--active':'' ?>" onclick="rddPick('rdd-status','pending','Pending',true)">
                            <span class="rdd__dot rdd__dot--gold"></span>Pending
                        </div>
                        <div class="rdd__item <?= $status_f==='cancelled'?'rdd__item--active':'' ?>" onclick="rddPick('rdd-status','cancelled','Cancelled',true)">
                            <span class="rdd__dot rdd__dot--red"></span>Cancelled
                        </div>
                    </div>
                </div>
                <!-- Event dropdown -->
                <div class="rdd rdd--wide" id="rdd-event">
                    <input type="hidden" name="event_id" id="rdd-event-val" value="<?= $event_f ?>">
                    <button type="button" class="rdd__btn" onclick="rddToggle('rdd-event')">
                        <span id="rdd-event-lbl"><?php
                            if ($event_f === 0) echo 'All Events';
                            else foreach ($events_list as $ev) if ($ev['event_id'] === $event_f) { echo htmlspecialchars(mb_strimwidth($ev['title'],0,28,'…')); break; }
                        ?></span>
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="rdd__menu">
                        <div class="rdd__item <?= $event_f===0?'rdd__item--active':'' ?>" onclick="rddPick('rdd-event','0','All Events',true)">All Events</div>
                        <?php foreach ($events_list as $ev): ?>
                        <div class="rdd__item <?= $event_f===$ev['event_id']?'rdd__item--active':'' ?>"
                             onclick="rddPick('rdd-event','<?= $ev['event_id'] ?>','<?= htmlspecialchars(addslashes(mb_strimwidth($ev['title'],0,28,'…'))) ?>',true)">
                            <?= htmlspecialchars(mb_strimwidth($ev['title'],0,32,'…')) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($search || $status_f !== 'all' || $event_f): ?>
                    <a href="registrations.php" class="c-btn c-btn--ghost">✕ Clear</a>
                <?php else: ?>
                    <button type="submit" class="c-btn c-btn--ghost">Search</button>
                <?php endif; ?>
            </form>
        </div>
        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="c-table">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <th>Student</th>
                        <th>Event</th>
                        <th style="width:120px">Category</th>
                        <th style="width:110px">Event Date</th>
                        <th style="width:110px">Status</th>
                        <th style="width:100px">Registered</th>
                        <th style="width:140px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($regs)): ?>
                    <tr><td colspan="8">
                        <div class="c-empty">
                            <svg width="60" height="60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <h3><?= ($search || $status_f !== 'all' || $event_f) ? 'No results found' : 'No registrations yet' ?></h3>
                            <p><?= ($search || $status_f !== 'all' || $event_f) ? 'Try adjusting your filters' : 'Registrations will appear here once students sign up for events' ?></p>
                        </div>
                    </td></tr>
                <?php else: foreach ($regs as $i => $r): ?>
                    <tr>
                        <!-- # -->
                        <td class="c-td-seq"><?= $pg['from'] + $i ?></td>

                        <!-- Student -->
                        <td>
                            <div class="r-student">
                                <div class="r-avatar">
                                    <?= strtoupper(mb_substr($r['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="r-student__name"><?= htmlspecialchars($r['full_name']) ?></div>
                                    <div class="r-student__email"><?= htmlspecialchars($r['email']) ?></div>
                                    <?php if ($r['student_id']): ?>
                                        <div class="r-student__id"><?= htmlspecialchars($r['student_id']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Event -->
                        <td>
                            <div class="r-event__title"><?= htmlspecialchars($r['event_title']) ?></div>
                            <div class="r-event__venue">
                                <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <?= htmlspecialchars($r['venue']) ?>
                            </div>
                        </td>

                        <!-- Category -->
                        <td>
                            <?php if ($r['category_name']): ?>
                                <span class="badge badge-blue"><?= htmlspecialchars($r['category_name']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--t3); font-size:.78rem;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Event date -->
                        <td class="r-date"><?= date('M j, Y', strtotime($r['date_time'])) ?></td>

                        <!-- Status badge -->
                        <td>
                            <?php
                            $badge_map = [
                                'confirmed' => ['class' => 'r-status r-status--confirmed', 'icon' => 'M9 12l2 2 4-4'],
                                'pending'   => ['class' => 'r-status r-status--pending',   'icon' => 'M12 8v4l3 3'],
                                'cancelled' => ['class' => 'r-status r-status--cancelled', 'icon' => 'M6 18L18 6M6 6l12 12'],
                            ];
                            $bm = $badge_map[$r['status']] ?? $badge_map['pending'];
                            ?>
                            <span class="<?= $bm['class'] ?>">
                                <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="<?= $bm['icon'] ?>"/>
                                </svg>
                                <?= ucfirst($r['status']) ?>
                            </span>
                        </td>

                        <!-- Registered date -->
                        <td class="r-date"><?= date('M j, Y', strtotime($r['registered_at'])) ?></td>

                        <!-- Actions -->
                        <td>
                            <div class="c-actions">
                                <button type="button" class="c-btn c-btn--edit"
                                        onclick="regOpenStatus(<?= $r['registration_id'] ?>, '<?= htmlspecialchars(addslashes($r['full_name'])) ?>', '<?= $r['status'] ?>')">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Status
                                </button>
                                <form method="POST" style="display:inline">
                                    <?= csrf_token_field() ?>
                                    <input type="hidden" name="action"  value="delete">
                                    <input type="hidden" name="reg_id"  value="<?= $r['registration_id'] ?>">
                                    <button type="submit" class="c-btn c-btn--del"
                                            onclick="return confirm('Remove this registration? This cannot be undone.')">
                                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pg['total_pages'] > 1): ?>
        <div class="c-pager">
            <span>Showing <?= $pg['from'] ?>–<?= $pg['to'] ?> of <?= number_format($pg['total']) ?></span>
            <div class="c-pager__btns">
                <?php for ($p = 1; $p <= $pg['total_pages']; $p++):
                    $active = $p === $pg['current'];
                    $link   = '?' . http_build_query(array_filter([
                        'q'        => $search,
                        'status'   => $status_f !== 'all' ? $status_f : null,
                        'event_id' => $event_f ?: null,
                        'page'     => $p,
                    ]));
                ?>
                    <a href="<?= $link ?>" class="c-pager__btn <?= $active ? 'c-pager__btn--active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>

<!-- ══ STATUS MODAL ══════════════════════════════════════════ -->
<div class="c-overlay" id="statusModal">
    <div class="c-modal" style="max-width:400px">
        <div class="c-modal__bar"></div>
        <div class="c-modal__head">
            <div>
                <div class="c-modal__title">Update Status</div>
                <div class="c-modal__sub">Change the registration status for <strong id="s_name" style="color:var(--t1)"></strong></div>
            </div>
            <button class="c-modal__close" onclick="catCloseModal('statusModal')">✕</button>
        </div>
        <form method="POST">
            <?= csrf_token_field() ?>
            <input type="hidden" name="action"  value="update_status">
            <input type="hidden" name="reg_id"  id="s_rid">
            <div class="c-modal__body">
                <div class="c-field">
                    <label class="c-label">New Status</label>
                    <div class="rdd rdd--full" id="rdd-modal-status">
                        <input type="hidden" name="status" id="s_status" value="pending">
                        <button type="button" class="rdd__btn" onclick="rddToggle('rdd-modal-status')">
                            <span id="rdd-modal-lbl">⏳ Pending</span>
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="rdd__menu">
                            <div class="rdd__item" onclick="rddPick('rdd-modal-status','confirmed','✓ Confirmed',false); updatePreview('confirmed')">
                                <span class="rdd__dot rdd__dot--green"></span>Confirmed
                            </div>
                            <div class="rdd__item" onclick="rddPick('rdd-modal-status','pending','⏳ Pending',false); updatePreview('pending')">
                                <span class="rdd__dot rdd__dot--gold"></span>Pending
                            </div>
                            <div class="rdd__item" onclick="rddPick('rdd-modal-status','cancelled','✕ Cancelled',false); updatePreview('cancelled')">
                                <span class="rdd__dot rdd__dot--red"></span>Cancelled
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Visual status preview -->
                <div class="r-status-preview">
                    <div class="r-sp__label">Preview</div>
                    <div id="s_preview" class="r-status r-status--pending">⏳ Pending</div>
                </div>
            </div>
            <div class="c-modal__foot">
                <button type="button" class="c-btn c-btn--ghost" onclick="catCloseModal('statusModal')">Cancel</button>
                <button type="submit" class="c-btn c-btn--primary">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Status
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/global.js"></script>
<script src="assets/js/admin.js"></script>
<script>
function regOpenStatus(id, name, status) {
    document.getElementById('s_rid').value        = id;
    document.getElementById('s_name').textContent = name;
    rddSetValue('rdd-modal-status', status,
        {confirmed:'✓ Confirmed', pending:'⏳ Pending', cancelled:'✕ Cancelled'}[status] || status,
        'rdd-modal-lbl');
    updatePreview(status);
    catOpenModal('statusModal');
}

function updatePreview(status) {
    var labels = { confirmed:'✓ Confirmed', pending:'⏳ Pending', cancelled:'✕ Cancelled' };
    var classes = { confirmed:'r-status--confirmed', pending:'r-status--pending', cancelled:'r-status--cancelled' };
    var el = document.getElementById('s_preview');
    el.textContent = labels[status] || status;
    el.className   = 'r-status ' + (classes[status] || 'r-status--pending');
}


</script>


<script>
/* ── Custom dropdown (rdd) helpers ── */
function rddToggle(id) {
    var el = document.getElementById(id);
    var isOpen = el.classList.contains('rdd--open');
    // close all
    document.querySelectorAll('.rdd--open').forEach(function(d){ d.classList.remove('rdd--open'); });
    if (!isOpen) el.classList.add('rdd--open');
}
function rddPick(id, value, label, submitForm) {
    var el = document.getElementById(id);
    el.querySelector('input[type=hidden]').value = value;
    el.querySelector('.rdd__btn span').textContent = label;
    el.querySelectorAll('.rdd__item').forEach(function(i){ i.classList.remove('rdd__item--active'); });
    el.classList.remove('rdd--open');
    if (submitForm) el.closest('form').submit();
}
function rddSetValue(id, value, label, lblId) {
    document.getElementById(id).querySelector('input[type=hidden]').value = value;
    document.getElementById(lblId || id+'-lbl').textContent = label;
}
// Close on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.rdd')) {
        document.querySelectorAll('.rdd--open').forEach(function(d){ d.classList.remove('rdd--open'); });
    }
});
</script>

</body>
</html>