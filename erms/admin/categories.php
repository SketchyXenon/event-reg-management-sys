<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
require_once __DIR__ . '/../backend/csrf_helper.php';
require_once __DIR__ . '/../backend/paginate.php';
admin_only();

<<<<<<< HEAD
$admin    = current_user();
=======
require_login('../login.php');
admin_only();

$admin     = $_SESSION;
$full_name = $_SESSION['full_name'];

// ── TODO: Implement category CRUD ─────────────────────────
// Your colleague needs to implement these three actions:
//
// ACTION: create
//   INSERT INTO event_categories (category_name, description) VALUES (?, ?)
//
// ACTION: edit
//   UPDATE event_categories SET category_name=?, description=? WHERE category_id=?
//
// ACTION: delete
//   Check no events reference this category first:
//   SELECT COUNT(*) FROM events WHERE category_id = ?
//   If 0 → DELETE FROM event_categories WHERE category_id=?
//   Else  → show error "Cannot delete — events are using this category"

>>>>>>> 618b50c91f7546823751c359eed8b48033ef3a92
$msg      = '';
$msg_type = '';

/* ── POST ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
<<<<<<< HEAD
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name']        ?? '');
    $desc   = trim($_POST['description'] ?? '');

    if (in_array($action, ['create', 'update'])) {
        if ($name === '') {
            $msg = 'Category name is required.'; $msg_type = 'error';
        } else {
            if ($action === 'create') {
                $dup = $pdo->prepare("SELECT 1 FROM event_categories WHERE category_name = ?");
                $dup->execute([$name]);
                if ($dup->fetch()) {
                    $msg = "A category named \"{$name}\" already exists."; $msg_type = 'error';
                } else {
                    $pdo->prepare("INSERT INTO event_categories (category_name, description) VALUES (?, ?)")->execute([$name, $desc]);
                    $msg = 'Category created successfully.'; $msg_type = 'success';
                }
            } else {
                $cid = (int)($_POST['category_id'] ?? 0);
                $dup = $pdo->prepare("SELECT 1 FROM event_categories WHERE category_name = ? AND category_id != ?");
                $dup->execute([$name, $cid]);
                if ($dup->fetch()) {
                    $msg = "A category named \"{$name}\" already exists."; $msg_type = 'error';
                } else {
                    $pdo->prepare("UPDATE event_categories SET category_name = ?, description = ? WHERE category_id = ?")->execute([$name, $desc, $cid]);
                    $msg = 'Category updated successfully.'; $msg_type = 'success';
                }
            }
        }
    } elseif ($action === 'delete') {
        $cid  = (int)($_POST['category_id'] ?? 0);
        $used = $pdo->prepare("SELECT COUNT(*) FROM events WHERE category_id = ?");
        $used->execute([$cid]);
        if ($used->fetchColumn() > 0) {
            $msg = 'Cannot delete: this category is still assigned to one or more events.'; $msg_type = 'error';
        } else {
            $pdo->prepare("DELETE FROM event_categories WHERE category_id = ?")->execute([$cid]);
            $msg = 'Category deleted.'; $msg_type = 'success';
=======
    csrf_verify(); // handles failure internally with die()

    $action = $_POST['action'] ?? '';

    // ══ ACTION: create ════════════════════════════════════════
    if ($action === 'create') {

        $category_name = trim($_POST['category_name'] ?? '');
        $description   = trim($_POST['description']   ?? '');

        if ($category_name === '') {
            $msg = 'Category name is required.'; $msg_type = 'error';
        } elseif (mb_strlen($category_name) > 100) {
            $msg = 'Category name must not exceed 100 characters.'; $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT category_id FROM event_categories WHERE category_name = ?");
            $stmt->execute([$category_name]);

            if ($stmt->fetch()) {
                $msg = 'A category with that name already exists.'; $msg_type = 'error';
            } else {
                $stmt = $pdo->prepare("INSERT INTO event_categories (category_name, description) VALUES (?, ?)");
                $stmt->execute([$category_name, $description ?: null]);
                $msg = 'Category created successfully.'; $msg_type = 'success';
            }
        }

    // ══ ACTION: edit ══════════════════════════════════════════
    } elseif ($action === 'edit') {

        $category_id   = (int) ($_POST['category_id']   ?? 0);
        $category_name = trim($_POST['category_name'] ?? '');
        $description   = trim($_POST['description']   ?? '');

        if ($category_id <= 0) {
            $msg = 'Invalid category.'; $msg_type = 'error';
        } elseif ($category_name === '') {
            $msg = 'Category name is required.'; $msg_type = 'error';
        } elseif (mb_strlen($category_name) > 100) {
            $msg = 'Category name must not exceed 100 characters.'; $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT category_id FROM event_categories WHERE category_name = ? AND category_id != ?");
            $stmt->execute([$category_name, $category_id]);

            if ($stmt->fetch()) {
                $msg = 'Another category with that name already exists.'; $msg_type = 'error';
            } else {
                $stmt = $pdo->prepare("UPDATE event_categories SET category_name = ?, description = ? WHERE category_id = ?");
                $stmt->execute([$category_name, $description ?: null, $category_id]);
                $msg = 'Category updated successfully.'; $msg_type = 'success';
            }
        }

    // ══ ACTION: delete ════════════════════════════════════════
    } elseif ($action === 'delete') {

        $category_id = (int) ($_POST['category_id'] ?? 0);

        if ($category_id <= 0) {
            $msg = 'Invalid category.'; $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE category_id = ?");
            $stmt->execute([$category_id]);

            if ((int) $stmt->fetchColumn() > 0) {
                $msg = 'Cannot delete — events are using this category.'; $msg_type = 'error';
            } else {
                $stmt = $pdo->prepare("DELETE FROM event_categories WHERE category_id = ?");
                $stmt->execute([$category_id]);
                $msg = 'Category deleted successfully.'; $msg_type = 'success';
            }
>>>>>>> 618b50c91f7546823751c359eed8b48033ef3a92
        }
    }
}

/* ── Query ────────────────────────────────────────────────── */
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$where  = $search ? "WHERE ec.category_name LIKE ? OR ec.description LIKE ?" : '';
$params = $search ? ["%{$search}%", "%{$search}%"] : [];

$cnt = $pdo->prepare("SELECT COUNT(*) FROM event_categories ec {$where}");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pg    = paginate($total, 15, $page);

$rows = $pdo->prepare(
    "SELECT ec.*, COUNT(e.event_id) AS event_count
     FROM event_categories ec
     LEFT JOIN events e ON e.category_id = ec.category_id
     {$where}
     GROUP BY ec.category_id
     ORDER BY ec.category_name
     LIMIT {$pg['per_page']} OFFSET {$pg['offset']}"
);
$rows->execute($params);
$cats = $rows->fetchAll(PDO::FETCH_ASSOC);

$total_events_assigned = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE category_id IS NOT NULL")->fetchColumn();
$in_use_count          = (int)$pdo->query("SELECT COUNT(DISTINCT category_id) FROM events WHERE category_id IS NOT NULL")->fetchColumn();

$accent_colors = ['#4a7ab5','#c9a84c','#4e9b72','#c45c5c','#9b7bc4','#4ab5a8'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Categories — ERMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="has-sidebar">

<?php include 'partials/sidebar.php'; ?>

<!-- TOPBAR -->
<div class="topbar">
    <button id="menuToggle" class="topbar-btn" style="display:none"
            onclick="document.querySelector('.sidebar').classList.toggle('open')">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
    <div>
        <div class="topbar-title">Event Categories</div>
        <div class="topbar-subtitle">Organise events by type &amp; theme</div>
    </div>
    <div class="topbar-spacer"></div>
    <div class="topbar-actions">
        <button class="btn btn-primary" onclick="catOpenModalFn('createModal')">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
            New Category
        </button>
    </div>
    <button class="theme-toggle-btn" id="themeToggle"><span id="themeIcon">☀️</span></button>
</div>

<!-- MAIN -->
<main class="main">
<div class="page-content">

<?php if ($msg): ?>
<div class="c-alert c-alert--<?= $msg_type ?>">
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

<!-- Stat cards -->
<div class="c-stats">
    <div class="c-stat c-stat--gold">
        <div class="c-stat__label">Total Categories</div>
        <div class="c-stat__value"><?= number_format($total) ?></div>
        <div class="c-stat__sub">Active classification groups</div>
        <div class="c-stat__icon">
            <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
            </svg>
        </div>
    </div>
    <div class="c-stat c-stat--blue">
        <div class="c-stat__label">Events Categorised</div>
        <div class="c-stat__value"><?= number_format($total_events_assigned) ?></div>
        <div class="c-stat__sub">Events assigned to a category</div>
        <div class="c-stat__icon">
            <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
    </div>
    <div class="c-stat c-stat--green">
        <div class="c-stat__label">In Use</div>
        <div class="c-stat__value"><?= number_format($in_use_count) ?></div>
        <div class="c-stat__sub">Categories with active events</div>
        <div class="c-stat__icon">
            <svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>
</div>

<<<<<<< HEAD
<!-- Table card -->
<div class="c-card">
    <!-- Card header -->
    <div class="c-card__head">
        <div>
            <div class="c-card__title">All Categories</div>
            <div class="c-card__sub">
                <?= number_format($total) ?> categor<?= $total !== 1 ? 'ies' : 'y' ?>
                <?php if ($search): ?>&mdash; results for &ldquo;<?= htmlspecialchars($search) ?>&rdquo;<?php endif; ?>
            </div>
        </div>
        <form method="GET" class="c-filter">
            <div class="c-search">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search categories…">
            </div>
            <?php if ($search): ?>
                <a href="categories.php" class="c-btn c-btn--ghost">✕ Clear</a>
            <?php else: ?>
                <button type="submit" class="c-btn c-btn--ghost">Search</button>
            <?php endif; ?>
        </form>
=======
      <table class="data-table" id="catsTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Category Name</th>
            <th>Description</th>
            <th>Events</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($categories)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:48px">
              No categories yet. Create one to get started.
            </td></tr>
          <?php else: ?>
            <?php foreach ($categories as $i => $cat): ?>
              <tr>
                <td style="color:var(--text-muted);font-family:var(--ff-m);font-size:0.75rem"><?= $i + 1 ?></td>
                <td>
                  <span class="badge badge-blue"><?= htmlspecialchars($cat['category_name']) ?></span>
                </td>
                <td style="color:var(--text-secondary);font-size:0.85rem;max-width:280px">
                  <?= htmlspecialchars($cat['description'] ?? '—') ?>
                </td>
                <td>
                  <span class="badge badge-neutral"><?= $cat['event_count'] ?> event<?= $cat['event_count'] != 1 ? 's' : '' ?></span>
                </td>
                <td>
                  <div class="action-btns">
                    <button class="btn-action edit"
                     data-id="<?= $cat['category_id'] ?>"
                     data-name="<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>"
                     data-desc="<?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES) ?>">
                      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                      Edit
                    </button>
                    <?php if ($cat['event_count'] == 0): ?>
                      <form method="POST" style="display:inline">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="action"      value="delete">
                        <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                        <button type="submit" class="btn-action delete"
                          data-confirm="Delete '<?= htmlspecialchars($cat['category_name']) ?>'? This cannot be undone.">
                          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                          Delete
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="badge badge-neutral" title="Remove events first to delete this category">In use</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
>>>>>>> 618b50c91f7546823751c359eed8b48033ef3a92
    </div>

    <!-- Table -->
    <div style="overflow-x:auto">
        <table class="c-table">
            <thead>
                <tr>
                    <th style="width:48px">#</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th style="width:110px;text-align:center">Events</th>
                    <th style="width:165px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($cats)): ?>
                <tr><td colspan="5">
                    <div class="c-empty">
                        <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <h3><?= $search ? 'No results found' : 'No categories yet' ?></h3>
                        <p><?= $search ? 'Try a different search term' : 'Create your first category to start organising events' ?></p>
                        <?php if (!$search): ?>
                            <button class="c-btn c-btn--primary" onclick="catOpenModalFn('createModal')">
                                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create First Category
                            </button>
                        <?php endif; ?>
                    </div>
                </td></tr>
            <?php else: foreach ($cats as $i => $c):
                $color  = $accent_colors[$i % count($accent_colors)];
                $cbg    = $color . '1e';
                $cbdr   = $color . '44';
            ?>
                <tr>
                    <td class="c-td-seq"><?= $pg['from'] + $i ?></td>

                    <td>
                        <div class="c-name-cell">
                            <div class="c-icon" style="background:<?= $cbg ?>;border-color:<?= $cbdr ?>">
                                <svg width="16" height="16" fill="none" stroke="<?= $color ?>" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="c-name"><?= htmlspecialchars($c['category_name']) ?></div>
                                <div class="c-id">ID #<?= $c['category_id'] ?></div>
                            </div>
                        </div>
                    </td>

                    <td class="c-desc">
                        <?php if ($c['description']): ?>
                            <?= htmlspecialchars(mb_strimwidth($c['description'], 0, 90, '…')) ?>
                        <?php else: ?>
                            <span class="c-desc--empty">No description added</span>
                        <?php endif; ?>
                    </td>

                    <td style="text-align:center">
                        <div class="c-count <?= (int)$c['event_count'] === 0 ? 'c-count--zero' : '' ?>">
                            <?= $c['event_count'] ?>
                        </div>
                        <div class="c-count-lbl">event<?= $c['event_count'] != 1 ? 's' : '' ?></div>
                    </td>

                    <td>
                        <div class="c-actions">
                            <!-- Single Edit button — always visible -->
                            <button type="button" class="c-btn c-btn--edit"
                                    onclick="catEditFn(<?= $c['category_id'] ?>, <?= htmlspecialchars(json_encode($c['category_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($c['description'] ?? ''), ENT_QUOTES) ?>)">
                                <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                                Edit
                            </button>
                            <?php if ((int)$c['event_count'] === 0): ?>
                                <!-- Delete only when not in use -->
                                <form method="POST" style="display:inline">
                                    <?= csrf_token_field() ?>
                                    <input type="hidden" name="action"      value="delete">
                                    <input type="hidden" name="category_id" value="<?= $c['category_id'] ?>">
                                    <button type="submit" class="c-btn c-btn--del"
                                            onclick="return confirm('Delete \'<?= htmlspecialchars(addslashes($c['category_name'])) ?>\'? This cannot be undone.')">
                                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            <?php else: ?>
                                <!-- In use pill — not clickable, just informational -->
                                <span style="display:inline-flex;align-items:center;gap:5px;font-size:.72rem;color:#505870;background:rgba(92,100,120,.1);border:1px solid rgba(255,255,255,.06);border-radius:20px;padding:4px 10px;">
                                    <svg width="9" height="9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    In use
                                </span>
                            <?php endif; ?>
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
                $link   = '?' . http_build_query(array_filter(['q' => $search, 'page' => $p]));
            ?>
                <a href="<?= $link ?>" class="c-pager__btn <?= $active ? 'c-pager__btn--active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</div>
</main>

<!-- ══ CREATE MODAL ══════════════════════════════════════════ -->
<div class="c-overlay" id="createModal">
    <div class="c-modal">
        <div class="c-modal__bar"></div>
        <div class="c-modal__head">
            <div>
                <div class="c-modal__title">New Category</div>
                <div class="c-modal__sub">Create a new event classification group</div>
            </div>
            <button class="c-modal__close" onclick="catCloseFn('createModal')">✕</button>
        </div>
        <form method="POST">
            <?= csrf_token_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="c-modal__body">
                <div class="c-field">
                    <label class="c-label" for="c_name">Name <span class="c-req">*</span></label>
                    <input type="text" id="c_name" name="name" class="c-input" required
                           placeholder="e.g. Sports, Academic, Cultural…" autocomplete="off">
                    <div class="c-hint">This name will appear on event cards and filters.</div>
                </div>
                <div class="c-field">
                    <label class="c-label" for="c_desc">Description</label>
                    <textarea id="c_desc" name="description" class="c-input c-textarea" rows="3"
                              placeholder="Brief description of what this category covers…"></textarea>
                </div>
            </div>
            <div class="c-modal__foot">
                <button type="button" class="c-btn c-btn--ghost" onclick="catCloseFn('createModal')">Cancel</button>
                <button type="submit" class="c-btn c-btn--primary">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ EDIT MODAL ════════════════════════════════════════════ -->
<div class="c-overlay" id="editModal">
    <div class="c-modal">
        <div class="c-modal__bar"></div>
        <div class="c-modal__head">
            <div>
                <div class="c-modal__title">Edit Category</div>
                <div class="c-modal__sub">Update this category's name and description</div>
            </div>
            <button class="c-modal__close" onclick="catCloseFn('editModal')">✕</button>
        </div>
        <form method="POST">
            <?= csrf_token_field() ?>
            <input type="hidden" name="action"      value="update">
            <input type="hidden" name="category_id" id="e_id">
            <div class="c-modal__body">
                <div class="c-field">
                    <label class="c-label" for="e_name">Name <span class="c-req">*</span></label>
                    <input type="text" id="e_name" name="name" class="c-input" required>
                </div>
                <div class="c-field">
                    <label class="c-label" for="e_desc">Description</label>
                    <textarea id="e_desc" name="description" class="c-input c-textarea" rows="3"></textarea>
                </div>
            </div>
            <div class="c-modal__foot">
                <button type="button" class="c-btn c-btn--ghost" onclick="catCloseFn('editModal')">Cancel</button>
                <button type="submit" class="c-btn c-btn--primary">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
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
<<<<<<< HEAD
/* catEditFn — inline so it works even with old global.js */
function catEditFn(id, name, desc) {
    var idEl   = document.getElementById('e_id');
    var nameEl = document.getElementById('e_name');
    var descEl = document.getElementById('e_desc');
    if (idEl)   idEl.value   = id;
    if (nameEl) nameEl.value = name;
    if (descEl) descEl.value = desc;

    var overlay = document.getElementById('editModal');
    if (!overlay) return;
    overlay.style.display = 'flex';
    requestAnimationFrame(function() {
        overlay.classList.add('c-overlay--open');
    });
}
=======
document.querySelectorAll('.btn-action.edit').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.getElementById('edit_category_id').value = btn.dataset.id;
    document.getElementById('edit_name').value        = btn.dataset.name;
    document.getElementById('edit_desc').value        = btn.dataset.desc;

    var freshToken = document.querySelector('#createModal input[name="_csrf_token"]');
    var editToken  = document.querySelector('#editModal input[name="_csrf_token"]');
    if (freshToken && editToken) editToken.value = freshToken.value;

    openModal('editModal');
  });
});
>>>>>>> 618b50c91f7546823751c359eed8b48033ef3a92

/* catOpenModalFn — for New Category button */
function catOpenModalFn(id) {
    var overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.style.display = 'flex';
    requestAnimationFrame(function() {
        overlay.classList.add('c-overlay--open');
    });
}

/* catCloseFn — for close/cancel buttons */
function catCloseFn(id) {
    var overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.remove('c-overlay--open');
    setTimeout(function() { overlay.style.display = 'none'; }, 220);
}

/* Wire backdrop click */
document.querySelectorAll('.c-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) catCloseFn(el.id);
    });
});
/* Wire Escape key */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.c-overlay--open').forEach(function(el) {
            catCloseFn(el.id);
        });
    }
});
</script>
 
</body>
</html>