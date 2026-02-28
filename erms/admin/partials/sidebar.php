<?php
/* Shared sidebar — included by all admin pages.
   Requires $admin = current_user() to already be set. */
$cur = basename($_SERVER['PHP_SELF']);

function _nav(string $file, string $label, string $d): void {
  global $cur;
  $active = ($cur === $file) ? ' active' : '';
  echo "<a href=\"{$file}\" class=\"nav-item{$active}\">
    <svg class=\"nav-icon\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"{$d}\"/></svg>
    {$label}</a>\n";
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sb-brand-top">
      <div class="brand-crest">E</div>
      <div class="sb-brand-text">
        <h1>ERMS Admin</h1>
        <p>Control Panel</p>
      </div>
    </div>
    <div class="sb-clock">
      <div class="sb-clock__time" id="sbTime">--:--:--</div>
      <div class="sb-clock__date" id="sbDate">--- --, ----</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <?php _nav('dashboard.php','Dashboard','M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'); ?>

    <div class="nav-section-label">Management</div>
    <?php _nav('events.php','Manage Events','M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'); ?>
    <?php _nav('users.php','Manage Users','M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'); ?>
    <?php _nav('registrations.php','Registrations','M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'); ?>
    <?php _nav('categories.php','Categories','M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z'); ?>
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
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Sign Out
    </a>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="document.querySelector('.sidebar').classList.remove('open');this.classList.remove('open')"></div>

<script>
(function() {
  var days  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  function tick() {
    var now  = new Date();
    var h    = String(now.getHours()).padStart(2,'0');
    var m    = String(now.getMinutes()).padStart(2,'0');
    var s    = String(now.getSeconds()).padStart(2,'0');
    var day  = days[now.getDay()];
    var mon  = months[now.getMonth()];
    var date = String(now.getDate()).padStart(2,'0');
    var yr   = now.getFullYear();
    var tEl  = document.getElementById('sbTime');
    var dEl  = document.getElementById('sbDate');
    if (tEl) tEl.textContent = h + ':' + m + ':' + s;
    if (dEl) dEl.textContent = day + ', ' + mon + ' ' + date + ' ' + yr;
  }
  tick();
  setInterval(tick, 1000);
})();
</script>