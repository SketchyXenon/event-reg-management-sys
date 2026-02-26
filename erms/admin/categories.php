<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
require_once __DIR__ . '/../backend/csrf_helper.php';

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

$msg      = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        }
    }
}

// ── Fetch categories with event count ──────────────────────
$categories = $pdo->query(
    "SELECT ec.*, COUNT(e.event_id) AS event_count
     FROM event_categories ec
     LEFT JOIN events e ON e.category_id = ec.category_id
     GROUP BY ec.category_id
     ORDER BY ec.category_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);
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

<!-- ══ SIDEBAR ═══════════════════════════════════════════════ -->
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
    <a href="registrations.php" class="nav-item">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
      Registrations
    </a>
    <a href="categories.php" class="nav-item active">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
      Categories
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
      <div class="user-info">
        <div class="name"><?= htmlspecialchars($full_name) ?></div>
        <div class="role">Administrator</div>
      </div>
    </div>
    <a href="../backend/logout.php" class="logout-btn">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Sign Out
    </a>
  </div>
</aside>

<!-- ══ TOPBAR ════════════════════════════════════════════════ -->
<div class="topbar">
  <button id="menuToggle" class="topbar-btn" style="display:none">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
  </button>
  <div>
    <div class="topbar-title">Event Categories</div>
    <div class="topbar-subtitle"><?= count($categories) ?> categories total</div>
  </div>
  <div class="topbar-spacer"></div>
  <div class="topbar-actions">
    <button class="btn btn-primary" onclick="openModal('createModal')">
      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      New Category
    </button>
  </div>
  <button class="theme-toggle-btn" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>
</div>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<main class="main">
  <div class="page-content">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg_type ?>" data-auto-dismiss><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Categories Table -->
    <div class="table-card">
      <div class="table-header">
        <div class="table-search">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" id="catSearch" placeholder="Search categories…">
        </div>
      </div>

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
    </div>

  </div>
</main>

<!-- ══ CREATE MODAL ══════════════════════════════════════════ -->
<div class="modal-overlay" id="createModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">New Category</h2>
      <button class="modal-close" onclick="closeModal('createModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrf_token_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="new_name">Category Name <span style="color:var(--red)">*</span></label>
          <input type="text" name="category_name" id="new_name" class="form-control"
            placeholder="e.g. Academic, Sports, Arts…" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="new_desc">Description</label>
          <textarea name="description" id="new_desc" class="form-control" rows="3"
            placeholder="Brief description of this category (optional)"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('createModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Category</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT MODAL ════════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Edit Category</h2>
      <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrf_token_field() ?>
      <input type="hidden" name="action"      value="edit">
      <input type="hidden" name="category_id" id="edit_category_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="edit_name">Category Name <span style="color:var(--red)">*</span></label>
          <input type="text" name="category_name" id="edit_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_desc">Description</label>
          <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/global.js"></script>
<script src="assets/js/admin.js"></script>
<script>
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

filterTable('catSearch', 'catsTable');
</script>
</body>
</html>