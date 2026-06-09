<?php
// Include the database connection file so $conn is available throughout this page
include '../includes/dbcon.php';

// Include the teacher session guard — redirects to login if the teacher is not logged in
include '../includes/session_teacher.php';

// Set the page title used by the layout
$pageTitle = 'Student Report';

// Read the teacher's assigned class ID and section (arm) ID from the session
// Cast to int to prevent non-numeric values from reaching the database
$classId    = (int)$_SESSION['classId'];
$classArmId = (int)$_SESSION['classArmId'];

// Read the student ID from the URL (?id=5)
// ctype_digit() ensures it's a valid positive whole number before casting
// Defaults to 0 if missing or invalid
$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;

// If $id is 0 (missing or invalid), redirect back to the students list and stop
if (!$id) { header("Location: my-students.php"); exit; }

// Fetch the student's info — but ONLY if they belong to this teacher's class and section
// The extra AND conditions (s.classId = ? AND s.classArmId = ?) prevent a teacher from
// accessing reports for students in another teacher's section by manually changing the URL
$stmt = $conn->prepare("
    SELECT s.*, c.className, ca.classArmName
    FROM tblstudents s
    JOIN tblclass c      ON c.Id  = s.classId
    JOIN tblclassarms ca ON ca.Id = s.classArmId
    WHERE s.Id = ? AND s.classId = ? AND s.classArmId = ?
");
// Bind all three IDs as integers ("iii")
$stmt->bind_param("iii", $id, $classId, $classArmId);
$stmt->execute();
// Fetch the single matching row
$student = $stmt->get_result()->fetch_assoc();

// If no student was found (wrong ID or wrong section), redirect back and stop
if (!$student) { header("Location: my-students.php"); exit; }

// Read the optional semester filter from the URL (?termId=3)
// Validate it's a digit, cast to int, or default to 0 (no filter)
$filterTerm = isset($_GET['termId']) && ctype_digit($_GET['termId']) ? (int)$_GET['termId'] : 0;

// Fetch all semesters for the filter dropdown
// fetch_all() loads ALL rows into a PHP array at once — unlike the admin version which used
// data_seek(), this approach avoids needing to reset the pointer when looping again later
$semResult  = $conn->query("SELECT st.*, t.termName FROM tblsessionterm st JOIN tblterm t ON t.Id=st.termId ORDER BY st.Id DESC");
$semesters  = $semResult ? $semResult->fetch_all(MYSQLI_ASSOC) : []; // Default to empty array if query fails

// Store the student's admission number — used as the key in all attendance queries
$admNo = $student['admissionNumber'];

if ($filterTerm) {
    // ── Branch: a specific semester was selected in the filter ──

    // Query 1: count every attendance record for this student in the selected semester
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM tblattendance WHERE admissionNo=? AND sessionTermId=?");
    $stmtTotal->bind_param("si", $admNo, $filterTerm); // "si" = string, integer
    $stmtTotal->execute();
    // Cast to int so arithmetic later is clean (no string addition issues)
    $total = (int)$stmtTotal->get_result()->fetch_assoc()['total'];

    // Query 2: count only present days (status=1) in this semester
    $stmtPres = $conn->prepare("SELECT COUNT(*) AS present FROM tblattendance WHERE admissionNo=? AND status=1 AND sessionTermId=?");
    $stmtPres->bind_param("si", $admNo, $filterTerm);
    $stmtPres->execute();
    $present = (int)$stmtPres->get_result()->fetch_assoc()['present'];

    // Query 3: monthly attendance breakdown for the progress-bar chart
    // DATE_FORMAT produces 'YYYY-MM' for sorting and 'Month YYYY' as a display label
    // SUM(status=1) counts present days; SUM(status=0) counts absent days per month
    $stmtMon = $conn->prepare("
        SELECT DATE_FORMAT(dateTimeTaken,'%Y-%m') AS ym,
               DATE_FORMAT(MIN(dateTimeTaken),'%M %Y') AS label,
               SUM(status=1) AS present, SUM(status=0) AS absent, COUNT(*) AS total
        FROM tblattendance
        WHERE admissionNo=? AND sessionTermId=?
        GROUP BY ym ORDER BY ym ASC
    ");
    $stmtMon->bind_param("si", $admNo, $filterTerm);
    $stmtMon->execute();
    // Fetch all monthly rows at once into a 2-D PHP array
    $monthly = $stmtMon->get_result()->fetch_all(MYSQLI_ASSOC);

    // Query 4: every individual attendance log entry for this semester
    // LEFT JOINs on session and term tables to get human-readable names
    // COALESCE replaces NULL with '—' when a join finds no match
    $stmtLog = $conn->prepare("
        SELECT a.dateTimeTaken, a.status,
               COALESCE(t.termName,'—') AS termName,
               COALESCE(st.sessionName,'—') AS sessionName
        FROM tblattendance a
        LEFT JOIN tblsessionterm st ON st.Id = a.sessionTermId
        LEFT JOIN tblterm t         ON t.Id  = st.termId
        WHERE a.admissionNo=? AND a.sessionTermId=?
        ORDER BY a.dateTimeTaken DESC
    ");
    $stmtLog->bind_param("si", $admNo, $filterTerm);
    $stmtLog->execute();
    $logs = $stmtLog->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // ── Branch: no filter — load all attendance records across all semesters ──

    // Count every attendance record ever for this student
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM tblattendance WHERE admissionNo=?");
    $stmtTotal->bind_param("s", $admNo); // "s" = string (admission number)
    $stmtTotal->execute();
    $total = (int)$stmtTotal->get_result()->fetch_assoc()['total'];

    // Count all-time present days for this student
    $stmtPres = $conn->prepare("SELECT COUNT(*) AS present FROM tblattendance WHERE admissionNo=? AND status=1");
    $stmtPres->bind_param("s", $admNo);
    $stmtPres->execute();
    $present = (int)$stmtPres->get_result()->fetch_assoc()['present'];

    // Same monthly breakdown query but without the semester filter
    $stmtMon = $conn->prepare("
        SELECT DATE_FORMAT(dateTimeTaken,'%Y-%m') AS ym,
               DATE_FORMAT(MIN(dateTimeTaken),'%M %Y') AS label,
               SUM(status=1) AS present, SUM(status=0) AS absent, COUNT(*) AS total
        FROM tblattendance
        WHERE admissionNo=?
        GROUP BY ym ORDER BY ym ASC
    ");
    $stmtMon->bind_param("s", $admNo);
    $stmtMon->execute();
    $monthly = $stmtMon->get_result()->fetch_all(MYSQLI_ASSOC);

    // Same full log query but without the semester filter
    $stmtLog = $conn->prepare("
        SELECT a.dateTimeTaken, a.status,
               COALESCE(t.termName,'—') AS termName,
               COALESCE(st.sessionName,'—') AS sessionName
        FROM tblattendance a
        LEFT JOIN tblsessionterm st ON st.Id = a.sessionTermId
        LEFT JOIN tblterm t         ON t.Id  = st.termId
        WHERE a.admissionNo=?
        ORDER BY a.dateTimeTaken DESC
    ");
    $stmtLog->bind_param("s", $admNo);
    $stmtLog->execute();
    $logs = $stmtLog->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Calculate absent days: total days minus days present
$absent = $total - $present;

// Calculate attendance rate as a percentage rounded to 1 decimal place
// Guard against division by zero: if $total is 0, rate is 0
$rate   = $total > 0 ? round(($present / $total) * 100, 1) : 0;

// Set standing label, badge color class, icon, and rate color based on attendance rate thresholds
// >= 80% → green / Good Standing
// 60–79% → yellow / At Risk
// < 60%  → red / Dropped
if ($rate >= 80)     { $rateColor = 'var(--success)'; $standing = 'Good Standing'; $standingClass = 'standing-good'; $standingIcon = 'circle-check'; }
elseif ($rate >= 60) { $rateColor = 'var(--warning)'; $standing = 'At Risk';       $standingClass = 'standing-risk'; $standingIcon = 'circle-exclamation'; }
else                 { $rateColor = 'var(--danger)';  $standing = 'Dropped';       $standingClass = 'standing-drop'; $standingIcon = 'circle-xmark'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Declare character encoding -->
  <meta charset="utf-8">
  <!-- Make the page responsive on mobile devices -->
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Dynamic page title shows the student's last and first name in the browser tab -->
  <title>Student Report – <?= htmlspecialchars($student['lastName'].', '.$student['firstName']) ?></title>
  <!-- Main site stylesheet -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- Font Awesome icons (CDN) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Blue gradient banner card at the top of the report */
    .report-hero {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-d) 100%); /* Diagonal blue gradient */
      border-radius: var(--radius);   /* Rounded corners */
      padding: 1.75rem 2rem;          /* Inner spacing */
      color: #fff;                    /* White text */
      display: flex;                  /* Row layout for avatar + info + circle */
      align-items: center;            /* Vertically center all children */
      gap: 1.5rem;                    /* Space between children */
      margin-bottom: 1.5rem;          /* Space below the hero card */
      flex-wrap: wrap;                /* Wrap on small screens */
    }

    /* Circle on the left showing the student's first initial */
    .report-avatar {
      width: 80px; height: 80px;              /* Fixed size */
      background: rgba(255,255,255,.2);       /* Translucent white fill */
      border-radius: 50%;                     /* Makes it a perfect circle */
      display: flex; align-items: center; justify-content: center; /* Center the letter */
      font-size: 2.2rem; font-weight: 700;    /* Large bold letter */
      flex-shrink: 0;                         /* Don't shrink when space is tight */
      border: 3px solid rgba(255,255,255,.4); /* Semi-transparent white ring */
    }

    /* Text block: name, student no., program, section */
    .report-hero-info { flex: 1; min-width: 200px; } /* Fills remaining space; min 200px wide */
    .report-hero h2 { font-size: 1.4rem; margin-bottom: .3rem; } /* Student name heading */
    .report-hero p  { font-size: .85rem; opacity: .88; margin: .2rem 0; display:flex; align-items:center; gap:.4rem; } /* Detail rows */

    /* Circular percentage display on the far right of the hero */
    .rate-circle {
      width: 120px; height: 120px;  /* Fixed size */
      border-radius: 50%;           /* Perfect circle */
      display: flex; flex-direction: column; /* Stack number above label vertically */
      align-items: center; justify-content: center; /* Center content inside */
      font-weight: 800;             /* Extra bold */
      margin-left: auto;            /* Push to the far right of the flex row */
      border: 6px solid rgba(255,255,255,.35); /* Semi-transparent ring */
      background: rgba(255,255,255,.12);       /* Faint white fill */
      flex-shrink: 0;               /* Don't shrink */
    }
    .rate-circle .rate-num { font-size: 1.9rem; line-height: 1; } /* Big % number */
    .rate-circle .rate-lbl { font-size: .65rem; opacity: .8; text-transform: uppercase; letter-spacing: .06em; margin-top:.2rem; } /* "ATTENDANCE" label */

    /* Pill-shaped standing badge below the student name */
    .standing-badge {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .3rem .9rem; border-radius: 50px; /* Fully rounded pill */
      font-size: .78rem; font-weight: 700;
      background: rgba(255,255,255,.2); color: #fff; /* Default translucent white */
      margin-top: .5rem;
    }
    /* Color overrides per standing level */
    .standing-good { background: rgba(45,198,83,.4); }  /* Green — >= 80% */
    .standing-risk { background: rgba(244,162,97,.4); } /* Orange — 60–79% */
    .standing-drop { background: rgba(230,57,70,.4); }  /* Red — < 60% */

    /* Flex row holding the four summary stat boxes */
    .summary-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }

    /* Each individual stat box */
    .summary-box {
      flex: 1; min-width: 130px;            /* Equal-width boxes; wrap if too narrow */
      background: var(--white); border-radius: var(--radius);
      box-shadow: var(--shadow); padding: 1.25rem;
      text-align: center;
    }
    .summary-box .s-num { font-size: 2.2rem; font-weight: 800; line-height: 1; margin-bottom:.3rem; } /* Big stat number */
    .summary-box .s-lbl { font-size: .72rem; color: var(--gray); text-transform: uppercase; font-weight: 600; letter-spacing:.04em; } /* Label below number */

    /* Monthly bar chart styles */
    .month-bar-wrap { margin-bottom: .9rem; }  /* Gap between each month's bar */
    .month-label { display: flex; justify-content: space-between; font-size: .8rem; margin-bottom: .3rem; } /* Label row: name left, stats right */
    .bar-track { background: #eef0f8; border-radius: 50px; height: 11px; overflow: hidden; } /* Grey track */
    .bar-fill  { height: 100%; border-radius: 50px; transition: width .5s ease; } /* Coloured fill; width/color set inline by PHP */

    /* Print styles */
    @media print {
      .sidebar, .topbar, .no-print { display: none !important; } /* Hide navigation and filter */
      .main-content { margin-left: 0 !important; }               /* Remove sidebar offset */
      .page-content { padding: 0 !important; }                   /* Remove padding */
      /* Force browser to print background colors and gradients */
      .report-hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .bar-fill    { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
<div class="app-wrapper"> <!-- Outer wrapper for the full page layout -->

  <?php include 'sidebar.php'; ?> <!-- Render the left navigation sidebar -->

  <div class="main-content"> <!-- Content area to the right of the sidebar -->

    <?php include 'topbar.php'; ?> <!-- Render the top navigation bar -->

    <div class="page-content"> <!-- Inner padded content area -->

      <!-- Page header row: Back button + title on the left, Print button on the right -->
      <!-- 'no-print' hides this entire row when the user prints the page -->
      <div class="page-header no-print">
        <div style="display:flex;align-items:center;gap:.75rem">
          <!-- Back button returns to the teacher's student list -->
          <a href="my-students.php" class="btn btn-warning btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
          <h2><i class="fas fa-chart-bar"></i> Student Attendance Report</h2>
        </div>
        <!-- Print button triggers the browser's print dialog -->
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Report</button>
      </div>

      <!-- Semester Filter form (hidden on print) -->
      <div class="card no-print" style="margin-bottom:1.25rem">
        <div class="card-body" style="padding:.9rem 1.25rem">
          <!-- GET form so the filter appears in the URL (bookmarkable/shareable) -->
          <form method="GET" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">

            <!-- Hidden field keeps the student ID in the URL when the form is submitted -->
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="form-group" style="flex:1;min-width:200px;margin:0">
              <label>Filter by Semester</label>
              <select name="termId" class="form-control">
                <!-- Default option: no filter, show all semesters -->
                <option value="">All Semesters</option>
                <?php foreach ($semesters as $sem): ?> <!-- Loop through the pre-fetched array -->
                <!-- Mark this option as selected if its ID matches the current filter -->
                <option value="<?= $sem['Id'] ?>" <?= $filterTerm == $sem['Id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sem['sessionName'].' – '.$sem['termName']) ?>
                  <?= $sem['isActive'] ? ' (Active)' : '' ?> <!-- Append "(Active)" for the current semester -->
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="display:flex;gap:.5rem">
              <!-- Submit button applies the selected filter -->
              <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
              <!-- Reset link removes the termId param, returning to all-semesters view -->
              <a href="?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-rotate-left"></i></a>
            </div>
          </form>
        </div>
      </div>

      <!-- Hero Card: gradient banner with student identity on the left and rate circle on the right -->
      <div class="report-hero">

        <!-- Avatar circle: uppercased first letter of the student's first name -->
        <!-- substr(..., 0, 1) extracts the first character only -->
        <div class="report-avatar"><?= strtoupper(substr($student['firstName'], 0, 1)) ?></div>

        <div class="report-hero-info">
          <!-- Full name "Last, First Other"; ?? '' prevents errors if otherName is null -->
          <h2><?= htmlspecialchars($student['lastName'].', '.$student['firstName'].' '.trim($student['otherName'] ?? '')) ?></h2>
          <!-- Student number row -->
          <p><i class="fas fa-id-card"></i> <strong>Student No.:</strong>&nbsp;<?= htmlspecialchars($student['admissionNumber']) ?></p>
          <!-- Program (class) name row -->
          <p><i class="fas fa-book-open"></i> <strong>Program:</strong>&nbsp;<?= htmlspecialchars($student['className']) ?></p>
          <!-- Section (class arm) name row -->
          <p><i class="fas fa-layer-group"></i> <strong>Section:</strong>&nbsp;<?= htmlspecialchars($student['classArmName']) ?></p>
          <!-- Standing badge: color and icon are set dynamically by the PHP variables above -->
          <span class="standing-badge <?= $standingClass ?>">
            <i class="fas fa-<?= $standingIcon ?>"></i> <?= $standing ?>
          </span>
        </div>

        <!-- Circular rate display pinned to the top-right of the hero -->
        <div class="rate-circle">
          <span class="rate-num"><?= $rate ?>%</span>  <!-- e.g. "87.5%" -->
          <span class="rate-lbl">Attendance</span>     <!-- Static label below the number -->
        </div>
      </div>

      <!-- ── Four Summary Stat Boxes ── -->
      <div class="summary-row">

        <!-- Box 1: Total school days (blue) -->
        <div class="summary-box">
          <div class="s-num" style="color:var(--primary)"><?= $total ?></div>
          <div class="s-lbl">Total School Days</div>
        </div>

        <!-- Box 2: Days present (green) -->
        <div class="summary-box">
          <div class="s-num" style="color:var(--success)"><?= $present ?></div>
          <div class="s-lbl">Days Present</div>
        </div>

        <!-- Box 3: Days absent (red) -->
        <div class="summary-box">
          <div class="s-num" style="color:var(--danger)"><?= $absent ?></div>
          <div class="s-lbl">Days Absent</div>
        </div>

        <!-- Box 4: Attendance rate % (color matches standing level) -->
        <div class="summary-box">
          <div class="s-num" style="color:<?= $rateColor ?>"><?= $rate ?>%</div>
          <div class="s-lbl">Attendance Rate</div>
        </div>
      </div>

      <!-- ── Monthly Breakdown: one progress bar per calendar month ── -->
      <?php if ($monthly): ?> <!-- Only render this card if there is monthly data -->
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Monthly Breakdown</h3></div>
        <div class="card-body">
          <?php foreach ($monthly as $m): // Loop through each month's aggregated data
            // Calculate this month's rate; guard against division by zero
            $mRate  = $m['total'] > 0 ? round(($m['present'] / $m['total']) * 100, 1) : 0;
            // Pick bar color using the same thresholds as the overall standing
            $mColor = $mRate >= 80 ? 'var(--success)' : ($mRate >= 60 ? 'var(--warning)' : 'var(--danger)');
          ?>
          <div class="month-bar-wrap">
            <!-- Label row: month name on the left, counts and rate on the right -->
            <div class="month-label">
              <span><strong><?= htmlspecialchars($m['label']) ?></strong></span> <!-- e.g. "January 2025" -->
              <span style="color:<?= $mColor ?>;font-weight:700">
                <?= $m['present'] ?> present / <?= $m['absent'] ?> absent &nbsp;|&nbsp; <?= $mRate ?>%
              </span>
            </div>
            <!-- Grey track -->
            <div class="bar-track">
              <!-- Coloured fill: width = monthly rate %; color set inline by $mColor -->
              <div class="bar-fill" style="width:<?= $mRate ?>%;background:<?= $mColor ?>"></div>
            </div>
          </div>
          <?php endforeach; ?> <!-- End of monthly loop -->
        </div>
      </div>

      <?php else: ?>
      <!-- Empty state: shown when $monthly is empty (no records for the selected period) -->
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-body" style="text-align:center;padding:2rem;color:var(--gray)">
          <!-- Faded icon as a visual cue for the empty state -->
          <i class="fas fa-chart-bar" style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
          No attendance data for the selected period.
        </div>
      </div>
      <?php endif; ?> <!-- End of monthly breakdown conditional -->

      <!-- ── Full Attendance Log: every individual attendance record in a table ── -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-list-check"></i> Full Attendance Log</h3>
          <!-- Record count next to the heading; "record" vs "records" handled server-side -->
          <span style="font-size:.8rem;color:var(--gray)"><?= count($logs) ?> records</span>
        </div>

        <!-- padding:0 lets the table stretch edge-to-edge inside the card -->
        <div class="card-body" style="padding:0">
          <div class="table-wrap"> <!-- Enables horizontal scrolling on small screens -->
            <table>
              <thead>
                <tr>
                  <th>#</th>        <!-- Row counter -->
                  <th>Date</th>     <!-- Formatted attendance date -->
                  <th>Semester</th> <!-- Session + term name -->
                  <th>Status</th>   <!-- Present or Absent badge -->
                </tr>
              </thead>
              <tbody>

                <!-- Empty state row: shown when $logs is empty -->
                <?php if (!$logs): ?>
                <tr>
                  <!-- colspan="4" spans all columns so the message fills the full table width -->
                  <td colspan="4" style="text-align:center;padding:2rem;color:var(--gray)">
                    No attendance records found.
                  </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($logs as $i => $log): ?> <!-- Loop through every log entry -->
                <tr>
                  <!-- $i is zero-based so +1 gives a human-readable row number starting at 1 -->
                  <td><?= $i + 1 ?></td>

                  <!-- Format the stored datetime as "Month DD, YYYY" (e.g. "March 05, 2025") -->
                  <!-- strtotime() converts the date string to a Unix timestamp for date() to format -->
                  <td><?= htmlspecialchars(date('F d, Y', strtotime($log['dateTimeTaken']))) ?></td>

                  <!-- Combine session name and term name with an em dash separator -->
                  <td><?= htmlspecialchars($log['sessionName'].' – '.$log['termName']) ?></td>

                  <td>
                    <!-- status=1 → Present (green badge); status=0 → Absent (red badge) -->
                    <?php if ($log['status']): ?>
                      <span class="badge badge-present"><i class="fas fa-check"></i> Present</span>
                    <?php else: ?>
                      <span class="badge badge-absent"><i class="fas fa-xmark"></i> Absent</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?> <!-- End of log loop -->

              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div> <!-- end .page-content -->
  </div> <!-- end .main-content -->
</div> <!-- end .app-wrapper -->

<!-- Main site JavaScript (sidebar toggle, dropdowns, etc.) -->
<script src="../assets/js/app.js"></script>
</body>
</html>