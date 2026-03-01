# SECURITY.md — ERMS Security Documentation

> **Event Registration & Management System**
> CTU Danao
> This document describes every security control implemented in the system, the threats each one addresses, and guidance for responsible disclosure.

---

## Table of Contents

- [Security Philosophy](#security-philosophy)
- [Threat Model](#threat-model)
- [Implemented Controls](#implemented-controls)
  - [1. SQL Injection Prevention](#1-sql-injection-prevention)
  - [2. Cross-Site Scripting (XSS) Prevention](#2-cross-site-scripting-xss-prevention)
  - [3. CSRF Protection](#3-csrf-protection)
  - [4. Password Security](#4-password-security)
  - [5. Brute Force & Rate Limiting](#5-brute-force--rate-limiting)
  - [6. Session Security](#6-session-security)
  - [7. HTTP Security Headers](#7-http-security-headers)
  - [8. Role-Based Access Control](#8-role-based-access-control)
  - [9. Password Reset Security](#9-password-reset-security)
  - [10. Input Validation](#10-input-validation)
- [Security Files Reference](#security-files-reference)
- [Known Limitations & Accepted Risks](#known-limitations--accepted-risks)
- [Production Hardening Checklist](#production-hardening-checklist)
- [Reporting a Vulnerability](#reporting-a-vulnerability)

---

## Security Philosophy

ERMS is built on the principle of **defense in depth** — no single control is relied upon alone. Every user-facing action is protected by multiple overlapping layers: input is validated before processing, queries are parameterized before execution, output is escaped before rendering, sessions are verified before serving protected content, and HTTP headers restrict what the browser is permitted to do.

All security controls are implemented **from scratch in PHP** without third-party security frameworks, making every decision explicit, auditable, and understood by the development team.

---

## Threat Model

The system operates in a campus intranet environment (XAMPP/Apache on localhost during development). The following threat actors and scenarios were considered during design:

| Threat Actor | Scenario | Likelihood |
|---|---|---|
| Curious student | Attempts to access admin panel or another student's data by guessing URLs | High |
| Automated bot | Runs credential stuffing or brute-force login attacks | Medium |
| Malicious student | Submits crafted form data to manipulate the database or inject scripts | Medium |
| Network eavesdropper | Intercepts unencrypted session traffic on shared campus WiFi | Medium (no HTTPS locally) |
| Insider threat | An admin abuses elevated privileges | Low |
| External attacker | Remote exploitation of web vulnerabilities | Low (local deployment) |

Out-of-scope threats (not addressed in current version): server-level OS exploits, physical access, supply-chain attacks on PHP/MySQL, and DDoS.

---

## Implemented Controls

### 1. SQL Injection Prevention

**File:** All database-facing PHP files via `backend/db_connect.php`

**Threat:** An attacker submits malicious SQL syntax inside a form field (e.g., `' OR '1'='1`) to bypass authentication, extract data, or destroy the database.

**Implementation:**
Every single database query in the system uses **PDO prepared statements** with bound parameters. Raw string interpolation into SQL is never used.

```php
// ✅ CORRECT — parameterized, safe
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ NEVER used — vulnerable to injection
$result = $pdo->query("SELECT * FROM users WHERE email = '$email'");
```

PDO is configured with `PDO::ATTR_EMULATE_PREPARES => false`, which forces the use of **real server-side prepared statements** (not client-side emulation), providing the strongest possible protection against injection even with unusual character sets.

```php
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,  // real prepared statements
];
```

**Coverage:** 100% of queries across all 16 PHP files that interact with the database.

---

### 2. Cross-Site Scripting (XSS) Prevention

**File:** All page-rendering PHP files

**Threat:** An attacker stores malicious JavaScript in the database (e.g., in an event title or their own name) that executes in other users' browsers — stealing session cookies, redirecting users, or defacing the page.

**Implementation:**
All user-generated content is passed through `htmlspecialchars()` with `ENT_QUOTES` and `UTF-8` encoding before being output to the browser. This converts characters like `<`, `>`, `"`, and `'` into their HTML entity equivalents, rendering them harmless.

```php
// Every output of user data follows this pattern:
<?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
<?= htmlspecialchars($event['title'],    ENT_QUOTES, 'UTF-8') ?>
```

Additionally, the **Content Security Policy** header (see [HTTP Security Headers](#7-http-security-headers)) acts as a second layer — even if an XSS payload were somehow injected, CSP restricts what scripts the browser is permitted to execute.

**JSON output in `onclick` attributes** uses `json_encode()` with the `ENT_QUOTES` flag to prevent attribute-injection when embedding PHP data in JavaScript event handlers:

```php
onclick='openDetail(<?= htmlspecialchars(json_encode($data), ENT_QUOTES) ?>)'
```

---

### 3. CSRF Protection

**File:** `backend/csrf_helper.php`

**Threat:** A malicious website tricks a logged-in user's browser into silently submitting a forged request to ERMS (e.g., cancelling their registrations, changing their email) without their knowledge.

**Implementation:**
The **Synchronizer Token Pattern** is applied to every state-changing POST form in the system.

**How it works:**

1. When a page with a form loads, `csrf_get()` generates or retrieves a cryptographically secure 128-character hex token stored in the user's session.
2. `csrf_token_field()` embeds the token as a hidden input in every form.
3. On POST submission, `csrf_verify()` compares the submitted token against the session token using `hash_equals()` — a **timing-safe** comparison that prevents timing attacks.
4. If the tokens don't match, the request is rejected with HTTP 403 and the violation is logged.
5. After each successful verification, the token is **rotated** (`csrf_generate()` is called again) to prevent token reuse attacks.

```php
// Token generation — 64 random bytes = 128 hex chars
$token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));

// Timing-safe comparison — prevents timing oracle attacks
if (!hash_equals($session_token, $submitted_token)) {
    error_log("CSRF validation failed. IP: " . $_SERVER['REMOTE_ADDR']);
    http_response_code(403);
    die('Request Blocked');
}
```

**Token settings:**
- Length: 64 bytes → 128 hex characters (512 bits of entropy)
- Expiry: 1 hour (`CSRF_TOKEN_EXPIRY = 3600`)
- Scope: Per-session (not per-form, for usability in tabbed browsing)

**Coverage:** All POST forms — login, register, profile update, password change, event registration/cancellation, all admin CRUD operations.

---

### 4. Password Security

**File:** `backend/password_helper.php`

**Threat:** Password database breach — if the database is stolen, plaintext or weakly hashed passwords allow attackers to compromise user accounts and potentially other accounts where the same password is reused.

**Implementation:**

**Storage — bcrypt hashing:**
Passwords are hashed using PHP's `password_hash()` with `PASSWORD_BCRYPT` at cost factor 12. bcrypt is a **deliberately slow** adaptive hashing algorithm designed to make brute-force attacks computationally expensive.

```php
define('BCRYPT_COST', 12);  // industry-recommended for 2024+

function hash_password(string $plain_password): string {
    return password_hash($plain_password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}
```

Key properties of this implementation:
- Every hash includes a **random salt** generated automatically — two identical passwords always produce different hashes
- The cost factor (12) means each hash takes ~250ms to compute on modern hardware, making bulk cracking extremely slow
- Hashes are 60 characters and stored in `VARCHAR(255)` columns, leaving room for future algorithm upgrades
- Plain-text passwords are **never logged, stored, echoed, or transmitted** — they exist in memory only for the duration of the hash/verify operation

**Verification — timing-safe comparison:**
`password_verify()` is used for all login checks. It is internally timing-safe, preventing timing side-channel attacks where an attacker measures response time differences to infer partial matches.

**Automatic rehashing:**
`needs_rehash()` is called after every successful login. If the stored hash was created with a lower cost factor (e.g., before a future upgrade), the password is silently rehashed at the new cost and the database is updated — no user action required.

```php
function needs_rehash(string $stored_hash): bool {
    return password_needs_rehash($stored_hash, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}
```

**Minimum length enforcement — two layers:**
- HTML: `minlength="8"` on the password input field (browser-level, first line)
- PHP: `validate_password_strength()` checks `strlen($password) >= 8` before hashing (server-level, authoritative)

The PHP check is always the authoritative one — the HTML attribute is a UX aid only.

---

### 5. Brute Force & Rate Limiting

**File:** `backend/login_limiter.php`

**Threat:** An automated script submits thousands of password guesses against a known email address to eventually find the correct password.

**Implementation:**
A **progressive lockout schedule** is applied at two levels simultaneously: per-account (email) and per-network (IP address).

**Account-level lockout (email-based):**

| Failed Attempts | Lockout Duration |
|---|---|
| 1–2 | No lockout — warning shown |
| 3 | 1 minute |
| 4 | 5 minutes |
| 5 | 15 minutes |
| 6 | 30 minutes |
| 7+ | 1 hour |

The lockout expiry is stored as a `locked_until` timestamp in the `users` table. On each login attempt, `is_account_locked()` checks this timestamp — no cron job or background process required.

```php
const LOCKOUT_SCHEDULE = [
    3 => 60,    // 1 minute
    4 => 300,   // 5 minutes
    5 => 900,   // 15 minutes
    6 => 1800,  // 30 minutes
    7 => 3600,  // 1 hour
];
```

After a successful login, `clear_lockout()` resets `failed_attempts` to 0 and sets `locked_until` to NULL.

**Network-level lockout (IP-based):**
A separate check via `is_ip_blocked()` monitors the `login_attempts` table. If a single IP address accumulates 20 failed attempts within a 15-minute sliding window, all login attempts from that IP are blocked — regardless of which email is being targeted. This stops **credential stuffing** attacks that rotate across many email addresses.

```php
const IP_MAX_ATTEMPTS    = 20;
const IP_WINDOW_SECONDS  = 900;  // 15 minutes
```

**Pre-lockout warnings:**
`remaining_attempts_message()` calculates how many attempts remain before the next lockout threshold and shows the user a specific warning (e.g., "Warning: 1 more failed attempt will lock your account for 15 minutes."). This reduces legitimate user frustration while still deterring attackers.

**Audit logging:**
Every login attempt (success or failure) is written to the `login_attempts` table with the email, IP address, result, and timestamp — providing a full forensic trail for security review.

---

### 6. Session Security

**File:** `backend/auth_guard.php`

**Threat 1 — Session Hijacking:** An attacker steals a valid session cookie (via network sniffing or XSS) and uses it to impersonate a logged-in user.

**Threat 2 — Session Fixation:** An attacker sets a known session ID before login, then uses it after the victim logs in to gain authenticated access.

**Threat 3 — Stale Sessions:** A user walks away from a shared computer leaving an authenticated session open.

**Implementation:**

**Secure cookie configuration:**
```php
session_set_cookie_params([
    'lifetime' => 0,        // Expires when browser closes (no persistent cookie)
    'path'     => '/',
    'secure'   => false,    // Set TRUE on production HTTPS server
    'httponly' => true,     // JS cannot access the cookie (blocks XSS cookie theft)
    'samesite' => 'Lax',   // Blocks cross-site request cookie sending
]);
```

`HttpOnly` is the most important flag here — it prevents JavaScript from reading the session cookie, which blocks the most common XSS-based session theft vector even if a script injection were to occur.

`SameSite: Lax` ensures the cookie is not sent with cross-origin POST requests, providing a secondary layer of CSRF protection on top of the token system.

**Session fixation prevention:**
`session_regenerate_id(true)` is called immediately after every successful login, invalidating the pre-login session ID and issuing a new one. This ensures an attacker who knew the pre-login session ID cannot use it post-authentication.

**Idle timeout (30 minutes):**
```php
define('SESSION_IDLE_TIMEOUT', 30 * 60);  // 30 minutes

if (($now - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: {$login_url}?session=timeout");
    exit();
}
```

**Absolute maximum session length (8 hours):**
```php
define('SESSION_ABSOLUTE_MAX', 8 * 3600);  // 8 hours
```

Even if a user is continuously active, sessions are hard-expired after 8 hours. This limits the damage window if a session token is ever compromised.

**Role verification on every request:**
`$_SESSION['role']` is read from the session on every page load — not from a hidden form field or URL parameter — and checked against the required role for that page. Changing a URL parameter or form value cannot elevate privileges.

---

### 7. HTTP Security Headers

**File:** `backend/security_headers.php`

Applied automatically to every page via `auth_guard.php` (which includes `security_headers.php`). Sends the following headers before any output:

| Header | Value | Purpose |
|---|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' fonts.googleapis.com; font-src 'self' fonts.gstatic.com; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'` | Restricts what resources the browser loads and from where; blocks most XSS execution paths; prevents iframe embedding |
| `X-Frame-Options` | `DENY` | Legacy clickjacking prevention (complements CSP `frame-ancestors`) |
| `X-Content-Type-Options` | `nosniff` | Prevents browsers from MIME-sniffing responses — stops execution of uploaded files misidentified as scripts |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Sends full URL for same-origin links, only origin for cross-origin — prevents leaking sensitive URL parameters to third parties |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=()` | Disables browser APIs the app doesn't need — prevents a compromised script from silently accessing hardware |
| `X-XSS-Protection` | `1; mode=block` | Legacy XSS filter for older browsers (modern browsers use CSP instead) |
| `Cache-Control` | `no-store, no-cache, must-revalidate` | Prevents browsers and proxies from caching authenticated pages — hitting Back after logout does not show cached sensitive content |
| `X-Powered-By` | *(removed)* | Hides PHP version from attackers performing fingerprinting |
| `Server` | *(cleared)* | Hides Apache version from attackers |

**Note:** `Strict-Transport-Security (HSTS)` is defined in the code but commented out, as it must only be sent over a live HTTPS connection. The comment includes instructions for enabling it in production.

---

### 8. Role-Based Access Control

**File:** `backend/auth_guard.php`

**Threat:** A student attempts to access admin-only pages, manage other users' data, or escalate their own privileges.

**Implementation:**
Two guard functions are called at the very top of every protected page, before any processing occurs:

```php
// Student pages — any logged-in user
require_login('login.php');

// Admin pages — only role='admin'
require_admin('login.php');
```

`require_admin()` first calls `require_login()`, then explicitly checks `$_SESSION['role'] !== 'admin'` and redirects with HTTP 403 if the check fails. The role is read from the server-side session — it cannot be manipulated by the client.

```php
function require_admin(string $login_url = '../login.php'): void {
    require_login($login_url);
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        header("Location: {$login_url}?error=forbidden");
        exit();
    }
}
```

**Data isolation:** Students can only query their own registrations. All student-facing queries include `WHERE user_id = ?` bound to `$_SESSION['user_id']` — a student cannot view or modify another student's records by manipulating URL parameters or POST data.

**Soft account disabling:** Admins can deactivate student accounts (`is_active = 0`) without deleting them. The login flow checks `is_active` and rejects login attempts from deactivated accounts.

---

### 9. Password Reset Security

**Files:** `forgot-password.php`, `reset-password.php`, `backend/db_connect.php` (via SendGrid)

**Threat:** Account takeover through an insecure password reset flow — guessable tokens, reusable tokens, or tokens that never expire.

**Implementation:**

**Cryptographically secure token generation:**
```php
$token = bin2hex(random_bytes(32));  // 32 bytes = 64 hex chars = 256 bits entropy
```
`random_bytes()` uses the operating system's cryptographically secure random number generator (CSPRNG) — not `rand()` or `mt_rand()`. A 256-bit token has `2^256` possible values, making brute-force guessing computationally infeasible.

**Storage:**
The token and its expiry timestamp are stored in the `password_resets` table. The token itself (not a hash of it) is stored, which is acceptable given the short expiry window. A future hardening step would be to store `hash('sha256', $token)` and compare hashes at verification time.

**Single-use enforcement:**
After a successful password reset, `used_at` is set to the current timestamp. The reset flow rejects any token where `used_at IS NOT NULL`.

**Time-limited:**
Tokens expire after a configurable window (default: 1 hour). The reset flow checks `expires_at > NOW()` and rejects expired tokens.

**Delivery via SendGrid:**
The reset link is delivered through the SendGrid transactional email API over HTTPS. The raw token is never logged — only the email address of the requester is logged for audit purposes.

**Enumeration protection:**
The forgot-password form always displays the same success message regardless of whether the submitted email exists in the database, preventing user enumeration via different response messages.

---

### 10. Input Validation

**Files:** `register.php`, `profile.php`, `login.php`, and all admin CRUD handlers

**Threat:** Malformed, oversized, or logically invalid input causes application errors, data corruption, or unexpected behavior.

**Implementation:**

All user input is validated **server-side in PHP** before any database interaction. HTML attributes (`required`, `maxlength`, `minlength`, `type="email"`) provide a first-pass UX layer but are never the authoritative check.

| Input | Validation Applied |
|---|---|
| Email | `filter_var($email, FILTER_VALIDATE_EMAIL)` + uniqueness check via prepared query |
| Student ID | Regex: `/^\d{7}$/` — exactly 7 digits |
| Full name | `strlen()` check: 2–100 characters |
| Password | `strlen($password) >= 8` |
| Event dates | Compared against `NOW()` in SQL to prevent past-date registrations |
| Registration actions | `user_id` always verified against session — no client-supplied user ID accepted |
| Category deletions | Referential integrity check — blocked if events reference the category |

**Type casting:** Integer inputs from URL parameters (`$_GET['page']`, `$_GET['event_id']`) are explicitly cast with `(int)` before use, converting any non-numeric input to 0 harmlessly before it reaches the query.

```php
$page     = max(1, (int)($_GET['page'] ?? 1));
$event_id = (int)($_GET['event_id'] ?? 0);
```

---

## Security Files Reference

| File | Responsibility |
|---|---|
| `backend/security_headers.php` | Sends all HTTP security headers on every page load |
| `backend/auth_guard.php` | Session management, idle/absolute timeout, `require_login()`, `require_admin()` |
| `backend/csrf_helper.php` | CSRF token generation, embedding, verification, rotation |
| `backend/login_limiter.php` | Progressive account lockout, IP-level rate limiting, audit logging |
| `backend/password_helper.php` | bcrypt hashing, verification, rehash detection |
| `backend/db_connect.php` | PDO connection with real prepared statements; sets PHP + MySQL timezone |

---

## Known Limitations & Accepted Risks

The following are acknowledged gaps accepted for the scope of this academic project:

| Limitation | Risk | Mitigation Recommended for Production |
|---|---|---|
| **No HTTPS** | Session cookies and credentials transmitted in plaintext on the network | Enable SSL/TLS on Apache; set `secure => true` on session cookie; enable HSTS header |
| **CSRF token not per-form** | A valid token from one form can be submitted with another form | Implement per-form token binding if stricter CSRF protection is needed |
| **Password reset token stored unhashed** | If the database is breached, reset tokens are directly usable | Store `hash('sha256', $token)` and compare hashes at verification |
| **No email verification on registration** | Anyone can register with any email address | Send verification email on registration; block login until verified |
| **No audit log UI** | `admin_logs` table is populated but not surfaced in any admin page | Build a dedicated audit trail viewer for admins |
| **`secure` session cookie disabled** | Cookies sent over HTTP as well as HTTPS | Set `'secure' => true` when deploying to an HTTPS server |
| **No rate limiting on registration** | Bots can create large numbers of student accounts | Add CAPTCHA or email verification to the registration flow |
| **Server headers not fully hidden** | `Server:` header cleared but Apache's `ServerTokens` config may still expose version info | Set `ServerTokens Prod` and `ServerSignature Off` in `httpd.conf` |

---

## Production Hardening Checklist

Before deploying to a live server, complete the following:

- [ ] Enable SSL/TLS — obtain a certificate (Let's Encrypt is free)
- [ ] Set `'secure' => true` in `session_set_cookie_params()` in `auth_guard.php`
- [ ] Uncomment the `Strict-Transport-Security` header in `security_headers.php`
- [ ] Set `DB_PASS` to a strong, unique MySQL password — not the XAMPP default empty string
- [ ] Create a dedicated MySQL user with only the permissions ERMS needs (`SELECT`, `INSERT`, `UPDATE`, `DELETE` on `event_registration_db` only — no `DROP`, `CREATE`, `GRANT`)
- [ ] Move `backend/` outside the web root, or protect it with Apache `Deny from all`
- [ ] Set `PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT` in production (errors currently throw exceptions that could expose stack traces)
- [ ] Configure `error_reporting(0)` and `display_errors = Off` in `php.ini`
- [ ] Set `ServerTokens Prod` and `ServerSignature Off` in Apache config
- [ ] Replace the SendGrid API key with an environment variable — never hardcode credentials
- [ ] Run a full dependency and PHP version audit before go-live
- [ ] Implement HTTPS redirect in `.htaccess` to prevent accidental HTTP access

---

## Reporting a Vulnerability

This is an academic project. If you find a security issue:

1. **Do not** open a public GitHub issue for vulnerability reports.
2. Contact the development team directly via the institutional email.
3. Provide a clear description of the issue, steps to reproduce, and the potential impact.
4. Allow reasonable time for the team to assess and address the issue before any public disclosure.

We appreciate responsible disclosure and will acknowledge all valid reports.

---

*This document was written alongside the implementation and reflects the actual security architecture of ERMS as built. Last updated: March 2026.*