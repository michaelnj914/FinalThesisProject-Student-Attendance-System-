<?php
// Include the database connection file so $conn is available throughout this page
include '../includes/dbcon.php';

// Include the admin session guard — redirects to login if the admin is not logged in
include '../includes/session_admin.php';

// Set the page title variable (used in the browser tab and possibly the layout)
$pageTitle = 'Student Report';

// Read 'id' from the URL query string (?id=5)
// ctype_digit() makes sure it's a whole positive number (prevents SQL injection)
// Cast to int for safety; default to 0 if missing or invalid
$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;

// If $id is 0 (invalid or missing), redirect to the students list and stop execution
if (!$id) { header("Location: students.php"); exit; }

// Prepare a SQL query to get the student's row plus their class name and section name
// by JOINing the students table with the class and class-arms (section) tables
$stmt = $conn->prepare("
    SELECT s.*, c.className, ca.classArmName
    FROM tblstudents s
    JOIN tblclass c      ON c.Id  = s.classId
    JOIN tblclassarms ca ON ca.Id = s.classArmId
    WHERE s.Id = ?
");

// Bind the integer $id as the parameter for the WHERE clause placeholder (?)
$stmt->bind_param("i", $id);

// Run the query against the database
$stmt->execute();

// Fetch the single matching row as an associative array and store it in $student
$student = $stmt->get_result()->fetch_assoc();

// If no student was found with that ID, redirect back to the list and stop
if (!$student) { header("Location: students.php"); exit; }

// Check the URL for an optional semester filter (?termId=3)
// Same pattern: validate it's digits, cast to int, or default to 0
$filterTerm = isset($_GET['termId']) && ctype_digit($_GET['termId']) ? (int)$_GET['termId'] : 0;

// Fetch all available semesters (with their term name) for the filter dropdown
// Ordered newest first so the most recent semester appears at the top
$semesters  = $conn->query("SELECT st.*, t.termName FROM tblsessionterm st JOIN tblterm t ON t.Id=st.termId ORDER BY st.Id DESC");

// Grab the student's admission number; this is the key used in the attendance table
$admNo = $student['admissionNumber'];

if ($filterTerm) {
    // ── Branch: a specific semester was selected in the filter ──

    // Query 1: count every attendance record for this student in the selected semester
    $s1 = $conn->prepare("SELECT COUNT(*) AS total FROM tblattendance WHERE admissionNo=? AND sessionTermId=?");
    $s1->bind_param("si", $admNo, $filterTerm); // "si" = string then integer
    $s1->execute();
    // Pull the single 'total' value from the result
    $total = $s1->get_result()->fetch_assoc()['total'];

    // Query 2: count only the days the student was marked present (status = 1) in this semester
    $s2 = $conn->prepare("SELECT COUNT(*) AS present FROM tblattendance WHERE admissionNo=? AND status=1 AND sessionTermId=?");
    $s2->bind_param("si", $admNo, $filterTerm);
    $s2->execute();
    // Pull the single 'present' count
    $present = $s2->get_result()->fetch_assoc()['present'];

    // Query 3: aggregate attendance by calendar month for the progress-bar chart
    // DATE_FORMAT turns a date into 'YYYY-MM' (for sorting) and 'Month YYYY' (for display)
    // SUM(status=1) counts present days; SUM(status=0) counts absent days per month
    $s3 = $conn->prepare("
        SELECT DATE_FORMAT(dateTimeTaken,'%Y-%m') AS ym,
               DATE_FORMAT(MIN(dateTimeTaken),'%M %Y') AS label,
               SUM(status=1) AS present, SUM(status=0) AS absent, COUNT(*) AS total
        FROM tblattendance WHERE admissionNo=? AND sessionTermId=?
        GROUP BY ym ORDER BY ym ASC
    ");
    $s3->bind_param("si", $admNo, $filterTerm);
    $s3->execute();
    // Fetch all monthly rows at once as a 2-D associative array
    $monthly = $s3->get_result()->fetch_all(MYSQLI_ASSOC);

    // Query 4: fetch every individual attendance log entry for this semester
    // LEFT JOINs on session and term tables to get human-readable names
    // COALESCE replaces NULL with '—' when a join finds no match
    // Ordered newest first so the most recent records appear at the top of the log table
    $s4 = $conn->prepare("
        SELECT a.dateTimeTaken, a.status,
               COALESCE(t.termName,'—')    AS termName,
               COALESCE(st.sessionName,'—') AS sessionName
        FROM tblattendance a
        LEFT JOIN tblsessionterm st ON st.Id = a.sessionTermId
        LEFT JOIN tblterm t         ON t.Id  = st.termId
        WHERE a.admissionNo=? AND a.sessionTermId=?
        ORDER BY a.dateTimeTaken DESC
    ");
    $s4->bind_param("si", $admNo, $filterTerm);
    $s4->execute();
    // Fetch all individual log rows into an array
    $logs = $s4->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // ── Branch: no semester filter — load all attendance records across all semesters ──

    // Count every attendance record ever for this student
    $s1 = $conn->prepare("SELECT COUNT(*) AS total FROM tblattendance WHERE admissionNo=?");
    $s1->bind_param("s", $admNo); // "s" = string (admission number)
    $s1->execute();
    $total = $s1->get_result()->fetch_assoc()['total'];

    // Count all-time present days (status = 1) for this student
    $s2 = $conn->prepare("SELECT COUNT(*) AS present FROM tblattendance WHERE admissionNo=? AND status=1");
    $s2->bind_param("s", $admNo);
    $s2->execute();
    $present = $s2->get_result()->fetch_assoc()['present'];

    // Same monthly breakdown query but without the semester filter
    $s3 = $conn->prepare("
        SELECT DATE_FORMAT(dateTimeTaken,'%Y-%m') AS ym,
               DATE_FORMAT(MIN(dateTimeTaken),'%M %Y') AS label,
               SUM(status=1) AS present, SUM(status=0) AS absent, COUNT(*) AS total
        FROM tblattendance WHERE admissionNo=?
        GROUP BY ym ORDER BY ym ASC
    ");
    $s3->bind_param("s", $admNo);
    $s3->execute();
    $monthly = $s3->get_result()->fetch_all(MYSQLI_ASSOC);

    // Same full log query but without the semester filter
    $s4 = $conn->prepare("
        SELECT a.dateTimeTaken, a.status,
               COALESCE(t.termName,'—')     AS termName,
               COALESCE(st.sessionName,'—') AS sessionName
        FROM tblattendance a
        LEFT JOIN tblsessionterm st ON st.Id = a.sessionTermId
        LEFT JOIN tblterm t         ON t.Id  = st.termId
        WHERE a.admissionNo=?
        ORDER BY a.dateTimeTaken DESC
    ");
    $s4->bind_param("s", $admNo);
    $s4->execute();
    $logs = $s4->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Calculate absent days by subtracting present from total
$absent = $total - $present;

// Calculate the overall attendance rate as a percentage, rounded to 1 decimal place
// Guard against division by zero: if $total is 0, rate is 0
$rate   = $total > 0 ? round(($present / $total) * 100, 1) : 0;

// Determine the student's academic standing color and label based on their attendance rate
// 80% or above = green / Good Standing
// 60–79%       = yellow / At Risk
// Below 60%    = red / Dropped
if ($rate >= 80)     { $rateColor = 'var(--success)'; $standing = 'Good Standing'; $standingClass = 'standing-good'; $standingIcon = 'circle-check'; }
elseif ($rate >= 60) { $rateColor = 'var(--warning)'; $standing = 'At Risk';       $standingClass = 'standing-risk'; $standingIcon = 'circle-exclamation'; }
else                 { $rateColor = 'var(--danger)';  $standing = 'Dropped';       $standingClass = 'standing-drop'; $standingIcon = 'circle-xmark'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Declare character encoding for the browser -->
  <meta charset="utf-8">
  <!-- Make the page responsive on mobile devices -->
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- Dynamic page title: shows the student's last name, first name in the browser tab -->
  <!-- htmlspecialchars() prevents XSS by escaping special HTML characters -->
  <title>Student Report – <?= htmlspecialchars($student['lastName'].', '.$student['firstName']) ?></title>

  <!-- Load the main site stylesheet -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- Load Font Awesome icon library from CDN for icons used throughout the page -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* ── Hero banner: blue gradient card at the top with student info ── */
    .report-hero {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-d) 100%); /* Blue gradient background */
      border-radius: var(--radius); padding: 1.75rem 2rem; color: #fff;
      display: flex; align-items: center; gap: 1.5rem;
      margin-bottom: 1.5rem; flex-wrap: wrap; /* Wrap items on small screens */
    }

    /* Circle avatar on the left showing the student's first initial */
    .report-avatar {
      width: 80px; height: 80px; background: rgba(255,255,255,.2);
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 2.2rem; font-weight: 700; flex-shrink: 0; /* Don't shrink when space is tight */
      border: 3px solid rgba(255,255,255,.4);
    }

    /* The text block in the middle of the hero showing student details */
    .report-hero-info { flex: 1; min-width: 200px; }
    .report-hero h2 { font-size: 1.4rem; margin-bottom: .3rem; }
    .report-hero p  { font-size: .85rem; opacity: .88; margin: .2rem 0; display:flex; align-items:center; gap:.4rem; }

    /* Large circular percentage display pinned to the right side of the hero */
    .rate-circle {
      width: 120px; height: 120px; border-radius: 50%;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      font-weight: 800; margin-left: auto; /* Push to the far right */
      border: 6px solid rgba(255,255,255,.35); background: rgba(255,255,255,.12); flex-shrink: 0;
    }
    .rate-circle .rate-num { font-size: 1.9rem; line-height: 1; } /* The big % number */
    .rate-circle .rate-lbl { font-size: .65rem; opacity: .8; text-transform: uppercase; letter-spacing: .06em; margin-top:.2rem; } /* "ATTENDANCE" label */

    /* Pill-shaped badge below student name that shows standing (Good / At Risk / Dropped) */
    .standing-badge {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .3rem .9rem; border-radius: 50px;
      font-size: .78rem; font-weight: 700;
      background: rgba(255,255,255,.2); color: #fff; margin-top: .5rem;
    }
    /* Override badge background color based on standing level */
    .standing-good { background: rgba(45,198,83,.4); }  /* Green for good */
    .standing-risk { background: rgba(244,162,97,.4); } /* Yellow for at-risk */
    .standing-drop { background: rgba(230,57,70,.4); }  /* Red for dropped */

    /* Flexbox row that holds the four summary stat boxes */
    .summary-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }

    /* Each individual stat box (Total Days, Present, Absent, Rate) */
    .summary-box {
      flex: 1; min-width: 130px; background: var(--white);
      border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.25rem; text-align: center;
    }
    .summary-box .s-num { font-size: 2.2rem; font-weight: 800; line-height: 1; margin-bottom:.3rem; } /* Big number */
    .summary-box .s-lbl { font-size: .72rem; color: var(--gray); text-transform: uppercase; font-weight: 600; letter-spacing:.04em; } /* Label below number */

    /* Container for each month row in the breakdown section */
    .month-bar-wrap { margin-bottom: .9rem; }

    /* Top row of a bar: month name on the left, stats on the right */
    .month-label { display: flex; justify-content: space-between; font-size: .8rem; margin-bottom: .3rem; }

    /* The grey background track that the coloured bar sits inside */
    .bar-track { background: #eef0f8; border-radius: 50px; height: 11px; overflow: hidden; }

    /* The coloured filled portion of the progress bar; width is set inline via PHP */
    .bar-fill  { height: 100%; border-radius: 50px; transition: width .5s ease; }

    /* Centered empty-state box shown when there are no records */
    .no-data-box { text-align: center; padding: 3rem 1rem; color: var(--gray); }
    .no-data-box i { font-size: 2.5rem; margin-bottom: .75rem; display: block; opacity: .25; }
    .no-data-box p { font-size: .9rem; }

    /* Print-specific styles: hide UI chrome and remove extra spacing */
    @media print {
      .sidebar, .topbar, .no-print { display: none !important; } /* Hide nav elements on print */
      .main-content { margin-left: 0 !important; }               /* Remove sidebar offset */
      .page-content { padding: 0 !important; }
      /* Force background colors and gradients to print (browsers suppress them by default) */
      .report-hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .bar-fill    { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
<div class="app-wrapper"> <!-- Outer wrapper for the full page layout -->

  <?php include 'sidebar.php'; ?> <!-- Render the left navigation sidebar -->

  <div class="main-content"> <!-- Right-side content area next to the sidebar -->

    <?php include 'topbar.php'; ?> <!-- Render the top navigation bar -->

    <div class="page-content"> <!-- Inner padded content area -->

      <!-- Page header row: Back button on the left, Print button on the right -->
      <!-- 'no-print' class hides this entire row when the user prints -->
      <div class="page-header no-print">
        <div style="display:flex;align-items:center;gap:.75rem">
          <!-- Back button navigates to the students list page -->
          <a href="students.php" class="btn btn-warning btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
          <h2><i class="fas fa-chart-bar"></i> Student Attendance Report</h2>
        </div>
        <!-- Print button triggers the browser's print dialog via JavaScript -->
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Report</button>
      </div>

      <!-- Semester Filter form — hidden when printing -->
      <div class="card no-print" style="margin-bottom:1.25rem">
        <div class="card-body" style="padding:.9rem 1.25rem">
          <!-- GET form so filter selections appear in the URL (bookmarkable) -->
          <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">

            <!-- Hidden input carries the student ID so it stays in the URL after filtering -->
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="form-group" style="flex:1;min-width:200px;margin:0">
              <label>Filter by Semester</label>
              <select name="termId" class="form-control">
                <!-- Default option: show all semesters (no filter) -->
                <option value="">All Semesters</option>
                <?php
                // Only render options if there are semesters in the database
                if ($semesters && $semesters->num_rows > 0):
                  $semesters->data_seek(0); // Reset the result pointer to the first row
                  while ($sem=$semesters->fetch_assoc()): // Loop through each semester row
                ?>
                <!-- Each option shows "Session Name – Term Name"; mark as selected if it matches current filter -->
                <option value="<?= $sem['Id'] ?>" <?= $filterTerm==$sem['Id']?'selected':'' ?>>
                  <?= htmlspecialchars($sem['sessionName'].' – '.$sem['termName']) ?>
                  <?= $sem['isActive']?' (Active)':'' ?> <!-- Append "(Active)" label for the current semester -->
                </option>
                <?php endwhile; endif; ?>
              </select>
            </div>

            <div style="display:flex;gap:.5rem">
              <!-- Submit button applies the selected semester filter -->
              <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
              <!-- Reset link removes the termId param, showing all semesters again -->
              <a href="?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-rotate-left"></i></a>
            </div>
          </form>
        </div>
      </div>

      <!-- Hero Card: full-width gradient card with student identity on the left and rate circle on the right -->
      <div class="report-hero">

        <!-- Avatar: a circle showing the UPPERCASED first letter of the student's first name -->
        <!-- substr(..., 0, 1) extracts just the first character -->
        <div class="report-avatar"><?= strtoupper(substr($student['firstName'],0,1)) ?></div>

        <div class="report-hero-info">
          <!-- Full name: "Last, First Other"; trim() removes leading/trailing spaces from otherName -->
          <!-- ?? '' is the null coalescing operator — provides empty string if otherName is null -->
          <h2><?= htmlspecialchars($student['lastName'].', '.$student['firstName'].' '.trim($student['otherName']??'')) ?></h2>

          <!-- Student number row -->
          <p><i class="fas fa-id-card"></i> <strong>Student No.:</strong>&nbsp;<?= htmlspecialchars($student['admissionNumber']) ?></p>

          <!-- Program (class) name row -->
          <p><i class="fas fa-book-open"></i> <strong>Program:</strong>&nbsp;<?= htmlspecialchars($student['className']) ?></p>

          <!-- Section (class arm) name row -->
          <p><i class="fas fa-layer-group"></i> <strong>Section:</strong>&nbsp;<?= htmlspecialchars($student['classArmName']) ?></p>

          <!-- Standing badge: color class and icon are set dynamically from the PHP variables above -->
          <span class="standing-badge <?= $standingClass ?>">
            <i class="fas fa-<?= $standingIcon ?>"></i> <?= $standing ?>
          </span>
        </div>

        <!-- Circular attendance rate display in the top-right corner of the hero -->
        <div class="rate-circle">
          <span class="rate-num"><?= $rate ?>%</span>   <!-- e.g. "87.5%" -->
          <span class="rate-lbl">Attendance</span>      <!-- Static label below the number -->
        </div>
      </div>

      <!-- ── Four summary stat boxes ── -->
      <div class="summary-row">

        <!-- Box 1: Total school days recorded (blue) -->
        <div class="summary-box">
          <div class="s-num" style="color:var(--primary)"><?= $total ?></div>
          <div class="s-lbl">Total School Days</div>
        </div>

        <!-- Box 2: Days the student was present (green) -->
        <div class="summary-box">
          <div class="s-num" style="color:var(--success)"><?= $present ?></div>
          <div class="s-lbl">Days Present</div>
        </div>

        <!-- Box 3: Days the student was absent (red) -->
        <div class="summary-box">
          <div class="s-num" style="color:var(--danger)"><?= $absent ?></div>
          <div class="s-lbl">Days Absent</div>
        </div>

        <!-- Box 4: Attendance rate percentage (color matches standing level) -->
        <div class="summary-box">
          <div class="s-num" style="color:<?= $rateColor ?>"><?= $rate ?>%</div>
          <div class="s-lbl">Attendance Rate</div>
        </div>
      </div>

      <!-- ── Monthly Breakdown section: one horizontal progress bar per month ── -->
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Monthly Breakdown</h3></div>
        <div class="card-body">

          <?php if ($monthly): ?> <!-- Only render bars if there is monthly data -->
            <?php foreach ($monthly as $m): // Loop through each month's aggregated data
              // Calculate this month's attendance rate (guard against division by zero)
              $mRate  = $m['total'] > 0 ? round(($m['present']/$m['total'])*100,1) : 0;
              // Choose bar color using the same thresholds as the overall standing
              $mColor = $mRate>=80?'var(--success)':($mRate>=60?'var(--warning)':'var(--danger)');
            ?>
            <div class="month-bar-wrap">
              <!-- Top row: month name on the left, present/absent counts + rate on the right -->
              <div class="month-label">
                <span><strong><?= htmlspecialchars($m['label']) ?></strong></span>
                <span style="color:<?= $mColor ?>;font-weight:700">
                  <?= $m['present'] ?> present / <?= $m['absent'] ?> absent &nbsp;|&nbsp; <?= $mRate ?>%
                </span>
              </div>
              <!-- Grey track background -->
              <div class="bar-track">
                <!-- Coloured fill: width% equals the monthly attendance rate; color is set inline -->
                <div class="bar-fill" style="width:<?= $mRate ?>%;background:<?= $mColor ?>"></div>
              </div>
            </div>
            <?php endforeach; ?>

          <?php else: ?>
            <!-- Empty state: shown when $monthly is an empty array (no records at all) -->
            <div class="no-data-box">
              <i class="fas fa-chart-bar"></i>
              <!-- Message changes slightly if a semester filter is active -->
              <p>No attendance records yet<?= $filterTerm ? ' for this semester' : '' ?>.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Full Attendance Log: table of every single attendance record ── -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-list-check"></i> Full Attendance Log</h3>
          <!-- Show count of records; "record" vs "records" with a ternary for pluralisation -->
          <span style="font-size:.8rem;color:var(--gray)"><?= count($logs) ?> record<?= count($logs)!==1?'s':'' ?></span>
        </div>

        <div class="card-body" style="padding:0"> <!-- Remove padding so table extends edge-to-edge -->
          <div class="table-wrap"> <!-- Enables horizontal scrolling on small screens -->
            <table>
              <thead>
                <tr>
                  <th>#</th>       <!-- Row number -->
                  <th>Date</th>    <!-- Attendance date -->
                  <th>Semester</th><!-- Session – term name -->
                  <th>Status</th>  <!-- Present or Absent badge -->
                </tr>
              </thead>
              <tbody>

                <?php if (!$logs): ?> <!-- If $logs is empty, show a "no records" message -->
                <tr>
                  <td colspan="4"> <!-- Span all 4 columns so the message is centred -->
                    <div class="no-data-box">
                      <i class="fas fa-clipboard-list"></i>
                      <p>No attendance records found<?= $filterTerm ? ' for this semester' : '' ?>.</p>
                    </div>
                  </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($logs as $i => $log): ?> <!-- Loop through every log entry -->
                <tr>
                  <!-- $i is zero-based, so add 1 for a human-readable row number -->
                  <td><?= $i+1 ?></td>

                  <!-- Format the stored datetime as "Month DD, YYYY" (e.g., "January 15, 2025") -->
                  <!-- strtotime() converts the string to a Unix timestamp; date() formats it -->
                  <td><?= htmlspecialchars(date('F d, Y', strtotime($log['dateTimeTaken']))) ?></td>

                  <!-- Combine session name and term name with an em dash separator -->
                  <td><?= htmlspecialchars($log['sessionName'].' – '.$log['termName']) ?></td>

                  <td>
                    <!-- status=1 means Present (green badge); status=0 means Absent (red badge) -->
                    <?php if ($log['status']): ?>
                      <span class="badge badge-present"><i class="fas fa-check"></i> Present</span>
                    <?php else: ?>
                      <span class="badge badge-absent"><i class="fas fa-xmark"></i> Absent</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>

              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div> <!-- end .page-content -->
  </div> <!-- end .main-content -->
</div> <!-- end .app-wrapper -->

<!-- Load the main JavaScript file (handles sidebar toggle, dropdowns, etc.) -->
<script src="../assets/js/app.js"></script>
</body>
</html>