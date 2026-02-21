<?php
// ============================================================
//  login_limiter.php
//  Login Rate Limiting & Account Lockout
//  Event Registration Management System
//
//  LOCKOUT SCHEDULE:
//   3 failed attempts  →  1 minute lockout
//   4 failed attempts  →  5 minutes lockout
//   5 failed attempts  →  15 minutes lockout
//   6 failed attempts  →  30 minutes lockout
//   7+ failed attempts →  1 hour lockout
//
//  Tracking is done BOTH by email (account-level)
//  AND by IP address (network-level) for stronger protection.
// ============================================================

require_once 'db_connect.php';


// ── Lockout Schedule ───────────────────────────────────────────
// Key   = number of failed attempts
// Value = lockout duration in seconds
const LOCKOUT_SCHEDULE = [
    3 => 60,        //  1 minute
    4 => 300,       //  5 minutes
    5 => 900,       //  15 minutes
    6 => 1800,      //  30 minutes
    7 => 3600,      //  1 hour
];

// Max attempts before hitting the 1-hour cap
const MAX_ATTEMPTS = 7;

// Max failed attempts from a single IP (regardless of email)
const IP_MAX_ATTEMPTS = 20;
const IP_WINDOW_SECONDS = 900; // 15-minute sliding window for IP tracking


// ══════════════════════════════════════════════════════════════
//  FUNCTION: get_lockout_duration()
//  Returns the lockout duration in seconds for a given
//  number of failed attempts
//
//  @param  int $attempts  Number of consecutive failed attempts
//  @return int            Lockout duration in seconds (0 = no lockout)
// ══════════════════════════════════════════════════════════════
function get_lockout_duration(int $attempts): int
{
    if ($attempts < 3) return 0;

    $schedule = LOCKOUT_SCHEDULE;
    krsort($schedule); // Check from highest to lowest

    foreach ($schedule as $threshold => $seconds) {
        if ($attempts >= $threshold) {
            return $seconds;
        }
    }

    return 0;
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: format_lockout_time()
//  Converts seconds into a human-readable string
//
//  @param  int    $seconds  Duration in seconds
//  @return string           e.g. "1 minute", "15 minutes", "1 hour"
// ══════════════════════════════════════════════════════════════
function format_lockout_time(int $seconds): string
{
    if ($seconds < 60)   return "$seconds seconds";
    if ($seconds < 3600) {
        $mins = intdiv($seconds, 60);
        return $mins === 1 ? "1 minute" : "$mins minutes";
    }
    $hrs = intdiv($seconds, 3600);
    return $hrs === 1 ? "1 hour" : "$hrs hours";
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: is_account_locked()
//  Checks if an account is currently locked out
//
//  @param  string $email  The email being checked
//  @return array  [
//    'locked'       => bool,
//    'seconds_left' => int,     // seconds remaining in lockout
//    'message'      => string   // human-readable message
//  ]
// ══════════════════════════════════════════════════════════════
function is_account_locked(string $email): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "SELECT failed_attempts, locked_until
               FROM users
              WHERE email = :email
              LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !$user['locked_until']) {
            return ['locked' => false, 'seconds_left' => 0, 'message' => ''];
        }

        $locked_until = strtotime($user['locked_until']);
        $now          = time();

        if ($now < $locked_until) {
            $seconds_left = $locked_until - $now;
            $time_str     = format_lockout_time($seconds_left);
            return [
                'locked'       => true,
                'seconds_left' => $seconds_left,
                'message'      => "Too many failed attempts. Please try again in <strong>$time_str</strong>.",
            ];
        }

        // Lockout has expired — clear it
        clear_lockout($email);
        return ['locked' => false, 'seconds_left' => 0, 'message' => ''];

    } catch (PDOException $e) {
        error_log("is_account_locked error: " . $e->getMessage());
        return ['locked' => false, 'seconds_left' => 0, 'message' => ''];
    }
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: is_ip_blocked()
//  Checks if an IP has too many failed attempts recently
//
//  @param  string $ip  The IP address to check
//  @return array  ['blocked' => bool, 'message' => string]
// ══════════════════════════════════════════════════════════════
function is_ip_blocked(string $ip): array
{
    global $pdo;

    try {
        $window_start = date('Y-m-d H:i:s', time() - IP_WINDOW_SECONDS);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS attempt_count
               FROM login_attempts
              WHERE ip_address  = :ip
                AND is_successful = 0
                AND attempted_at >= :window_start"
        );
        $stmt->execute([':ip' => $ip, ':window_start' => $window_start]);
        $row = $stmt->fetch();

        if ($row['attempt_count'] >= IP_MAX_ATTEMPTS) {
            return [
                'blocked' => true,
                'message' => 'Too many login attempts from your network. Please wait 15 minutes before trying again.',
            ];
        }

        return ['blocked' => false, 'message' => ''];

    } catch (PDOException $e) {
        error_log("is_ip_blocked error: " . $e->getMessage());
        return ['blocked' => false, 'message' => ''];
    }
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: record_failed_attempt()
//  Logs a failed login and applies lockout if threshold is met
//
//  @param  string $email  The email that failed to log in
//  @param  string $ip     The IP address of the request
//  @return array  [
//    'attempts'     => int,
//    'locked'       => bool,
//    'lockout_time' => string  // human-readable lockout duration
//  ]
// ══════════════════════════════════════════════════════════════
function record_failed_attempt(string $email, string $ip): array
{
    global $pdo;

    try {
        // Log to login_attempts table
        $log = $pdo->prepare(
            "INSERT INTO login_attempts (email, ip_address, is_successful)
             VALUES (:email, :ip, 0)"
        );
        $log->execute([':email' => $email, ':ip' => $ip]);

        // Increment failed_attempts on users table (if user exists)
        $inc = $pdo->prepare(
            "UPDATE users
                SET failed_attempts = failed_attempts + 1
              WHERE email = :email"
        );
        $inc->execute([':email' => $email]);

        // Fetch current attempt count
        $fetch = $pdo->prepare(
            "SELECT failed_attempts FROM users WHERE email = :email LIMIT 1"
        );
        $fetch->execute([':email' => $email]);
        $row = $fetch->fetch();

        $attempts = $row ? (int) $row['failed_attempts'] : 1;

        // Apply lockout if threshold reached
        $lockout_seconds = get_lockout_duration($attempts);
        $lockout_time    = '';

        if ($lockout_seconds > 0) {
            $locked_until = date('Y-m-d H:i:s', time() + $lockout_seconds);
            $lock = $pdo->prepare(
                "UPDATE users SET locked_until = :locked_until WHERE email = :email"
            );
            $lock->execute([':locked_until' => $locked_until, ':email' => $email]);
            $lockout_time = format_lockout_time($lockout_seconds);
        }

        return [
            'attempts'     => $attempts,
            'locked'       => $lockout_seconds > 0,
            'lockout_time' => $lockout_time,
        ];

    } catch (PDOException $e) {
        error_log("record_failed_attempt error: " . $e->getMessage());
        return ['attempts' => 0, 'locked' => false, 'lockout_time' => ''];
    }
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: record_successful_login()
//  Resets failed attempt counter and clears lockout on success
//
//  @param  string $email  The email that logged in successfully
//  @param  string $ip     The IP address of the request
//  @return void
// ══════════════════════════════════════════════════════════════
function record_successful_login(string $email, string $ip): void
{
    global $pdo;

    try {
        // Log the successful attempt
        $log = $pdo->prepare(
            "INSERT INTO login_attempts (email, ip_address, is_successful)
             VALUES (:email, :ip, 1)"
        );
        $log->execute([':email' => $email, ':ip' => $ip]);

        // Reset lockout state on the user
        clear_lockout($email);

    } catch (PDOException $e) {
        error_log("record_successful_login error: " . $e->getMessage());
    }
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: clear_lockout()
//  Resets the failed_attempts counter and removes lockout
//
//  @param  string $email  The email to clear
//  @return void
// ══════════════════════════════════════════════════════════════
function clear_lockout(string $email): void
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "UPDATE users
                SET failed_attempts = 0,
                    locked_until    = NULL
              WHERE email = :email"
        );
        $stmt->execute([':email' => $email]);
    } catch (PDOException $e) {
        error_log("clear_lockout error: " . $e->getMessage());
    }
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: get_client_ip()
//  Retrieves the real IP address of the client
//  Handles proxies and load balancers
//
//  @return string  IP address
// ══════════════════════════════════════════════════════════════
function get_client_ip(): string
{
    $keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            // X-Forwarded-For may contain multiple IPs — take the first
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: remaining_attempts_message()
//  Returns a warning message showing how many tries are left
//  before the next lockout threshold
//
//  @param  int $attempts  Current failed attempt count
//  @return string         Warning message or empty string
// ══════════════════════════════════════════════════════════════
function remaining_attempts_message(int $attempts): string
{
    $thresholds = array_keys(LOCKOUT_SCHEDULE);
    sort($thresholds);

    foreach ($thresholds as $threshold) {
        if ($attempts < $threshold) {
            $remaining = $threshold - $attempts;
            $next_lock = format_lockout_time(LOCKOUT_SCHEDULE[$threshold]);
            if ($remaining === 1) {
                return "Warning: 1 more failed attempt will lock your account for $next_lock.";
            }
            return "Warning: $remaining more failed attempts will lock your account for $next_lock.";
        }
    }

    return "Warning: Your account will be locked for 1 hour after the next failed attempt.";
}