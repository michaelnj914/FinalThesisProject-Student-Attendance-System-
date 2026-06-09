<?php
// Include the database connection file so $conn is available throughout this page
include '../includes/dbcon.php';

// Include the teacher session guard — redirects to login if the teacher is not logged in
include '../includes/session_teacher.php';

// Set the browser/layout page title
$pageTitle = 'Dashboard';

// Read the teacher's assigned class ID and section (arm) ID from the session
// These were stored in $_SESSION when the teacher logged in
// Cast to int to prevent any accidental non-numeric values
$classId    = (int)$_SESSION['classId'];
$classArmId = (int)$_SESSION['classArmId'];

// ── Query 1: Get the class name and section name for the teacher's assigned section ──
// JOINs tblclass and tblclassarms using the IDs stored in the session
$stmt = $conn->prepare("
  SELECT c.className, ca.classArmName
  FROM tblclass c
  JOIN tblclassarms ca ON ca.Id = ?
  WHERE c.Id = ?
");
// Bind both IDs as integers ("ii") to the two placeholders (?)
$stmt->bind_param("ii", $classArmId, $classId);
$stmt->execute();
// Fetch the single result row as an associative array
$classInfo = $stmt->get_result()->fetch_assoc();

// ── Query 2: Count how many students are assigned to this teacher's class and section ──
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tblstudents WHERE classId=? AND classArmId=?");
$stmt->bind_param("ii", $classId, $classArmId);
$stmt->execute();
// Pull just the count value from the result
$totalStudents = $stmt->get_result()->fetch_assoc()['c'];

// ── Query 3: Get today's attendance summary (how many present vs absent today) ──
$today = date('Y-m-d'); // Get today's date in YYYY-MM-DD format (matches the DB date column)

// GROUP BY status returns at most 2 rows: one for status=1 (present) and one for status=0 (absent)
$stmt = $conn->prepare("SELECT status, COUNT(*) AS c FROM tblattendance WHERE classId=? AND classArmId=? AND dateTimeTaken=? GROUP BY status");
$stmt->bind_param("iis", $classId, $classArmId, $today); // "iis" = int, int, string
$stmt->execute();
$res = $stmt->get_result();

// Initialise counters to 0 in case no attendance has been taken today
$presentToday = 0; $absentToday = 0;

// Loop through the (up to 2) grouped rows and assign counts based on the status value
while ($r = $res->fetch_assoc()) {
    if ($r['status']) $presentToday = $r['c']; // status=1 → present count
    else $absentToday = $r['c'];               // status=0 → absent count
}

// ── Query 4: Fetch the 15 most recent attendance records for this class/section ──
// JOINs tblstudents to get the student's name alongside the attendance record
// Ordered by most recent date first, then alphabetically by last name within the same date
$stmt = $conn->prepare("
  SELECT s.firstName, s.lastName, s.admissionNumber, a.status, a.dateTimeTaken
  FROM tblattendance a
  JOIN tblstudents s ON s.admissionNumber = a.admissionNo
  WHERE a.classId=? AND a.classArmId=?
  ORDER BY a.dateTimeTaken DESC, s.lastName
  LIMIT 15
");
$stmt->bind_param("ii", $classId, $classArmId);
$stmt->execute();
// Store the result object; rows will be fetched later in the HTML section
$recentAtt = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Declare character encoding -->
  <meta charset="utf-8">
  <!-- Make the layout responsive on mobile devices -->
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard – Faculty</title>
  <!-- Main site stylesheet -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- Font Awesome icon library (CDN) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper"> <!-- Outer wrapper for the full page layout -->

  <?php include 'sidebar.php'; ?> <!-- Render the left navigation sidebar -->

  <div class="main-content"> <!-- Content area to the right of the sidebar -->

    <?php include 'topbar.php'; ?> <!-- Render the top navigation bar -->

    <div class="page-content"> <!-- Inner padded content area -->

      <!-- Info banner showing the teacher's assigned class and section -->
      <!-- ?? '' provides an empty string fallback if the value is null (e.g. bad session data) -->
      <div class="alert alert-info">
        <i class="fas fa-chalkboard-user"></i>
        Your Assigned Section: <strong><?= htmlspecialchars($classInfo['className'] ?? '') ?> – <?= htmlspecialchars($classInfo['classArmName'] ?? '') ?></strong>
      </div>

      <!-- ── Three stat cards in a grid ── -->
      <div class="stats-grid">

        <!-- Card 1: Total number of students in this teacher's section (green icon) -->
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-users"></i></div>
          <div class="stat-info">
            <div class="label">My Students</div>
            <div class="value"><?= $totalStudents ?></div>
          </div>
        </div>

        <!-- Card 2: Students marked present today (blue icon) -->
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fas fa-user-check"></i></div>
          <div class="stat-info">
            <div class="label">Present Today</div>
            <div class="value"><?= $presentToday ?></div>
          </div>
        </div>

        <!-- Card 3: Students marked absent today (orange icon) -->
        <div class="stat-card">
          <div class="stat-icon orange"><i class="fas fa-user-xmark"></i></div>
          <div class="stat-info">
            <div class="label">Absent Today</div>
            <div class="value"><?= $absentToday ?></div>
          </div>
        </div>
      </div>

      <!-- Section header row: title on the left, "Take Attendance" button on the right -->
      <div class="page-header" style="margin-top:.5rem">
        <h2>Recent Attendance</h2>
        <!-- Button links to the attendance-taking page -->
        <a href="take-attendance.php" class="btn btn-primary"><i class="fas fa-clipboard-check"></i> Take Attendance</a>
      </div>

      <!-- ── Recent Attendance table ── -->
      <div class="card">
        <div class="card-body" style="padding:0"> <!-- Remove padding so the table fills edge-to-edge -->
          <div class="table-wrap"> <!-- Enables horizontal scrolling on small screens -->
            <table>
              <thead>
                <tr>
                  <th>Student</th>     <!-- Student's full name -->
                  <th>Student No.</th> <!-- Admission number -->
                  <th>Status</th>      <!-- Present or Absent badge -->
                  <th>Date</th>        <!-- Date/time the attendance was recorded -->
                </tr>
              </thead>
              <tbody>
                <!-- Loop through each of the 15 most recent attendance records -->
                <?php while ($r=$recentAtt->fetch_assoc()): ?>
                <tr>
                  <!-- Concatenate first and last name into one cell -->
                  <td><?= htmlspecialchars($r['firstName'].' '.$r['lastName']) ?></td>

                  <!-- Student's admission/ID number -->
                  <td><?= htmlspecialchars($r['admissionNumber']) ?></td>

                  <td>
                    <!-- Show a green "Present" or red "Absent" badge depending on status value -->
                    <?php if ($r['status']): ?> <!-- status=1 = present -->
                      <span class="badge badge-present">Present</span>
                    <?php else: ?>             <!-- status=0 = absent -->
                      <span class="badge badge-absent">Absent</span>
                    <?php endif; ?>
                  </td>

                  <!-- Raw date/time value from the database (e.g. "2025-01-15") -->
                  <td><?= htmlspecialchars($r['dateTimeTaken']) ?></td>
                </tr>
                <?php endwhile; ?> <!-- End of attendance row loop -->
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div> <!-- end .page-content -->
  </div> <!-- end .main-content -->
</div> <!-- end .app-wrapper -->

<!-- Main site JavaScript (handles sidebar toggle, dropdowns, etc.) -->
<script src="../assets/js/app.js"></script>
</body>
</html>