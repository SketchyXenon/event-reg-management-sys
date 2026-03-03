<?php
require_once __DIR__ . '/../backend/csrf_helper.php';
require_once __DIR__ . '/../backend/auth_guard.php';
require_once __DIR__ . '/../backend/db_connect.php';
require_once __DIR__ . '/../backend/security_headers.php';

admin_only();

$admin  = current_user();
$type   = in_array($_GET['type']   ?? '', ['registrations', 'events', 'users'])
  ? $_GET['type'] : 'registrations';
$format = in_array($_GET['format'] ?? '', ['csv', 'excel', 'pdf'])
  ? $_GET['format'] : 'csv';
$scope  = ($_GET['scope'] ?? 'filtered') === 'all' ? 'all' : 'filtered';

// Shared filter params (passed from parent page via URL)
$search   = trim($_GET['q']            ?? '');
$status_f =      $_GET['status']       ?? 'all';
$event_f  = (int)($_GET['event_id']    ?? 0);
$role_f   =      $_GET['role']         ?? 'all';
$cat_f    = (int)($_GET['category_id'] ?? 0);

switch ($type) {

  case 'registrations':
    $headers = [
      '#',
      'Student Name',
      'Student ID',
      'Email',
      'Event',
      'Category',
      'Venue',
      'Event Date',
      'Status',
      'Registered At'
    ];
    $base = "FROM registrations r
             JOIN users u  ON u.user_id  = r.user_id
             JOIN events e ON e.event_id = r.event_id
             LEFT JOIN event_categories ec ON ec.category_id = e.category_id";
    $where = [];
    $params = [];
    if ($scope === 'filtered') {
      if ($search) {
        $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR e.title LIKE ?)";
        $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
      }
      if ($status_f !== 'all') {
        $where[] = "r.status=?";
        $params[] = $status_f;
      }
      if ($event_f  > 0) {
        $where[] = "r.event_id=?";
        $params[] = $event_f;
      }
    }
    $ws   = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
      "SELECT r.registration_id, u.full_name, u.student_id, u.email,
              e.title AS event_title, ec.category_name, e.venue,
              e.date_time, r.status, r.registered_at
       $base $ws ORDER BY r.registered_at DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = [];
    foreach ($rows as $i => $r) {
      $data[] = [
        $i + 1,
        $r['full_name'],
        $r['student_id'],
        $r['email'],
        $r['event_title'],
        $r['category_name'] ?? '-',
        $r['venue'],
        date('M d, Y g:i A', strtotime($r['date_time'])),
        ucfirst($r['status']),
        date('M d, Y g:i A', strtotime($r['registered_at'])),
      ];
    }
    $title    = 'ERMS — Registrations Report';
    $filename = 'erms_registrations_' . date('Ymd_His');
    break;

  case 'events':
    $headers = [
      '#',
      'Title',
      'Category',
      'Venue',
      'Date & Time',
      'Max Slots',
      'Enrolled',
      'Available',
      'Status'
    ];
    $where = [];
    $params = [];
    if ($scope === 'filtered') {
      if ($search) {
        $where[] = "(e.title LIKE ? OR e.venue LIKE ?)";
        $params  = array_merge($params, ["%$search%", "%$search%"]);
      }
      if ($status_f !== 'all') {
        $where[] = "e.status=?";
        $params[] = $status_f;
      }
      if ($cat_f    > 0) {
        $where[] = "e.category_id=?";
        $params[] = $cat_f;
      }
    }
    $ws   = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
      "SELECT e.event_id, e.title, ec.category_name, e.venue, e.date_time,
              e.max_slots, e.status,
              COUNT(r.registration_id) AS enrolled
       FROM events e
       LEFT JOIN event_categories ec ON ec.category_id = e.category_id
       LEFT JOIN registrations r ON r.event_id = e.event_id AND r.status != 'cancelled'
       $ws GROUP BY e.event_id ORDER BY e.date_time DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = [];
    foreach ($rows as $i => $r) {
      $enrolled  = (int)$r['enrolled'];
      $available = max(0, (int)$r['max_slots'] - $enrolled);
      $data[] = [
        $i + 1,
        $r['title'],
        $r['category_name'] ?? '-',
        $r['venue'],
        date('M d, Y g:i A', strtotime($r['date_time'])),
        $r['max_slots'],
        $enrolled,
        $available,
        ucfirst($r['status']),
      ];
    }
    $title    = 'ERMS — Events Report';
    $filename = 'erms_events_' . date('Ymd_His');
    break;

  case 'users':
    $headers = [
      '#',
      'Full Name',
      'Student ID',
      'Email',
      'Role',
      'Status',
      'Registrations',
      'Joined'
    ];
    $where = [];
    $params = [];
    if ($scope === 'filtered') {
      if ($role_f !== 'all') {
        $where[] = "u.role=?";
        $params[] = $role_f;
      }
      if ($search) {
        $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)";
        $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
      }
    }
    $ws   = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare(
      "SELECT u.user_id, u.full_name, u.student_id, u.email,
              u.role, u.is_active, u.created_at,
              COUNT(r.registration_id) AS reg_count
       FROM users u
       LEFT JOIN registrations r ON r.user_id = u.user_id
       $ws GROUP BY u.user_id ORDER BY u.created_at DESC"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = [];
    foreach ($rows as $i => $r) {
      $data[] = [
        $i + 1,
        $r['full_name'],
        $r['student_id'],
        $r['email'],
        ucfirst($r['role']),
        $r['is_active'] ? 'Active' : 'Inactive',
        $r['reg_count'],
        date('M d, Y', strtotime($r['created_at'])),
      ];
    }
    $title    = 'ERMS — Users Report';
    $filename = 'erms_users_' . date('Ymd_His');
    break;
}

$scope_label = $scope === 'all' ? 'All Records' : 'Filtered Results';
$count       = count($data);
$generated   = date('F j, Y \a\t g:i A');

// ══════════════════════════════════════════════════════════
//  CSV
// ══════════════════════════════════════════════════════════
if ($format === 'csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
  header('Cache-Control: no-store');
  $out = fopen('php://output', 'w');
  fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
  fputcsv($out, [$title]);
  fputcsv($out, ['Scope: ' . $scope_label . ' | Records: ' . $count . ' | Generated: ' . $generated]);
  fputcsv($out, []);
  fputcsv($out, $headers);
  foreach ($data as $row) fputcsv($out, $row);
  fclose($out);
  exit;
}

// ══════════════════════════════════════════════════════════
//  EXCEL  (SpreadsheetML — opens in Excel & LibreOffice)
// ══════════════════════════════════════════════════════════
if ($format === 'excel') {
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
  header('Cache-Control: no-store');
  $xe = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
  $nc = count($headers);
  echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
  echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
   xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
  echo '<Styles>
  <Style ss:ID="T"><Font ss:Bold="1" ss:Size="13" ss:Color="#ffffff"/>
    <Interior ss:Color="#1a2645" ss:Pattern="Solid"/></Style>
  <Style ss:ID="M"><Font ss:Italic="1" ss:Size="9" ss:Color="#666666"/>
    <Interior ss:Color="#f0f4fa" ss:Pattern="Solid"/></Style>
  <Style ss:ID="H"><Font ss:Bold="1" ss:Size="10" ss:Color="#ffffff"/>
    <Interior ss:Color="#4a7ab5" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="E"><Interior ss:Color="#f5f7fa" ss:Pattern="Solid"/>
    <Font ss:Size="9"/></Style>
  <Style ss:ID="O"><Interior ss:Color="#ffffff" ss:Pattern="Solid"/>
    <Font ss:Size="9"/></Style>
  <Style ss:ID="N"><Font ss:Bold="1" ss:Size="9" ss:Color="#4a7ab5"/>
    <Alignment ss:Horizontal="Center"/></Style>
</Styles>' . "\n";
  echo '<Worksheet ss:Name="' . $xe(ucfirst($type)) . '"><Table>' . "\n";
  echo '<Column ss:Width="30"/>';
  for ($c = 1; $c < $nc; $c++) echo '<Column ss:Width="115"/>';
  echo "\n";
  // Title row
  echo '<Row ss:Height="22"><Cell ss:MergeAcross="' . ($nc - 1) . '" ss:StyleID="T">'
    . '<Data ss:Type="String">' . $xe($title) . '</Data></Cell></Row>' . "\n";
  // Meta row
  echo '<Row ss:Height="16"><Cell ss:MergeAcross="' . ($nc - 1) . '" ss:StyleID="M">'
    . '<Data ss:Type="String">Scope: ' . $xe($scope_label) . '  |  Records: ' . $count
    . '  |  Generated: ' . $xe($generated) . '</Data></Cell></Row>' . "\n";
  echo '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>' . "\n";
  // Header
  echo '<Row ss:Height="20">';
  foreach ($headers as $h)
    echo '<Cell ss:StyleID="H"><Data ss:Type="String">' . $xe($h) . '</Data></Cell>';
  echo '</Row>' . "\n";
  // Data
  foreach ($data as $i => $row) {
    $s = ($i % 2 === 0) ? 'E' : 'O';
    echo '<Row ss:Height="16">';
    foreach ($row as $ci => $cell) {
      $cs   = ($ci === 0) ? 'N' : $s;
      $dt   = (is_numeric($cell) && $ci !== 0) ? 'Number' : 'String';
      echo '<Cell ss:StyleID="' . $cs . '"><Data ss:Type="' . $dt . '">' . $xe($cell) . '</Data></Cell>';
    }
    echo '</Row>' . "\n";
  }
  echo '</Table></Worksheet></Workbook>' . "\n";
  exit;
}

// ══════════════════════════════════════════════════════════
//  PDF  (styled HTML page — browser Print → Save as PDF)
// ══════════════════════════════════════════════════════════
if ($format === 'pdf') {
  $rows_html = '';
  foreach ($data as $i => $row) {
    $rows_html .= '<tr class="' . ($i % 2 === 0 ? 'e' : 'o') . '">';
    foreach ($row as $ci => $cell)
      $rows_html .= '<td' . ($ci === 0 ? ' class="n"' : '') . '>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
    $rows_html .= '</tr>';
  }
  $hdr_html = implode('', array_map(
    fn($h) => '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>',
    $headers
  ));
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
      * {
        box-sizing: border-box;
        margin: 0;
        padding: 0
      }

      body {
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 11px;
        color: #1a1a2e;
        background: #fff
      }

      .hdr {
        background: linear-gradient(135deg, #0d1420, #1a2645);
        color: #fff;
        padding: 18px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between
      }

      .hdr-l {
        display: flex;
        align-items: center;
        gap: 12px
      }

      .crest {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #4a7ab5, #c9a84c);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        color: #080b10
      }

      .sys {
        font-size: 15px;
        font-weight: 700
      }

      .sub {
        font-size: 8px;
        color: rgba(255, 255, 255, .5);
        letter-spacing: .1em;
        text-transform: uppercase;
        margin-top: 2px
      }

      .hdr-r {
        text-align: right;
        font-size: 9px;
        color: rgba(255, 255, 255, .55);
        line-height: 1.8
      }

      .hdr-r strong {
        color: #c9a84c;
        font-size: 11px;
        display: block
      }

      .meta {
        background: #f0f4fa;
        border-bottom: 2px solid #4a7ab5;
        padding: 9px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between
      }

      .meta-t {
        font-size: 13px;
        font-weight: 700;
        color: #1a2645
      }

      .pills {
        display: flex;
        gap: 6px
      }

      .pill {
        background: #4a7ab5;
        color: #fff;
        font-size: 8px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 20px;
        letter-spacing: .04em
      }

      .pill.g {
        background: #c9a84c
      }

      .content {
        padding: 14px 24px 24px
      }

      table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px
      }

      thead tr {
        background: #1a2645
      }

      th {
        color: #fff;
        font-size: 8.5px;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        padding: 8px 9px;
        text-align: left;
        border-right: 1px solid rgba(255, 255, 255, .08)
      }

      th:first-child {
        text-align: center;
        width: 30px
      }

      tr.e {
        background: #f8fafd
      }

      tr.o {
        background: #fff
      }

      td {
        padding: 6px 9px;
        border-bottom: 1px solid #e8edf5;
        font-size: 9.5px;
        vertical-align: middle
      }

      td.n {
        text-align: center;
        font-weight: 700;
        color: #4a7ab5
      }

      .foot {
        margin-top: 16px;
        padding: 10px 24px;
        border-top: 1px solid #e0e6f0;
        display: flex;
        justify-content: space-between;
        font-size: 8.5px;
        color: #999
      }

      .no-data {
        text-align: center;
        padding: 40px;
        color: #999;
        font-style: italic
      }

      .actions {
        position: fixed;
        bottom: 18px;
        right: 18px;
        display: flex;
        gap: 8px;
        z-index: 999
      }

      .btn-p {
        background: #4a7ab5;
        color: #fff;
        border: none;
        padding: 9px 20px;
        border-radius: 7px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(74, 122, 181, .4)
      }

      .btn-c {
        background: #e8edf5;
        color: #444;
        border: none;
        padding: 9px 16px;
        border-radius: 7px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer
      }

      @media print {
        .actions {
          display: none !important
        }

        .hdr,
        .meta,
        thead tr {
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact
        }

        tr.e {
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact
        }
      }
    </style>
  </head>

  <body>

    <div class="hdr">
      <div class="hdr-l">
        <div class="crest">CTU</div>
        <div>
          <div class="sys">Event Registration &amp; Management System</div>
          <div class="sub">Cebu Technological University — ERMS</div>
        </div>
      </div>
      <div class="hdr-r">
        <strong><?= htmlspecialchars($title) ?></strong>
        <span>Generated: <?= htmlspecialchars($generated) ?></span>
        <span>By: <?= htmlspecialchars($admin['full_name'] ?? 'Admin') ?></span>
      </div>
    </div>

    <div class="meta">
      <div class="meta-t"><?= ucfirst($type) ?> Report</div>
      <div class="pills">
        <span class="pill"><?= $count ?> Records</span>
        <span class="pill g"><?= htmlspecialchars($scope_label) ?></span>
      </div>
    </div>

    <div class="content">
      <?php if (empty($data)): ?>
        <div class="no-data">No records found for the selected filters.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr><?= $hdr_html ?></tr>
          </thead>
          <tbody><?= $rows_html ?></tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="foot">
      <span>ERMS — Cebu Technological University</span>
      <span><?= $count ?> record<?= $count !== 1 ? 's' : '' ?></span>
      <span><?= htmlspecialchars($generated) ?></span>
    </div>

    <div class="actions">
      <button class="btn-c" onclick="window.close()">✕ Close</button>
      <button class="btn-p" onclick="window.print()">🖨 Print / Save PDF</button>
    </div>

    <script>
      window.addEventListener('load', function() {
        setTimeout(function() {
          window.print();
        }, 800);
      });
    </script>
  </body>

  </html>
<?php
  exit;
}
