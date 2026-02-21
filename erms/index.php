<?php
require_once __DIR__ . '/backend/db_connect.php';
require_once __DIR__ . '/backend/session_manager.php';
require_once __DIR__ . '/backend/security_headers.php';

if (session_status() === PHP_SESSION_NONE) 
    {
    session_start();
}
// Check if user is already logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role    = $_SESSION['role'] ?? '';

// Fetch featured/upcoming events (public)
$featured = $pdo->query(
  "SELECT e.*, ec.name AS category_name,
          COUNT(r.id) AS enrolled
   FROM events e
   LEFT JOIN event_categories ec ON ec.id = e.category_id
   LEFT JOIN registrations r ON r.event_id = e.id AND r.status != 'cancelled'
   WHERE e.status IN ('active','upcoming') AND e.event_date >= CURDATE()
   GROUP BY e.id
   ORDER BY e.event_date ASC
   LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

// Stats for counter section
$stat_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$stat_events   = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$stat_regs     = $pdo->query("SELECT COUNT(*) FROM registrations WHERE status='confirmed'")->fetchColumn();
$stat_cats     = $pdo->query("SELECT COUNT(*) FROM event_categories")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ERMS — Event Registration & Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    /* ═══════════════════════════════════════════════════
       ROOT & RESET
    ═══════════════════════════════════════════════════ */
    :root {
      --bg:          #0b0e15;
      --bg-surface:  #111620;
      --bg-card:     #161c2a;
      --bg-hover:    #1c2335;
      --border:      rgba(255,255,255,0.07);
      --border-acc:  rgba(74,122,181,0.3);

      --text:        #e6e3db;
      --text-2:      #8e97ae;
      --text-3:      #505870;

      --blue:        #4a7ab5;
      --blue-l:      #6a96cc;
      --gold:        #c9a84c;
      --gold-l:      #dbbf6e;
      --green:       #4e9b72;

      --ff-display:  'Playfair Display', Georgia, serif;
      --ff-body:     'Source Sans 3', sans-serif;
      --ff-mono:     'JetBrains Mono', monospace;

      --nav-h: 68px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; scroll-behavior: smooth; }

    body {
      font-family: var(--ff-body);
      background: var(--bg);
      color: var(--text);
      overflow-x: hidden;
    }

    a { text-decoration: none; color: inherit; }
    img { max-width: 100%; }

    /* ═══════════════════════════════════════════════════
       NAV
    ═══════════════════════════════════════════════════ */
    .nav {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: var(--nav-h);
      display: flex;
      align-items: center;
      padding: 0 48px;
      z-index: 100;
      transition: background 0.3s ease, border-color 0.3s ease;
    }

    .nav.scrolled {
      background: rgba(11,14,21,0.92);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
    }

    .nav-brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .nav-crest {
      width: 38px; height: 38px;
      background: linear-gradient(135deg, var(--blue), var(--gold));
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      font-family: var(--ff-display);
      font-size: 1.1rem; font-weight: 700;
      color: #0b0e15;
      box-shadow: 0 0 18px rgba(74,122,181,0.35);
    }

    .nav-name {
      font-family: var(--ff-display);
      font-size: 1.05rem;
      font-weight: 600;
      letter-spacing: 0.01em;
    }

    .nav-name span {
      color: var(--gold);
    }

    .nav-links {
      display: flex;
      gap: 6px;
      margin: 0 auto;
      list-style: none;
    }

    .nav-links a {
      padding: 6px 14px;
      font-size: 0.88rem;
      color: var(--text-2);
      border-radius: 6px;
      transition: all 0.18s ease;
    }

    .nav-links a:hover {
      color: var(--text);
      background: rgba(255,255,255,0.05);
    }

    .nav-actions {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 20px;
      border-radius: 7px;
      font-family: var(--ff-body);
      font-size: 0.88rem;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: all 0.18s ease;
      white-space: nowrap;
    }

    .btn-outline {
      background: transparent;
      color: var(--text-2);
      border: 1px solid var(--border);
    }
    .btn-outline:hover { background: var(--bg-hover); color: var(--text); border-color: var(--border-acc); }

    .btn-primary {
      background: var(--blue);
      color: white;
    }
    .btn-primary:hover { background: var(--blue-l); box-shadow: 0 0 20px rgba(74,122,181,0.4); }

    .btn-gold {
      background: var(--gold);
      color: #0b0e15;
      font-weight: 600;
    }
    .btn-gold:hover { background: var(--gold-l); box-shadow: 0 0 20px rgba(201,168,76,0.4); }

    .btn-lg { padding: 13px 28px; font-size: 0.95rem; }

    /* ═══════════════════════════════════════════════════
       HERO
    ═══════════════════════════════════════════════════ */
    .hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      padding: calc(var(--nav-h) + 60px) 48px 80px;
    }

    /* Layered background */
    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse 70% 60% at 60% 40%, rgba(74,122,181,0.12) 0%, transparent 65%),
        radial-gradient(ellipse 40% 40% at 20% 70%, rgba(201,168,76,0.07) 0%, transparent 60%);
    }

    /* Grid lines */
    .hero::after {
      content: '';
      position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(74,122,181,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(74,122,181,0.04) 1px, transparent 1px);
      background-size: 60px 60px;
    }

    /* Floating orbs */
    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(80px);
      pointer-events: none;
      animation: orbDrift 12s ease-in-out infinite alternate;
    }

    .orb-1 {
      width: 500px; height: 500px;
      background: rgba(74,122,181,0.08);
      top: -100px; right: -100px;
      animation-delay: 0s;
    }

    .orb-2 {
      width: 350px; height: 350px;
      background: rgba(201,168,76,0.06);
      bottom: 0; left: -80px;
      animation-delay: -4s;
    }

    .orb-3 {
      width: 250px; height: 250px;
      background: rgba(78,155,114,0.05);
      top: 40%; right: 20%;
      animation-delay: -8s;
    }

    @keyframes orbDrift {
      from { transform: translate(0, 0) scale(1); }
      to   { transform: translate(30px, -20px) scale(1.05); }
    }

    .hero-content {
      position: relative;
      z-index: 1;
      max-width: 780px;
      text-align: center;
    }

    .hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 5px 14px;
      background: rgba(201,168,76,0.1);
      border: 1px solid rgba(201,168,76,0.25);
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--gold-l);
      margin-bottom: 28px;
      animation: fadeUp 0.6s ease both;
    }

    .hero-eyebrow::before {
      content: '';
      width: 6px; height: 6px;
      background: var(--gold);
      border-radius: 50%;
      box-shadow: 0 0 8px var(--gold);
      animation: pulse 2s ease infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50%       { opacity: 0.4; }
    }

    .hero-title {
      font-family: var(--ff-display);
      font-size: clamp(2.8rem, 6vw, 4.5rem);
      font-weight: 700;
      line-height: 1.1;
      letter-spacing: -0.02em;
      margin-bottom: 24px;
      animation: fadeUp 0.6s 0.1s ease both;
    }

    .hero-title em {
      font-style: italic;
      color: var(--gold);
    }

    .hero-title .line-accent {
      display: block;
      color: var(--blue-l);
    }

    .hero-desc {
      font-size: 1.08rem;
      line-height: 1.7;
      color: var(--text-2);
      max-width: 560px;
      margin: 0 auto 36px;
      animation: fadeUp 0.6s 0.2s ease both;
    }

    .hero-actions {
      display: flex;
      gap: 14px;
      justify-content: center;
      flex-wrap: wrap;
      animation: fadeUp 0.6s 0.3s ease both;
    }

    .hero-scroll {
      position: absolute;
      bottom: 32px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      color: var(--text-3);
      font-size: 0.72rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      z-index: 1;
      animation: fadeUp 0.6s 0.6s ease both;
    }

    .scroll-line {
      width: 1px;
      height: 40px;
      background: linear-gradient(to bottom, var(--text-3), transparent);
      animation: scrollPulse 2s ease infinite;
    }

    @keyframes scrollPulse {
      0%, 100% { opacity: 0.4; transform: scaleY(1); }
      50%       { opacity: 1; transform: scaleY(0.7); }
    }

    /* ═══════════════════════════════════════════════════
       STATS BAND
    ═══════════════════════════════════════════════════ */
    .stats-band {
      background: var(--bg-surface);
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
      padding: 48px;
    }

    .stats-inner {
      max-width: 900px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0;
    }

    .stat-item {
      text-align: center;
      padding: 16px 24px;
      position: relative;
    }

    .stat-item + .stat-item::before {
      content: '';
      position: absolute;
      left: 0; top: 20%; bottom: 20%;
      width: 1px;
      background: var(--border);
    }

    .stat-num {
      font-family: var(--ff-display);
      font-size: 2.6rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1;
      margin-bottom: 6px;
    }

    .stat-num .accent { color: var(--gold); }

    .stat-label-text {
      font-size: 0.8rem;
      font-weight: 500;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-3);
    }

    /* ═══════════════════════════════════════════════════
       SECTION SHARED
    ═══════════════════════════════════════════════════ */
    section { padding: 96px 48px; }

    .section-label {
      display: inline-block;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 14px;
    }

    .section-title {
      font-family: var(--ff-display);
      font-size: clamp(1.9rem, 3.5vw, 2.8rem);
      font-weight: 600;
      line-height: 1.2;
      margin-bottom: 16px;
      max-width: 540px;
    }

    .section-desc {
      font-size: 1rem;
      line-height: 1.7;
      color: var(--text-2);
      max-width: 480px;
    }

    .section-inner {
      max-width: 1160px;
      margin: 0 auto;
    }

    /* ═══════════════════════════════════════════════════
       EVENTS SECTION
    ═══════════════════════════════════════════════════ */
    .events-section { background: var(--bg); }

    .events-header {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      margin-bottom: 44px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .events-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
    }

    .event-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
      position: relative;
    }

    .event-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.5);
      border-color: var(--border-acc);
    }

    /* Decorative top stripe per category */
    .event-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--blue), var(--gold));
    }

    .event-card-body { padding: 22px 22px 16px; }

    .event-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
    }

    .event-category {
      font-size: 0.68rem;
      font-weight: 600;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--blue-l);
      background: rgba(74,122,181,0.12);
      border: 1px solid rgba(74,122,181,0.2);
      padding: 2px 9px;
      border-radius: 20px;
    }

    .event-slots {
      font-family: var(--ff-mono);
      font-size: 0.72rem;
      color: var(--text-3);
    }

    .event-slots.full { color: #d87c7c; }

    .event-title-card {
      font-family: var(--ff-display);
      font-size: 1.12rem;
      font-weight: 600;
      line-height: 1.35;
      color: var(--text);
      margin-bottom: 10px;
    }

    .event-desc-card {
      font-size: 0.84rem;
      line-height: 1.6;
      color: var(--text-2);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      margin-bottom: 16px;
    }

    .event-card-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 22px;
      border-top: 1px solid var(--border);
    }

    .event-date-loc {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .event-date {
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--gold-l);
    }

    .event-location {
      font-size: 0.75rem;
      color: var(--text-3);
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .event-register-btn {
      font-size: 0.8rem;
      padding: 7px 14px;
      border-radius: 6px;
      background: rgba(74,122,181,0.15);
      color: var(--blue-l);
      border: 1px solid rgba(74,122,181,0.25);
      transition: all 0.18s ease;
      white-space: nowrap;
    }

    .event-register-btn:hover {
      background: var(--blue);
      color: white;
      border-color: var(--blue);
    }

    .event-register-btn.disabled {
      opacity: 0.5;
      pointer-events: none;
      background: var(--bg-hover);
      color: var(--text-3);
      border-color: var(--border);
    }

    .no-events {
      text-align: center;
      padding: 60px 20px;
      color: var(--text-3);
      grid-column: 1/-1;
    }

    .no-events p {
      font-family: var(--ff-display);
      font-size: 1.1rem;
      margin-bottom: 8px;
    }

    /* ═══════════════════════════════════════════════════
       HOW IT WORKS
    ═══════════════════════════════════════════════════ */
    .how-section {
      background: var(--bg-surface);
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
    }

    .how-inner {
      display: grid;
      grid-template-columns: 1fr 1.4fr;
      gap: 80px;
      align-items: center;
    }

    .steps-list {
      display: flex;
      flex-direction: column;
      gap: 0;
      margin-top: 36px;
      position: relative;
    }

    /* Vertical connector line */
    .steps-list::before {
      content: '';
      position: absolute;
      left: 19px; top: 24px; bottom: 24px;
      width: 1px;
      background: linear-gradient(to bottom, var(--blue), var(--gold), transparent);
    }

    .step {
      display: flex;
      gap: 20px;
      padding: 20px 0;
      position: relative;
    }

    .step-num {
      width: 38px; height: 38px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 1px solid var(--border-acc);
      display: flex; align-items: center; justify-content: center;
      font-family: var(--ff-mono);
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--blue-l);
      flex-shrink: 0;
      position: relative;
      z-index: 1;
    }

    .step-body { padding-top: 6px; }

    .step-title {
      font-family: var(--ff-display);
      font-size: 1rem;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 5px;
    }

    .step-desc {
      font-size: 0.87rem;
      line-height: 1.6;
      color: var(--text-2);
    }

    /* Visual panel */
    .how-visual {
      position: relative;
      height: 440px;
    }

    .how-card {
      position: absolute;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 18px 20px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    }

    .how-card-main {
      width: 100%;
      top: 0; left: 0; right: 0;
    }

    .how-card-float {
      width: 70%;
      bottom: 0; right: 0;
      background: var(--bg-hover);
      border-color: var(--border-acc);
    }

    .hc-title {
      font-family: var(--ff-display);
      font-size: 0.95rem;
      font-weight: 600;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .hc-title .dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 8px var(--green);
    }

    .hc-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid var(--border);
      font-size: 0.82rem;
    }

    .hc-row:last-child { border-bottom: none; }

    .hc-row .label { color: var(--text-2); }
    .hc-row .val   { color: var(--text); font-weight: 500; }

    .hc-badge {
      display: inline-flex;
      padding: 2px 9px;
      border-radius: 20px;
      font-size: 0.68rem;
      font-weight: 600;
    }

    .hc-badge.confirmed { background: rgba(78,155,114,0.15); color: #6ec49a; }
    .hc-badge.pending   { background: rgba(201,168,76,0.15); color: var(--gold-l); }

    /* ═══════════════════════════════════════════════════
       CTA SECTION
    ═══════════════════════════════════════════════════ */
    .cta-section {
      background: var(--bg);
      text-align: center;
      padding: 100px 48px;
      position: relative;
      overflow: hidden;
    }

    .cta-section::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse 60% 50% at 50% 50%, rgba(74,122,181,0.1) 0%, transparent 70%);
    }

    .cta-inner { position: relative; z-index: 1; }

    .cta-title {
      font-family: var(--ff-display);
      font-size: clamp(2rem, 4vw, 3.2rem);
      font-weight: 700;
      line-height: 1.15;
      margin-bottom: 18px;
    }

    .cta-title em { font-style: italic; color: var(--gold); }

    .cta-desc {
      font-size: 1rem;
      color: var(--text-2);
      max-width: 480px;
      margin: 0 auto 36px;
      line-height: 1.7;
    }

    /* ═══════════════════════════════════════════════════
       FOOTER
    ═══════════════════════════════════════════════════ */
    footer {
      background: var(--bg-surface);
      border-top: 1px solid var(--border);
      padding: 32px 48px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 14px;
    }

    .footer-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: var(--ff-display);
      font-size: 0.95rem;
    }

    .footer-crest {
      width: 28px; height: 28px;
      background: linear-gradient(135deg, var(--blue), var(--gold));
      border-radius: 5px;
      display: flex; align-items: center; justify-content: center;
      font-family: var(--ff-display);
      font-size: 0.75rem; font-weight: 700;
      color: #0b0e15;
    }

    .footer-copy {
      font-size: 0.8rem;
      color: var(--text-3);
    }

    .footer-links {
      display: flex;
      gap: 20px;
      list-style: none;
    }

    .footer-links a {
      font-size: 0.82rem;
      color: var(--text-3);
      transition: color 0.18s ease;
    }

    .footer-links a:hover { color: var(--text-2); }

    /* ═══════════════════════════════════════════════════
       ANIMATIONS
    ═══════════════════════════════════════════════════ */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .reveal {
      opacity: 0;
      transform: translateY(24px);
      transition: opacity 0.6s ease, transform 0.6s ease;
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    /* ═══════════════════════════════════════════════════
       RESPONSIVE
    ═══════════════════════════════════════════════════ */
    @media (max-width: 1024px) {
      .events-grid { grid-template-columns: repeat(2, 1fr); }
      .how-inner   { grid-template-columns: 1fr; gap: 40px; }
      .how-visual  { height: 320px; }
    }

    @media (max-width: 768px) {
      section { padding: 64px 24px; }
      .nav { padding: 0 24px; }
      .nav-links { display: none; }
      .stats-inner { grid-template-columns: repeat(2, 1fr); }
      .stat-item + .stat-item::before { display: none; }
      .events-grid { grid-template-columns: 1fr; }
      footer { flex-direction: column; text-align: center; }
      .hero { padding: calc(var(--nav-h) + 40px) 24px 60px; }
      .stats-band { padding: 40px 24px; }
      .how-visual { display: none; }
      .events-header { flex-direction: column; align-items: flex-start; }
    }
  </style>
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
                  📅 <?= date('M d, Y · g:i A', strtotime($ev['event_date'])) ?>
                </div>
                <div class="event-location">
                  📍 <?= htmlspecialchars($ev['location']) ?>
                </div>
              </div>
              <?php if ($is_logged_in): ?>
                <a href="events.php?id=<?= $ev['id'] ?>"
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

<script>
// ── Navbar scroll effect ──────────────────────────────────
window.addEventListener('scroll', () => {
  document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 30);
});

// ── Scroll reveal ─────────────────────────────────────────
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('visible'), i * 80);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// ── Counter animation ─────────────────────────────────────
function animateCounter(el) {
  const target = parseInt(el.dataset.target, 10);
  if (isNaN(target) || target === 0) return;
  const duration = 1200;
  const start = performance.now();
  const ease = t => t < 0.5 ? 2*t*t : -1+(4-2*t)*t;

  function tick(now) {
    const progress = Math.min((now - start) / duration, 1);
    const current  = Math.round(ease(progress) * target);
    // Preserve the + suffix span
    const suffix = el.querySelector('.accent');
    el.childNodes[0].textContent = current.toLocaleString();
    if (progress < 1) requestAnimationFrame(tick);
  }

  requestAnimationFrame(tick);
}

const counterObs = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      animateCounter(entry.target);
      counterObs.unobserve(entry.target);
    }
  });
}, { threshold: 0.5 });

document.querySelectorAll('.stat-num[data-target]').forEach(el => counterObs.observe(el));
</script>
</body>
</html>