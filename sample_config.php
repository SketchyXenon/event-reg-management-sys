<?php
/**
 * Configuration file for CTU Danao ERMS
 * 
 * This file stores sensitive credentials and environment-specific settings.
 * It should NEVER be committed to version control (add to .gitignore).
 */
// Step 1: GOTO https://console.twilio.com/
// Step 2: Register your Account and Verify using an Email Verification and Phone Number to access their services.
// Step 3: GOTO https://console.twilio.com/us1/develop/sendgrid-email/overview
// Step 4: GOTO SendGrid Console and log in your account/ if no account create new.

// =============================================
// SendGrid API Key
// =============================================
// Replace 'PASTE_YOUR_API_KEY_HERE' with the actual API key from your SendGrid dashboard.
// Keep this key secret – do not share or commit it.
define('SENDGRID_API_KEY', 'YOUR_API_KEY_HERE');
// =============================================
// Future configuration options (optional)
// =============================================
// You can add more constants here as your project grows, for example:
// define('SITE_NAME', 'ERMS');
// define('BASE_URL', 'http://localhost/event-reg-management-sys');
// define('DEBUG_MODE', true);
//

?>