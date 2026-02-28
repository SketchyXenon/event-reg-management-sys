<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
require_once __DIR__ . '/../backend/csrf_helper.php';
admin_only();
$admin   = current_user();
if (!isset($admin['user_id']) && isset($admin['id'])) $admin['user_id'] = $admin['id'];
$self_id = (int)($admin['user_id'] ?? $_SESSION['user_id'] ?? 0);

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);
    if ($action === 'update_role') {
        $role = in_array($_POST['role'] ?? '', ['admin','student']) ? $_POST['role'] : 'student';
        if ($uid !== $self_id) { $pdo->prepare("UPDATE users SET role=? WHERE user_id=?")->execute([$role, $uid]); $msg = 'User role updated.'; $msg_type = 'success'; }
        else { $msg = 'You cannot change your own role.'; $msg_type = 'error'; }
    }
    if ($action === 'toggle_status') {
        if ($uid !== $self_id) { $pdo->prepare("UPDATE users SET is_active=NOT is_active WHERE user_id=?")->execute([$uid]); $msg = 'User status updated.'; $msg_type = 'success'; }
        else { $msg = 'You cannot deactivate yourself.'; $msg_type = 'error'; }
    }
    if ($action === 'delete') {
        if ($uid !== $self_id) { $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$uid]); $msg = 'User deleted.'; $msg_type = 'success'; }
        else { $msg = 'You cannot delete yourself.'; $msg_type = 'error'; }
    }
}

$role_f = $_GET['role'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$where  = []; $params = [];
if ($role_f !== 'all') { $where[] = "u.role=?"; $params[] = $role_f; }
if ($search)           { $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
$ws = $where ? 'WHERE '.implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT u.user_id, u.full_name, u.student_id, u.email, u.role, u.is_active, u.created_at, COUNT(r.registration_id) AS reg_count FROM users u LEFT JOIN registrations r ON r.user_id = u.user_id $ws GROUP BY u.user_id ORDER BY u.created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stat_total   = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stat_student = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$stat_admin   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$stat_active  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Users — ERMS Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/global.css">
<link rel="stylesheet" href="assets/css/admin.css">
<style>
/* ── Users page: theme-aware overrides (ensures correct rendering) ── */
.u-tabs {
  display: flex; gap: 4px;
  padding: 10px 20px;
  border-bottom: 1px solid var(--bdr);
  background: var(--bg3);
}
.u-tab {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 6px 16px; border-radius: 6px;
  font-size: .8rem; font-weight: 600;
  text-decoration: none;
  color: var(--t3);
  border: 1px solid transparent;
  transition: all .18s;
}
.u-tab:hover { color: var(--t2); background: var(--bgh); }
.u-tab--active {
  background: rgba(74,122,181,.15);
  color: #6a96cc;
  border-color: rgba(74,122,181,.3);
}
.u-user__name  { font-weight: 600; font-size: .85rem; color: var(--t1); }
.u-user__email { font-size: .72rem; color: var(--t3); margin-top: 1px; }
.u-user__id    { font-family: var(--fm); font-size: .72rem; color: var(--t3); }
.u-role {
  display: inline-flex; align-items: center;
  font-size: .72rem; font-weight: 600;
  padding: 3px 9px; border-radius: 20px;
  border: 1px solid transparent; white-space: nowrap;
}
.u-role--student { background: rgba(74,122,181,.12);   color: #6a96cc; border-color: rgba(74,122,181,.25); }
.u-role--admin   { background: rgba(201,120,76,.12);   color: #d4956a; border-color: rgba(201,120,76,.25); }
.u-active {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .72rem; font-weight: 600;
  padding: 3px 9px; border-radius: 20px;
  border: 1px solid transparent;
}
.u-active--on  { background: rgba(78,155,114,.13); color: #6ec49a; border-color: rgba(78,155,114,.28); }
.u-active--off { background: rgba(92,100,120,.11); color: var(--t3); border-color: var(--bdr); }
.u-regs {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 24px; height: 24px; padding: 0 7px;
  border-radius: 12px; font-size: .72rem; font-weight: 700;
  font-family: var(--fm);
  background: var(--bgh); color: var(--t3);
}
.u-regs--pos { background: rgba(74,122,181,.12); color: #6a96cc; }
.u-role-preview {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; border-radius: 8px;
  background: var(--bgh); border: 1px solid var(--bdr);
  margin-bottom: 14px;
}
</style>
</head>
<body class="has-sidebar">
<?php include 'partials/sidebar.php'; ?>

<div class="topbar">
    <button id="menuToggle" class="topbar-btn" style="display:none" onclick="document.querySelector('.sidebar').classList.toggle('open')">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div>
        <div class="topbar-title">Manage Users</div>
        <div class="topbar-subtitle">View and manage all registered accounts</div>
    </div>
    <div class="topbar-spacer"></div>
    <button class="theme-toggle-btn" id="themeToggle"><span id="themeIcon">☀️</span></button>
</div>

<main class="main"><div class="page-content">

<?php if ($msg): ?>
<div class="c-alert c-alert--<?= $msg_type ?>" data-auto-dismiss>
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <?= $msg_type==='success' ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>' : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' ?>
    </svg>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- 4-col stat grid — inline grid override is layout-only, not a color -->
<div class="c-stats" style="grid-template-columns:repeat(4,1fr)">
    <div class="c-stat c-stat--blue">
        <div class="c-stat__label">Total Users</div>
        <div class="c-stat__value"><?= number_format($stat_total) ?></div>
        <div class="c-stat__sub">All accounts</div>
        <div class="c-stat__icon"><svg width="56" height="56" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg></div>
    </div>
    <div class="c-stat c-stat--gold">
        <div class="c-stat__label">Students</div>
        <div class="c-stat__value"><?= number_format($stat_student) ?></div>
        <div class="c-stat__sub">Student accounts</div>
        <div class="c-stat__icon"><svg width="56" height="56" fill="currentColor" viewBox="0 0 24 24"><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg></div>
    </div>
    <div class="c-stat c-stat--red">
        <div class="c-stat__label">Admins</div>
        <div class="c-stat__value"><?= number_format($stat_admin) ?></div>
        <div class="c-stat__sub">Admin accounts</div>
        <div class="c-stat__icon"><svg width="56" height="56" fill="currentColor" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg></div>
    </div>
    <div class="c-stat c-stat--green">
        <div class="c-stat__label">Active</div>
        <div class="c-stat__value"><?= number_format($stat_active) ?></div>
        <div class="c-stat__sub">Enabled accounts</div>
        <div class="c-stat__icon"><svg width="56" height="56" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
    </div>
</div>

<div class="c-card">
    <div class="c-card__head">
        <div>
            <div class="c-card__title">All Users</div>
            <div class="c-card__sub"><?= count($users) ?> user<?= count($users)!==1?'s':'' ?><?= ($search||$role_f!=='all')?' &mdash; filtered':'' ?></div>
        </div>
        <form method="GET" class="c-filter">
            <?php if ($role_f !== 'all'): ?><input type="hidden" name="role" value="<?= htmlspecialchars($role_f) ?>"><?php endif; ?>
            <div class="c-search">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, ID…">
            </div>
            <?php if ($search): ?>
                <a href="users.php<?= $role_f!=='all'?'?role='.$role_f:'' ?>" class="c-btn c-btn--ghost">✕ Clear</a>
            <?php else: ?>
                <button type="submit" class="c-btn c-btn--ghost">Search</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Role tabs — all these classes exist in admin.css -->
    <div class="u-tabs">
        <?php $qs = $search ? '?q='.urlencode($search).'&role=' : '?role='; ?>
        <a href="<?= $qs ?>all"     class="u-tab <?= $role_f==='all'?'u-tab--active':'' ?>">All (<?= $stat_total ?>)</a>
        <a href="<?= $qs ?>student" class="u-tab <?= $role_f==='student'?'u-tab--active':'' ?>">Students (<?= $stat_student ?>)</a>
        <a href="<?= $qs ?>admin"   class="u-tab <?= $role_f==='admin'?'u-tab--active':'' ?>">Admins (<?= $stat_admin ?>)</a>
    </div>

    <div style="overflow-x:auto">
        <table class="c-table">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>User</th>
                    <th style="width:110px">Student ID</th>
                    <th style="width:90px">Role</th>
                    <th style="width:70px;text-align:center">Regs</th>
                    <th style="width:90px">Status</th>
                    <th style="width:110px">Joined</th>
                    <th style="width:200px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="8">
                    <div class="c-empty">
                        <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <h3>No users found</h3>
                        <p><?= $search ? 'Try a different search term' : 'No accounts match these filters' ?></p>
                    </div>
                </td></tr>
            <?php else: foreach ($users as $i => $u):
                $isSelf  = ((int)($u['user_id']??0) === $self_id);
                $isAdmin = ($u['role'] === 'admin');
            ?>
                <tr>
                    <td class="c-td-seq"><?= $i+1 ?></td>
                    <td>
                        <div class="u-user">
                            <div class="u-avatar <?= $isAdmin?'u-avatar--admin':'' ?>"><?= strtoupper(mb_substr($u['full_name'],0,1)) ?></div>
                            <div>
                                <div class="u-user__name"><?= htmlspecialchars($u['full_name']) ?><?php if($isSelf): ?><span class="u-you">You</span><?php endif; ?></div>
                                <div class="u-user__email"><?= htmlspecialchars($u['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="u-user__id"><?= $u['student_id'] ? htmlspecialchars($u['student_id']) : '—' ?></span></td>
                    <td><span class="u-role u-role--<?= $u['role'] ?>"><?= $isAdmin?'Admin':'Student' ?></span></td>
                    <td style="text-align:center"><span class="u-regs <?= $u['reg_count']>0?'u-regs--pos':'' ?>"><?= $u['reg_count'] ?></span></td>
                    <td><span class="u-active <?= $u['is_active']?'u-active--on':'u-active--off' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                    <td style="font-family:var(--fm);font-size:.75rem;color:var(--t3);white-space:nowrap"><?= date('M d, Y',strtotime($u['created_at'])) ?></td>
                    <td>
                        <div class="c-actions">
                            <button type="button" class="c-btn c-btn--edit"
                                    <?= $isSelf?'disabled':'' ?>
                                    onclick="uOpenRole(<?= htmlspecialchars(json_encode(['id'=>(int)($u['user_id']??0),'name'=>$u['full_name'],'role'=>$u['role']]), ENT_QUOTES) ?>)">
                                <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                Role
                            </button>
                            <form method="POST" style="display:inline">
                                <?= csrf_token_field() ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $u['user_id']??0 ?>">
                                <button type="submit" class="c-btn <?= $u['is_active']?'c-btn--ghost':'c-btn--edit' ?>" <?= $isSelf?'disabled':'' ?>
                                        <?= !$isSelf?'onclick="return confirm(\''.($u['is_active']?'Deactivate':'Activate').' '.htmlspecialchars(addslashes($u['full_name'])).'?\')"':'' ?>>
                                    <?= $u['is_active']?'Deactivate':'Activate' ?>
                                </button>
                            </form>
                            <?php if (!$isSelf): ?>
                            <form method="POST" style="display:inline">
                                <?= csrf_token_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['user_id']??0 ?>">
                                <button type="submit" class="c-btn c-btn--del"
                                        onclick="return confirm('Delete <?= htmlspecialchars(addslashes($u['full_name'])) ?>? This cannot be undone.')">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></main>

<!-- ROLE MODAL -->
<div class="c-overlay" id="roleModal">
    <div class="c-modal">
        <div class="c-modal__bar"></div>
        <div class="c-modal__head">
            <div>
                <div class="c-modal__title">Change Role</div>
                <div class="c-modal__sub">Update access level for this account</div>
            </div>
            <button class="c-modal__close" onclick="uCloseRole()">✕</button>
        </div>
        <form method="POST" action="users.php">
            <?= csrf_token_field() ?>
            <input type="hidden" name="action"  value="update_role">
            <input type="hidden" name="user_id" id="role_uid">
            <div class="c-modal__body">
                <div class="u-role-preview">
                    <div class="u-avatar" id="role_avatar">?</div>
                    <div>
                        <div class="u-user__name" id="role_name">—</div>
                        <div class="u-user__email" style="margin-top:3px">Current role: <span id="role_current">—</span></div>
                    </div>
                </div>
                <div class="c-field">
                    <label class="c-label">New Role</label>
                    <select name="role" id="role_select" class="c-input">
                        <option value="student">Student</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="c-modal__foot">
                <button type="button" class="c-btn c-btn--ghost" onclick="uCloseRole()">Cancel</button>
                <button type="submit" class="c-btn c-btn--primary">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Save Role
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/global.js"></script>
<script src="assets/js/admin.js"></script>
<script>
function uOpenRole(u) {
    document.getElementById('role_uid').value = u.id;
    document.getElementById('role_name').textContent = u.name;

    var av = document.getElementById('role_avatar');
    av.textContent = u.name.charAt(0).toUpperCase();
    av.className = 'u-avatar' + (u.role === 'admin' ? ' u-avatar--admin' : '');

    document.getElementById('role_current').textContent =
        u.role.charAt(0).toUpperCase() + u.role.slice(1);
    document.getElementById('role_select').value = u.role;

    var el = document.getElementById('roleModal');
    el.style.display = 'flex';
    requestAnimationFrame(function() {
        el.classList.add('c-overlay--open');
    });
}

function uCloseRole() {
    var el = document.getElementById('roleModal');
    el.classList.remove('c-overlay--open');
    setTimeout(function() {
        el.style.display = 'none';
    }, 220);
}

document.querySelectorAll('.c-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) uCloseRole();
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') uCloseRole();
});
</script>
</script>
</body>
</html>