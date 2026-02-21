<?php
// ============================================================
//  csrf_helper.php
//  CSRF (Cross-Site Request Forgery) Protection
//  Event Registration Management System
//
//  HOW CSRF WORKS:
//  - Every form gets a hidden token unique to the user's session
//  - When the form is submitted, the token is verified server-side
//  - If the token is missing or wrong, the request is rejected
//  - This prevents attackers from tricking logged-in users into
//    submitting forms on your site from a malicious external page
//
//  USAGE:
//  1. In your form:
//       <?php echo csrf_token_field();
//
//  2. At the top of your form handler:
//       csrf_verify();
// ============================================================


// ── Token Settings ─────────────────────────────────────────────
define('CSRF_TOKEN_NAME',   '_csrf_token');
define('CSRF_TOKEN_LENGTH', 64);            // bytes → 128 hex chars
define('CSRF_TOKEN_EXPIRY', 3600);          // 1 hour in seconds


// ══════════════════════════════════════════════════════════════
//  FUNCTION: csrf_generate()
//  Generates a new cryptographically secure CSRF token and
//  stores it in the session with a timestamp.
//
//  @return string  The generated token (hex string)
// ══════════════════════════════════════════════════════════════
function csrf_generate(): string
{
    $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));

    $_SESSION[CSRF_TOKEN_NAME] = [
        'token'      => $token,
        'created_at' => time(),
    ];

    return $token;
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: csrf_get()
//  Returns the current session CSRF token, generating a new
//  one if it doesn't exist or has expired.
//
//  @return string  Active CSRF token
// ══════════════════════════════════════════════════════════════
function csrf_get(): string
{
    // Generate fresh token if none exists or if expired
    if (
        !isset($_SESSION[CSRF_TOKEN_NAME]['token']) ||
        !isset($_SESSION[CSRF_TOKEN_NAME]['created_at']) ||
        (time() - $_SESSION[CSRF_TOKEN_NAME]['created_at']) > CSRF_TOKEN_EXPIRY
    ) {
        return csrf_generate();
    }

    return $_SESSION[CSRF_TOKEN_NAME]['token'];
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: csrf_token_field()
//  Outputs a hidden HTML input field with the CSRF token.
//  Drop this inside every <form> tag.
//
//  @return string  HTML hidden input element
// ══════════════════════════════════════════════════════════════
function csrf_token_field(): string
{
    $token = csrf_get();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: csrf_verify()
//  Validates the CSRF token submitted with a POST request.
//  Terminates the request with a 403 if validation fails.
//  Call this at the top of every POST form handler.
//
//  @return void
// ══════════════════════════════════════════════════════════════
function csrf_verify(): void
{
    // Only validate POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $submitted_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $session_token   = $_SESSION[CSRF_TOKEN_NAME]['token'] ?? '';

    // hash_equals() is timing-safe — prevents timing attacks
    if (
        empty($submitted_token) ||
        empty($session_token)   ||
        !hash_equals($session_token, $submitted_token)
    ) {
        // Log the violation
        error_log("CSRF validation failed. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        http_response_code(403);
        die('
            <div style="font-family:sans-serif;text-align:center;margin-top:80px;">
                <h2 style="color:#c0392b;">&#9888; Request Blocked</h2>
                <p>Invalid or expired security token. Please <a href="javascript:history.back()">go back</a> and try again.</p>
            </div>
        ');
    }

    // Rotate token after successful verification (prevents reuse)
    csrf_generate();
}
?>