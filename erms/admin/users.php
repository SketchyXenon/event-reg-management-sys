<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
admin_only();

$admin = current_user();
$msg   = '';
$msg_type = '';

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_role') {
        $uid  = (int)$_POST['user_id'];
        $role = in_array($_POST['role'], ['admin','student']) ? $_POST['role'] : 'student';
        if ($uid !== (int)($_SESSION['user_id'] ?? 0)) {
            $pdo->prepare("UPDATE users SET role=? WHERE user_id=?")->execute([$role, $uid]);
            $msg = 'User role updated.';
            $msg_type = 'success';
        } else {
            $msg = 'You cannot change your own role.';
            $msg_type = 'error';
        }
    }

    if ($action === 'toggle_status') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== (int)($_SESSION['user_id'] ?? 0)) {
            $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id=?")->execute([$uid]);
            $msg = 'User status updated.';
            $msg_type = 'success';
        } else {
            $msg = 'You cannot deactivate yourself.';
            $msg_type = 'error';
        }
    }

    if ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== (int)($_SESSION['user_id'] ?? 0)) {
            $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$uid]);
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

$users = $pdo->query("
    SELECT u.user_id, u.full_name, u.student_id, u.email, u.role, u.is_active, u.created_at,
           COUNT(r.registration_id) AS reg_count
    FROM users u
    LEFT JOIN registrations r ON r.user_id = u.user_id
    " . ($filter !== 'all' ? "WHERE u.role='" . ($filter==='admin'?'admin':'student') . "'" : '') . "
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_admin   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$total_student = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Users — ERMS Admin</title>
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
    <a href="users.php" class="nav-item active">Manage Users</a>
    <a href="registrations.php" class="nav-item">Registrations</a>
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

<!-- TOPBAR -->
<div class="topbar">
  <div>
    <div class="topbar-title">Manage Users</div>
    <div class="topbar-subtitle"><?= count($users) ?> users shown</div>
  </div>
  <div class="topbar-spacer"></div>
  <div class="topbar-search">
    <input type="text" id="userSearch" placeholder="Search users…">
  </div>
</div>

<!-- MAIN -->
<main class="main">
  <div class="page-content">
    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
      <div class="stat-card blue"><div class="stat-label">Total Users</div><div class="stat-value"><?= $total_admin + $total_student ?></div></div>
      <div class="stat-card gold"><div class="stat-label">Students</div><div class="stat-value"><?= $total_student ?></div></div>
      <div class="stat-card green"><div class="stat-label">Administrators</div><div class="stat-value"><?= $total_admin ?></div></div>
    </div>

    <!-- Role Filter -->
    <div class="tabs" style="margin-bottom:16px">
      <a href="?role=all" class="tab <?= $filter==='all'?'active':'' ?>">All Users</a>
      <a href="?role=student" class="tab <?= $filter==='student'?'active':'' ?>">Students</a>
      <a href="?role=admin" class="tab <?= $filter==='admin'?'active':'' ?>">Admins</a>
    </div>

    <!-- Users Table -->
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
                  <td><?= $u['user_id'] ?></td>
                  <td><?= htmlspecialchars($u['full_name']) ?></td>
                  <td><?= htmlspecialchars($u['student_id'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td><span class="badge"><?= ucfirst($u['role']) ?></span></td>
                  <td style="text-align:center"><?= $u['reg_count'] ?></td>
                  <td><span class="badge <?= $u['is_active'] ? 'badge-active':'badge-inactive' ?>"><?= $u['is_active'] ? 'Active':'Inactive' ?></span></td>
                  <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                  <td>
                    <div style="display:flex;gap:6px">
                      <!-- Role Modal -->
                      <button class="btn btn-ghost btn-sm" onclick='openRoleModal(<?= json_encode(["id"=>$u["user_id"],"name"=>$u["full_name"],"role"=>$u["role"]]) ?>)'>Role</button>
                      <!-- Toggle Status -->
                      <form method="POST" style="display:inline">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                        <button type="submit"><?= $u['is_active'] ? 'Deactivate':'Activate' ?></button>
                      </form>
                      <!-- Delete -->
                      <?php if ($u['user_id'] != $admin['user_id']): ?>
                      <form method="POST" style="display:inline">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                        <button type="submit">Delete</button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-3)">No users found.</td></tr>
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
        <p>Changing role for: <strong id="role_user_name"></strong></p>
        <select name="role" id="role_select">
          <option value="student">Student</option>
          <option value="admin">Administrator</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeModal('roleModal')">Cancel</button>
        <button type="submit">Save Role</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRoleModal(user){
  document.getElementById('role_user_id').value = user.id;
  document.getElementById('role_user_name').textContent = user.name;
  document.getElementById('role_select').value = user.role;
  openModal('roleModal');
}
filterTable('userSearch','usersTable');
</script>

</body>
</html>