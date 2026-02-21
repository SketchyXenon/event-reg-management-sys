<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
admin_only();

$admin = current_user();
$msg   = '';
$msg_type = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'update_role') {
    $uid  = (int)$_POST['user_id'];
    $role = in_array($_POST['role'], ['admin','student']) ? $_POST['role'] : 'student';
    if ($uid !== (int)$admin['id']) {
      $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);
      $msg = 'User role updated.';
      $msg_type = 'success';
    } else {
      $msg = 'You cannot change your own role.';
      $msg_type = 'error';
    }
  }

  if ($action === 'toggle_status') {
    $uid = (int)$_POST['user_id'];
    if ($uid !== (int)$admin['id']) {
      $pdo->prepare(
        "UPDATE users SET is_active = NOT is_active WHERE id=?"
      )->execute([$uid]);
      $msg = 'User status updated.';
      $msg_type = 'success';
    } else {
      $msg = 'You cannot deactivate yourself.';
      $msg_type = 'error';
    }
  }

  if ($action === 'delete') {
    $uid = (int)$_POST['user_id'];
    if ($uid !== (int)$admin['id']) {
      $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
      $msg = 'User deleted.';
      $msg_type = 'success';
    } else {
      $msg = 'You cannot delete yourself.';
      $msg_type = 'error';
    }
  }
}

// ── Fetch users ────────────────────────────────────────────
$filter = $_GET['role'] ?? 'all';
$sql = "SELECT u.*, COUNT(r.id) AS reg_count
        FROM users u
        LEFT JOIN registrations r ON r.user_id = u.id
        " . ($filter !== 'all' ? "WHERE u.role='" . ($filter==='admin'?'admin':'student') . "'" : '') . "
        GROUP BY u.id ORDER BY u.created_at DESC";
$users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$total_admin   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$total_student = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Users — ERMS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

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
    <a href="users.php" class="nav-item active">
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
    <div class="topbar-title">Manage Users</div>
    <div class="topbar-subtitle"><?= count($users) ?> users shown</div>
  </div>
  <div class="topbar-spacer"></div>
  <div class="topbar-search">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" id="userSearch" placeholder="Search users…">
  </div>
</div>

<!-- MAIN -->
<main class="main">
  <div class="page-content">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg_type ?>" data-auto-dismiss><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Summary Row -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
      <div class="stat-card blue">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?= $total_admin + $total_student ?></div>
      </div>
      <div class="stat-card gold">
        <div class="stat-label">Students</div>
        <div class="stat-value"><?= $total_student ?></div>
      </div>
      <div class="stat-card green">
        <div class="stat-label">Administrators</div>
        <div class="stat-value"><?= $total_admin ?></div>
      </div>
    </div>

    <!-- Role Filter Tabs -->
    <div class="tabs" style="margin-bottom:16px">
      <a href="?role=all" class="tab <?= $filter==='all'?'active':'' ?>">All Users</a>
      <a href="?role=student" class="tab <?= $filter==='student'?'active':'' ?>">Students</a>
      <a href="?role=admin" class="tab <?= $filter==='admin'?'active':'' ?>">Admins</a>
    </div>

    <div class="card">
      <div class="table-wrapper">
        <table id="usersTable">
          <thead>
            <tr>
              <th>#</th>
              <th>Full Name</th>
              <th>Student ID</th>
              <th>Email</th>
              <th>Role</th>
              <th>Events</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($users)): ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td class="td-mono"><?= $u['id'] ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px">
                      <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent-blue),var(--accent-blue-l));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:0.75rem;color:white;flex-shrink:0">
                        <?= strtoupper(substr($u['full_name'],0,1)) ?>
                      </div>
                      <span class="td-primary"><?= htmlspecialchars($u['full_name']) ?></span>
                    </div>
                  </td>
                  <td class="td-mono"><?= htmlspecialchars($u['student_id'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                  <td style="text-align:center"><?= $u['reg_count'] ?></td>
                  <td>
                    <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                      <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                  <td>
                    <div style="display:flex;gap:6px">
                      <!-- Change role -->
                      <button class="btn btn-ghost btn-sm btn-icon" title="Change Role"
                        onclick='openRoleModal(<?= json_encode(["id"=>$u["id"],"name"=>$u["full_name"],"role"=>$u["role"]]) ?>)'>
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                      </button>
                      <!-- Toggle active -->
                      <form method="POST" style="display:inline">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm btn-icon" title="<?= $u['is_active']?'Deactivate':'Activate' ?>">
                          <?php if ($u['is_active']): ?>
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                          <?php else: ?>
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                          <?php endif; ?>
                        </button>
                      </form>
                      <!-- Delete -->
                      <?php if ($u['id'] != $admin['id']): ?>
                        <form method="POST" style="display:inline">
                          <?= csrf_token_field() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete"
                            data-confirm="Delete user '<?= htmlspecialchars($u['full_name'],ENT_QUOTES) ?>'?">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No users found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<!-- ROLE MODAL -->
<div class="modal-overlay" id="roleModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Change User Role</div>
      <button class="modal-close" onclick="closeModal('roleModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrf_token_field() ?>
      <input type="hidden" name="action" value="update_role">
      <input type="hidden" name="user_id" id="role_user_id">
      <div class="modal-body">
        <p style="color:var(--text-secondary);margin-bottom:16px;font-size:0.9rem">
          Changing role for: <strong id="role_user_name" style="color:var(--text-primary)"></strong>
        </p>
        <div class="form-group">
          <label class="form-label">New Role</label>
          <select name="role" id="role_select" class="form-control">
            <option value="student">Student</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('roleModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Role</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/admin.js"></script>
<script>
function openRoleModal(user) {
  document.getElementById('role_user_id').value    = user.id;
  document.getElementById('role_user_name').textContent = user.name;
  document.getElementById('role_select').value     = user.role;
  openModal('roleModal');
}
filterTable('userSearch','usersTable');
</script>
</body>
</html>