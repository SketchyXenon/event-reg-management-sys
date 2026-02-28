/* ============================================================
   ERMS Admin Panel — admin.js
   Shared across all admin pages. Loaded after global.js.
   ============================================================ */

/* ── 1. MOBILE SIDEBAR ──────────────────────────────────────── */
(function () {
  var sidebar = document.querySelector('.sidebar');
  var toggle  = document.getElementById('menuToggle');
  var overlay = document.getElementById('sidebarOverlay');

  function openSidebar()  { sidebar?.classList.add('open');    overlay?.classList.add('open'); }
  function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.remove('open'); }

  if (toggle)  toggle.addEventListener('click', openSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);
})();

/* ── 2. C-ALERT AUTO-DISMISS ───────────────────────────────── */
document.querySelectorAll('.c-alert[data-auto-dismiss]').forEach(function (el) {
  setTimeout(function () {
    el.style.transition = 'opacity .4s ease';
    el.style.opacity    = '0';
    setTimeout(function () { if (el.parentNode) el.remove(); }, 400);
  }, 4000);
});

/* ── 3. MODAL HELPERS (c-overlay system) ───────────────────── */
function modalOpen(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.style.display = 'flex';
  requestAnimationFrame(function () { el.classList.add('c-overlay--open'); });
}

function modalClose(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('c-overlay--open');
  el.addEventListener('transitionend', function done() {
    el.style.display = 'none';
    el.removeEventListener('transitionend', done);
  });
}

/* Wire backdrop clicks */
document.querySelectorAll('.c-overlay').forEach(function (el) {
  el.addEventListener('click', function (e) {
    if (e.target === el) modalClose(el.id);
  });
});

/* Wire Escape key */
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('.c-overlay--open').forEach(function (el) {
    modalClose(el.id);
  });
});

/* ── 4. CUSTOM DROPDOWN (rdd) ──────────────────────────────── */
function rddToggle(id) {
  var el     = document.getElementById(id);
  var isOpen = el.classList.contains('rdd--open');
  document.querySelectorAll('.rdd--open').forEach(function (d) { d.classList.remove('rdd--open'); });
  if (!isOpen) el.classList.add('rdd--open');
}

function rddPick(id, value, label, submitForm) {
  var el = document.getElementById(id);
  el.querySelector('input[type=hidden]').value       = value;
  el.querySelector('.rdd__btn span').textContent     = label;
  el.querySelectorAll('.rdd__item').forEach(function (i) { i.classList.remove('rdd__item--active'); });
  el.classList.remove('rdd--open');
  if (submitForm) el.closest('form').submit();
}

function rddSetValue(id, value, label, lblId) {
  document.getElementById(id).querySelector('input[type=hidden]').value = value;
  document.getElementById(lblId || id + '-lbl').textContent = label;
}

/* Close dropdowns on outside click */
document.addEventListener('click', function (e) {
  if (!e.target.closest('.rdd')) {
    document.querySelectorAll('.rdd--open').forEach(function (d) { d.classList.remove('rdd--open'); });
  }
});

/* ── 5. CONFIRM DELETE (data-confirm) ──────────────────────── */
document.querySelectorAll('[data-confirm]').forEach(function (btn) {
  btn.addEventListener('click', function (e) {
    if (!confirm(btn.dataset.confirm)) e.preventDefault();
  });
});

/* ── 6. CATEGORY ICON COLORS (data-color attr) ─────────────── */
document.querySelectorAll('.c-icon[data-color]').forEach(function (el) {
  var c = el.getAttribute('data-color');
  el.style.background  = c + '1e';
  el.style.borderColor = c + '44';
});