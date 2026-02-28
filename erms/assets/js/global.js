/* ============================================================
   ERMS — global.js  (place at: assets/js/global.js)
   Shared JS for all pages. Load BEFORE admin.js.
   ============================================================ */

/* ── 1. THEME ────────────────────────────────────────────────
   Applies saved theme immediately, wires toggle button.
   Expects: #themeToggle, #themeIcon
   ----------------------------------------------------------- */
(function () {
  const html = document.documentElement;
  const btn = document.getElementById("themeToggle");
  const icon = document.getElementById("themeIcon");

  const saved = localStorage.getItem("erms-theme") || "dark";
  html.setAttribute("data-theme", saved);
  if (icon) icon.textContent = saved === "dark" ? "☀️" : "🌙";

  if (btn) {
    btn.addEventListener("click", () => {
      const next =
        html.getAttribute("data-theme") === "dark" ? "light" : "dark";
      html.setAttribute("data-theme", next);
      localStorage.setItem("erms-theme", next);
      if (icon) icon.textContent = next === "dark" ? "☀️" : "🌙";
    });
  }
})();

/* ── 2. MODALS ───────────────────────────────────────────────
   openModal(id) / closeModal(id) — global functions.
   Also wires backdrop click to close.
   ----------------------------------------------------------- */
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add("open");
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove("open");
}

document.querySelectorAll(".modal-overlay").forEach(function (overlay) {
  overlay.addEventListener("click", function (e) {
    if (e.target === overlay) overlay.classList.remove("open");
  });
});

/* ── 3. ALERT AUTO-DISMISS ───────────────────────────────────
   .alert[data-auto-dismiss] fades out after 4 seconds.
   ----------------------------------------------------------- */
document.querySelectorAll(".alert[data-auto-dismiss]").forEach(function (el) {
  setTimeout(function () {
    el.style.transition = "opacity 0.4s ease";
    el.style.opacity = "0";
    setTimeout(function () {
      if (el.parentNode) el.remove();
    }, 400);
  }, 4000);
});

/* ── 4. MOBILE SIDEBAR (student pages) ───────────────────────
   Expects: #sidebar, #menuBtn
   Admin pages use #menuToggle handled by admin.js instead.
   ----------------------------------------------------------- */
(function () {
  var sidebar = document.getElementById("sidebar");
  var menuBtn = document.getElementById("menuBtn");
  if (!sidebar || !menuBtn) return;

  if (window.innerWidth <= 768) menuBtn.style.display = "flex";

  document.addEventListener("click", function (e) {
    if (window.innerWidth <= 768 && sidebar.classList.contains("open")) {
      if (!sidebar.contains(e.target) && !e.target.closest("#menuBtn")) {
        sidebar.classList.remove("open");
      }
    }
  });
})();

/* ── 5. PASSWORD STRENGTH ────────────────────────────────────
   Expects: #password, #strengthBar OR #strengthFill, #strengthLabel
   Used on register.php and admin-register.php.
   ----------------------------------------------------------- */
(function () {
  var pw = document.getElementById("password");
  var bar =
    document.getElementById("strengthBar") ||
    document.getElementById("strengthFill");
  var label = document.getElementById("strengthLabel");
  if (!pw || !bar || !label) return;

  var levels = [
    { pct: "0%", color: "", txt: "Enter a password" },
    { pct: "25%", color: "#c45c5c", txt: "Weak" },
    { pct: "50%", color: "#c9a84c", txt: "Fair" },
    { pct: "75%", color: "#6a96cc", txt: "Good" },
    { pct: "100%", color: "#4e9b72", txt: "Strong ✓" },
  ];

  pw.addEventListener("input", function () {
    var v = pw.value;
    var score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    var lvl = v.length === 0 ? levels[0] : levels[score];
    bar.style.width = lvl.pct;
    bar.style.background = lvl.color || "transparent";
    label.textContent = lvl.txt;
    label.style.color = lvl.color || "";
    // Trigger confirm-match recheck
    var confirm = document.getElementById("confirm_password");
    if (confirm && confirm.value) confirm.dispatchEvent(new Event("input"));
  });
})();

/* ── 6. CONFIRM PASSWORD MATCH ───────────────────────────────
   Expects: #password, #confirm_password,
            #confirmHint (register) OR #matchHint (admin-register)
   ----------------------------------------------------------- */
(function () {
  var pw = document.getElementById("password");
  var confirm = document.getElementById("confirm_password");
  var hint =
    document.getElementById("confirmHint") ||
    document.getElementById("matchHint");
  if (!pw || !confirm || !hint) return;

  confirm.addEventListener("input", function () {
    if (!confirm.value) {
      hint.textContent = "";
      return;
    }
    var match = confirm.value === pw.value;
    hint.textContent = match ? "✓ Passwords match" : "✗ Passwords do not match";
    if (hint.id === "matchHint") {
      hint.className = match ? "hint ok" : "hint err";
    } else {
      hint.className = match ? "input-hint valid" : "input-hint invalid";
      confirm.classList.toggle("is-ok", match);
      confirm.classList.toggle("is-error", !match);
    }
  });
})();

/* ── 7. PASSWORD SHOW/HIDE ───────────────────────────────────
   Wires #pwToggle → #password
         #pwToggle1 → #password
         #pwToggle2 → #confirm_password
   ----------------------------------------------------------- */
(function () {
  var map = {
    pwToggle: "password",
    pwToggle1: "password",
    pwToggle2: "confirm_password",
  };
  Object.keys(map).forEach(function (btnId) {
    var btn = document.getElementById(btnId);
    var inp = document.getElementById(map[btnId]);
    if (!btn || !inp) return;
    btn.addEventListener("click", function () {
      inp.type = inp.type === "password" ? "text" : "password";
    });
  });
})();

/* ── 8. STUDENT ID HINT ──────────────────────────────────────
   Expects: #student_id, #idHint. Used on register.php.
   ----------------------------------------------------------- */
(function () {
  var input = document.getElementById("student_id");
  var hint = document.getElementById("idHint");
  if (!input || !hint) return;

  input.addEventListener("input", function () {
    var v = input.value.trim();
    if (!v) {
      hint.textContent = "7-digit number, e.g. 3240000";
      hint.className = "input-hint";
      return;
    }
    if (/^\d{7}$/.test(v)) {
      hint.textContent = "✓ Valid format";
      hint.className = "input-hint valid";
      input.classList.remove("is-error");
      input.classList.add("is-ok");
    } else {
      hint.textContent = "Must be exactly 7 digits";
      hint.className = "input-hint invalid";
      input.classList.remove("is-ok");
    }
  });
})();

/* ── 9. SUBMIT LOADING STATE ─────────────────────────────────
   Covers registerForm and regForm (admin-register).
   login.php handles its own submit due to mode-switching logic.
   ----------------------------------------------------------- */
(function () {
  var pairs = [
    ["registerForm", "Creating account…"],
    ["regForm", "Creating account…"],
  ];
  pairs.forEach(function (pair) {
    var form = document.getElementById(pair[0]);
    var btn = document.getElementById("submitBtn");
    if (!form || !btn) return;
    form.addEventListener("submit", function () {
      btn.disabled = true;
      btn.textContent = pair[1];
    });
  });
})();

/* ── 10. NAVBAR SCROLL EFFECT ────────────────────────────────
   Expects: #navbar. Used on index.php only.
   ----------------------------------------------------------- */
(function () {
  var navbar = document.getElementById("navbar");
  if (!navbar) return;
  window.addEventListener("scroll", function () {
    navbar.classList.toggle("scrolled", window.scrollY > 30);
  });
})();

/* ── 11. SCROLL REVEAL ───────────────────────────────────────
   Animates .reveal elements. Used on index.php.
   ----------------------------------------------------------- */
(function () {
  var els = document.querySelectorAll(".reveal");
  if (!els.length || !window.IntersectionObserver) return;

  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry, i) {
        if (entry.isIntersecting) {
          setTimeout(function () {
            entry.target.classList.add("visible");
          }, i * 80);
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12 },
  );

  els.forEach(function (el) {
    observer.observe(el);
  });
})();

/* ── 12. COUNTER ANIMATION ───────────────────────────────────
   Animates .stat-num[data-target]. Used on index.php.
   ----------------------------------------------------------- */
(function () {
  var els = document.querySelectorAll(".stat-num[data-target]");
  if (!els.length || !window.IntersectionObserver) return;

  var ease = function (t) {
    return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
  };

  function animateCounter(el) {
    var target = parseInt(el.dataset.target, 10);
    if (isNaN(target) || target === 0) return;
    var duration = 1200;
    var start = performance.now();
    (function tick(now) {
      var progress = Math.min((now - start) / duration, 1);
      el.childNodes[0].textContent = Math.round(
        ease(progress) * target,
      ).toLocaleString();
      if (progress < 1) requestAnimationFrame(tick);
    })(start);
  }

  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 },
  );

  els.forEach(function (el) {
    observer.observe(el);
  });
})();

/* ── 13. TOGGLE PASSWORD (onclick API) ───────────────────────
   Called via onclick="togglePw('inputId')" in admin-register.php.
   ----------------------------------------------------------- */
function togglePw(inputId) {
  var inp = document.getElementById(inputId);
  if (inp) inp.type = inp.type === "password" ? "text" : "password";
}

/* ── 14. CATEGORY PAGE MODALS (c-overlay system) ─────────────
   catOpenModal(id) / catCloseModal(id) — animate opacity + scale.
   catEdit(id, name, desc)             — pre-fill & open edit modal.
   Auto-wires backdrop click + Escape key on DOMContentLoaded.
   ----------------------------------------------------------- */
function catOpenModal(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.style.display = "flex";
  requestAnimationFrame(function () {
    el.classList.add("c-overlay--open");
  });
}

function catCloseModal(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.remove("c-overlay--open");
  el.addEventListener("transitionend", function onEnd() {
    el.style.display = "none";
    el.removeEventListener("transitionend", onEnd);
  });
}

function catEdit(id, name, desc) {
  var elId = document.getElementById("e_id");
  var elName = document.getElementById("e_name");
  var elDesc = document.getElementById("e_desc");
  if (elId) elId.value = id;
  if (elName) elName.value = name;
  if (elDesc) elDesc.value = desc;
  catOpenModal("editModal");
}

document.addEventListener("DOMContentLoaded", function () {
  /* Backdrop click closes the overlay */
  document.querySelectorAll(".c-overlay").forEach(function (el) {
    el.addEventListener("click", function (e) {
      if (e.target === el) catCloseModal(el.id);
    });
  });

  /* Escape key closes any open overlay */
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    document.querySelectorAll(".c-overlay--open").forEach(function (el) {
      catCloseModal(el.id);
    });
  });
});
