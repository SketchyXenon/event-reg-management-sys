<?php
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
admin_only();

$admin = current_user();

$total_students      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_events        = (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$total_registrations = (int)$pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$total_upcoming      = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status='active' AND date_time >= NOW()")->fetchColumn();
$total_categories    = (int)$pdo->query("SELECT COUNT(*) FROM event_categories")->fetchColumn();
$reg_by_status = $pdo->query("SELECT status, COUNT(*) AS cnt FROM registrations GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$chart = $pdo->query("SELECT DATE_FORMAT(registered_at,'%b') AS label, MONTH(registered_at) AS mo, YEAR(registered_at) AS yr, COUNT(*) AS cnt FROM registrations WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY yr, mo, label ORDER BY yr ASC, mo ASC")->fetchAll(PDO::FETCH_ASSOC);
$recent = $pdo->query("SELECT r.registration_id, r.status, r.registered_at, u.full_name, u.student_id, e.title AS event_title, ec.category_name FROM registrations r JOIN users u ON u.user_id=r.user_id JOIN events e ON e.event_id=r.event_id LEFT JOIN event_categories ec ON ec.category_id=e.category_id ORDER BY r.registered_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
$events_cap = $pdo->query("SELECT e.title, e.max_slots, e.status, ec.category_name, COUNT(r.registration_id) AS enrolled FROM events e LEFT JOIN event_categories ec ON ec.category_id=e.category_id LEFT JOIN registrations r ON r.event_id=e.event_id AND r.status != 'cancelled' WHERE e.date_time >= NOW() GROUP BY e.event_id ORDER BY e.date_time ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
$top_cats = $pdo->query("SELECT ec.category_name, COUNT(r.registration_id) AS total FROM event_categories ec LEFT JOIN events ev ON ev.category_id=ec.category_id LEFT JOIN registrations r ON r.event_id=ev.event_id AND r.status != 'cancelled' GROUP BY ec.category_id ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — ERMS Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Source+Sans+3:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="assets/css/admin.css">
  <style>
    .db-stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 20px;
    }

    .db-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }

    .db-stat {
      background: #111620;
      border: 1px solid rgba(255, 255, 255, .08);
      border-radius: 12px;
      padding: 20px 22px;
      position: relative;
      overflow: hidden;
      transition: transform .2s, box-shadow .2s;
      text-decoration: none;
      display: block;
    }

    .db-stat:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 28px rgba(0, 0, 0, .5);
    }

    .db-stat::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
    }

    .db-stat--blue::before {
      background: linear-gradient(90deg, #4a7ab5, transparent);
    }

    .db-stat--gold::before {
      background: linear-gradient(90deg, #c9a84c, transparent);
    }

    .db-stat--green::before {
      background: linear-gradient(90deg, #4e9b72, transparent);
    }

    .db-stat--red::before {
      background: linear-gradient(90deg, #c45c5c, transparent);
    }

    .db-stat__icon {
      position: absolute;
      right: 18px;
      top: 50%;
      transform: translateY(-50%);
      opacity: .07;
    }

    .db-stat__label {
      font-size: .62rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: #505870;
      margin-bottom: 8px;
    }

    .db-stat__value {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      font-weight: 700;
      color: #e6e3db;
      line-height: 1;
    }

    .db-stat__sub {
      font-size: .7rem;
      color: #505870;
      margin-top: 6px;
    }

    .db-card {
      background: #111620;
      border: 1px solid rgba(255, 255, 255, .08);
      border-radius: 12px;
      overflow: hidden;
    }

    .db-card__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 20px;
      border-bottom: 1px solid rgba(255, 255, 255, .07);
      background: #161c2a;
      border-radius: 12px 12px 0 0;
    }

    .db-card__title {
      font-family: 'Playfair Display', serif;
      font-size: .96rem;
      font-weight: 500;
      color: #e6e3db;
    }

    .db-card__sub {
      font-size: .73rem;
      color: #505870;
      margin-top: 2px;
    }

    .db-card__body {
      padding: 20px;
    }

    .db-card__link {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: .78rem;
      color: #6a96cc;
      text-decoration: none;
      padding: 5px 10px;
      border-radius: 6px;
      border: 1px solid rgba(74, 122, 181, .3);
      transition: all .18s;
    }

    .db-card__link:hover {
      background: rgba(74, 122, 181, .12);
    }

    .db-chart {
      display: flex;
      align-items: flex-end;
      gap: 10px;
      height: 130px;
      padding-top: 10px;
    }

    .db-bar-col {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      height: 100%;
      min-width: 0;
    }

    .db-bar-val {
      font-size: .63rem;
      color: #6a96cc;
      font-family: 'JetBrains Mono', monospace;
      min-height: 14px;
    }

    .db-bar-wrap {
      flex: 1;
      display: flex;
      align-items: flex-end;
      width: 100%;
    }

    .db-bar {
      width: 100%;
      border-radius: 4px 4px 0 0;
      background: linear-gradient(to top, #4a7ab5, rgba(74, 122, 181, .3));
      min-height: 4px;
      transition: height .5s ease;
    }

    .db-bar-lbl {
      font-size: .62rem;
      color: #505870;
      white-space: nowrap;
      margin-top: 6px;
    }

    .db-activity {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .db-activity li {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid rgba(255, 255, 255, .05);
    }

    .db-activity li:last-child {
      border-bottom: none;
    }

    .db-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
      margin-top: 5px;
    }

    .db-dot--green {
      background: #4e9b72;
      box-shadow: 0 0 6px rgba(78, 155, 114, .5);
    }

    .db-dot--gold {
      background: #c9a84c;
      box-shadow: 0 0 6px rgba(201, 168, 76, .5);
    }

    .db-dot--red {
      background: #c45c5c;
      box-shadow: 0 0 6px rgba(196, 92, 92, .5);
    }

    .db-act-text {
      font-size: .83rem;
      color: #8e97ae;
      line-height: 1.5;
    }

    .db-act-text strong {
      color: #e6e3db;
      font-weight: 600;
    }

    .db-act-time {
      font-size: .7rem;
      color: #505870;
      margin-top: 2px;
      font-family: 'JetBrains Mono', monospace;
    }

    .db-cap-item {
      margin-bottom: 18px;
    }

    .db-cap-item:last-child {
      margin-bottom: 0;
    }

    .db-cap-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
    }

    .db-cap-title {
      font-size: .83rem;
      color: #e6e3db;
      font-weight: 500;
    }

    .db-cap-cat {
      font-size: .7rem;
      color: #505870;
      margin-top: 1px;
    }

    .db-cap-count {
      font-size: .74rem;
      color: #505870;
      font-family: 'JetBrains Mono', monospace;
      white-space: nowrap;
    }

    .db-cap-track {
      height: 5px;
      background: rgba(255, 255, 255, .07);
      border-radius: 10px;
      overflow: hidden;
    }

    .db-cap-fill {
      height: 100%;
      border-radius: 10px;
      transition: width .6s ease;
    }

    .db-reg-table {
      width: 100%;
      border-collapse: collapse;
    }

    .db-reg-table th {
      padding: 8px 14px;
      text-align: left;
      font-size: .62rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: #505870;
      border-bottom: 1px solid rgba(255, 255, 255, .07);
    }

    .db-reg-table td {
      padding: 11px 14px;
      border-bottom: 1px solid rgba(255, 255, 255, .05);
      vertical-align: middle;
    }

    .db-reg-table tr:last-child td {
      border-bottom: none;
    }

    .db-reg-table tr:hover td {
      background: rgba(74, 122, 181, .04);
    }

    .db-reg-name {
      font-weight: 600;
      font-size: .85rem;
      color: #e6e3db;
    }

    .db-reg-id {
      font-size: .69rem;
      font-family: 'JetBrains Mono', monospace;
      color: #505870;
    }

    .db-reg-event {
      font-size: .82rem;
      color: #8e97ae;
      max-width: 160px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .db-reg-cat {
      font-size: .68rem;
      color: #505870;
      margin-top: 1px;
    }

    .db-status {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: .72rem;
      font-weight: 600;
      padding: 3px 9px;
      border-radius: 20px;
      border: 1px solid transparent;
      white-space: nowrap;
    }

    .db-status--confirmed {
      background: rgba(78, 155, 114, .13);
      color: #6ec49a;
      border-color: rgba(78, 155, 114, .28);
    }

    .db-status--pending {
      background: rgba(201, 168, 76, .13);
      color: #dbbf6e;
      border-color: rgba(201, 168, 76, .28);
    }

    .db-status--cancelled {
      background: rgba(196, 92, 92, .13);
      color: #d87c7c;
      border-color: rgba(196, 92, 92, .28);
    }

    .db-cat-row {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 14px;
    }

    .db-cat-row:last-child {
      margin-bottom: 0;
    }

    .db-cat-name {
      width: 130px;
      font-size: .82rem;
      color: #8e97ae;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      flex-shrink: 0;
    }

    .db-cat-track {
      flex: 1;
      height: 8px;
      background: rgba(255, 255, 255, .07);
      border-radius: 10px;
      overflow: hidden;
    }

    .db-cat-fill {
      height: 100%;
      border-radius: 10px;
      transition: width .6s ease;
    }

    .db-cat-count {
      width: 28px;
      text-align: right;
      font-size: .77rem;
      font-family: 'JetBrains Mono', monospace;
      color: #505870;
      flex-shrink: 0;
    }

    [data-theme="light"] .db-stat,
    [data-theme="light"] .db-card {
      background: #f5f3ee;
      border-color: rgba(0, 0, 0, .08);
    }

    [data-theme="light"] .db-card__head {
      background: #eeece6;
      border-color: rgba(0, 0, 0, .07);
    }

    [data-theme="light"] .db-stat__value,
    [data-theme="light"] .db-card__title,
    [data-theme="light"] .db-cap-title,
    [data-theme="light"] .db-reg-name,
    [data-theme="light"] .db-act-text strong {
      color: #1a1d27;
    }

    @media(max-width:900px) {
      .db-stats {
        grid-template-columns: repeat(2, 1fr);
      }

      .db-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body class="has-sidebar">
  <?php include 'partials/sidebar.php'; ?>

  <div class="topbar">
    <button id="menuToggle" class="topbar-btn" style="display:none" onclick="document.querySelector('.sidebar').classList.toggle('open')">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
    <div>
      <div class="topbar-title">Dashboard</div>
      <div class="topbar-subtitle"><?= date('l, F j, Y') ?></div>
    </div>
    <div class="topbar-spacer"></div>
    <button class="theme-toggle-btn" id="themeToggle"><span id="themeIcon">☀️</span></button>
  </div>

  <main class="main">
    <div class="page-content">

      <!-- ── Stat Cards ── -->
      <div class="db-stats">
        <a href="users.php" class="db-stat db-stat--blue">
          <div class="db-stat__icon"><svg width="60" height="60" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z" />
            </svg></div>
          <div class="db-stat__label">Students</div>
          <div class="db-stat__value"><?= number_format($total_students) ?></div>
          <div class="db-stat__sub">Registered accounts</div>
        </a>
        <a href="events.php" class="db-stat db-stat--gold">
          <div class="db-stat__icon"><svg width="60" height="60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg></div>
          <div class="db-stat__label">Events</div>
          <div class="db-stat__value"><?= number_format($total_events) ?></div>
          <div class="db-stat__sub"><?= $total_upcoming ?> upcoming</div>
        </a>
        <a href="registrations.php" class="db-stat db-stat--green">
          <div class="db-stat__icon"><svg width="60" height="60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg></div>
          <div class="db-stat__label">Registrations</div>
          <div class="db-stat__value"><?= number_format($total_registrations) ?></div>
          <div class="db-stat__sub"><?= number_format($reg_by_status['confirmed'] ?? 0) ?> confirmed</div>
        </a>
        <a href="categories.php" class="db-stat db-stat--red">
          <div class="db-stat__icon"><svg width="60" height="60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
            </svg></div>
          <div class="db-stat__label">Categories</div>
          <div class="db-stat__value"><?= number_format($total_categories) ?></div>
          <div class="db-stat__sub">Event types</div>
        </a>
      </div>

      <!-- ── Chart + Activity ── -->
      <div class="db-grid">
        <div class="db-card">
          <div class="db-card__head">
            <div>
              <div class="db-card__title">Registrations Overview</div>
              <div class="db-card__sub">Last 6 months</div>
            </div>
            <a href="registrations.php" class="db-card__link">View All <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg></a>
          </div>
          <div class="db-card__body">
            <?php if (!empty($chart)): $maxC = max(array_column($chart, 'cnt')) ?: 1; ?>
              <div class="db-chart">
                <?php foreach ($chart as $bar): ?>
                  <div class="db-bar-col">
                    <div class="db-bar-val"><?= $bar['cnt'] ?></div>
                    <div class="db-bar-wrap">
                      <div class="db-bar" style="height:<?= round(($bar['cnt'] / $maxC) * 100) ?>%"></div>
                    </div>
                    <div class="db-bar-lbl"><?= substr($bar['label'], 0, 3) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="text-align:center;padding:40px 0;color:#505870;font-size:.85rem;">
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 8px;display:block;opacity:.25">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                No registration data yet
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="db-card">
          <div class="db-card__head">
            <div>
              <div class="db-card__title">Recent Activity</div>
              <div class="db-card__sub">Latest sign-ups</div>
            </div>
            <a href="registrations.php" class="db-card__link">View All <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg></a>
          </div>
          <div class="db-card__body" style="padding:0 20px;">
            <?php if (!empty($recent)): ?>
              <ul class="db-activity">
                <?php foreach (array_slice($recent, 0, 6) as $r):
                  $dot = $r['status'] === 'confirmed' ? 'db-dot--green' : ($r['status'] === 'pending' ? 'db-dot--gold' : 'db-dot--red'); ?>
                  <li>
                    <div class="db-dot <?= $dot ?>"></div>
                    <div>
                      <div class="db-act-text"><strong><?= htmlspecialchars($r['full_name']) ?></strong> registered for <strong><?= htmlspecialchars($r['event_title']) ?></strong></div>
                      <div class="db-act-time"><?= date('M d, Y · g:i A', strtotime($r['registered_at'])) ?></div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div style="text-align:center;padding:32px 0;color:#505870;font-size:.84rem;">No recent activity.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── Capacity + Latest Registrations ── -->
      <div class="db-grid">
        <div class="db-card">
          <div class="db-card__head">
            <div>
              <div class="db-card__title">Upcoming Event Capacity</div>
              <div class="db-card__sub">Enrollment progress</div>
            </div>
            <a href="events.php" class="db-card__link">View All <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg></a>
          </div>
          <div class="db-card__body">
            <?php if (!empty($events_cap)):
              foreach ($events_cap as $ev):
                $pct   = $ev['max_slots'] > 0 ? round(($ev['enrolled'] / $ev['max_slots']) * 100) : 0;
                $color = $pct >= 100 ? '#c45c5c' : ($pct >= 75 ? '#c9a84c' : '#4e9b72'); ?>
                <div class="db-cap-item">
                  <div class="db-cap-row">
                    <div>
                      <div class="db-cap-title"><?= htmlspecialchars($ev['title']) ?></div>
                      <?php if ($ev['category_name']): ?><div class="db-cap-cat"><?= htmlspecialchars($ev['category_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="db-cap-count"><?= $ev['enrolled'] ?>/<?= $ev['max_slots'] ?></div>
                  </div>
                  <div class="db-cap-track">
                    <div class="db-cap-fill" style="width:<?= min($pct, 100) ?>%;background:<?= $color ?>;"></div>
                  </div>
                </div>
              <?php endforeach;
            else: ?>
              <div style="text-align:center;padding:28px 0;color:#505870;font-size:.84rem;">No upcoming events.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="db-card">
          <div class="db-card__head">
            <div>
              <div class="db-card__title">Latest Registrations</div>
              <div class="db-card__sub">Most recent sign-ups</div>
            </div>
            <a href="registrations.php" class="db-card__link">View All <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg></a>
          </div>
          <div style="overflow-x:auto">
            <table class="db-reg-table">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Event</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($recent)):
                  foreach ($recent as $r):
                    $sc = ['confirmed' => 'db-status--confirmed', 'pending' => 'db-status--pending', 'cancelled' => 'db-status--cancelled']; ?>
                    <tr>
                      <td>
                        <div class="db-reg-name"><?= htmlspecialchars($r['full_name']) ?></div>
                        <?php if ($r['student_id']): ?><div class="db-reg-id"><?= htmlspecialchars($r['student_id']) ?></div><?php endif; ?>
                      </td>
                      <td>
                        <div class="db-reg-event"><?= htmlspecialchars($r['event_title']) ?></div>
                        <?php if ($r['category_name']): ?><div class="db-reg-cat"><?= htmlspecialchars($r['category_name']) ?></div><?php endif; ?>
                      </td>
                      <td><span class="db-status <?= $sc[$r['status']] ?? 'db-status--pending' ?>"><?= ucfirst($r['status']) ?></span></td>
                    </tr>
                  <?php endforeach;
                else: ?>
                  <tr>
                    <td colspan="3" style="text-align:center;color:#505870;padding:32px 14px;font-size:.84rem;">No registrations yet.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── Category Performance ── -->
      <?php if (!empty($top_cats)):
        $maxReg = max(array_column($top_cats, 'total')) ?: 1;
        $colors = ['#4a7ab5', '#c9a84c', '#4e9b72', '#c45c5c', '#9b7bc4']; ?>
        <div class="db-card" style="margin-bottom:20px;">
          <div class="db-card__head">
            <div>
              <div class="db-card__title">Category Performance</div>
              <div class="db-card__sub">Registrations per category</div>
            </div>
            <a href="categories.php" class="db-card__link">Manage <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg></a>
          </div>
          <div class="db-card__body">
            <?php foreach ($top_cats as $ci => $cat): ?>
              <div class="db-cat-row">
                <div class="db-cat-name"><?= htmlspecialchars($cat['category_name']) ?></div>
                <div class="db-cat-track">
                  <div class="db-cat-fill" style="width:<?= round(($cat['total'] / $maxReg) * 100) ?>%;background:<?= $colors[$ci % count($colors)] ?>;min-width:<?= $cat['total'] > 0 ? '6px' : '0' ?>"></div>
                </div>
                <div class="db-cat-count"><?= $cat['total'] ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </main>
  <script src="../assets/js/global.js"></script>
  <script src="assets/js/admin.js"></script>
</body>

</html>