<?php
// ============================================================
//  security_headers.php
//  HTTP Security Headers
//  Event Registration Management System
//
//  HOW TO USE:
//  Include this file at the very TOP of every PHP page,
//  before any HTML output:
//
//    require_once 'backend/security_headers.php';
//
//  Or add it once inside auth_guard.php so it applies
//  automatically to all protected pages.
// ============================================================


// ══════════════════════════════════════════════════════════════
//  FUNCTION: send_security_headers()
//  Sends all recommended HTTP security headers.
//  Must be called BEFORE any output (echo, HTML, etc.)
// ══════════════════════════════════════════════════════════════
function send_security_headers(): void
{
    // ── 1. Content Security Policy (CSP) ──────────────────────
    // Controls which scripts, styles, images, and fonts
    // are allowed to load. Blocks inline script injections (XSS).
    //
    // Adjust 'script-src' and 'style-src' if you use CDNs.
    header("Content-Security-Policy: " . implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline'",      // Allow inline JS (needed for vanilla PHP forms)
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data:",
        "connect-src 'self'",
        "frame-ancestors 'none'",                 // Blocks iframes (same as X-Frame-Options)
        "form-action 'self'",                     // Forms can only submit to your own domain
        "base-uri 'self'",
    ]));

    // ── 2. X-Frame-Options ─────────────────────────────────────
    // Prevents your pages from being embedded in iframes.
    // Stops clickjacking attacks where attackers overlay
    // your site in a hidden frame to steal clicks.
    header("X-Frame-Options: DENY");

    // ── 3. X-Content-Type-Options ──────────────────────────────
    // Stops browsers from guessing (sniffing) file MIME types.
    // Prevents attacks where a malicious file is uploaded and
    // the browser executes it as a script.
    header("X-Content-Type-Options: nosniff");

    // ── 4. Referrer-Policy ─────────────────────────────────────
    // Controls how much URL info is passed when a user clicks
    // a link to another site. 'strict-origin-when-cross-origin'
    // shares full URL within your site, only the origin externally.
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // ── 5. Permissions-Policy ──────────────────────────────────
    // Disables browser features your app doesn't need.
    // Prevents hijacking of camera, mic, geolocation, etc.
    header("Permissions-Policy: " . implode(', ', [
        "camera=()",
        "microphone=()",
        "geolocation=()",
        "payment=()",
        "usb=()",
    ]));

    // ── 6. X-XSS-Protection ────────────────────────────────────
    // Legacy header for older browsers. Modern browsers use CSP,
    // but this provides a fallback for IE/Edge.
    header("X-XSS-Protection: 1; mode=block");

    // ── 7. Strict-Transport-Security (HSTS) ────────────────────
    // Forces browsers to always use HTTPS for your domain.
    // IMPORTANT: Only enable this on a live HTTPS server.
    //            Keep commented out for local XAMPP development.
    //
    // header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

    // ── 8. Cache-Control for sensitive pages ───────────────────
    // Prevents browsers from caching sensitive pages like
    // dashboards, so they aren't visible after logout via back button.
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");

    // ── 9. Remove server fingerprint headers ───────────────────
    // Hides PHP version and server info from attackers
    header_remove("X-Powered-By");
    header("Server: ");
}

// Auto-apply headers when this file is included
send_security_headers();