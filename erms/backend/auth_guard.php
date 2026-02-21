<?php
// ============================================================
//  auth_guard.php
//  Central Security Gateway — include at top of every page
//  Automatically applies:
//    ✔ HTTP Security Headers
//    ✔ Session Configuration & Timeout
//    ✔ Authentication Check
//    ✔ CSRF Token availability
//  Event Registration Management System
// ============================================================

require_once __DIR__ . '/security_headers.php';
require_once __DIR__ . '/session_manager.php';

session_configure();
session_start();

require_once __DIR__ . '/csrf_helper.php';


function require_login(string $login_url = 'backend/login.php'): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: {$login_url}?error=unauthorized");
        exit();
    }
    session_check_timeout($login_url);
}

function admin_only(): void
{
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: ../dashboard.php?error=forbidden");
        exit();
    }
}

function current_user(): array
{
    return [
        'user_id'   => $_SESSION['user_id']   ?? null,
        'full_name' => $_SESSION['full_name']  ?? '',
        'email'     => $_SESSION['email']      ?? '',
        'role'      => $_SESSION['role']       ?? 'student',
    ];
}

require_login();