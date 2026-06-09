<?php
// Include the database connection file so $conn is available throughout this page
include '../includes/dbcon.php';

// Include the teacher session guard — redirects to login if the teacher is not logged in
include '../includes/session_teacher.php';

// Set the page title used by the layout
$pageTitle = 'My Students';

// Read the teacher's assigned class ID and section (arm) ID from the session
// Cast to int to prevent non-numeric values from reaching the database
$classId    = (int)$_SESSION['classId'];
$classArmId = (int)$_SESSION['classArmId'];

// Fetch all students in this teacher's assigned class and section
// JOIN tblclass and tblclassarms to also get the human-readable class and section names
// ORDER BY lastName then firstName so the list is alphabetically sorted
$stmt = $conn->prepare("
  SELECT s.*, c.className, ca.classArmName
  FROM tblstudents s
  JOIN tblclass c      ON c.Id  = s.classId
  JOIN tblclassarms ca ON ca.Id = s.classArmId
  WHERE s.classId = ? AND s.classArmId = ?
  ORDER BY s.lastName, s.firstName
");
// Bind both IDs as integers ("ii") to the two placeholders (?)
$stmt->bind_param("ii", $classId, $classArmId);
$stmt->execute();

// Store the full result set; rows will be fetched later in the HTML loop
$students = $stmt->get_result();

// Get the total number of students returned; used for the badge counter in the header
$total = $students->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Declare character encoding -->
  <meta charset="utf-8">
  <!-- Make the page responsive on mobile devices -->
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Students – Faculty</title>
  <!-- Main site stylesheet -->
  <link rel="stylesheet" href="../assets/css/style.css">
  <!-- Font Awesome icon library (CDN) for icons used in the page -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper"> <!-- Outer wrapper for the full page layout -->

  <?php include 'sidebar.php'; ?> <!-- Render the left navigation sidebar -->

  <div class="main-content"> <!-- Content area to the right of the sidebar -->

    <?php include 'topbar.php'; ?> <!-- Render the top navigation bar -->

    <div class="page-content"> <!-- Inner padded content area -->

      <!-- Page header: title on the left, student count badge on the right -->
      <div class="page-header">
        <h2><i class="fas fa-users"></i> My Students</h2>
        <!-- Badge showing the total number of students; styled inline to be slightly larger -->
        <span class="badge badge-active" style="font-size:.85rem;padding:.4rem .8rem"><?= $total ?> students</span>
      </div>

      <!-- Students table card -->
      <div class="card">
        <div class="card-body" style="padding:0"> <!-- padding:0 lets the table fill edge-to-edge -->
          <div class="table-wrap"> <!-- Enables horizontal scrolling on small/mobile screens -->
            <table>
              <thead>
                <tr>
                  <th>#</th>          <!-- Row counter -->
                  <th>Name</th>       <!-- Student full name (Last, First Other) -->
                  <th>Student No.</th><!-- Admission number -->
                  <th>Section</th>    <!-- Class arm/section name -->
                  <th>Date Added</th> <!-- Date the student record was created -->
                  <th>Report</th>     <!-- Link button to the student's attendance report -->
                </tr>
              </thead>
              <tbody>

                <!-- Loop counter starts at 1 for the # column -->
                <?php $i=1; while ($s=$students->fetch_assoc()): ?>
                <tr>
                  <!-- Row number; $i++ prints the current value then increments it by 1 -->
                  <td><?= $i++ ?></td>

                  <!-- Full name formatted as "Last, First Other" -->
                  <!-- ?? '' provides an empty string if otherName is null -->
                  <!-- htmlspecialchars() escapes special HTML characters to prevent XSS -->
                  <td><?= htmlspecialchars($s['lastName'].', '.$s['firstName'].' '.($s['otherName']??'')) ?></td>

                  <!-- Student's admission/ID number -->
                  <td><?= htmlspecialchars($s['admissionNumber']) ?></td>

                  <!-- Section (class arm) name fetched via JOIN -->
                  <td><?= htmlspecialchars($s['classArmName']) ?></td>

                  <!-- Date the student record was created in the database -->
                  <td><?= htmlspecialchars($s['dateCreated']) ?></td>

                  <td>
                    <!-- Button linking to the student's individual attendance report page -->
                    <!-- Passes the student's database ID as a URL query parameter -->
                    <!-- title= shows a tooltip on hover explaining what the button does -->
                    <a href="student-report.php?id=<?= $s['Id'] ?>" class="btn btn-primary btn-sm" title="View Attendance Report">
                      <i class="fas fa-chart-bar"></i> <!-- Bar chart icon -->
                    </a>
                  </td>
                </tr>
                <?php endwhile; ?> <!-- End of student row loop -->

                <!-- Empty state: shown only when $total is 0 (no students in the section) -->
                <?php if ($total === 0): ?>
                <tr>
                  <!-- colspan="6" spans all 6 columns so the message is centred across the full table width -->
                  <td colspan="6" style="text-align:center;padding:2rem;color:var(--gray)">
                    No students enrolled in your section yet.
                  </td>
                </tr>
                <?php endif; ?>

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