<?php
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/security_headers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role    = $_SESSION['role'] ?? '';

// Safely fetch events — works regardless of event_categories structure
$featured = [];
try {
    $featured = $pdo->query(
      "SELECT e.event_id, e.title, e.description, e.date_time, e.venue,
              e.max_slots, e.category_id,
              COUNT(r.registration_id) AS enrolled,
              NULL AS category_name
       FROM events e
       LEFT JOIN registrations r ON r.event_id = e.event_id AND r.status != 'cancelled'
       WHERE e.date_time >= CURDATE() AND e.date_time >= CURDATE()
       GROUP BY e.event_id
       ORDER BY e.date_time ASC
       LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $featured = []; }

// Stats for counter section
$stat_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$stat_events   = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$stat_regs     = $pdo->query("SELECT COUNT(*) FROM registrations WHERE status='confirmed'")->fetchColumn();
try {
    $stat_cats = $pdo->query("SELECT COUNT(*) FROM event_categories")->fetchColumn();
} catch (PDOException $e) { $stat_cats = 0; }
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ERMS — Event Registration & Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="assets/css/global.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

<!-- ══ NAV ═══════════════════════════════════════════════════ -->
<nav class="nav" id="navbar">
  <div class="nav-brand">
    <div class="nav-crest">E</div>
    <div class="nav-name">ERM<span>S</span></div>
  </div>

  <ul class="nav-links">
    <li><a href="#events">Events</a></li>
    <li><a href="#how-it-works">How It Works</a></li>
    <?php if ($is_logged_in): ?>
      <?php if ($user_role === 'admin'): ?>
        <li><a href="admin/dashboard.php">Admin Panel</a></li>
      <?php else: ?>
        <li><a href="dashboard.php">My Dashboard</a></li>
      <?php endif; ?>
    <?php endif; ?>
  </ul>

  <div class="nav-actions">
    <button class="theme-toggle-btn" id="themeToggle" aria-label="Toggle theme"><span id="themeIcon">☀️</span></button>
    <?php if ($is_logged_in): ?>
      <?php $dash = $user_role === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'; ?>
      <a href="<?= $dash ?>" class="btn btn-outline">Go to Dashboard</a>
      <a href="backend/logout.php" class="btn btn-primary">Sign Out</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-outline">Sign In</a>
      <a href="register.php" class="btn btn-gold">Register Free</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ══ HERO ═══════════════════════════════════════════════════ -->
<section class="hero">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>

  <div class="hero-content">
    <div class="hero-eyebrow">Academic Event Management System</div>

    <h1 class="hero-title">
      Discover &amp; Register for
      <em>Events That Matter</em>
      <span class="line-accent">to Your Journey</span>
    </h1>

    <p class="hero-desc">
      Browse upcoming academic events, seminars, and workshops — then
      register in seconds. Your campus experience, organized in one place.
    </p>

    <div class="hero-actions">
      <?php if ($is_logged_in): ?>
        <a href="<?= $user_role==='admin'?'admin/dashboard.php':'dashboard.php' ?>" class="btn btn-gold btn-lg">Go to Dashboard</a>
        <a href="#events" class="btn btn-outline btn-lg">Browse Events</a>
      <?php else: ?>
        <a href="register.php" class="btn btn-gold btn-lg">Get Started — It's Free</a>
        <a href="#events" class="btn btn-outline btn-lg">Browse Events</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="hero-scroll">
    <div class="scroll-line"></div>
    Scroll
  </div>
</section>

<!-- ══ STATS BAND ═════════════════════════════════════════════ -->
<div class="stats-band">
  <div class="stats-inner">
    <div class="stat-item reveal">
      <div class="stat-num" data-target="<?= $stat_students ?>">
        <?= number_format($stat_students) ?><span class="accent">+</span>
      </div>
      <div class="stat-label-text">Students Enrolled</div>
    </div>
    <div class="stat-item reveal">
      <div class="stat-num" data-target="<?= $stat_events ?>">
        <?= number_format($stat_events) ?><span class="accent">+</span>
      </div>
      <div class="stat-label-text">Events Hosted</div>
    </div>
    <div class="stat-item reveal">
      <div class="stat-num" data-target="<?= $stat_regs ?>">
        <?= number_format($stat_regs) ?><span class="accent">+</span>
      </div>
      <div class="stat-label-text">Registrations Made</div>
    </div>
    <div class="stat-item reveal">
      <div class="stat-num" data-target="<?= $stat_cats ?>">
        <?= number_format($stat_cats) ?><span class="accent">+</span>
      </div>
      <div class="stat-label-text">Event Categories</div>
    </div>
  </div>
</div>

<!-- ══ FEATURED EVENTS ════════════════════════════════════════ -->
<section class="events-section" id="events">
  <div class="section-inner">
    <div class="events-header reveal">
      <div>
        <span class="section-label">Upcoming Events</span>
        <h2 class="section-title">Events Happening <em style="font-style:italic;color:var(--gold)">Soon</em></h2>
        <p class="section-desc">Browse and register for the latest academic and campus events.</p>
      </div>
      <?php if ($is_logged_in): ?>
        <a href="events.php" class="btn btn-outline">View All Events →</a>
      <?php else: ?>
        <a href="register.php" class="btn btn-outline">Sign up to See All →</a>
      <?php endif; ?>
    </div>

    <div class="events-grid">
      <?php if (!empty($featured)): ?>
        <?php foreach ($featured as $ev):
          $slots_left = $ev['max_slots'] - $ev['enrolled'];
          $is_full    = $slots_left <= 0;
        ?>
          <div class="event-card reveal">
            <div class="event-card-body">
              <div class="event-meta">
                <span class="event-category"><?= htmlspecialchars($ev['category_name'] ?? 'General') ?></span>
                <span class="event-slots <?= $is_full ? 'full' : '' ?>">
                  <?= $is_full ? 'Full' : $slots_left . ' slots left' ?>
                </span>
              </div>
              <div class="event-title-card"><?= htmlspecialchars($ev['title']) ?></div>
              <?php if ($ev['description']): ?>
                <div class="event-desc-card"><?= htmlspecialchars($ev['description']) ?></div>
              <?php endif; ?>
            </div>
            <div class="event-card-footer">
              <div class="event-date-loc">
                <div class="event-date">
                  📅 <?= date('M d, Y · g:i A', strtotime($ev['date_time'])) ?>
                </div>
                <div class="event-venue">
                  📍 <?= htmlspecialchars($ev['venue']) ?>
                </div>
              </div>
              <?php if ($is_logged_in): ?>
                <a href="events.php?id=<?= $ev['event_id'] ?>"
                   class="event-register-btn <?= $is_full ? 'disabled' : '' ?>">
                  <?= $is_full ? 'Full' : 'Register →' ?>
                </a>
              <?php else: ?>
                <a href="login.php" class="event-register-btn">Sign In →</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-events">
          <p>No upcoming events at the moment.</p>
          <span style="font-size:0.85rem;color:var(--text-3)">Check back soon or ask your administrator.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══ HOW IT WORKS ═══════════════════════════════════════════ -->
<section class="how-section" id="how-it-works">
  <div class="section-inner">
    <div class="how-inner">
      <div>
        <span class="section-label reveal">How It Works</span>
        <h2 class="section-title reveal">Simple Steps to <em style="font-style:italic;color:var(--gold)">Get Started</em></h2>
        <p class="section-desc reveal">From sign-up to event day — the entire process takes under two minutes.</p>

        <div class="steps-list">
          <div class="step reveal">
            <div class="step-num">01</div>
            <div class="step-body">
              <div class="step-title">Create Your Account</div>
              <div class="step-desc">Register with your student ID and institutional email. Verification is instant.</div>
            </div>
          </div>
          <div class="step reveal">
            <div class="step-num">02</div>
            <div class="step-body">
              <div class="step-title">Browse Events</div>
              <div class="step-desc">Explore upcoming seminars, workshops, and campus activities across all departments.</div>
            </div>
          </div>
          <div class="step reveal">
            <div class="step-num">03</div>
            <div class="step-body">
              <div class="step-title">Register in One Click</div>
              <div class="step-desc">Select your event and confirm your slot. Your dashboard tracks everything automatically.</div>
            </div>
          </div>
          <div class="step reveal">
            <div class="step-num">04</div>
            <div class="step-body">
              <div class="step-title">Attend &amp; Engage</div>
              <div class="step-desc">Show up, participate, and build your academic portfolio event by event.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Decorative mock UI card -->
      <div class="how-visual reveal">
        <div class="how-card how-card-main">
          <div class="hc-title">
            <div class="dot"></div>
            My Registered Events
          </div>
          <div class="hc-row">
            <span class="label">Annual Science Fair</span>
            <span class="hc-badge confirmed">Confirmed</span>
          </div>
          <div class="hc-row">
            <span class="label">Tech Leadership Summit</span>
            <span class="hc-badge confirmed">Confirmed</span>
          </div>
          <div class="hc-row">
            <span class="label">Research Symposium</span>
            <span class="hc-badge pending">Pending</span>
          </div>
          <div class="hc-row">
            <span class="label">Campus Cultural Night</span>
            <span class="hc-badge confirmed">Confirmed</span>
          </div>
        </div>

        <div class="how-card how-card-float">
          <div class="hc-title" style="margin-bottom:10px;font-size:0.82rem">Quick Stats</div>
          <div class="hc-row">
            <span class="label">Events Joined</span>
            <span class="val">4</span>
          </div>
          <div class="hc-row">
            <span class="label">This Semester</span>
            <span class="val" style="color:var(--gold-l)">3 Active</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ CTA ════════════════════════════════════════════════════ -->
<?php if (!$is_logged_in): ?>
<section class="cta-section">
  <div class="cta-inner">
    <div class="section-label reveal">Ready?</div>
    <h2 class="cta-title reveal">
      Don't Miss Your Next<br>
      <em>Campus Opportunity</em>
    </h2>
    <p class="cta-desc reveal">
      Join hundreds of students already using ERMS to stay connected with
      academic events that shape careers.
    </p>
    <div class="hero-actions reveal">
      <a href="register.php" class="btn btn-gold btn-lg">Create Free Account</a>
      <a href="login.php" class="btn btn-outline btn-lg">Sign In</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ FOOTER ═════════════════════════════════════════════════ -->
<footer>
  <div class="footer-brand">
    <div class="footer-crest">E</div>
    ERMS — Event Registration &amp; Management System
  </div>
  <div class="footer-copy">
    &copy; <?= date('Y') ?> All rights reserved.
  </div>
  <ul class="footer-links">
    <li><a href="#events">Events</a></li>
    <li><a href="#how-it-works">How It Works</a></li>
    <li><a href="login.php">Sign In</a></li>
    <li><a href="register.php">Register</a></li>
  </ul>
</footer>


<script src="assets/js/global.js"></script>
</body>
</html>