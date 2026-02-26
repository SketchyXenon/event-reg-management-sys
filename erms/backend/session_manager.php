<?php
// ============================================================
//  session_manager.php
//  Session Timeout & Security Configuration
//  Event Registration Management System
//
//  HOW SESSION TIMEOUT WORKS:
//  - Tracks the last time the user did something (last_activity)
//  - On every page load, checks if too much time has passed
//  - If idle for longer than the timeout, the session is destroyed
//    and the user is redirected to the login page
//
//  INCLUDE THIS FILE in auth_guard.php so it runs on
//  every protected page automatically.
// ============================================================


// ── Timeout Settings ───────────────────────────────────────────
define('SESSION_IDLE_TIMEOUT',  1800);   // 30 minutes of inactivity
define('SESSION_ABSOLUTE_MAX',  28800);  // 8 hours max regardless of activity
define('SESSION_WARN_BEFORE',   300);    // Warn user 5 minutes before timeout


// ══════════════════════════════════════════════════════════════
//  FUNCTION: session_configure()
//  Sets secure PHP session settings.
//  Call this ONCE before session_start() on every page.
//
//  @return void
// ══════════════════════════════════════════════════════════════
function session_configure(): void
{
    // Prevent JavaScript from accessing the session cookie (XSS protection)
    ini_set('session.cookie_httponly', 1);

    // Only send cookie over HTTPS (set to 0 for local XAMPP development)
    // Change to 1 when deploying to a live HTTPS server
    ini_set('session.cookie_secure', 0);

    // Strict mode — rejects uninitialized session IDs
    ini_set('session.use_strict_mode', 1);

    // Prevent session ID from appearing in URLs
    ini_set('session.use_only_cookies', 1);

    // Set SameSite to Lax (prevents CSRF via cross-site requests)
    ini_set('session.cookie_samesite', 'Lax');

    // Session lifetime (browser session — expires when browser closes)
    ini_set('session.cookie_lifetime', 0);
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: session_check_timeout()
//  Checks if the session has been idle too long or exceeded
//  the absolute maximum duration. Destroys and redirects if so.
//
//  @param  string $login_url  Path to redirect to on timeout
//  @return void
// ══════════════════════════════════════════════════════════════
function session_check_timeout(string $login_url = 'backend/login.php'): void
{
    $now = time();

    // ── Absolute session max ───────────────────────────────────
    // Force logout after 8 hours no matter what (even if active)
    if (isset($_SESSION['session_start_time'])) {
        if (($now - $_SESSION['session_start_time']) > SESSION_ABSOLUTE_MAX) {
            session_destroy_and_redirect($login_url, 'expired');
        }
    } else {
        $_SESSION['session_start_time'] = $now;
    }

    // ── Idle timeout ───────────────────────────────────────────
    if (isset($_SESSION['last_activity'])) {
        $idle_time = $now - $_SESSION['last_activity'];

        if ($idle_time > SESSION_IDLE_TIMEOUT) {
            session_destroy_and_redirect($login_url, 'timeout');
        }
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = $now;
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: session_seconds_remaining()
//  Returns how many seconds are left before idle timeout.
//  Used to show a countdown warning on the frontend.
//
//  @return int  Seconds remaining (0 if already expired)
// ══════════════════════════════════════════════════════════════
function session_seconds_remaining(): int
{
    if (!isset($_SESSION['last_activity'])) return SESSION_IDLE_TIMEOUT;

    $idle    = time() - $_SESSION['last_activity'];
    $remaining = SESSION_IDLE_TIMEOUT - $idle;

    return max(0, $remaining);
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: session_should_warn()
//  Returns true when the session is close to timing out
//  (within SESSION_WARN_BEFORE seconds)
//
//  @return bool
// ══════════════════════════════════════════════════════════════
function session_should_warn(): bool
{
    return session_seconds_remaining() <= SESSION_WARN_BEFORE;
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: session_destroy_and_redirect()
//  Safely clears all session data and redirects to login.
//
//  @param  string $login_url  Login page URL
//  @param  string $reason     'timeout' | 'expired' | 'logout'
//  @return void
// ══════════════════════════════════════════════════════════════
function session_destroy_and_redirect(string $login_url, string $reason = 'timeout'): void
{
    // Clear session data
    $_SESSION = [];

    // Destroy session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header("Location: {$login_url}?session={$reason}");
    exit();
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: session_timeout_warning_html()
//  Returns an HTML + JS snippet that shows a countdown modal
//  warning the user before their session expires.
//  Include this at the bottom of your layout/template file.
//
//  @return string  HTML modal + JavaScript
// ══════════════════════════════════════════════════════════════
function session_timeout_warning_html(): string
{
    $seconds_left  = session_seconds_remaining();
    $warn_at       = SESSION_WARN_BEFORE;
    $login_url     = 'backend/login.php?session=timeout';

    return <<<HTML
<!-- Session Timeout Warning Modal -->
<div id="sessionModal" style="
    display:none; position:fixed; inset:0; z-index:9999;
    background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
  <div style="
      background:#fff; border-radius:12px; padding:32px 40px;
      max-width:380px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <h3 style="margin:0 0 12px; color:#c0392b;">&#9201; Session Expiring</h3>
    <p style="color:#555; margin:0 0 20px;">
      Your session will expire in <strong id="sessionCountdown">5:00</strong>.<br>
      Do you want to stay logged in?
    </p>
    <button onclick="extendSession()" style="
        background:#2e6da4; color:#fff; border:none;
        padding:10px 28px; border-radius:8px; cursor:pointer;
        font-size:15px; margin-right:10px;">
      Stay Logged In
    </button>
    <a href="{$login_url}" style="
        color:#888; font-size:14px; text-decoration:none;">
      Log Out
    </a>
  </div>
</div>

<script>
(function() {
    let secondsLeft  = {$seconds_left};
    const warnAt     = {$warn_at};
    const modal      = document.getElementById('sessionModal');
    const countdown  = document.getElementById('sessionCountdown');
    const loginUrl   = '{$login_url}';

    function formatTime(s) {
        const m = Math.floor(s / 60);
        const sec = s % 60;
        return m + ':' + String(sec).padStart(2, '0');
    }

    const timer = setInterval(() => {
        secondsLeft--;

        if (secondsLeft <= 0) {
            clearInterval(timer);
            window.location.href = loginUrl;
            return;
        }

        if (secondsLeft <= warnAt) {
            modal.style.display = 'flex';
            countdown.textContent = formatTime(secondsLeft);
        }
    }, 1000);

    window.extendSession = function() {
        fetch('backend/extend_session.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    secondsLeft = data.seconds_remaining;
                    modal.style.display = 'none';
                }
            })
            .catch(() => { modal.style.display = 'none'; });
    };
})();
</script>
HTML;
}