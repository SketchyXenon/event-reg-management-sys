/* ============================================================
   ERMS Admin Panel — Shared JS
   ============================================================ */

// ── Mobile sidebar toggle ──────────────────────────────────
const sidebar   = document.querySelector('.sidebar');
const menuToggle = document.getElementById('menuToggle');

if (menuToggle) {
  menuToggle.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
  });
}

// Close sidebar on outside click (mobile)
document.addEventListener('click', (e) => {
  if (window.innerWidth <= 768 && sidebar?.classList.contains('open')) {
    if (!sidebar.contains(e.target) && e.target !== menuToggle) {
      sidebar.classList.remove('open');
    }
  }
});

// ── Modal helpers ──────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}

// Close modal on backdrop click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

// ── Alert auto-dismiss ─────────────────────────────────────
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
  setTimeout(() => {
    alert.style.transition = 'opacity 0.4s ease';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 400);
  }, 5000);
});

// ── Confirm delete ─────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', (e) => {
    if (!confirm(btn.dataset.confirm)) e.preventDefault();
  });
});

// ── Table search filter ─────────────────────────────────────
function filterTable(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── Animate stat counters ──────────────────────────────────
function animateCounter(el) {
  const target = parseInt(el.dataset.target || el.textContent, 10);
  if (isNaN(target)) return;
  const duration = 900;
  const start = performance.now();
  const ease = t => t < 0.5 ? 2*t*t : -1+(4-2*t)*t;

  function update(now) {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    el.textContent = Math.round(ease(progress) * target).toLocaleString();
    if (progress < 1) requestAnimationFrame(update);
  }

  requestAnimationFrame(update);
}

document.querySelectorAll('.stat-value[data-target]').forEach(animateCounter);

// ── Tabs ───────────────────────────────────────────────────
document.querySelectorAll('.tabs').forEach(tabGroup => {
  tabGroup.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.tab;
      tabGroup.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      // Find panel container (next sibling or data-panels)
      const panelContainer = document.querySelector(tabGroup.dataset.panels || '.tab-panels');
      if (panelContainer) {
        panelContainer.querySelectorAll('.tab-panel').forEach(p => {
          p.style.display = p.dataset.panel === target ? '' : 'none';
        });
      }
    });
  });
});