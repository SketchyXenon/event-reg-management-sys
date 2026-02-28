<?php
// ============================================================
//  Bcrypt Password Utility

//  HOW BCRYPT WORKS:
//  - Bcrypt is a one-way hashing algorithm — you can NEVER
//    reverse it back to plain text.
//  - Every hash includes a random "salt" so two identical
//    passwords will always produce DIFFERENT hashes.
//  - The "cost factor" controls how slow the hashing is.
//    Slower = harder for attackers to brute-force.
//  - PHP's password_hash() and password_verify() handle
//    everything automatically and safely.
// ============================================================


// ── Cost Factor ────────────────────────────────────────────────
// Range: 4 (fastest) to 31 (slowest)
// 12 is the industry-recommended sweet spot for 2024+
// Increase this as hardware gets faster over the years
define('BCRYPT_COST', 12);


// ══════════════════════════════════════════════════════════════
//  FUNCTION: hash_password()
//  Hashes a plain text password using bcrypt
//
//  @param  string $plain_password  The raw password from the form
//  @return string                  The bcrypt hash (always 60 chars)
// ══════════════════════════════════════════════════════════════
function hash_password(string $plain_password): string
{
    $hash = password_hash(
        $plain_password,
        PASSWORD_BCRYPT,
        ['cost' => BCRYPT_COST]
    );

    // password_hash() should never return false, but guard anyway
    if ($hash === false) {
        error_log("password_hash() failed unexpectedly.");
        throw new RuntimeException('Password hashing failed. Please try again.');
    }

    return $hash;
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: verify_password()
//  Checks a plain text password against a stored bcrypt hash
//
//  @param  string $plain_password  The raw password from the login form
//  @param  string $stored_hash     The hash retrieved from the database
//  @return bool                    true if match, false if not
// ══════════════════════════════════════════════════════════════
function verify_password(string $plain_password, string $stored_hash): bool
{
    // password_verify() is timing-safe — prevents timing attacks
    return password_verify($plain_password, $stored_hash);
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: needs_rehash()
//  Checks if a stored hash needs to be upgraded
//  (e.g. if you increase BCRYPT_COST in the future)
//
//  Call this after a successful login. If true, re-hash and
//  save the new hash to the database.
//
//  @param  string $stored_hash  The hash retrieved from the database
//  @return bool                 true if rehash is needed
// ══════════════════════════════════════════════════════════════
function needs_rehash(string $stored_hash): bool
{
    return password_needs_rehash(
        $stored_hash,
        PASSWORD_BCRYPT,
        ['cost' => BCRYPT_COST]
    );
}


// ══════════════════════════════════════════════════════════════
//  FUNCTION: validate_password_strength()
//  Validates password meets minimum security requirements
//  BEFORE hashing — run this on the registration form input
//
//  Rules:
//    - At least 8 characters
//    - At least 1 uppercase letter (A-Z)
//    - At least 1 lowercase letter (a-z)
//    - At least 1 number (0-9)
//    - At least 1 special character (!@#$%^&* etc.)
//
//  @param  string $password   The raw password to check
//  @return array              ['valid' => bool, 'errors' => string[]]
// ══════════════════════════════════════════════════════════════
function validate_password_strength(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter (a-z).';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number (0-9).';
    }

    if (!preg_match('/[\W_]/', $password)) {
        $errors[] = 'Password must contain at least one special character (e.g. !@#$%^&*).';
    }

    return [
        'valid'  => empty($errors),
        'errors' => $errors,
    ];
}