<?php
// ============================================================
//  auth_guard.php
//  Central Security Gateway
//  Event Registration Management System
// ============================================================

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/csrf_helper.php';

// Start session safely — works even if already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // set true if using HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Session timeout check ──────────────────────────────────
define('SESSION_IDLE_TIMEOUT',    30 * 60);   // 30 minutes
define('SESSION_ABSOLUTE_MAX',   8 * 3600);   // 8 hours

function session_check_and_redirect(string $login_url): void
{
    if (!isset($_SESSION['user_id'])) return;

    $now = time();

    // Idle timeout
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: {$login_url}?session=timeout");
        exit();
    }

    // Absolute max
    if (isset($_SESSION['created_at']) && ($now - $_SESSION['created_at']) > SESSION_ABSOLUTE_MAX) {
        session_unset();
        session_destroy();
        header("Location: {$login_url}?session=expired");
        exit();
    }

    $_SESSION['last_activity'] = $now;
}


function require_login(string $login_url = '../login.php'): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: {$login_url}?error=unauthorized");
        exit();
    }
    session_check_and_redirect($login_url);
}

function admin_only(): void
{
    require_login('../login.php');
    if ($_SESSION['role'] !== 'admin') {
        header("Location: ../dashboard.php?error=forbidden");
        exit();
    }
}

function current_user(): array
{
    return [
        'id'        => $_SESSION['user_id']  ?? null,
        'full_name' => $_SESSION['full_name'] ?? '',
        'email'     => $_SESSION['email']     ?? '',
        'role'      => $_SESSION['role']      ?? 'student',
    ];
}
