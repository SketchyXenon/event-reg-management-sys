// ============================================================
//  validate.js
//  Client-side Form Validation & UX Enhancements
//  Event Registration Management System
//
//  Features:
//  - Real-time field validation
//  - Password strength meter
//  - Password visibility toggle
//  - Submit button loading state
//  - Shake animation on error
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ── Password Toggle ───────────────────────────────────────
  document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.previousElementSibling;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.textContent = isHidden ? '🙈' : '👁️';
    });
  });

  // ── Password Strength Meter ───────────────────────────────
  const passwordInput = document.getElementById('password');
  const strengthFill  = document.querySelector('.strength-fill');
  const strengthText  = document.querySelector('.strength-text');

  if (passwordInput && strengthFill) {
    passwordInput.addEventListener('input', () => {
      const val   = passwordInput.value;
      const score = getPasswordScore(val);
      const levels = [
        { pct: '0%',   color: 'transparent',       label: ''            },
        { pct: '25%',  color: '#c05050',            label: 'Weak'        },
        { pct: '50%',  color: '#b5893a',            label: 'Fair'        },
        { pct: '75%',  color: '#4a7ab5',            label: 'Good'        },
        { pct: '100%', color: '#3a9d6e',            label: 'Strong ✓'    },
      ];
      const level = levels[score];
      strengthFill.style.width      = level.pct;
      strengthFill.style.background = level.color;
      if (strengthText) strengthText.textContent = val.length ? level.label : '';
    });
  }

  function getPasswordScore(pw) {
    if (!pw) return 0;
    let score = 0;
    if (pw.length >= 8)          score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[\W_]/.test(pw))        score++;
    return score;
  }

  // ── Confirm Password Match ────────────────────────────────
  const confirmInput = document.getElementById('confirm_password');
  if (confirmInput && passwordInput) {
    confirmInput.addEventListener('input', () => {
      if (confirmInput.value && confirmInput.value !== passwordInput.value) {
        confirmInput.classList.add('is-error');
        setHint(confirmInput, '⚠ Passwords do not match', 'error');
      } else if (confirmInput.value) {
        confirmInput.classList.remove('is-error');
        setHint(confirmInput, '✓ Passwords match', 'success');
      } else {
        confirmInput.classList.remove('is-error');
        clearHint(confirmInput);
      }
    });
  }

  // ── Email Validation ──────────────────────────────────────
  const emailInput = document.getElementById('email');
  if (emailInput) {
    emailInput.addEventListener('blur', () => {
      const val = emailInput.value.trim();
      if (val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
        emailInput.classList.add('is-error');
        setHint(emailInput, '⚠ Please enter a valid email address', 'error');
      } else if (val) {
        emailInput.classList.remove('is-error');
        clearHint(emailInput);
      }
    });
  }

  // ── Submit Loading State ──────────────────────────────────
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', (e) => {
      const btn = form.querySelector('.btn-primary');
      if (!btn) return;

      // Basic client-side check before allowing submit
      let hasError = false;
      form.querySelectorAll('input[required]').forEach(input => {
        if (!input.value.trim()) {
          input.classList.add('is-error');
          hasError = true;
        }
      });

      if (hasError) {
        e.preventDefault();
        // Shake the form card
        const card = document.querySelector('.auth-form-panel');
        if (card) {
          card.classList.remove('shake');
          void card.offsetWidth; // Reflow to restart animation
          card.classList.add('shake');
        }
        return;
      }

      btn.disabled = true;
      btn.dataset.original = btn.textContent;
      btn.textContent = 'Please wait…';
    });
  });

  // ── Helper: set inline hint below field ───────────────────
  function setHint(input, msg, type) {
    let hint = input.parentElement.querySelector('.input-hint');
    if (!hint) {
      hint = document.createElement('span');
      hint.className = 'input-hint';
      input.parentElement.appendChild(hint);
    }
    hint.textContent = msg;
    hint.style.color = type === 'error' ? '#e08080' : '#6dc9a0';
  }

  function clearHint(input) {
    const hint = input.parentElement.querySelector('.input-hint');
    if (hint) hint.textContent = '';
  }

  // ── Auto-dismiss alerts after 6 seconds ──────────────────
  const alerts = document.querySelectorAll('.alert-success');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity    = '0';
      setTimeout(() => alert.remove(), 500);
    }, 6000);
  });

});