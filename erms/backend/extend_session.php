<?php
// ============================================================
//  extend_session.php
//  AJAX endpoint — resets the session idle timer
//  Called by the "Stay Logged In" button in the timeout modal
//  Event Registration Management System
// ============================================================

session_start();

header('Content-Type: application/json');

// Must be a POST request from a logged-in user
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'session_manager.php';

// Reset idle timer
$_SESSION['last_activity'] = time();

echo json_encode([
    'success'          => true,
    'seconds_remaining' => SESSION_IDLE_TIMEOUT,
]);