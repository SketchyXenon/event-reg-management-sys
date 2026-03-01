# ERMS — Event Registration & Management System

> A full-stack web application built for **Cebu Technological University** that enables administrators to create and manage campus events while allowing students to discover, register for, and track their event participation — all through a clean, role-aware interface.

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [System Architecture](#system-architecture)
- [Features](#features)
  - [Public Landing Page](#public-landing-page)
  - [Authentication System](#authentication-system)
  - [Student Panel](#student-panel)
  - [Admin Panel](#admin-panel)
- [Database Schema](#database-schema)
- [Security Implementation](#security-implementation)
- [UI & Design System](#ui--design-system)
- [Project Structure](#project-structure)
- [Installation & Setup](#installation--setup)
- [Known Limitations](#known-limitations)
- [Future Development](#future-development)
- [Credits](#credits)

---

## Overview

ERMS is a **midterm group project** developed to solve a real institutional problem: managing event registrations manually across departments is error-prone, slow, and unscalable. This system centralizes the entire lifecycle of a campus event — from creation and publication to student registration and admin oversight — in one secure web application.

The system supports two distinct user roles with completely separate panels, each tailored to their responsibilities. Admins have full control over the event catalog, user management, and registration records. Students get a clean portal to browse upcoming events, register with a single click, and track their participation history.

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.x (procedural, no framework) |
| **Database** | MySQL 8 / MariaDB 10.3+ via PDO |
| **Frontend** | Vanilla HTML5, CSS3, JavaScript (ES6) |
| **Fonts** | Playfair Display, Source Sans 3, JetBrains Mono (Google Fonts) |
| **Email** | SendGrid API (transactional password reset emails) |
| **Server** | Apache via XAMPP (local development) |
| **Auth** | PHP sessions + bcrypt password hashing |

No frontend frameworks (React, Vue, etc.) or backend frameworks (Laravel, Symfony, etc.) were used. Everything is hand-built from scratch.

---

## System Architecture

```
event-reg-management-sys/
└── erms/
    ├── index.php                  ← Public homepage
    ├── login.php                  ← Unified login (student & admin)
    ├── register.php               ← Student self-registration
    ├── admin-register.php         ← Admin account creation
    ├── forgot-password.php        ← Password reset request
    ├── reset-password.php         ← Token-based password reset
    ├── dashboard.php              ← Student dashboard
    ├── events.php                 ← Student event browser
    ├── my-registrations.php       ← Student registration history
    ├── profile.php                ← Student profile & password
    │
    ├── admin/
    │   ├── dashboard.php          ← Admin overview & stats
    │   ├── events.php             ← Event CRUD management
    │   ├── users.php              ← User management & roles
    │   ├── registrations.php      ← Registration oversight
    │   ├── categories.php         ← Event category management
    │   ├── admin-register.php     ← Create admin accounts
    │   └── partials/
    │       └── sidebar.php        ← Shared admin sidebar
    │
    ├── assets/
    │   ├── css/global.css         ← Master stylesheet (~1000 lines)
    │   └── js/global.js           ← Master JavaScript
    │
    ├── admin/assets/
    │   ├── css/admin.css          ← Admin-specific styles
    │   └── js/admin.js            ← Admin-specific JS
    │
    └── backend/
        ├── db_connect.php         ← PDO connection singleton
        ├── auth_guard.php         ← require_login() / require_admin()
        ├── csrf_helper.php        ← CSRF token generation & validation
        ├── login_limiter.php      ← Rate limiting & lockout logic
        ├── password_helper.php    ← bcrypt hashing & validation
        ├── logout.php             ← Session destruction
        ├── security_headers.php   ← HTTP security headers
        └── paginate.php           ← Reusable pagination helper
```

**Asset load order:**
```
Student pages:   global.css → global.js
Admin pages:     global.css → admin.css → global.js → admin.js → [page inline]
Auth pages:      global.css → global.js → [page inline]
```

---

## Features

### Public Landing Page

- **Hero section** with animated gradient background and a clear call-to-action
- **Live events preview** — shows upcoming active events pulled directly from the database, with category pills, date/venue info, and slot availability progress bars
- **How It Works** section — 3-step visual walkthrough for new users
- **Responsive navigation bar** — grows to 80px with full institution branding (CTU crest, full system name, tagline) that mirrors the footer for visual consistency
- **Dark/Light theme toggle** — persists via `localStorage`, applied on `<html data-theme>` for instant, flash-free switching
- Footer with matching CTU branding and site navigation links

---

### Authentication System

A secure, multi-layered auth flow covering the full account lifecycle:

**Login (`login.php`)**
- Single unified login page for both students and admins — role is read from the database and redirects accordingly
- Failed login tracking with configurable lockout (e.g. 5 attempts → 15-minute lockout)
- Locked account messaging with remaining lockout time displayed
- CSRF token validation on every POST submission
- Session regeneration on successful login to prevent session fixation attacks

**Registration (`register.php`)**
- Full name, student ID (7-digit format enforced), email, password, confirm password
- Real-time password strength meter — fills red while under 8 characters, snaps to solid green at 8+ characters
- Duplicate email and student ID detection with inline error messaging
- `minlength="8"` HTML attribute for browser-level enforcement before PHP validation

**Forgot / Reset Password**
- Student requests reset by email → secure token generated with `bin2hex(random_bytes(32))`
- Token stored in `password_resets` table with expiry timestamp
- Reset link delivered via **SendGrid** transactional email
- Token validated server-side on reset; single-use and time-limited

**Password Security**
- All passwords hashed with **bcrypt** at cost factor 12 (industry standard for 2024+)
- `password_needs_rehash()` checked on login for automatic cost upgrades
- Plain-text passwords are never logged, stored, or transmitted

---

### Student Panel

A dedicated portal at `erms/*.php` (non-admin routes), accessible only to authenticated students.

**Dashboard (`dashboard.php`)**
- Personalized greeting (Good morning / afternoon / evening) based on system time
- 4 stat cards: Total Registered, Confirmed, Pending, Upcoming
- *My Events* section — latest 6 registrations with status badges and progress bars
- *Discover Events* section — 6 upcoming events the student hasn't registered for yet
- Realtime sidebar clock showing HH:MM:SS and full date

**Browse Events (`events.php`)**
- Grid layout of all active events with full details per card (title, description, category, date, venue, slot progress bar)
- **Filter tabs** — All / Available / Registered (functional: updates via form submission with a single hidden input to avoid name-collision bug)
- **Search** — by event title or venue with 500ms debounce
- **Category dropdown** and **Sort** (Earliest First / Most Slots)
- Count chips showing Total / Registered / Available counts
- **Detail modal** — full event info on click, dynamically shows Register / Already Registered / Full state
- **Register modal** — confirmation step with slot warning if nearly full
- Pagination for large event catalogs

**My Registrations (`my-registrations.php`)**
- Full history of all registrations with status badges (Confirmed / Pending / Cancelled)
- Filter tabs by status, search by event name
- Cancel registration with confirmation modal
- Empty state messaging when no registrations exist

**Profile (`profile.php`)**
- **Profile hero card** — avatar initial, full name, email, student ID, member-since date, and 3 inline mini-stats (Total / Confirmed / Upcoming)
- **Two-column layout**: Personal Information form (name + email, student ID locked) and Change Password form (current, new, confirm with strength meter)
- Password visibility toggles on all three password fields
- Student ID is non-editable with admin contact guidance

---

### Admin Panel

A fully separate panel at `erms/admin/*.php`, accessible only to users with `role = 'admin'`. All routes protected by `require_admin()`.

**Dashboard (`admin/dashboard.php`)**
- Summary stats: Total Events, Active Events, Total Users, Total Registrations
- Recent activity timeline
- Quick-access cards for each management section
- Shared sidebar with realtime clock, active page highlight, and admin user card at bottom

**Manage Events (`admin/events.php`)**
- 4-column stat bar: Total Events / Active / Upcoming / Full
- Full data table with search, category filter, status filter
- Inline status badges (Active / Inactive / Cancelled) and enrollment progress
- **Create Event modal** — title, description, venue, date/time, max slots, category, status
- **Edit Event modal** — pre-populated with existing data
- **Delete** with confirmation prompt
- Pagination with configurable page size

**Manage Users (`admin/users.php`)**
- Searchable, filterable user table (by role, status, search query)
- Role management modal — promote student to admin or demote admin to student
- Account activation / deactivation (soft-disable without deleting)
- Registration count per user visible inline

**Registrations (`admin/registrations.php`)**
- Full registration log across all events and students
- Filter by event, status, and date range
- Status update (Confirmed → Pending → Cancelled) with instant feedback
- Student and event details shown inline per row

**Categories (`admin/categories.php`)**
- Create, rename, and delete event categories
- Deletion blocked if any events reference the category (referential integrity)

---

## Database Schema

Seven tables with full relational integrity:

| Table | Purpose | Key Columns |
|---|---|---|
| `event_categories` | Event classification | `category_id`, `category_name` |
| `users` | All accounts | `user_id`, `student_id`, `email`, `role`, `is_active`, `failed_attempts`, `locked_until` |
| `events` | Event catalog | `event_id`, `title`, `venue`, `date_time`, `max_slots`, `status` (active/inactive/cancelled) |
| `registrations` | Student ↔ event bridge | `registration_id`, `user_id`, `event_id`, `status` (confirmed/pending/cancelled) |
| `admin_logs` | Admin audit trail | `log_id`, `admin_id`, `action`, `target_type`, `target_id`, `created_at` |
| `login_attempts` | Rate limiting | `attempt_id`, `email`, `ip_address`, `attempted_at` |
| `password_resets` | Reset tokens | `token`, `user_id`, `expires_at`, `used_at` |

**Character set:** `utf8mb4` with `utf8mb4_unicode_ci` collation throughout — supports full Unicode including emoji and multilingual names.

---

## Security Implementation

| Threat | Mitigation |
|---|---|
| **SQL Injection** | 100% PDO prepared statements — no raw string interpolation in queries |
| **CSRF Attacks** | Synchronizer token pattern — every POST form includes `csrf_token_field()`, validated server-side via `csrf_verify()` |
| **XSS** | All user-generated output passed through `htmlspecialchars()` before rendering |
| **Brute Force** | `login_limiter.php` tracks failed attempts per email; account locked after threshold with time-based expiry |
| **Session Fixation** | `session_regenerate_id(true)` called on every successful login |
| **Password Storage** | bcrypt at cost 12 via `password_hash()` — never stored plain, never logged |
| **Privilege Escalation** | `require_login()` and `require_admin()` guards on every protected route — role checked from session on every request |
| **Clickjacking** | `X-Frame-Options: DENY` header set via `security_headers.php` |
| **MIME Sniffing** | `X-Content-Type-Options: nosniff` header applied globally |

---

## UI & Design System

The entire UI is built on a custom design system defined in `assets/css/global.css` (~1000 lines across 35 sections).

**Theme system**
- Dark and light themes via CSS custom properties on `[data-theme="dark"]` and `[data-theme="light"]` selectors on `<html>`
- Toggle persisted in `localStorage` for zero-flash on page load
- All colors, backgrounds, borders, and shadows defined as CSS variables — no hardcoded values in component styles

**Typography**
- **Playfair Display** — display headings, card titles, stat values (serif, editorial weight)
- **Source Sans 3** — all body text, labels, navigation (clean, readable)
- **JetBrains Mono** — sidebar clock, code-adjacent values (monospace, technical feel)

**Component library** (all in `global.css`):
- Buttons: `.btn`, `.btn-primary`, `.btn-outline`, `.btn-gold`, `.btn-ghost`
- Cards: `.card`, `.card-header`, `.card-body`, `.card-title`
- Forms: `.form-group`, `.form-label`, `.form-control`, `.input-wrap`, `.input-icon`, `.input-hint`, `.field-error`
- Alerts: `.alert`, `.alert-success`, `.alert-error` with `data-auto-dismiss`
- Badges: `.badge`, `.badge-blue`, `.badge-green`, `.badge-gold`, `.badge-red`
- Progress bars: `.progress`, `.progress-bar`
- Modals: `.modal`, `.modal-overlay`, `.modal-content`, `.modal-header`, `.modal-footer`
- Sidebar: `.sidebar`, `.sidebar-nav`, `.nav-item`, `.nav-icon`, `.sidebar-footer`
- Filter tabs: `.filter-tabs`, `.filter-tab`, `.filter-tab.active`
- Pagination: `.pagination`, `.page-btn`

**Sidebar realtime clock** — present on all authenticated pages (student and admin), updates every second via JavaScript `setInterval`, showing `HH:MM:SS` and full `Day, Mon DD YYYY` format.

---

## Installation & Setup

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (PHP 8.0+, MySQL, Apache)
- A SendGrid account and API key (for password reset emails)

### Steps

**1. Clone / copy the project**
```bash
# Place inside your XAMPP htdocs folder
C:\xampp\htdocs\event-reg-management-sys\erms\
```

**2. Import the database**
```
phpMyAdmin → New database → event_registration_db
Import tab → Select event_registration_db.sql → Go
```

**3. Configure the database connection**
```php
// backend/db_connect.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'event_registration_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // your MySQL password
```

**4. Configure SendGrid (for password reset)**
```php
// backend/forgot-password.php (or config.php)
define('SENDGRID_API_KEY', 'SG.your_key_here');
define('MAIL_FROM',        'noreply@yourdomain.com');
define('APP_URL',          'http://localhost/event-reg-management-sys/erms');
```

**5. Create an admin account**
```
Visit: http://localhost/event-reg-management-sys/erms/admin-register.php
```

**6. Launch the app**
```
http://localhost/event-reg-management-sys/erms/
```

---

## Known Limitations

- **No file uploads** — event images/attachments are not supported; events display text-only
- **No real-time updates** — registration counts and slot availability require a page refresh to update
- **Single-institution scope** — no multi-tenant support; built for one organization
- **No email notifications to students** — students do not receive confirmation emails when registering; only password resets are emailed
- **No QR code / attendance tracking** — registration is recorded digitally but there is no check-in system at the physical event
- **XAMPP-only tested** — not hardened for production server deployment (no HTTPS enforcement, no reverse proxy config)

---

## Future Development

The architecture is clean and modular enough that these features can be added incrementally without restructuring the existing codebase:

### Near-term Enhancements
- **Email notifications** — send registration confirmation emails to students using the existing SendGrid integration; extend to reminder emails 24 hours before an event
- **Event image uploads** — add a cover photo per event stored in `/uploads/events/` with server-side validation (type, size, dimensions)
- **Student registration certificates** — generate a downloadable PDF certificate of participation per event using a PDF library
- **Export to CSV/Excel** — allow admins to export registration lists per event for reporting purposes
- **Admin audit log viewer** — surface the existing `admin_logs` table in a dedicated UI page with filter and search

### Medium-term Features
- **QR code check-in** — generate a unique QR code per registration; admins scan on event day to mark attendance, feeding an `attended` boolean on the `registrations` table
- **Waitlist system** — when an event is full, allow students to join a waitlist; auto-promote when a slot opens up due to cancellation
- **Event comments / Q&A** — a simple threaded comment section per event for students to ask questions visible to all attendees
- **Recurring events** — support weekly/monthly recurrence patterns so admins don't have to create the same event manually each time
- **Dashboard analytics charts** — integrate Chart.js or ApexCharts to visualize registration trends over time, peak event periods, and category popularity

### Long-term / Architectural
- **REST API layer** — expose core functionality (events, registrations) as JSON endpoints to enable a mobile app or third-party integrations
- **Mobile app** — a companion React Native or Flutter app consuming the REST API for on-the-go registration and check-in
- **Multi-department support** — add a `departments` table so each department manages its own event catalog while a super-admin oversees all
- **Single Sign-On (SSO)** — integrate with the university's existing student information system (SIS) for unified login using OAuth2 or SAML, eliminating manual registration
- **Notifications system** — in-app notification bell with real-time updates (WebSockets or polling) for registration status changes, upcoming event reminders, and admin announcements
- **Role expansion** — introduce an `organizer` role between student and admin — a faculty member who can create events for their department but cannot manage users system-wide

---

## Credits

**Built by:** John Ray Abenasa & Development Team
**Institution:** Cebu Technological University
**Course:** Web Systems & Technologies — Midterm Group Project
**Academic Year:** 2025–2026

**Co-developed with:** Classmates and my Anon. co-pilot.

---

*ERMS is an academic project. It is not affiliated with or officially endorsed by Cebu Technological University.*