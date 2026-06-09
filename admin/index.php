<?php
include '../includes/dbcon.php';       // Load database connection ($conn)
include '../includes/session_admin.php'; // Verify user is logged in as admin; redirect if not
$pageTitle = 'Dashboard'; // Used by topbar.php to display the current page name

// Count total number of students in the system
$students = $conn->query("SELECT COUNT(*) AS c FROM tblstudents")->fetch_assoc()['c'];

// Count total number of faculty/teachers
$teachers = $conn->query("SELECT COUNT(*) AS c FROM tblclassteacher")->fetch_assoc()['c'];

// Count total number of programs (classes)
$programs = $conn->query("SELECT COUNT(*) AS c FROM tblclass")->fetch_assoc()['c'];

$today = date('Y-m-d'); // Get today's date in YYYY-MM-DD format

// Count how many students are marked present today (status=1)
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tblattendance WHERE dateTimeTaken=? AND status=1");
$stmt->bind_param("s", $today); $stmt->execute(); // Bind today's date and run query
$presentToday = $stmt->get_result()->fetch_assoc()['c']; // Extract the count

// --- Chart 1: Daily attendance trend (last 14 days) ---
// Get present and absent counts grouped by date for the last 14 days
$trend = $conn->query("
  SELECT dateTimeTaken AS d,
         SUM(status=1) AS present,
         SUM(status=0) AS absent
  FROM tblattendance
  WHERE dateTimeTaken >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
  GROUP BY d ORDER BY d ASC
")->fetch_all(MYSQLI_ASSOC); // Fetch all rows as an associative array

// --- Chart 2: Attendance rate by Program ---
// For each program, calculate how many records are present vs total
$byProgram = $conn->query("
  SELECT c.className,
         SUM(a.status=1) AS present,
         COUNT(*) AS total
  FROM tblattendance a
  JOIN tblclass c ON c.Id = a.classId
  GROUP BY c.Id, c.className
  ORDER BY c.className
")->fetch_all(MYSQLI_ASSOC);

// --- Chart 3: Monthly overview (last 6 months) ---
// Group attendance data by month, showing present/absent counts per month
$monthly = $conn->query("
  SELECT DATE_FORMAT(dateTimeTaken,'%Y-%m') AS ym,
         DATE_FORMAT(MIN(dateTimeTaken),'%b %Y') AS label,
         SUM(status=1) AS present,
         SUM(status=0) AS absent
  FROM tblattendance
  WHERE dateTimeTaken >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  GROUP BY ym ORDER BY ym ASC
")->fetch_all(MYSQLI_ASSOC);

// --- Recent records ---
// Fetch the 10 most recent attendance records across all sections with student and class info
$recent = $conn->query("
  SELECT s.firstName, s.lastName, s.admissionNumber,
         c.className, ca.classArmName, a.status, a.dateTimeTaken
  FROM tblattendance a
  JOIN tblstudents s   ON s.admissionNumber = a.admissionNo
  JOIN tblclass c      ON c.Id = a.classId
  JOIN tblclassarms ca ON ca.Id = a.classArmId
  ORDER BY a.dateTimeTaken DESC, a.Id DESC
  LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard – Admin</title>
  <link rel="stylesheet" href="../assets/css/style.css"> <!-- Main stylesheet -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> <!-- Icons -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script> <!-- Chart.js library (used by commented-out chart code below) -->
  <style>
    /* CSS Grid layout for the chart section: 2 columns by default */
    .charts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.25rem;
      margin-bottom: 1.5rem;
    }
    .chart-card-wide { grid-column: 1 / -1; } /* Span the full width of the grid */
    .chart-wrap { position: relative; height: 240px; } /* Fixed-height container for Chart.js */
    .chart-wrap-tall { position: relative; height: 260px; } /* Slightly taller chart container */
    @media (max-width: 768px) {
      /* On small screens, collapse to single column */
      .charts-grid { grid-template-columns: 1fr; }
      .chart-card-wide { grid-column: 1; }
    }
  </style>
</head>
<body>
<div class="app-wrapper"> <!-- Main layout wrapper (sidebar + content) -->
  <?php include 'sidebar.php'; ?> <!-- Left navigation sidebar -->
  <div class="main-content"> <!-- Content area to the right of the sidebar -->
    <?php include 'topbar.php'; ?> <!-- Top navigation bar -->
    <div class="page-content"> <!-- Inner padding wrapper for page content -->

      <!-- Stat Cards: 4 summary tiles at the top of the dashboard -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fas fa-users"></i></div> <!-- Blue icon -->
          <div class="stat-info"><div class="label">Students</div><div class="value"><?= $students ?></div></div> <!-- Total student count -->
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-chalkboard-user"></i></div> <!-- Green icon -->
          <div class="stat-info"><div class="label">Faculty</div><div class="value"><?= $teachers ?></div></div> <!-- Total teacher count -->
        </div>
        <div class="stat-card">
          <div class="stat-icon purple"><i class="fas fa-book-open"></i></div> <!-- Purple icon -->
          <div class="stat-info"><div class="label">Programs</div><div class="value"><?= $programs ?></div></div> <!-- Total programs count -->
        </div>
        <div class="stat-card">
          <div class="stat-icon orange"><i class="fas fa-calendar-check"></i></div> <!-- Orange icon -->
          <div class="stat-info"><div class="label">Present Today</div><div class="value"><?= $presentToday ?></div></div> <!-- Today's present count -->
        </div>
      </div>

      <!-- Charts section is commented out (disabled) — kept for future use -->
      <!-- <div class="charts-grid"> ... </div> -->

      <!-- Recent Attendance Table -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-clock"></i> Recent Attendance</h3>
          <a href="attendance.php" class="btn btn-primary btn-sm">View All</a> <!-- Link to full attendance page -->
        </div>
        <div class="card-body" style="padding:0">
          <div class="table-wrap"> <!-- Horizontally scrollable container -->
            <table>
              <thead>
                <tr><th>Student</th><th>Student No.</th><th>Section</th><th>Status</th><th>Date</th></tr>
              </thead>
              <tbody>
                <!-- Loop through each recent attendance row -->
                <?php while ($row = $recent->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['firstName'].' '.$row['lastName']) ?></td> <!-- Full name (escaped) -->
                  <td><?= htmlspecialchars($row['admissionNumber']) ?></td> <!-- Student number -->
                  <td><?= htmlspecialchars($row['classArmName']) ?></td> <!-- Section name -->
                  <td>
                    <!-- Show green "Present" badge or red "Absent" badge based on status value -->
                    <?php if ($row['status'] == 1): ?>
                      <span class="badge badge-present">Present</span>
                    <?php else: ?>
                      <span class="badge badge-absent">Absent</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($row['dateTimeTaken']) ?></td> <!-- Date the record was taken -->
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Chart.js initialization scripts are commented out (disabled) — kept for future use -->
<!-- <script> ... </script> -->

<script src="../assets/js/app.js"></script> <!-- Load shared JS for sidebar toggle, modals, etc. -->
</body>
</html>